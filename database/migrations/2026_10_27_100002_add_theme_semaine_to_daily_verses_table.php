<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verset du jour rattaché au thème de sa semaine, et version biblique du texte importé
 * (« LSG 1910 »). Voir docs/fonctionnalites/verset-du-jour.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_verses', function (Blueprint $table) {
            $table->foreignId('theme_semaine_id')->nullable()->after('week_number')->constrained('themes_semaine')->nullOnDelete();
            $table->string('version', 50)->nullable()->after('theme_semaine_id');
        });
    }

    public function down(): void
    {
        Schema::table('daily_verses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('theme_semaine_id');
            $table->dropColumn('version');
        });
    }
};
