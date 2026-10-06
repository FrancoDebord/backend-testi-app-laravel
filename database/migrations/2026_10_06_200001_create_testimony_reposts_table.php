<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partage interne (« republier ») : un témoignage partagé sur TestiApp par un compte, avec un
 * commentaire facultatif ; un seul partage par personne et par témoignage. Compteur
 * testimonies.repost_count. Voir docs/fonctionnalites/partage-interne.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimony_reposts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 36);
            $table->char('testimony_id', 36);
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'testimony_id']);
            $table->index(['testimony_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('testimony_id')->references('id')->on('testimonies')->cascadeOnDelete();
        });

        Schema::table('testimonies', function (Blueprint $table) {
            $table->unsignedInteger('repost_count')->default(0)->after('share_count');
        });
    }

    public function down(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->dropColumn('repost_count');
        });
        Schema::dropIfExists('testimony_reposts');
    }
};
