<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preuves d'un témoignage (2 au plus : images ou PDF), stockées hors du dossier public.
 * Visibles de l'auteur et de l'équipe de modération seulement. Voir docs/fonctionnalites/preuves.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimony_proofs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('testimony_id')->constrained('testimonies')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');   // 1 ou 2
            $table->string('disk', 20)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->unique(['testimony_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimony_proofs');
    }
};
