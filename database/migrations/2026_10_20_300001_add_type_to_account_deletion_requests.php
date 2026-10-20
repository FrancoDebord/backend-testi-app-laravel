<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de la page /suppression-compte : suppression du compte (account) ou de certaines données
 * seulement (partial), précisées dans le message. Voir docs/fonctionnalites/confidentialite-et-suppression.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_deletion_requests', function (Blueprint $table) {
            $table->string('type', 10)->default('account')->after('id'); // account | partial
        });
    }

    public function down(): void
    {
        Schema::table('account_deletion_requests', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
