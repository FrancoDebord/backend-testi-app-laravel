<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Champs de publication sur le web d'un témoignage, communs au site et à l'API (création, modification) :
 * accord pour le web, anonymat sur le site, résumé et transcription (texte lu par Google pour une vidéo).
 * Noms acceptés : snake_case (site) ou camelCase (application). Champ absent : valeur inchangée
 * (à la création : accord pour le web donné, non anonyme). Voir docs/fonctionnalites/referencement.md
 */
final class WebPublication
{
    private const FIELDS = [
        'publication_web' => 'publicationWeb',
        'is_anonymous'    => 'isAnonymous',
        'resume'          => 'resume',
        'transcription'   => 'transcription',
    ];

    public static function rules(): array
    {
        return [
            'publication_web' => ['nullable', 'boolean'],
            'publicationWeb'  => ['nullable', 'boolean'],
            'is_anonymous'    => ['nullable', 'boolean'],
            'isAnonymous'     => ['nullable', 'boolean'],
            'resume'          => ['nullable', 'string', 'max:5000'],
            'transcription'   => ['nullable', 'string', 'max:100000'],
        ];
    }

    public static function messages(): array
    {
        return [
            'resume.max'        => __('seo.validation.resume_max'),
            'transcription.max' => __('seo.validation.transcription_max'),
        ];
    }

    /** Valeurs envoyées (seulement celles présentes dans la requête). */
    public static function attributes(Request $request): array
    {
        $out = [];
        foreach (self::FIELDS as $snake => $camel) {
            $key = $request->has($snake) ? $snake : ($request->has($camel) ? $camel : null);
            if ($key === null) continue;
            $out[$snake] = in_array($snake, ['publication_web', 'is_anonymous'], true)
                ? $request->boolean($key)
                : (filled($request->input($key)) ? trim((string) $request->input($key)) : null);
        }

        return $out;
    }
}
