<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct YouTube d'un événement : identifiant (11 caractères) tiré du lien collé par l'organisateur.
 * Voir docs/fonctionnalites/evenements.md › Direct YouTube
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('youtube_live_id', 20)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('youtube_live_id');
        });
    }
};
