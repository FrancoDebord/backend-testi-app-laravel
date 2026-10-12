<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vidéos hébergées par Bunny Stream : docs/fonctionnalites/videos-bunny-stream.md
 *
 * Les colonnes de l'ancien stockage (media_url, renditions) restent en place tant que
 * les anciennes vidéos n'ont pas toutes été reprises (videos:migrate-to-bunny).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->string('bunny_video_id', 64)->nullable()->after('youtube_id')->index();
            // uploading, processing, ready, failed. Null : pas de vidéo Bunny (texte, audio, ancienne
            // vidéo LWS, YouTube) ; une valeur par défaut masquerait tous les témoignages existants.
            $table->string('video_status', 20)->nullable()->after('bunny_video_id')->index();
            // Durée donnée par Bunny (secondes) ; recopiée dans duration_sec (shorts, affichage).
            $table->unsignedInteger('duration')->nullable()->after('video_status');
            // Qualités produites par Bunny, telles que renvoyées : « 240p,360p,480p,720p ».
            $table->string('available_resolutions')->nullable()->after('duration');
        });
    }

    public function down(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->dropIndex(['bunny_video_id']);
            $table->dropIndex(['video_status']);
            $table->dropColumn(['bunny_video_id', 'video_status', 'duration', 'available_resolutions']);
        });
    }
};
