<?php

namespace App\Services;

use App\Enums\TestimonyKind;
use App\Models\Category;
use App\Models\Testimony;
use App\Models\User;
use App\Support\BibleReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;

/**
 * Témoignage de conversion (docs/fonctionnalites/temoignage-de-conversion.md) : un seul par compte (corbeille
 * exclue), modifiable par son auteur, même modération que les autres témoignages. Champs propres facultatifs :
 * année, lieu, verset. Pour une organisation, c'est le témoignage de la genèse du ministère : même sorte
 * « conversion », libellés conversion.genesis.*, l'année est celle de la fondation.
 */
class ConversionTestimonies
{
    public const FIELDS = ['conversion_year', 'conversion_place', 'conversion_verse'];

    public const MIN_YEAR = 1900;

    /** Règles communes à la création et à la modification (API et site). */
    public static function rules(): array
    {
        $max = (int) now()->year;

        return [
            'kind'             => ['sometimes', 'nullable', Rule::enum(TestimonyKind::class)],
            'conversion_year'  => ['nullable', 'integer', 'between:' . self::MIN_YEAR . ',' . $max],
            'conversion_place' => ['nullable', 'string', 'max:150'],
            'conversion_verse' => ['nullable', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && BibleReference::parse((string) $value) === null) {
                    $fail(__('conversion.invalid_verse'));
                }
            }],
        ];
    }

    public static function messages(?User $author = null): array
    {
        return [
            'conversion_year.between' => self::label('year_range', $author, ['min' => self::MIN_YEAR, 'max' => now()->year]),
        ];
    }

    /** Tout compte : témoignage de conversion pour une personne, genèse du ministère pour une organisation. */
    public static function canPublish(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Libellé selon l'auteur : conversion.genesis.{clé} pour une organisation (genèse du ministère) s'il existe,
     * sinon conversion.{clé}.
     */
    public static function label(string $key, ?User $author, array $replace = []): string
    {
        $genesis = 'conversion.genesis.' . $key;

        return __($author?->isOrganization() && Lang::has($genesis) ? $genesis : 'conversion.' . $key, $replace);
    }

    /** Catégorie par défaut : « ministere » (organisation) ou « conversion » si elle existe, sinon « autre ». */
    public static function defaultCategory(?User $author): string
    {
        $slug = $author?->isOrganization() ? 'ministere' : 'conversion';

        return Category::where('slug', $slug)->exists() ? $slug : 'autre';
    }

    /** Témoignage de conversion du compte (tous statuts, carnet compris ; corbeille exclue). */
    public function of(User|string $user, ?string $ignoreId = null): ?Testimony
    {
        return Testimony::query()
            ->where('user_id', $user instanceof User ? $user->id : $user)
            ->conversion()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->latest()
            ->first();
    }

    /** Témoignage de conversion publié (approuvé, public, vidéo lisible) d'un compte. */
    public function publishedOf(string $userId): ?Testimony
    {
        return Testimony::query()->where('user_id', $userId)->conversion()->published()->latest()->first();
    }

    /**
     * Le compte peut-il avoir ce témoignage de conversion (ou de genèse du ministère) ? 409 s'il en a déjà un.
     *
     * @throws ContentActionException
     */
    public function ensureAllowed(User $author, ?string $ignoreId = null): void
    {
        if ($existing = $this->of($author, $ignoreId)) {
            $message = self::label('already_exists', $author);
            throw new ContentActionException($message, 409, [
                'kind'                   => [$message],
                'conversion_testimony_id' => [$existing->id],
            ]);
        }
    }

    /**
     * Crée le témoignage ($create) ; pour un témoignage de conversion, vérifie les règles sous verrou du compte
     * (deux envois simultanés n'en créent qu'un). Utilisé par l'API (TestimonyCreator) et le site.
     *
     * @param callable(): Testimony $create
     * @throws ContentActionException
     */
    public function create(User $author, bool $isConversion, callable $create): Testimony
    {
        if (!$isConversion) {
            return $create();
        }

        return DB::transaction(function () use ($author, $create) {
            User::whereKey($author->id)->lockForUpdate()->first();
            $this->ensureAllowed($author);

            return $create();
        });
    }

    /** Valeurs de création : sorte et champs propres (vides pour un témoignage standard). */
    public static function attributesFor(Request $request): array
    {
        $kind = TestimonyKind::tryFrom((string) $request->input('kind')) ?? TestimonyKind::Standard;

        return ['kind' => $kind->value, ...self::fieldValues($request, $kind === TestimonyKind::Conversion, null)];
    }

    /**
     * Modification (PUT /testimonies/{id}, page « Modifier ») : changement de sorte vérifié pour l'auteur du
     * témoignage, champs propres mis à jour s'ils sont envoyés, effacés si le témoignage redevient standard.
     *
     * @throws ContentActionException
     */
    public function attributesForUpdate(Testimony $testimony, Request $request): array
    {
        $kind = $request->filled('kind') ? TestimonyKind::from((string) $request->input('kind')) : $testimony->kind;
        $changes = [];
        if ($kind !== $testimony->kind) {
            if ($kind === TestimonyKind::Conversion) {
                $this->ensureAllowed($testimony->user ?? User::findOrFail($testimony->user_id), $testimony->id);
            }
            $changes['kind'] = $kind->value;
        }

        return $changes + self::fieldValues($request, $kind === TestimonyKind::Conversion, $testimony);
    }

    /** Champs propres envoyés (null pour un témoignage standard). */
    private static function fieldValues(Request $request, bool $isConversion, ?Testimony $current): array
    {
        if (!$isConversion) {
            return array_fill_keys(self::FIELDS, null);
        }
        $values = [];
        foreach (self::FIELDS as $field) {
            if ($current === null || $request->exists($field)) {
                $value = $request->input($field);
                $values[$field] = is_string($value) ? (trim($value) !== '' ? trim($value) : null) : $value;
            }
        }
        if (array_key_exists('conversion_year', $values) && $values['conversion_year'] !== null) {
            $values['conversion_year'] = (int) $values['conversion_year'];
        }

        return $values;
    }
}
