<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Badge « Approuvé » des comptes (docs/fonctionnalites/badge-approuve.md) : le badge reste
 * verification_status = verified (+ verified_at, verified_by) ; on enregistre d'où il vient.
 * approval_source : verification_form | organization_review | admin_manual (null sans badge).
 * Données existantes : organisations vérifiées → organization_review, personnes vérifiées → verification_form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('approval_source', 30)->nullable()->after('verification_note');
        });

        DB::table('users')->where('verification_status', 'verified')->where('account_type', 'organization')
            ->update(['approval_source' => 'organization_review']);
        DB::table('users')->where('verification_status', 'verified')->where('account_type', '!=', 'organization')
            ->update(['approval_source' => 'verification_form']);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('approval_source'));
    }
};
