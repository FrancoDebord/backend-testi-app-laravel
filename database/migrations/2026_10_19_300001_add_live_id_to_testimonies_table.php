<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Témoigner après un direct terminé (docs/fonctionnalites/lives.md, « Directs passés ») :
 * témoignage rattaché à un direct (live_id), lien retiré si le direct est supprimé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('testimonies', 'live_id')) {
            return;
        }
        Schema::table('testimonies', function (Blueprint $table) {
            $table->foreignUuid('live_id')->nullable()->after('event_id')->constrained('live_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('testimonies', 'live_id')) {
            Schema::table('testimonies', function (Blueprint $table) {
                $table->dropConstrainedForeignId('live_id');
            });
        }
    }
};
