<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « J'aime » sur les commentaires (témoignages, événements, verset du jour) — docs/fonctionnalites/jaime-commentaires.md
 * Une ligne par personne et par commentaire ; compteur likes_count sur chaque table de commentaires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();
            // testimony_comment | event_comment | verse_comment
            $table->string('likeable_type', 30);
            $table->string('likeable_id', 36);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['likeable_type', 'likeable_id', 'user_id'], 'comment_likes_unique');
            $table->index('user_id');
        });

        // comments.likes_count existe déjà (témoignages).
        if (!Schema::hasColumn('event_comments', 'likes_count')) {
            Schema::table('event_comments', function (Blueprint $table) {
                $table->unsignedInteger('likes_count')->default(0);
            });
        }
        if (!Schema::hasColumn('daily_verse_comments', 'likes_count')) {
            Schema::table('daily_verse_comments', function (Blueprint $table) {
                $table->unsignedInteger('likes_count')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_likes');

        foreach (['event_comments', 'daily_verse_comments'] as $table) {
            if (Schema::hasColumn($table, 'likes_count')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('likes_count'));
            }
        }
    }
};
