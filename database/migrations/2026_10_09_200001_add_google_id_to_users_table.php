<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Connexion Google (docs/fonctionnalites/connexion-google.md) : identifiant Google (`sub`) porté par le compte.
 * Les liens déjà enregistrés dans social_auth_providers sont recopiés, pour retrouver les comptes déjà liés.
 * `password` est nullable depuis la création de la table : rien à changer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('firebase_uid');
        });

        DB::table('social_auth_providers')
            ->where('provider', 'google')
            ->orderBy('created_at')
            ->get(['user_id', 'provider_id'])
            ->each(function ($link) {
                // Premier lien d'un compte seulement (provider_id est déjà unique par fournisseur).
                DB::table('users')->where('id', $link->user_id)->whereNull('google_id')
                    ->update(['google_id' => $link->provider_id]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });
    }
};
