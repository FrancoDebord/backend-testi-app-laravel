<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiques des témoignages (docs/fonctionnalites/gestion-des-temoignages.md) :
 * - testimony_events : événements bruts (vue dédoublonnée, partage, secondes regardées), gardés
 *   config('testimonies.events_keep_days') jours ;
 * - testimony_daily_stats : agrégat par témoignage et par jour (commande testimonies:aggregate-stats).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimony_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('testimony_id')->constrained('testimonies')->cascadeOnDelete();
            $table->string('type', 10);                     // view | share | watch
            $table->string('viewer_key', 64)->nullable();   // empreinte (compte, appareil ou IP + navigateur)
            $table->unsignedInteger('seconds')->default(0); // watch : secondes regardées
            $table->timestamp('created_at')->useCurrent();
            $table->index(['testimony_id', 'created_at']);
            $table->index(['created_at', 'type']);
        });

        Schema::create('testimony_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('testimony_id')->constrained('testimonies')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('unique_viewers')->default(0);
            $table->unsignedInteger('reactions')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedBigInteger('video_watch_seconds')->default(0);
            $table->timestamps();
            $table->unique(['testimony_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimony_daily_stats');
        Schema::dropIfExists('testimony_events');
    }
};
