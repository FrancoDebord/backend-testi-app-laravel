<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lieu précis des événements : adresse complète et coordonnées GPS (repère placé sur une carte
 * OpenStreetMap), pour proposer l'itinéraire aux participants. Voir docs/fonctionnalites/evenements.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'address')) {
                $table->string('address', 300)->nullable()->after('location'); // rue, quartier, repère…
            }
            if (!Schema::hasColumn('events', 'country')) {
                $table->string('country', 100)->nullable()->after('city');
            }
            if (!Schema::hasColumn('events', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('country');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // `country` appartient à la migration de création : on ne la retire pas.
            $table->dropColumn(['address', 'latitude', 'longitude']);
        });
    }
};
