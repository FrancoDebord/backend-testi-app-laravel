<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crédit de la vidéo YouTube d'origine (docs/fonctionnalites/videos-youtube.md) :
 * auteur ou intervenant, nom de la chaîne, lien de la chaîne ou de la vidéo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('testimonies', 'youtube_credit_author')) {
            return;
        }
        Schema::table('testimonies', function (Blueprint $table) {
            $table->string('youtube_credit_author', 150)->nullable()->after('youtube_id');
            $table->string('youtube_credit_channel', 150)->nullable()->after('youtube_credit_author');
            $table->string('youtube_credit_url', 300)->nullable()->after('youtube_credit_channel');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('testimonies', 'youtube_credit_author')) {
            Schema::table('testimonies', function (Blueprint $table) {
                $table->dropColumn(['youtube_credit_author', 'youtube_credit_channel', 'youtube_credit_url']);
            });
        }
    }
};
