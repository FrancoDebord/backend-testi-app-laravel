<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrateurs de compte : journal des actions faites par un administrateur
 * « au nom » d'un compte (le titulaire le consulte). La table organization_managers
 * sert désormais à tous les comptes. Voir docs/fonctionnalites/administrateurs-de-compte.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_delegate_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('account_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120)->nullable();
            $table->string('method', 8);
            $table->string('path', 255);
            $table->string('route_name', 120)->nullable();
            $table->unsignedSmallInteger('status');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_delegate_actions');
    }
};
