<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intervenants d'un direct : file d'attente des demandes, puis passage à l'antenne, un à la fois.
 * Voir docs/fonctionnalites/lives-intervenants.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_speakers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // waiting | invited | on_stage | done | cancelled | declined | expired
            $table->string('status', 20)->default('waiting');
            $table->string('message', 200)->nullable();   // sujet annoncé, lu par le diffuseur
            $table->string('identity')->nullable();       // participant LiveKit à l'antenne
            $table->boolean('camera')->default(false);    // caméra choisie en acceptant
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason', 20)->nullable(); // left | removed | disconnected | live_ended | banned
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['live_session_id', 'status', 'created_at']);
            $table->index(['live_session_id', 'user_id']);
        });

        Schema::table('live_sessions', function (Blueprint $table) {
            $table->boolean('speakers_enabled')->default(true)->after('comments_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn('speakers_enabled');
        });
        Schema::dropIfExists('live_speakers');
    }
};
