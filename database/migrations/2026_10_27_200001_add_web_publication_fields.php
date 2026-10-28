<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Référencement (docs/fonctionnalites/referencement.md) :
 * - témoignages : texte écrit pour Google (résumé, transcription, description), anonymat sur le web,
 *   consentement à la publication sur le web (les témoignages existants le gardent : leur auteur a déjà
 *   accepté un partage public sur la plateforme) ;
 * - catégories : texte d'introduction de leur page ;
 * - thèmes de semaine du verset du jour : slug de leur page /themes/{slug}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonies', function (Blueprint $table) {
            $table->string('meta_description', 160)->nullable()->after('body_text');
            $table->text('resume')->nullable()->after('meta_description');
            $table->longText('transcription')->nullable()->after('resume');
            $table->boolean('is_anonymous')->default(false)->after('transcription');
            $table->boolean('publication_web')->default(true)->after('is_anonymous');
            $table->timestamp('publication_web_at')->nullable()->after('publication_web');
            $table->timestamp('publication_web_revoked_at')->nullable()->after('publication_web_at');
        });
        DB::table('testimonies')->whereNull('publication_web_at')->update(['publication_web_at' => DB::raw('COALESCE(approved_at, created_at)')]);

        Schema::table('categories', function (Blueprint $table) {
            $table->text('intro')->nullable()->after('name');
        });

        Schema::table('themes_semaine', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('thematique')->index();
        });
        foreach (DB::table('themes_semaine')->get(['id', 'thematique']) as $theme) {
            DB::table('themes_semaine')->where('id', $theme->id)->update(['slug' => Str::slug($theme->thematique)]);
        }
    }

    public function down(): void
    {
        Schema::table('themes_semaine', fn (Blueprint $table) => $table->dropColumn('slug'));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('intro'));
        Schema::table('testimonies', fn (Blueprint $table) => $table->dropColumn([
            'meta_description', 'resume', 'transcription', 'is_anonymous',
            'publication_web', 'publication_web_at', 'publication_web_revoked_at',
        ]));
    }
};
