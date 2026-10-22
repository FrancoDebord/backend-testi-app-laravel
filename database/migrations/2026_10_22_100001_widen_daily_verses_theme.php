<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thème du verset du jour : libellé complet (« Le témoignage de Dieu, sa Loi, l'arche du témoignage »)
 * en plus des anciens codes (faithfulness, testimonies…). Voir docs/fonctionnalites/verset-du-jour.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_verses', function (Blueprint $table) {
            $table->string('theme', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('daily_verses', function (Blueprint $table) {
            $table->string('theme', 50)->nullable()->change();
        });
    }
};
