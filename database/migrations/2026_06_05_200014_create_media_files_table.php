<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 36)->index();
            $table->string('disk', 20)->default('public'); // public, s3
            $table->string('path');
            $table->string('url');
            $table->string('mime_type', 50);
            $table->string('type', 15); // image, audio, video
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('duration_sec')->default(0)->comment('For audio/video');
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
