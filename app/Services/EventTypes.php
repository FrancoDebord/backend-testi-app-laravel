<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des types d'événements par les administrateurs (API et site) : création, modification,
 * ordre, suppression avec remplacement. Voir docs/fonctionnalites/types-evenements.md
 */
class EventTypes
{
    // ─── Validation ──────────────────────────────────────────────────────────

    public static function createRules(): array
    {
        return [
            'name_fr'   => ['required', 'string', 'max:80'],
            'name_en'   => ['nullable', 'string', 'max:80'],
            'icon'      => ['nullable', 'string', Rule::in(array_keys(EventType::ICONS))],
            'slug'      => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/', 'unique:event_types,slug'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public static function updateRules(): array
    {
        return [
            'name_fr'   => ['sometimes', 'required', 'string', 'max:80'],
            'name_en'   => ['nullable', 'string', 'max:80'],
            'icon'      => ['nullable', 'string', Rule::in(array_keys(EventType::ICONS))],
            'is_active' => ['nullable', 'boolean'],
            'position'  => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public static function messages(): array
    {
        return [
            'name_fr.required' => __('event_types.validation.name_required'),
            'name_fr.max'      => __('event_types.validation.name_max'),
            'name_en.max'      => __('event_types.validation.name_max'),
            'icon.in'          => __('event_types.validation.icon_invalid'),
            'slug.regex'       => __('event_types.validation.slug_format'),
            'slug.unique'      => __('event_types.validation.slug_taken'),
            'slug.max'         => __('event_types.validation.slug_max'),
        ];
    }

    /**
     * Règle du champ `type` d'un événement : nom court d'un type actif ; `$current` (modification) :
     * le type déjà enregistré reste accepté même s'il a été désactivé depuis.
     */
    public static function eventRule(?string $current = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($current) {
            $slug = is_string($value) ? $value : null;
            $type = EventType::findBySlug($slug);
            if ($type && ($type->is_active || ($current !== null && $slug === $current))) {
                return;
            }
            $fail(__('event_types.validation.event_type_invalid'));
        };
    }

    // ─── Format JSON ─────────────────────────────────────────────────────────

    /** @param array<string, int>|null $counts nombres d'événements par slug (EventType::eventCounts()) */
    public static function toArray(EventType $type, ?array $counts = null): array
    {
        return [
            'id'          => $type->id,
            'slug'        => $type->slug,
            'name'        => $type->localizedName(),
            'nameFr'      => $type->name_fr,
            'nameEn'      => $type->name_en,
            'icon'        => $type->icon ?: EventType::DEFAULT_ICON,
            'position'    => (int) $type->position,
            'isActive'    => (bool) $type->is_active,
            'eventsCount' => $counts !== null ? ($counts[$type->slug] ?? 0) : $type->eventsCount(),
        ];
    }

    /** Liste (actifs seulement, sauf `$all`) au format JSON. */
    public static function listArray(bool $all = false): array
    {
        $counts = EventType::eventCounts();
        $types  = $all ? EventType::cached() : EventType::activeTypes();

        return $types->map(fn (EventType $t) => self::toArray($t, $counts))->values()->all();
    }

    // ─── Actions ─────────────────────────────────────────────────────────────

    public function create(array $data): EventType
    {
        $slug = filled($data['slug'] ?? null) ? $data['slug'] : EventType::uniqueSlugFrom($data['name_fr']);

        return EventType::create([
            'slug'      => $slug,
            'name_fr'   => trim($data['name_fr']),
            'name_en'   => filled($data['name_en'] ?? null) ? trim($data['name_en']) : null,
            'icon'      => ($data['icon'] ?? null) ?: EventType::DEFAULT_ICON,
            'position'  => (int) EventType::query()->max('position') + 1,
            'is_active' => array_key_exists('is_active', $data) && $data['is_active'] !== null ? (bool) $data['is_active'] : true,
        ]);
    }

    /** Le nom court n'est jamais modifié (les événements y font référence). */
    public function update(EventType $type, array $data): EventType
    {
        $changes = [];
        if (array_key_exists('name_fr', $data)) $changes['name_fr'] = trim((string) $data['name_fr']);
        if (array_key_exists('name_en', $data)) $changes['name_en'] = filled($data['name_en']) ? trim($data['name_en']) : null;
        if (filled($data['icon'] ?? null))      $changes['icon'] = $data['icon'];
        if (array_key_exists('position', $data) && $data['position'] !== null) $changes['position'] = (int) $data['position'];
        if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
            $active = (bool) $data['is_active'];
            if (!$active && $type->is_active && $this->isLastActive($type)) {
                throw ValidationException::withMessages(['is_active' => __('event_types.errors.last_active')]);
            }
            $changes['is_active'] = $active;
        }

        $type->fill($changes)->save();

        return $type->refresh();
    }

    /**
     * Supprime un type. Utilisé par des événements : `$replaceWith` (nom court d'un autre type actif)
     * obligatoire, les événements y sont déplacés (supprimés compris). Jamais le dernier type actif.
     *
     * @throws EventTypeInUseException|ValidationException
     */
    public function delete(EventType $type, ?string $replaceWith = null): void
    {
        if ($type->is_active && $this->isLastActive($type)) {
            throw ValidationException::withMessages(['event_type' => __('event_types.errors.last_active')]);
        }

        $count  = $type->eventsCount();
        $target = filled($replaceWith) ? EventType::query()->where('slug', $replaceWith)->where('is_active', true)
            ->whereKeyNot($type->id)->first() : null;

        if ($count > 0 && !$target) {
            throw new EventTypeInUseException($count, filled($replaceWith));
        }

        DB::transaction(function () use ($type, $target) {
            if ($target) {
                Event::withTrashed()->where('type', $type->slug)->update(['type' => $target->slug]);
            }
            $type->delete();
        });
    }

    /** @param int[] $ids ordre voulu ; les types absents de la liste suivent, dans leur ordre actuel. */
    public function reorder(array $ids): void
    {
        $ids     = array_values(array_unique(array_map('intval', $ids)));
        $current = EventType::query()->ordered()->pluck('id')->all();
        $order   = [...array_values(array_intersect($ids, $current)), ...array_values(array_diff($current, $ids))];

        DB::transaction(function () use ($order) {
            foreach ($order as $i => $id) {
                EventType::query()->whereKey($id)->update(['position' => $i + 1]);
            }
        });
        EventType::clearCache();
    }

    private function isLastActive(EventType $type): bool
    {
        return !EventType::query()->where('is_active', true)->whereKeyNot($type->id)->exists();
    }
}
