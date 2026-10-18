<?php

// Temps de prière (App\Services\PrayerFocus, composant components/prayer-focus-modal). Voir docs/fonctionnalites/temps-de-priere.md
return [
    // Erreurs (422)
    'required'     => 'Prenez le temps de prier avant de valider votre prière.',
    'not_started'  => 'Commencez par le temps de prière (« Je prie ») avant de valider votre prière.',
    'too_early'    => 'Votre temps de prière n\'est pas terminé : encore :time.',
    'time_min_sec' => ':min min :sec s',
    'time_sec'     => ':sec s',
    'started'      => 'Temps de prière commencé.',
    'not_found'    => 'Témoignage introuvable.',

    // Écran « Temps de prière » du site
    'modal' => [
        'title'         => 'Temps de prière',
        'subtitle'      => 'Prenez quelques minutes pour prier vraiment.',
        'for_request'   => 'Vous priez pour cette requête',
        'for_testimony' => 'Vous priez pour ce témoignage',
        'remaining'     => 'restantes',
        'paused'        => 'En pause : revenez sur cet onglet pour continuer.',
        'done'          => 'Le temps est écoulé. Amen !',
        'verse_text'    => '« Priez les uns pour les autres, afin que vous soyez guéris. La prière agissante du juste a une grande efficacité. »',
        'verse_ref'     => 'Jacques 5:16',
        'confirm_request'   => 'J\'ai prié pour cette requête',
        'confirm_testimony' => 'J\'ai prié pour ce témoignage',
        'validate'      => 'Valider ma prière',
        'validating'    => 'Enregistrement…',
        'close'         => 'Quitter sans enregistrer',
        'starting'      => 'Préparation…',
        'wait_hint'     => 'Le bouton s\'active à la fin du décompte.',
    ],
];
