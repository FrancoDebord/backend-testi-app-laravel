<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 36)->index();
            $table->char('category_id', 36)->nullable()->index();
            $table->string('title');
            $table->string('type', 10); // text, audio, video
            $table->string('category_slug', 30); // denormalized for mobile sync speed
            $table->longText('body_text')->nullable();
            $table->string('media_url')->nullable();
            $table->string('cover_url')->nullable();
            $table->unsignedInteger('duration_sec')->default(0);
            $table->string('bible_verse')->nullable();
            $table->string('bible_ref', 100)->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility', 15)->default('public'); // public, private, followers
            $table->string('status', 15)->default('pending'); // draft, pending, approved, rejected
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('prayer_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('share_count')->default(0);
            $table->unsignedInteger('bookmark_count')->default(0);
            $table->char('approved_by', 36)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['category_slug', 'status']);
            $table->index(['is_featured', 'status']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonies');
    }
};
