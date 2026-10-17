<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - users.locale : langue du compte (fr | en) pour les push et les e-mails (docs/fonctionnalites/langues.md) ;
 * - testimonies.kind (standard | conversion) et champs du témoignage de conversion
 *   (docs/fonctionnalites/temoignage-de-conversion.md) ;
 * - device_tokens.access_token_id : session de l'application qui a enregistré le jeton, supprimé à la
 *   déconnexion de cette session (docs/fonctionnalites/notifications-push.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('fr')->after('country');
        });

        Schema::table('testimonies', function (Blueprint $table) {
            $table->string('kind', 20)->default('standard')->after('type');
            $table->unsignedSmallInteger('conversion_year')->nullable()->after('kind');
            $table->string('conversion_place', 150)->nullable()->after('conversion_year');
            $table->string('conversion_verse', 100)->nullable()->after('conversion_place');
            $table->index(['kind', 'status']);
            $table->index(['user_id', 'kind']);
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('access_token_id')->nullable()->after('platform')->index();
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropIndex(['access_token_id']);
            $table->dropColumn('access_token_id');
        });
        Schema::table('testimonies', function (Blueprint $table) {
            $table->dropIndex(['kind', 'status']);
            $table->dropIndex(['user_id', 'kind']);
            $table->dropColumn(['kind', 'conversion_year', 'conversion_place', 'conversion_verse']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
