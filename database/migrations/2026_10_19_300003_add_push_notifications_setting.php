<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Interrupteur global des notifications push (PushNotifications::GLOBAL_SETTING), affiché dans les paramètres
 * du site et de l'application (groupe « notifications », docs/fonctionnalites/parametres-globaux.md).
 * Activé par défaut ; une valeur déjà enregistrée n'est jamais écrasée.
 */
return new class extends Migration
{
    private const KEY = 'push_notifications_enabled';

    public function up(): void
    {
        if (DB::table('app_settings')->where('key', self::KEY)->exists()) {
            // Ligne créée par un enregistrement antérieur (type « string » par défaut) : type et groupe corrigés.
            DB::table('app_settings')->where('key', self::KEY)->update(['type' => 'boolean', 'group' => 'notifications']);
            return;
        }

        DB::table('app_settings')->insert([
            'key' => self::KEY, 'value' => '1', 'type' => 'boolean', 'group' => 'notifications',
            'label' => 'Notifications push', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', self::KEY)->delete();
    }
};
