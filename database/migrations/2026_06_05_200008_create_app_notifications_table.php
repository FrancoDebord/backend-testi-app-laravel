<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('recipient_id', 36)->index();
            $table->char('actor_id', 36)->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_avatar')->nullable();
            $table->string('type', 40); // like, comment, reply, follow, testimony_approved, testimony_rejected, mention, share
            $table->char('testimony_id', 36)->nullable();
            $table->string('testimony_title')->nullable();
            $table->char('comment_id', 36)->nullable();
            $table->string('message');
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['recipient_id', 'is_read', 'created_at']);
            $table->foreign('recipient_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
