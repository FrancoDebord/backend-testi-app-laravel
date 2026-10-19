<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Crédit de la vidéo YouTube d'origine (docs/fonctionnalites/videos-youtube.md) : auteur ou intervenant,
 * chaîne, lien http(s). Accepté seulement pour un témoignage YouTube ; vidé sinon.
 */
final class YouTubeCredit
{
    public const FIELDS = ['youtube_credit_author', 'youtube_credit_channel', 'youtube_credit_url'];

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'youtube_credit_author'  => ['nullable', 'string', 'max:150'],
            'youtube_credit_channel' => ['nullable', 'string', 'max:150'],
            'youtube_credit_url'     => ['nullable', 'string', 'max:300', 'url:http,https'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'youtube_credit_author.string'  => __('youtube_credit.validation.string'),
            'youtube_credit_author.max'     => __('youtube_credit.validation.author_max'),
            'youtube_credit_channel.string' => __('youtube_credit.validation.string'),
            'youtube_credit_channel.max'    => __('youtube_credit.validation.channel_max'),
            'youtube_credit_url.string'     => __('youtube_credit.validation.url_invalid'),
            'youtube_credit_url.max'        => __('youtube_credit.validation.url_max'),
            'youtube_credit_url.url'        => __('youtube_credit.validation.url_invalid'),
        ];
    }

    /**
     * Colonnes à enregistrer à la création : valeurs nettoyées pour une vidéo YouTube, null sinon.
     *
     * @return array<string, ?string>
     */
    public static function attributes(Request $request, bool $isYouTube): array
    {
        return collect(self::FIELDS)->mapWithKeys(fn (string $field) => [
            $field => $isYouTube ? self::clean($request->input($field)) : null,
        ])->all();
    }

    /**
     * Colonnes à modifier : seulement les champs envoyés pour une vidéo YouTube ; tout est vidé sinon.
     *
     * @return array<string, ?string>
     */
    public static function attributesForUpdate(Request $request, bool $isYouTube): array
    {
        if (!$isYouTube) {
            return array_fill_keys(self::FIELDS, null);
        }

        return collect(self::FIELDS)->filter(fn (string $field) => $request->exists($field))
            ->mapWithKeys(fn (string $field) => [$field => self::clean($request->input($field))])->all();
    }

    private static function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
