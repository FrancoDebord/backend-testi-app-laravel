<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réactions (j'aime, cœur, feu, je prie, amen…) et partages internes des événements, requêtes et
 * sessions de prière (docs/fonctionnalites/reactions.md, partage-interne.md). Une réaction par groupe
 * (j'aime / je prie) et un partage par personne et par publication ; compteurs tenus à jour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 30); // event | prayer_request | prayer_session
            $table->uuid('subject_id');
            $table->string('type', 10);         // ReactionType
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_id', 'type'], 'content_reactions_unique');
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('content_reposts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 30);
            $table->uuid('subject_id');
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_id'], 'content_reposts_unique');
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        foreach (['events', 'prayer_sessions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedInteger('like_count')->default(0);
                $table->unsignedInteger('prayer_count')->default(0);
                $table->unsignedInteger('repost_count')->default(0);
            });
        }
        // Requêtes de prière : « Je prie » existe déjà (prayer_count) ; on ajoute j'aime et partages.
        Schema::table('prayer_requests', function (Blueprint $table) {
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('repost_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('prayer_requests', fn (Blueprint $table) => $table->dropColumn(['like_count', 'repost_count']));
        foreach (['events', 'prayer_sessions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['like_count', 'prayer_count', 'repost_count']));
        }
        Schema::dropIfExists('content_reposts');
        Schema::dropIfExists('content_reactions');
    }
};
