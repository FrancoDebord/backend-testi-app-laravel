<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accord de l'auteur pour montrer ses preuves au public (une fois le témoignage publié).
 * La modération peut retirer cet affichage. Voir docs/fonctionnalites/preuves.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->boolean('proofs_public')->default(false)->after('youtube_id');
        });
    }

    public function down(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->dropColumn('proofs_public');
        });
    }
};
