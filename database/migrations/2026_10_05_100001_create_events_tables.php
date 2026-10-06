<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Événements chrétiens (croisades, conférences, séminaires, camps, tournées…) :
 * images de couverture, participations, commentaires (témoignages des participants).
 * Les témoignages officiels et les directs y sont rattachés par `event_id`.
 * Voir docs/fonctionnalites/evenements.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('type', 30)->default('other');       // EventType
            $table->string('status', 20)->default('published'); // EventStatus
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('location', 200)->nullable();          // lieu (salle, stade…)
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->json('guests')->nullable();                   // [{ name, role }]
            $table->boolean('comments_enabled')->default(true);
            $table->unsignedInteger('going_count')->default(0);
            $table->unsignedInteger('not_going_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
        });

        Schema::create('event_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('url', 500);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('event_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20); // going | not_going
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('event_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            // Commentaire enregistré comme témoignage officiel par un gestionnaire.
            $table->foreignUuid('testimony_id')->nullable()->constrained('testimonies')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'created_at']);
        });

        Schema::table('testimonies', function (Blueprint $table) {
            $table->foreignUuid('event_id')->nullable()->after('category_id')->constrained('events')->nullOnDelete();
        });

        Schema::table('live_sessions', function (Blueprint $table) {
            $table->foreignUuid('event_id')->nullable()->after('host_id')->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
        Schema::table('testimonies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
        Schema::dropIfExists('event_comments');
        Schema::dropIfExists('event_participations');
        Schema::dropIfExists('event_images');
        Schema::dropIfExists('events');
    }
};
