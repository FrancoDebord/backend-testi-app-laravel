<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recherches validées, anonymisées (aucun identifiant de personne), pour les « recherches
 * populaires » de l'autocomplétion. Une ligne par requête normalisée (minuscules, sans accents).
 * Voir docs/fonctionnalites/recherche.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('normalized', 80)->unique(); // clé de regroupement (Str::ascii + minuscules)
            $table->string('query', 80);                // forme affichée (dernière saisie, minuscules)
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();

            $table->index(['hits', 'last_searched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
