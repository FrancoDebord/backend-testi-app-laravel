<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Qualités des médias : docs/fonctionnalites/qualites-media.md */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            // Versions produites par ffmpeg : [{quality, height?, bitrate, disk, path}]
            $table->json('renditions')->nullable()->after('duration_sec');
            // none, pending, processing, done, failed
            $table->string('processing_status', 20)->default('none')->after('renditions');
            $table->unsignedInteger('width')->nullable()->after('processing_status');
            $table->unsignedInteger('height')->nullable()->after('width');
        });

        Schema::table('testimonies', function (Blueprint $table) {
            // Copie des versions du fichier média référencé par media_url.
            $table->json('renditions')->nullable()->after('media_url');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn(['renditions', 'processing_status', 'width', 'height']);
        });

        Schema::table('testimonies', function (Blueprint $table) {
            $table->dropColumn('renditions');
        });
    }
};
