<?php

use App\Models\Testimony;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Liens de partage enregistrés avec APP_URL (souvent « http://localhost ») : recalculés avec
 * l'adresse publique (config app.share_url). Voir docs/fonctionnalites/lien-de-partage.md
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('testimonies')->select('id')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('testimonies')->where('id', $row->id)->update(['share_url' => Testimony::shareUrlFor($row->id)]);
            }
        });
    }

    public function down(): void
    {
        // Rien à restaurer : les anciennes valeurs étaient erronées.
    }
};
