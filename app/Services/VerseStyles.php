<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerseStylePreference;
use App\Support\VerseDesigns;
use Illuminate\Validation\Rule;

/**
 * Design et réglages du texte du verset du jour, par membre (API et site).
 * Règle de l'app : un design choisi vaut pour le jour du choix (design_chosen_on) ; le lendemain,
 * retour au design du jour, sauf si design_always. Voir docs/fonctionnalites/designs-du-verset.md
 */
class VerseStyles
{
    public const FIELDS = ['design_id', 'design_chosen_on', 'design_always', 'font', 'text_size', 'text_align'];

    /** Valeurs par défaut (aucune préférence enregistrée). */
    public static function defaults(): array
    {
        $d = VerseDesigns::defaults();

        return [
            'design_id'        => null,
            'design_chosen_on' => null,
            'design_always'    => (bool) $d['design_always'],
            'font'             => $d['font'],
            'text_size'        => $d['text_size'],
            'text_align'       => $d['text_align'],
        ];
    }

    /** Valeurs d'un membre (ou valeurs par défaut s'il n'a rien enregistré). */
    public function forUser(User $user): array
    {
        $pref = $user->verseStylePreference;

        return $pref ? $this->format($pref) : self::defaults();
    }

    /** Validation stricte contre le catalogue (PUT partiel : seuls les champs envoyés). */
    public static function rules(): array
    {
        return [
            'design_id'        => ['sometimes', 'nullable', 'string', Rule::in(VerseDesigns::activeIds())],
            'design_chosen_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'design_always'    => ['sometimes', 'boolean'],
            'font'             => ['sometimes', 'string', Rule::in(VerseDesigns::fontIds())],
            'text_size'        => ['sometimes', 'string', Rule::in(VerseDesigns::sizeIds())],
            'text_align'       => ['sometimes', 'string', Rule::in(VerseDesigns::alignIds())],
        ];
    }

    public static function messages(): array
    {
        return [
            'design_id.in'                 => __('api.verse_style.unknown_design'),
            'font.in'                      => __('api.verse_style.unknown_font'),
            'text_size.in'                 => __('api.verse_style.unknown_size'),
            'text_align.in'                => __('api.verse_style.unknown_align'),
            'design_chosen_on.date_format' => __('api.verse_style.date_format'),
        ];
    }

    /**
     * Préférences + design du jour et design affiché (GET / PUT /me/verse-style) : effective_design_id vaut le
     * design choisi s'il est « toujours » (design_always) ou choisi aujourd'hui, quel que soit le jour.
     */
    public static function withEffectiveDesign(array $style, ?string $date = null): array
    {
        $date ??= VerseDesignOfTheDay::todayDate();
        $ofTheDay = VerseDesignOfTheDay::forDate($date);

        return $style + [
            'design_of_the_day'   => $ofTheDay,
            'effective_design_id' => self::effectiveDesignId($style, $ofTheDay),
        ];
    }

    /** Enregistre les champs validés ($data) et renvoie les valeurs complètes. */
    public function update(User $user, array $data): array
    {
        $values = array_intersect_key($data, array_flip(self::FIELDS));
        if (array_key_exists('design_always', $values)) {
            $values['design_always'] = (bool) $values['design_always'];
        }
        $pref = VerseStylePreference::updateOrCreate(['user_id' => $user->id], $values);
        $user->setRelation('verseStylePreference', $pref);

        return $this->format($pref->refresh());
    }

    /**
     * Design affiché : le design choisi s'il vaut « toujours » ou s'il a été choisi aujourd'hui
     * (fuseau du verset) et qu'il est encore actif ; sinon le design du jour.
     */
    public static function effectiveDesignId(?array $style, string $designOfTheDay, ?string $today = null): string
    {
        $chosen = $style['design_id'] ?? null;
        if (!VerseDesigns::isActive($chosen)) {
            return $designOfTheDay;
        }
        $today ??= VerseDesignOfTheDay::todayDate();

        return !empty($style['design_always']) || ($style['design_chosen_on'] ?? null) === $today ? $chosen : $designOfTheDay;
    }

    /**
     * Données du site (carte, plein écran) : valeurs du membre (défauts pour un visiteur, complétées
     * par son navigateur), design affiché et configuration JSON lue par resources/js/verse-style.js.
     */
    public function forView(?User $viewer, string $date): array
    {
        $ofTheDay = VerseDesignOfTheDay::forDate($date);
        $style = $viewer ? $this->forUser($viewer) : self::defaults();

        return [
            'style'     => $style,
            'designId'  => self::effectiveDesignId($viewer ? $style : null, $ofTheDay),
            'designs'   => VerseDesigns::active(),
            'config'    => [
                'today'          => VerseDesignOfTheDay::todayDate(),
                'designOfTheDay' => $ofTheDay,
                'signedIn'       => $viewer !== null,
                'style'          => $viewer ? $style : null,
                'defaults'       => self::defaults(),
                'designs'        => VerseDesigns::active()->map(fn ($d) => ['id' => $d['id'], 'name' => $d['name'], 'tone' => VerseDesigns::tone($d['id'])])->all(),
                'fonts'          => VerseDesigns::fontIds(),
                'sizes'          => VerseDesigns::sizeIds(),
                'aligns'         => VerseDesigns::alignIds(),
                'endpoint'       => route('daily-verse.style'),
            ],
        ];
    }

    /** Tailles (px) du texte pour chaque réglage (small, medium, large) dans un contexte. */
    public static function sizesFor(string $text, string $context): array
    {
        return collect(VerseDesigns::sizeIds())->mapWithKeys(fn ($id) => [$id => VerseDesigns::textSize($text, $context, $id)])->all();
    }

    private function format(VerseStylePreference $pref): array
    {
        $d = self::defaults();

        return [
            'design_id'        => $pref->design_id,
            'design_chosen_on' => $pref->design_chosen_on?->toDateString(),
            'design_always'    => (bool) $pref->design_always,
            'font'             => in_array($pref->font, VerseDesigns::fontIds(), true) ? $pref->font : $d['font'],
            'text_size'        => in_array($pref->text_size, VerseDesigns::sizeIds(), true) ? $pref->text_size : $d['text_size'],
            'text_align'       => in_array($pref->text_align, VerseDesigns::alignIds(), true) ? $pref->text_align : $d['text_align'],
        ];
    }
}
