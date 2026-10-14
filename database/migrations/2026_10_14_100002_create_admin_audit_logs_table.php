<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit de l'administration : qui, quoi, quand, avant / après (modération des
 * témoignages, mise à la une, label, corbeille, badge des comptes, super-administrateurs).
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete(); // null : ligne de commande ou système
            $table->string('actor_name', 150)->nullable();
            $table->string('action', 60);                 // testimony.approved, user.badge_granted…
            $table->string('subject_type', 40);           // testimony, user
            $table->uuid('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
