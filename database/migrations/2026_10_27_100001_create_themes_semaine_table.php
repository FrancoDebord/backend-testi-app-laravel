<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thèmes des semaines du verset du jour (fichier storage/dailyverse/versets_du_jour_2027.json,
 * commande versets:import). Voir docs/fonctionnalites/verset-du-jour.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes_semaine', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('annee')->unsigned();
            $table->tinyInteger('numero_semaine')->unsigned();
            $table->string('thematique');
            $table->string('verset_cle');
            $table->string('periode')->nullable();   // ex. « Pâques (28 mars) »
            $table->date('date_debut');
            $table->date('date_fin');
            $table->timestamps();

            $table->unique(['annee', 'numero_semaine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes_semaine');
    }
};
