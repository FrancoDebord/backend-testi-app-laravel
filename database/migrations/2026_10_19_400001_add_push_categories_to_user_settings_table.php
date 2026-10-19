<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications push sur consentement : chaque type de notification appartient à une catégorie que la personne
 * peut couper (App\Services\PushNotifications::PREFERENCES). Nouvelles catégories, activées par défaut :
 * abonnés, événements et sessions de prière, compte, modération (équipe).
 * Voir docs/fonctionnalites/notifications-push.md
 */
return new class extends Migration
{
    private const COLUMNS = ['push_follows', 'push_events', 'push_account', 'push_moderation'];

    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (!Schema::hasColumn('user_settings', $column)) {
                    $table->boolean($column)->default(true);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('user_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
