<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct diffusé sur YouTube (source « youtube ») : identifiant de la vidéo tiré du lien collé.
 * Aucune salle LiveKit ; la vidéo YouTube sert aussi de rediffusion. Voir docs/fonctionnalites/lives.md
 * Sources possibles (colonne source, texte) : browser, rtmp, url, youtube.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->string('youtube_video_id', 20)->nullable()->after('camera_url');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn('youtube_video_id');
        });
    }
};
