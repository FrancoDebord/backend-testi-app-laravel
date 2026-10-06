<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique de lecture des personnes connectées : centres d'intérêt et « déjà vu » des
 * recommandations. Une ligne par personne et par témoignage. Voir docs/fonctionnalites/recommandations.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimony_views', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('testimony_id')->constrained('testimonies')->cascadeOnDelete();
            $table->unsignedInteger('view_count')->default(1);
            $table->timestamp('last_viewed_at')->useCurrent();

            $table->unique(['user_id', 'testimony_id']);
            $table->index(['user_id', 'last_viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimony_views');
    }
};
