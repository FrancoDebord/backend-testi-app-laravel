<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('category_slug')->nullable();
            $table->string('room_name')->unique();
            $table->string('status', 20)->default('preparing')->index(); // preparing | live | ended
            $table->boolean('comments_enabled')->default(true);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignUuid('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('end_reason', 30)->nullable(); // host | moderator | connection | abandoned
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('pray_count')->default(0);
            $table->unsignedInteger('amen_count')->default(0);
            $table->unsignedInteger('fire_count')->default(0);
            $table->timestamps();
        });

        Schema::create('live_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_hidden')->default(false);
            $table->foreignUuid('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['live_session_id', 'created_at']);
        });

        Schema::create('live_bans', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('banned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['live_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_bans');
        Schema::dropIfExists('live_comments');
        Schema::dropIfExists('live_sessions');
    }
};
