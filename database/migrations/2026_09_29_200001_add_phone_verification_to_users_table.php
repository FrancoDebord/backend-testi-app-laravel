<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numéro de téléphone de contact (vérification des comptes) : pays de l'indicatif, et date de
 * vérification par SMS. Seul un numéro vérifié permet la connexion par téléphone.
 * Voir docs/fonctionnalites/telephone.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_country', 2)->nullable()->after('phone');  // code ISO du pays de l'indicatif
            $table->timestamp('phone_verified_at')->nullable()->after('phone_country');
        });

        // Numéros existants créés par la connexion par téléphone (Firebase) : déjà vérifiés par SMS.
        DB::table('users')->whereNotNull('phone')->whereNotNull('firebase_uid')
            ->update(['phone_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_country', 'phone_verified_at']);
        });
    }
};
