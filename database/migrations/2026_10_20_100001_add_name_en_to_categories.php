<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Types de témoignages (catégories) : nom anglais facultatif (name_en), affiché quand la langue est
 * l'anglais (App\Models\Category::displayName()). Remplissage des noms connus.
 * Voir docs/fonctionnalites/categories-de-temoignages.md
 */
return new class extends Migration
{
    /** Noms anglais des types connus (slug => nom) : copie figée de App\Models\Category::ENGLISH_NAMES. */
    private const ENGLISH_NAMES = [
        'guerison'          => 'Healing',
        'delivrance'        => 'Deliverance',
        'conversion'        => 'Conversion',
        'salut'             => 'Salvation',
        'mariage'           => 'Marriage',
        'famille'           => 'Family',
        'finances'          => 'Finances',
        'provision'         => 'Provision',
        'miracles'          => 'Miracles',
        'protection'        => 'Divine protection',
        'protection_divine' => 'Divine protection',
        'ministere'         => 'Ministry',
        'emploi'            => 'Employment & Career',
        'etudes'            => 'Studies',
        'autre'             => 'Other',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('categories', 'name_en')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('name_en', 100)->nullable()->after('name');
            });
        }

        foreach (self::ENGLISH_NAMES as $slug => $name) {
            DB::table('categories')->where('slug', $slug)->whereNull('name_en')->update(['name_en' => $name]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'name_en')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('name_en');
            });
        }
    }
};
