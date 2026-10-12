<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Heure à laquelle la parole prophétique a été reçue (« HH:MM », facultative), en plus de la date
 * (docs/fonctionnalites/paroles-prophetiques.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prophecies', function (Blueprint $table) {
            $table->string('received_time', 5)->nullable()->after('received_on');
        });
    }

    public function down(): void
    {
        Schema::table('prophecies', function (Blueprint $table) {
            $table->dropColumn('received_time');
        });
    }
};
