<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verset du jour : design et réglages du texte choisis par chaque membre, synchronisés entre ses
 * appareils (docs/fonctionnalites/designs-du-verset.md). Valeurs = ids du catalogue config/verse_designs.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verse_style_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('design_id', 40)->nullable();
            $table->date('design_chosen_on')->nullable();   // le choix vaut pour ce jour-là…
            $table->boolean('design_always')->default(false); // …ou tous les jours
            $table->string('font', 40)->default('lora');
            $table->string('text_size', 20)->default('medium');
            $table->string('text_align', 20)->default('start');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verse_style_preferences');
    }
};
