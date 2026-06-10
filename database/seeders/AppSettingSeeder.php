<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // App group
            ['key' => 'app_name', 'value' => 'TestiApp', 'type' => 'string', 'group' => 'app', 'label' => 'Nom de l\'application'],
            ['key' => 'app_tagline', 'value' => 'Partagez votre foi', 'type' => 'string', 'group' => 'app', 'label' => 'Slogan'],
            ['key' => 'app_version', 'value' => '1.0.0', 'type' => 'string', 'group' => 'app', 'label' => 'Version'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'app', 'label' => 'Mode maintenance'],

            // Moderation group
            ['key' => 'auto_approve_testimonies', 'value' => '0', 'type' => 'boolean', 'group' => 'moderation', 'label' => 'Approuver automatiquement les témoignages'],
            ['key' => 'max_testimony_length', 'value' => '10000', 'type' => 'integer', 'group' => 'moderation', 'label' => 'Longueur max. du texte'],
            ['key' => 'max_audio_duration_sec', 'value' => '900', 'type' => 'integer', 'group' => 'moderation', 'label' => 'Durée audio max (secondes)'],
            ['key' => 'max_video_duration_sec', 'value' => '600', 'type' => 'integer', 'group' => 'moderation', 'label' => 'Durée vidéo max (secondes)'],
            ['key' => 'max_file_size_mb', 'value' => '100', 'type' => 'integer', 'group' => 'moderation', 'label' => 'Taille max. fichier (Mo)'],

            // Features group
            ['key' => 'allow_comments', 'value' => '1', 'type' => 'boolean', 'group' => 'features', 'label' => 'Autoriser les commentaires'],
            ['key' => 'allow_reactions', 'value' => '1', 'type' => 'boolean', 'group' => 'features', 'label' => 'Autoriser les réactions'],
            ['key' => 'allow_sharing', 'value' => '1', 'type' => 'boolean', 'group' => 'features', 'label' => 'Autoriser le partage'],
            ['key' => 'allow_audio', 'value' => '1', 'type' => 'boolean', 'group' => 'features', 'label' => 'Autoriser les témoignages audio'],
            ['key' => 'allow_video', 'value' => '1', 'type' => 'boolean', 'group' => 'features', 'label' => 'Autoriser les témoignages vidéo'],
            ['key' => 'require_consent_form', 'value' => '1', 'type' => 'boolean', 'group' => 'features', 'label' => 'Formulaire de consentement obligatoire'],

            // Registration group
            ['key' => 'allow_registration', 'value' => '1', 'type' => 'boolean', 'group' => 'registration', 'label' => 'Autoriser les inscriptions'],
            ['key' => 'default_role', 'value' => 'utilisateur', 'type' => 'string', 'group' => 'registration', 'label' => 'Rôle par défaut'],
            ['key' => 'require_email_verification', 'value' => '0', 'type' => 'boolean', 'group' => 'registration', 'label' => 'Vérification email obligatoire'],
        ];

        foreach ($settings as $setting) {
            DB::table('app_settings')->insertOrIgnore($setting);
        }
    }
}
