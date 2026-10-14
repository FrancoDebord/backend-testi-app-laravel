<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vidéos Bunny Stream (docs/fonctionnalites/videos-bunny-stream.md) :
 * - video_processing_at : début du traitement chez Bunny (alerte au-delà de 30 minutes) ;
 * - video_alerted_at    : alerte « traitement trop long » envoyée aux super-administrateurs (une seule fois).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->timestamp('video_processing_at')->nullable()->after('video_status');
            $table->timestamp('video_alerted_at')->nullable()->after('video_processing_at');
        });
    }

    public function down(): void
    {
        Schema::table('testimonies', fn (Blueprint $table) => $table->dropColumn(['video_processing_at', 'video_alerted_at']));
    }
};
