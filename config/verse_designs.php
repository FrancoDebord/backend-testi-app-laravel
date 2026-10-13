<?php

/*
|--------------------------------------------------------------------------
| Designs du verset du jour — catalogue (source unique de vérité)
|--------------------------------------------------------------------------
| Ces identifiants doivent rester identiques à ceux de l'app Flutter (kVerseDesigns, VerseFont,
| VerseTextSize, VerseAlign). Ne jamais renommer un id : désactiver avec active = false.
|
| Couleurs des designs : config/verse_design_colors.php et resources/css/verse-designs.css, générés
| par scripts/generate-verse-colors.php. Voir docs/fonctionnalites/designs-du-verset.md
*/

return [

    // Ordre du sélecteur. tone_light_theme : rendu indicatif en thème clair (calculé d'après la
    // luminosité du fond, jamais lu comme une donnée de référence ; voir le champ « tone » de l'API).
    'designs' => [
        ['id' => 'aube',       'name' => 'Aube',          'mood' => 'dawn',    'tone_light_theme' => 'light', 'order' => 1,  'active' => true],
        ['id' => 'nuit',       'name' => 'Nuit étoilée',  'mood' => 'night',   'tone_light_theme' => 'dark',  'order' => 2,  'active' => true],
        ['id' => 'ocean',      'name' => 'Océan profond', 'mood' => 'deep',    'tone_light_theme' => 'dark',  'order' => 3,  'active' => true],
        ['id' => 'lumiere',    'name' => 'Lumière douce', 'mood' => 'light',   'tone_light_theme' => 'light', 'order' => 4,  'active' => true],
        ['id' => 'braise',     'name' => 'Braise',        'mood' => 'warm',    'tone_light_theme' => 'dark',  'order' => 5,  'active' => true],
        ['id' => 'minimal',    'name' => 'Minimal',       'mood' => 'minimal', 'tone_light_theme' => 'light', 'order' => 6,  'active' => true],
        ['id' => 'vitrail',    'name' => 'Vitrail',       'mood' => 'deep',    'tone_light_theme' => 'dark',  'order' => 7,  'active' => true],
        ['id' => 'crepuscule', 'name' => 'Crépuscule',    'mood' => 'dawn',    'tone_light_theme' => 'dark',  'order' => 8,  'active' => true],
        ['id' => 'matin',      'name' => 'Matin clair',   'mood' => 'light',   'tone_light_theme' => 'light', 'order' => 9,  'active' => true],
        ['id' => 'or',         'name' => 'Or doux',       'mood' => 'warm',    'tone_light_theme' => 'light', 'order' => 10, 'active' => true],
        ['id' => 'encre',      'name' => 'Encre',         'mood' => 'deep',    'tone_light_theme' => 'dark',  'order' => 11, 'active' => true],
        ['id' => 'ardoise',    'name' => 'Ardoise',       'mood' => 'minimal', 'tone_light_theme' => 'dark',  'order' => 12, 'active' => true],
    ],

    // VerseFont (Flutter). Polices embarquées : resources/fonts (woff2) et resources/fonts/ttf (image de partage).
    'fonts' => [
        ['id' => 'lora',        'family' => 'Lora',           'label' => 'Lora'],
        ['id' => 'sourceSerif', 'family' => 'Source Serif 4', 'label' => 'Source Serif'],
        ['id' => 'inter',       'family' => 'Inter',          'label' => 'Inter'],
        ['id' => 'nunitoSans',  'family' => 'Nunito Sans',    'label' => 'Nunito Sans'],
    ],

    // VerseTextSize : facteur appliqué à la taille calculée d'après la longueur (text_size_range).
    'text_sizes' => [
        ['id' => 'small',  'factor' => 0.86, 'label' => 'Petit'],
        ['id' => 'medium', 'factor' => 1.0,  'label' => 'Moyen'],
        ['id' => 'large',  'factor' => 1.16, 'label' => 'Grand'],
    ],

    // VerseAlign.
    'text_aligns' => [
        ['id' => 'start',  'label' => 'À gauche'],
        ['id' => 'center', 'label' => 'Centré'],
    ],

    'defaults' => [
        'design_always' => false,
        'font'          => 'lora',
        'text_size'     => 'medium',
        'text_align'    => 'start',
    ],

    // Taille du texte (px logiques), interpolation de l'app Flutter :
    //   len = longueur du texte affiché (sans espaces de début et de fin, guillemets « » compris)
    //   t = clamp((len − length_from) / length_span, 0, 1)
    //   taille = max((max − (max − min) × t) × facteur, min)
    // Plein écran et image de partage : réduite ensuite de 1 en 1 jusqu'à ce que le texte tienne,
    // sans descendre sous min. L'image (1080 px de large) est dessinée à l'échelle share_scale.
    'text_size_range' => [
        'card'       => ['min' => 18, 'max' => 24],
        'fullscreen' => ['min' => 22, 'max' => 34],
        'share'      => ['min' => 18, 'max' => 30],
    ],
    'length_from'  => 80,
    'length_span'  => 240,
    'share_scale'  => 3,
    'card_max_lines' => 8,
    'line_height'  => 1.4,

    // Design du jour : algorithme de l'app (dailyVerseDesign), sur les designs ACTIFS dans l'ordre :
    // index = ((jours depuis day_epoch × pas) mod n + n) mod n, pas = premier de day_steps premier avec n.
    'day_epoch' => '2024-01-01',
    'day_steps' => [5, 7, 3, 1],
    'timezone'  => 'Africa/Porto-Novo',
];
