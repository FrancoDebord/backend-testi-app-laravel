<?php

use App\Enums\ReactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Une réaction par groupe (j'aime : like/love/fire ; je prie : pray/amen/worship) et par personne
 * sur un témoignage (docs/fonctionnalites/reactions.md). On garde la plus récente de chaque
 * groupe, on supprime les autres et on retire leur poids du compteur (sans descendre sous 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['appreciation' => 'like_count', 'prayer' => 'prayer_count'] as $group => $field) {
            $types = ReactionType::valuesOfGroup($group);

            DB::table('reactions')
                ->select('user_id', 'testimony_id')
                ->whereIn('type', $types)
                ->groupBy('user_id', 'testimony_id')
                ->havingRaw('COUNT(*) > 1')
                ->orderBy('testimony_id')
                ->chunk(500, function ($pairs) use ($types, $field) {
                    foreach ($pairs as $pair) {
                        $ids = DB::table('reactions')
                            ->where('user_id', $pair->user_id)->where('testimony_id', $pair->testimony_id)
                            ->whereIn('type', $types)
                            ->orderByDesc('created_at')->orderByDesc('id')
                            ->pluck('id');
                        $extra = $ids->slice(1)->values();
                        if ($extra->isEmpty()) continue;

                        DB::table('reactions')->whereIn('id', $extra)->delete();
                        $n = $extra->count();
                        DB::table('testimonies')->where('id', $pair->testimony_id)
                            ->update([$field => DB::raw("CASE WHEN {$field} > {$n} THEN {$field} - {$n} ELSE 0 END")]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Les doublons supprimés ne sont pas recréés.
    }
};
