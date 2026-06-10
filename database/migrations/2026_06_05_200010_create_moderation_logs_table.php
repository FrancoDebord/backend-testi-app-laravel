<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('testimony_id', 36)->index();
            $table->char('moderator_id', 36)->nullable()->index();
            $table->string('action', 20); // approved, rejected, flagged, in_review
            $table->string('rejection_reason', 40)->nullable(); // inappropriateContent, falseTestimony, hateSpeech, spam, other
            $table->text('moderator_note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('testimony_id')->references('id')->on('testimonies')->cascadeOnDelete();
            $table->foreign('moderator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
