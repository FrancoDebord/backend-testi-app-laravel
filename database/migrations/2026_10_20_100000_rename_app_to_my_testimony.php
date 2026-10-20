<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * L'application s'appelle désormais « My Testimony » (nom de marque, jamais traduit).
 * Met à jour le paramètre global app_name s'il porte encore l'ancien nom.
 */
return new class extends Migration
{
    private const OLD_NAMES = ['TestiApp', 'Testi App', 'Témoignages', 'Témoignages de Gloire'];

    public function up(): void
    {
        DB::table('app_settings')->where('key', 'app_name')->whereIn('value', self::OLD_NAMES)
            ->update(['value' => 'My Testimony', 'updated_at' => now()]);
        Cache::forget('setting_app_name');
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'app_name')->where('value', 'My Testimony')
            ->update(['value' => 'TestiApp', 'updated_at' => now()]);
        Cache::forget('setting_app_name');
    }
};
