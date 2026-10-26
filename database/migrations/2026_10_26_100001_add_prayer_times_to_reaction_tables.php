<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prier plusieurs fois : nombre de prières de la personne sur la publication (1 pour une réaction « j'aime »).
 * Les compteurs des publications (prayer_count) restent le nombre de personnes. Voir docs/fonctionnalites/prier-plusieurs-fois.md
 */
return new class extends Migration
{
    private const TABLES = ['reactions', 'content_reactions', 'daily_verse_reactions', 'prayer_request_prayers'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'prayer_times')) continue;
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedInteger('prayer_times')->default(1)->after('user_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasColumn($table, 'prayer_times')) continue;
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('prayer_times'));
        }
    }
};
