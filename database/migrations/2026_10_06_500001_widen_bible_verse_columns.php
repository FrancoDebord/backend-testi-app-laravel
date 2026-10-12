<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verset cité dans un témoignage : la validation accepte 500 caractères et l'application
 * insère « Référence — «texte» » depuis la Bible ; VARCHAR(255) provoquait une erreur 500
 * (« Data too long ») au-delà de 255 caractères.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonies', fn (Blueprint $table) => $table->text('bible_verse')->nullable()->change());
        Schema::table('drafts', fn (Blueprint $table) => $table->text('bible_verse')->nullable()->change());
    }

    public function down(): void
    {
        // Retour à 255 caractères : tronquer d'abord ce qui dépasse (MySQL strict refuserait la conversion).
        foreach (['testimonies', 'drafts'] as $t) {
            DB::table($t)->whereRaw('CHAR_LENGTH(bible_verse) > 255')->update(['bible_verse' => DB::raw('LEFT(bible_verse, 255)')]);
        }
        Schema::table('testimonies', fn (Blueprint $table) => $table->string('bible_verse')->nullable()->change());
        Schema::table('drafts', fn (Blueprint $table) => $table->string('bible_verse')->nullable()->change());
    }
};
