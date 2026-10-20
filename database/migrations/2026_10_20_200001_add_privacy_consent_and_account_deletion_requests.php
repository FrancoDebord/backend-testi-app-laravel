<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consentement à la politique de confidentialité (users.accepted_privacy_at, renseigné à l'inscription)
 * et demandes de suppression de compte faites sans connexion (page /suppression-compte).
 * Voir docs/fonctionnalites/confidentialite-et-suppression.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null pour les comptes créés avant le 2026-10-20 ou par Google / téléphone sans case cochée.
            $table->timestamp('accepted_privacy_at')->nullable()->after('email_verified_at');
        });

        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contact', 190);           // e-mail ou numéro saisi
            $table->string('contact_type', 10);       // email | phone
            // Compte reconnu à partir du contact (null si aucun compte ne correspond)
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 20)->default('pending'); // pending | done | rejected
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('accepted_privacy_at');
        });
    }
};
