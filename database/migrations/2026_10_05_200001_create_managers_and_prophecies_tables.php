<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Gestionnaires : 2 personnes au plus par organisation (gèrent ses événements en son nom)
 *   et 2 co-gestionnaires au plus par événement. Voir docs/fonctionnalites/evenements.md
 * - Paroles prophétiques du carnet privé et journal de prière.
 *   Voir docs/fonctionnalites/paroles-prophetiques.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('event_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('prophecies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150)->nullable();
            $table->date('received_on');
            $table->text('body_text')->nullable();
            $table->string('audio_url', 500)->nullable();
            $table->unsignedInteger('audio_duration')->default(0); // secondes
            $table->string('given_by', 150)->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('waiting'); // waiting | fulfilled
            $table->date('fulfilled_on')->nullable();
            // Témoignage de l'accomplissement ; la parole est montrée avec lui si `is_public`.
            $table->foreignUuid('testimony_id')->nullable()->constrained('testimonies')->nullOnDelete();
            $table->boolean('is_public')->default(false);
            // Rappel de prière (programmé sur le téléphone) : daily | weekly, heure locale HH:MM, jour 1-7.
            $table->string('reminder_frequency', 10)->nullable();
            $table->string('reminder_time', 5)->nullable();
            $table->unsignedTinyInteger('reminder_weekday')->nullable();
            $table->unsignedInteger('prayer_count')->default(0);
            $table->timestamp('last_prayed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        Schema::create('prophecy_prayers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('prophecy_id')->constrained('prophecies')->cascadeOnDelete();
            $table->string('note', 1000)->nullable();
            $table->timestamp('prayed_at');
            $table->timestamps();

            $table->index(['prophecy_id', 'prayed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prophecy_prayers');
        Schema::dropIfExists('prophecies');
        Schema::dropIfExists('event_managers');
        Schema::dropIfExists('organization_managers');
    }
};
