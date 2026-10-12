<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Localisation grossière (≈ 1 km) pour classer les fils par zone puis par goûts.
 *   - user_locations : lieu de la personne (consentement GPS, ville saisie) — jamais exposé publiquement ;
 *   - content_locations : copie grossière du lieu d'un contenu (témoignage, requête ou session de prière)
 *     au moment de sa création. Les événements gardent leurs propres colonnes (city, country, latitude…).
 * Voir docs/fonctionnalites/localisation.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_locations', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->boolean('use_gps')->default(false);             // « Utiliser ma position pour personnaliser mes fils »
            $table->decimal('latitude', 5, 2)->nullable();          // arrondi à 2 décimales (≈ 1 km)
            $table->decimal('longitude', 5, 2)->nullable();
            $table->string('locality', 120)->nullable();            // village / quartier
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();              // état, province, département
            $table->string('country_code', 2)->nullable();
            $table->string('continent', 2)->nullable();
            $table->string('source', 20)->nullable();               // gps (coordonnées ci-dessus)
            $table->string('home_city', 120)->nullable();           // « Ma ville » saisie (profil), sans GPS
            $table->timestamp('location_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('content_locations', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 30);                     // testimony | prayer_request | prayer_session
            $table->uuid('subject_id');
            $table->uuid('user_id')->nullable()->index();           // auteur (effacement de sa position)
            $table->decimal('latitude', 5, 2)->nullable();
            $table->decimal('longitude', 5, 2)->nullable();
            $table->string('locality', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('continent', 2)->nullable();
            $table->string('source', 20)->nullable();               // gps | manual | profile | phone | event
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id']);
            $table->index(['subject_type', 'country_code', 'city']);
            $table->index(['subject_type', 'country_code', 'region']);
            $table->index(['subject_type', 'continent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_locations');
        Schema::dropIfExists('user_locations');
    }
};
