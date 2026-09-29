<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes organisation (églises, ministères, associations…) et vérification
 * par un administrateur. Voir docs/fonctionnalites/comptes-organisation.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type', 20)->default('individual')->index()->after('role');
            $table->string('organization_name')->nullable()->after('account_type');
            $table->string('organization_type', 20)->nullable()->after('organization_name');
            $table->string('organization_city')->nullable()->after('organization_type');
            $table->string('organization_website')->nullable()->after('organization_city');
            $table->string('verification_status', 20)->nullable()->index()->after('organization_website');
            $table->timestamp('verified_at')->nullable()->after('verification_status');
            $table->uuid('verified_by')->nullable()->after('verified_at');
            $table->string('verification_note')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_type']);
            $table->dropIndex(['verification_status']);
            $table->dropColumn([
                'account_type', 'organization_name', 'organization_type', 'organization_city',
                'organization_website', 'verification_status', 'verified_at', 'verified_by',
                'verification_note',
            ]);
        });
    }
};
