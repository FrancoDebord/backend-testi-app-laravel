<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requête de prière confiée depuis le témoignage de quelqu'un (prayer_requests.source_testimony_id).
 * Distinct de testimony_id (témoignage de l'exaucement, écrit par l'auteur de la requête).
 * Voir docs/fonctionnalites/requetes-de-priere.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prayer_requests', function (Blueprint $table) {
            $table->foreignUuid('source_testimony_id')->nullable()->after('daily_verse_id')->constrained('testimonies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prayer_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_testimony_id');
        });
    }
};
