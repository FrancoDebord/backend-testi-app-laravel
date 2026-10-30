<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listes de témoignages (comme les playlists YouTube) : un compte regroupe ses témoignages qui se rapportent
 * à une même chose ; un témoignage peut être dans plusieurs listes, ou dans aucune. L'ordre est `position`.
 * Voir docs/fonctionnalites/listes-de-temoignages.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimony_playlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 36);
            $table->string('title', 120);
            $table->string('description', 1000)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('testimony_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->char('playlist_id', 36);
            $table->char('testimony_id', 36);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['playlist_id', 'testimony_id']);
            $table->index(['playlist_id', 'position']);
            $table->index('testimony_id');
            $table->foreign('playlist_id')->references('id')->on('testimony_playlists')->cascadeOnDelete();
            $table->foreign('testimony_id')->references('id')->on('testimonies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimony_playlist_items');
        Schema::dropIfExists('testimony_playlists');
    }
};
