<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 36)->index();
            $table->string('type', 10)->default('text'); // text, audio, video
            $table->string('title')->default('');
            $table->char('category_id', 36)->nullable();
            $table->string('category_slug', 30)->nullable();
            $table->longText('body_text')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('video_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('bible_verse')->nullable();
            $table->string('bible_ref', 100)->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility', 15)->default('public');
            $table->boolean('consent_given')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
