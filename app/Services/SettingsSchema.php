<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Paramètres globaux : source unique des groupes, de l'ordre, des libellés et des descriptions,
 * utilisée par la page web (Administration › Paramètres) ET par l'API (GET /api/v1/admin/settings/schema).
 * Les deux affichent donc exactement les mêmes réglages. Voir docs/fonctionnalites/parametres-globaux.md
 */
class SettingsSchema
{
    /** Groupes et réglages, dans l'ordre d'affichage. Libellés : lang/{fr,en}/settings.php. */
    public const GROUPS = [
        'app'          => ['app_name', 'app_tagline', 'app_version', 'maintenance_mode'],
        // Types de témoignages autorisés (docs/fonctionnalites/types-de-temoignages.md)
        'publishing'   => ['testimony_type_text_enabled', 'testimony_type_audio_enabled', 'testimony_type_video_enabled', 'testimony_type_youtube_enabled'],
        'moderation'   => ['auto_approve_testimonies', 'max_testimony_length', 'max_audio_duration_sec', 'max_video_duration_sec', 'max_file_size_mb'],
        'features'     => ['allow_comments', 'allow_reactions', 'allow_sharing', 'require_consent_form'],
        // Interrupteur global des push (PushNotifications::GLOBAL_SETTING, docs/fonctionnalites/notifications-push.md)
        'notifications' => [\App\Services\PushNotifications::GLOBAL_SETTING],
        'registration' => ['allow_registration', 'default_role', 'require_email_verification'],
    ];

    /**
     * Réglages masqués partout (et refusés en modification) : remplacés par les interrupteurs
     * du groupe « publishing » (testimony_type_audio_enabled, testimony_type_video_enabled).
     */
    public const HIDDEN = ['allow_audio', 'allow_video'];

    public const TYPES = ['boolean', 'integer', 'string', 'json'];

    /** Longueur maximale d'une valeur texte ou JSON. */
    private const MAX_LENGTH = 5000;

    /**
     * Groupes affichés : réglages enregistrés (table app_settings), hors réglages masqués, dans l'ordre de GROUPS ;
     * un réglage enregistré mais absent de GROUPS est ajouté à la fin de son groupe (libellé de la base).
     *
     * @return list<array{key: string, label: string, settings: list<array{key: string, label: string, description: ?string, type: string, value: mixed}>}>
     */
    public function groups(): array
    {
        $stored = AppSetting::query()->whereNotIn('key', self::HIDDEN)->orderBy('key')->get()->keyBy('key');

        $groups = [];
        foreach (self::GROUPS as $group => $keys) {
            foreach ($keys as $key) {
                if ($setting = $stored->pull($key)) {
                    $groups[$group][] = $setting;
                }
            }
        }
        // Réglages non répertoriés : à la fin de leur groupe (groupes inconnus : à la fin, par ordre alphabétique).
        foreach ($stored->sortBy('group') as $setting) {
            $groups[$setting->group ?: 'app'][] = $setting;
        }

        return collect($groups)->map(fn (array $settings, string $group) => [
            'key'      => $group,
            'label'    => $this->groupLabel($group),
            'settings' => array_map(fn (AppSetting $s) => $this->item($s), $settings),
        ])->values()->all();
    }

    /** @return array<string, AppSetting> réglages modifiables (affichés), par clé */
    public function editable(): Collection
    {
        return AppSetting::query()->whereNotIn('key', self::HIDDEN)->get()->keyBy('key');
    }

    /**
     * Vérifie `{ clé: valeur }` contre le schéma (clé affichée, type) et renvoie les valeurs à enregistrer.
     *
     * @return array<string, string>
     * @throws ValidationException 422 : clé inconnue (ou masquée) ou type incorrect
     */
    public function validate(array $input): array
    {
        if ($input === []) {
            throw ValidationException::withMessages(['settings' => __('settings.validation.empty')]);
        }

        $settings = $this->editable();
        $errors = [];
        $values = [];
        foreach ($input as $key => $value) {
            $key = (string) $key;
            $setting = $settings->get($key);
            if (!$setting) {
                $errors[$key] = __('settings.validation.unknown', ['key' => $key]);
                continue;
            }
            $normalized = self::normalize($setting->type ?: 'string', $value);
            if ($normalized === null) {
                $errors[$key] = __('settings.validation.' . (in_array($setting->type, self::TYPES, true) ? $setting->type : 'string'), ['key' => $key]);
                continue;
            }
            if (mb_strlen($normalized) > self::MAX_LENGTH) {
                $errors[$key] = __('settings.validation.too_long', ['key' => $key]);
                continue;
            }
            $values[$key] = $normalized;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    /** Valide puis enregistre (cache vidé par AppSetting::set). */
    public function update(array $input): void
    {
        foreach ($this->validate($input) as $key => $value) {
            AppSetting::set($key, $value);
        }
    }

    public function groupLabel(string $group): string
    {
        $key = "settings.groups.{$group}";

        return __($key) !== $key ? __($key) : Str::ucfirst(str_replace('_', ' ', $group));
    }

    /** @return array{key: string, label: string, description: ?string, type: string, value: mixed} */
    private function item(AppSetting $setting): array
    {
        $key = "settings.items.{$setting->key}";
        $translated = __($key);
        $known = is_array($translated);

        return [
            'key'         => $setting->key,
            'label'       => $known ? $translated['label'] : ($setting->label ?: $setting->key),
            'description' => $known ? ($translated['description'] ?? null) : null,
            'type'        => in_array($setting->type, self::TYPES, true) ? $setting->type : 'string',
            'value'       => $setting->typedValue(),
        ];
    }

    private static function isJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /** Valeur enregistrée (texte) si elle correspond au type, sinon null. */
    private static function normalize(string $type, mixed $value): ?string
    {
        return match ($type) {
            'boolean' => match (true) {
                is_bool($value)                                           => $value ? '1' : '0',
                $value === 1 || $value === 0                              => (string) $value,
                is_string($value) && in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true)  => '1',
                is_string($value) && in_array(strtolower($value), ['0', 'false', 'off', 'no'], true) => '0',
                default                                                   => null,
            },
            'integer' => match (true) {
                is_int($value)                                            => (string) $value,
                is_string($value) && preg_match('/^-?\d{1,15}$/', trim($value)) === 1 => (string) (int) trim($value),
                default                                                   => null,
            },
            'json' => match (true) {
                is_array($value)                                          => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                is_string($value) && self::isJson($value)                 => $value,
                default                                                   => null,
            },
            default => match (true) {
                is_string($value)                                         => $value,
                $value === null                                           => '',
                default                                                   => null,
            },
        };
    }
}
