<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Requêtes de prière : publication directe, « Je prie », messages d'encouragement, signalements.
 *   Voir docs/fonctionnalites/requetes-de-priere.md
 * - Sessions de prière programmées, inscriptions ; la salle est un direct (live_sessions.prayer_session_id).
 *   Voir docs/fonctionnalites/sessions-de-priere.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->text('body');
            $table->string('visibility', 20)->default('public'); // public | followers | private
            $table->boolean('is_anonymous')->default(false);      // nom masqué pour les autres (pas pour la modération)
            $table->string('status', 20)->default('open');        // open | answered
            $table->timestamp('answered_at')->nullable();
            $table->string('answer_note', 1000)->nullable();
            $table->foreignUuid('testimony_id')->nullable()->constrained('testimonies')->nullOnDelete();
            $table->unsignedInteger('prayer_count')->default(0);
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('report_count')->default(0);
            // Retrait par la modération (ou automatique après plusieurs signalements).
            $table->timestamp('hidden_at')->nullable();
            $table->foreignUuid('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hidden_reason', 300)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['visibility', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['event_id', 'created_at']);
        });

        Schema::create('prayer_request_prayers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('prayer_request_id')->constrained('prayer_requests')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['prayer_request_id', 'user_id']);
        });

        Schema::create('prayer_request_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prayer_request_id')->constrained('prayer_requests')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('bible_reference', 100)->nullable(); // ex. « Philippiens 4:6-7 »
            $table->timestamps();
            $table->softDeletes();

            $table->index(['prayer_request_id', 'created_at']);
        });

        Schema::create('prayer_request_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('prayer_request_id')->constrained('prayer_requests')->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 50); // inappropriate_content | hate_speech | spam | other
            $table->string('comment', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable(); // traité par la modération
            $table->timestamps();

            $table->unique(['prayer_request_id', 'reporter_id']);
        });

        Schema::create('prayer_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->json('topics')->nullable();                     // sujets de prière (liste de textes)
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->dateTime('ends_at');                              // starts_at + durée (listes à venir / passées)
            $table->string('visibility', 20)->default('public');  // public | followers
            $table->string('status', 20)->default('scheduled');   // scheduled | cancelled
            $table->unsignedInteger('participant_count')->default(0);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();            // première ouverture de la salle
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
            $table->index('ends_at');
        });

        Schema::create('prayer_session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('prayer_session_id')->constrained('prayer_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['prayer_session_id', 'user_id']);
        });

        // La salle d'une session de prière est un direct (spectateurs, commentaires, intervenants).
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->foreignUuid('prayer_session_id')->nullable()->after('event_id')->constrained('prayer_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prayer_session_id');
        });
        Schema::dropIfExists('prayer_session_participants');
        Schema::dropIfExists('prayer_sessions');
        Schema::dropIfExists('prayer_request_reports');
        Schema::dropIfExists('prayer_request_messages');
        Schema::dropIfExists('prayer_request_prayers');
        Schema::dropIfExists('prayer_requests');
    }
};
