<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Carnet privé : un témoignage privé n'est jamais soumis à la modération.
// Les témoignages privés déjà « en attente » ou « refusés » deviennent des
// entrées du carnet (statut brouillon). Voir docs/fonctionnalites/carnet-prive.md
return new class extends Migration
{
    public function up(): void
    {
        DB::table('testimonies')
            ->where('visibility', 'private')
            ->whereIn('status', ['pending', 'rejected'])
            ->update(['status' => 'draft']);
    }

    public function down(): void
    {
        // Rien à défaire : les entrées restent privées.
    }
};
