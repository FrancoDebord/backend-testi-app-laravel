<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Événements : report (« Reporté », date à confirmer), annulation motivée, signalements et
 * masquage par la modération, sondage après l'événement (« A-t-il vraiment eu lieu ? »)
 * et suivi des événements non confirmés. Voir docs/fonctionnalites/evenements.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Report / annulation
            $table->dateTime('original_starts_at')->nullable()->after('ends_at'); // première date annoncée (affichée barrée)
            $table->dateTime('original_ends_at')->nullable()->after('original_starts_at');
            $table->boolean('date_tbd')->default(false)->after('original_ends_at'); // reporté, date à confirmer
            $table->timestamp('postponed_at')->nullable()->after('date_tbd');
            $table->string('status_reason', 500)->nullable()->after('postponed_at'); // motif du report / de l'annulation
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');
            // Signalements et modération
            $table->unsignedInteger('report_count')->default(0);
            $table->timestamp('hidden_at')->nullable();
            $table->foreignUuid('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hidden_reason', 300)->nullable();
            // Sondage après l'événement
            $table->timestamp('survey_sent_at')->nullable();
            $table->unsignedInteger('survey_yes_count')->default(0);
            $table->unsignedInteger('survey_no_count')->default(0);
            $table->unsignedInteger('survey_absent_count')->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('rating_sum')->default(0);
            $table->timestamp('unconfirmed_at')->nullable();          // majorité de « Non » (3 réponses au moins)
            $table->timestamp('unconfirmed_reviewed_at')->nullable(); // examiné par la modération

            $table->index(['hidden_at']);
            $table->index(['survey_sent_at', 'ends_at']);
        });

        Schema::create('event_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 50); // not_christian | fake | inappropriate_content | scam | other
            $table->string('comment', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable(); // traité par la modération
            $table->timestamps();

            $table->unique(['event_id', 'reporter_id']);
        });

        Schema::create('event_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('attended', 10); // yes | no | absent
            $table->unsignedTinyInteger('rating')->nullable(); // 1 à 5 (si « Oui »)
            $table->text('body')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reviews');
        Schema::dropIfExists('event_reports');
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['hidden_at']);
            $table->dropIndex(['survey_sent_at', 'ends_at']);
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropColumn([
                'original_starts_at', 'original_ends_at', 'date_tbd', 'postponed_at', 'status_reason', 'status_changed_at',
                'report_count', 'hidden_at', 'hidden_reason',
                'survey_sent_at', 'survey_yes_count', 'survey_no_count', 'survey_absent_count',
                'rating_count', 'rating_sum', 'unconfirmed_at', 'unconfirmed_reviewed_at',
            ]);
        });
    }
};
