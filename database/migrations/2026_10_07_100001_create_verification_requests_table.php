<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de vérification des comptes personnels (pasteurs, chantres, personnalités…).
 * Le statut affiché (badge) reste users.verification_status, comme pour les organisations ;
 * cette table garde le dossier (informations, pièces justificatives privées, décision).
 * Voir docs/fonctionnalites/verification-des-comptes.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 36);
            $table->string('full_name', 150);
            $table->string('role', 30);                 // App\Enums\PersonalVerificationRole
            $table->string('role_other', 100)->nullable();
            $table->string('church', 150);
            $table->json('links')->nullable();          // liens publics (http/https)
            $table->text('message')->nullable();
            // Pièces justificatives sur le disque privé « local » : [{path, name, mime, size}]
            $table->json('documents');
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | revoked
            $table->string('review_note', 255)->nullable();
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_requests');
    }
};
