<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Témoignage vidéo publié par un lien YouTube (administrateurs) : identifiant de la vidéo.
 * Voir docs/fonctionnalites/videos-youtube.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->string('youtube_id', 11)->nullable()->after('media_url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->dropIndex(['youtube_id']);
            $table->dropColumn('youtube_id');
        });
    }
};
