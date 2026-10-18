<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;

/**
 * Types de témoignages autorisés : text, audio, video, youtube (lien YouTube) — docs/fonctionnalites/types-de-temoignages.md
 *
 * Réglage global (app_settings, groupe « publishing ») : testimony_type_{type}_enabled, activé par défaut.
 * Exception par profil (users.testimony_type_overrides) : true | false | null (null ou absent = suit le global).
 *
 *   text, audio, video : exception ?? global
 *   youtube            : exception ?? (global ET administrateur) — réservé aux administrateurs par défaut ;
 *                        un administrateur peut l'accorder à un profil précis.
 *
 * Le compte qui agit (X-Act-As, compte choisi sur le site) est celui dont les droits s'appliquent.
 * Le carnet privé suit la même règle ; modifier un témoignage existant n'est jamais bloqué.
 */
class TestimonyTypePolicy
{
    public const TYPES = ['text', 'audio', 'video', 'youtube'];

    public static function settingKey(string $type): string
    {
        return "testimony_type_{$type}_enabled";
    }

    /**
     * Interrupteurs globaux.
     *
     * @return array{text: bool, audio: bool, video: bool, youtube: bool}
     */
    public function global(): array
    {
        $global = [];
        foreach (self::TYPES as $type) {
            $global[$type] = (bool) AppSetting::get(self::settingKey($type), true);
        }

        return $global;
    }

    /**
     * Exceptions du profil (null = suit le réglage global), toujours avec les quatre clés.
     *
     * @return array{text: ?bool, audio: ?bool, video: ?bool, youtube: ?bool}
     */
    public function overrides(User $user): array
    {
        $stored = is_array($user->testimony_type_overrides) ? $user->testimony_type_overrides : [];
        $overrides = [];
        foreach (self::TYPES as $type) {
            $value = $stored[$type] ?? null;
            $overrides[$type] = $value === null ? null : (bool) $value;
        }

        return $overrides;
    }

    public function allows(User $user, string $type): bool
    {
        if (!in_array($type, self::TYPES, true)) {
            return false;
        }

        return $this->decide($type, $this->overrides($user)[$type], $this->global()[$type], $user);
    }

    /**
     * Types que la personne peut publier, dans l'ordre text, audio, video, youtube.
     *
     * @return array<int, string>
     */
    public function allowed(User $user): array
    {
        $global    = $this->global();
        $overrides = $this->overrides($user);

        return array_values(array_filter(self::TYPES, fn (string $type) => $this->decide($type, $overrides[$type], $global[$type], $user)));
    }

    /**
     * Enregistre les exceptions envoyées (clés absentes inchangées ; null = retour au réglage global).
     *
     * @param array<string, bool|null> $changes
     */
    public function updateOverrides(User $user, array $changes): void
    {
        $overrides = $this->overrides($user);
        foreach ($changes as $type => $value) {
            if (in_array($type, self::TYPES, true)) {
                $overrides[$type] = $value === null ? null : (bool) $value;
            }
        }

        // Plus aucune exception : colonne remise à null.
        $kept = array_filter($overrides, fn ($v) => $v !== null);
        $user->forceFill(['testimony_type_overrides' => $kept === [] ? null : $overrides])->save();
    }

    /**
     * Type demandé à la publication : youtube dès qu'un lien YouTube est envoyé, sinon `type`.
     */
    public static function requestedType(?string $type, ?string $youtubeUrl): ?string
    {
        if (filled($youtubeUrl) || $type === 'youtube') {
            return 'youtube';
        }

        return $type;
    }

    /**
     * Vérifie qu'un témoignage du type `$type` peut être publié par la personne.
     *
     * @throws ContentActionException 403, erreurs { type: [...] }
     */
    public function ensureAllowed(User $user, ?string $type): void
    {
        if ($type === null || !in_array($type, self::TYPES, true) || $this->allows($user, $type)) {
            return;
        }

        $error = $this->disabledMessage($type);
        $message = $type === 'youtube' && $this->overrides($user)['youtube'] === null && $this->global()['youtube']
            ? __('testimonies.validation.youtube_admin_only') // réservé aux administrateurs (comportement d'origine)
            : $error;

        throw new ContentActionException($message, 403, ['type' => [$error]]);
    }

    public function disabledMessage(string $type): string
    {
        return __('publishing.type_disabled', ['type' => __('publishing.type_names.' . $type)]);
    }

    private function decide(string $type, ?bool $override, bool $global, User $user): bool
    {
        if ($override !== null) {
            return $override;
        }

        return $type === 'youtube' ? ($global && $user->isAdmin()) : $global;
    }
}
