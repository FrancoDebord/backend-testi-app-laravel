<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Commentaire épinglé d'un direct (un seul à la fois), par le diffuseur ou un modérateur.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            // Sans contrainte : live_comments référence déjà live_sessions (pas de
            // dépendance circulaire). La validité est vérifiée par pinnedCommentPayload().
            $table->uuid('pinned_comment_id')->nullable()->after('comment_count');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn('pinned_comment_id');
        });
    }
};
