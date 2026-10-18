<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Types de témoignages autorisés (docs/fonctionnalites/types-de-temoignages.md) :
 * quatre interrupteurs globaux (app_settings, groupe « publishing », activés par défaut)
 * et des exceptions par profil (users.testimony_type_overrides, null = suit le réglage global).
 */
return new class extends Migration
{
    private const SETTINGS = [
        'testimony_type_text_enabled'    => 'Témoignages écrits (texte)',
        'testimony_type_audio_enabled'   => 'Témoignages audio',
        'testimony_type_video_enabled'   => 'Témoignages vidéo',
        'testimony_type_youtube_enabled' => 'Liens YouTube (administrateurs, et profils autorisés)',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('users', 'testimony_type_overrides')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('testimony_type_overrides')->nullable();
            });
        }

        $now = now();
        foreach (self::SETTINGS as $key => $label) {
            // Une valeur déjà enregistrée n'est jamais écrasée.
            if (!DB::table('app_settings')->where('key', $key)->exists()) {
                DB::table('app_settings')->insert([
                    'key' => $key, 'value' => '1', 'type' => 'boolean', 'group' => 'publishing', 'label' => $label,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', array_keys(self::SETTINGS))->delete();

        if (Schema::hasColumn('users', 'testimony_type_overrides')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('testimony_type_overrides'));
        }
    }
};
