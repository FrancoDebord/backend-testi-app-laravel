<?php

/*
|--------------------------------------------------------------------------
| Témoignages : modération, mise à la une, corbeille, statistiques
|--------------------------------------------------------------------------
| Voir docs/fonctionnalites/gestion-des-temoignages.md
*/

return [

    // true : seuls les témoignages approuvés par la modération sont publics (l'auteur voit toujours
    // les siens, avec leur statut). false : approuvés automatiquement à la création.
    'require_moderation' => (bool) env('TESTIMONIES_REQUIRE_MODERATION', true),

    // « À la une » : GET /testimonies/featured. Complément avec les témoignages publiés les plus
    // récents tant qu'il y en a moins de featured_limit mis à la une manuellement (false : manuel strict).
    'featured_fill_recent'   => (bool) env('TESTIMONIES_FEATURED_FILL_RECENT', true),
    'featured_limit'         => 10,
    'featured_cache_minutes' => 10,

    // Vues : une par personne (compte, sinon appareil, sinon IP + navigateur) et par témoignage sur cette durée.
    'view_dedup_minutes' => 30,

    // Corbeille : restauration possible pendant trash_days jours, puis suppression définitive (testimonies:purge-trash).
    'trash_days' => 30,

    // Statistiques : jour du calendrier de ce fuseau ; événements bruts gardés events_keep_days jours.
    'stats_timezone'   => 'Africa/Porto-Novo',
    'events_keep_days' => 120,
];
