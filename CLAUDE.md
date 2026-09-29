# TestiApp — serveur Laravel (AIRID)

Documentation complète : [docs/README.md](docs/README.md).

## Règle obligatoire : documentation à jour

À chaque ajout de fonctionnalité, modification de comportement ou correction, dans la même intervention :

1. ajouter une entrée datée dans `docs/journal-des-modifications.md` (en haut) ;
2. mettre à jour la page concernée de `docs/`, ou créer `docs/fonctionnalites/<nom>.md` pour une nouvelle fonctionnalité et l'ajouter au tableau de `docs/README.md` ;
3. reporter dans `docs/README.md` §4 tout changement de déploiement (migration, variable d'environnement, recompilation des assets).

## Interface : charte ARISE & SHINE Krea

Détails : `docs/interface.md`. Jetons (couleurs, police, arrondis, ombres) : `resources/css/theme.css`, source unique. En bref :

- Laravel 12, Blade, Tailwind CSS 4 (`resources/css/app.css` + `resources/css/theme.css`), Font Awesome 6.5, police Plus Jakarta Sans, JavaScript natif uniquement (`resources/js/app.js`).
- Bleu Krea `primary-600` (`#184797`) : structure, titres, navigation, action principale (un seul `btn-primary` par zone). Orange `accent-500` (`#F18717`) : `btn-cta` pour l'action très importante, avec parcimonie. Jaune `sun-400` (`#FCC11D`) : accent seulement, jamais de texte blanc dessus. Fonds blancs, neutres `slate` (redéfinis sur les gris de la charte). Messages : `success-*`, `warning-*`, `error-*`.
- Arrondis : boutons et champs 10 px (`rounded-lg`), cartes 16 px (`rounded-xl`). Ombres très légères, bordures fines, très peu de dégradés.
- Réutiliser les composants existants (`btn-*`, `card`, `card-title`, `form-*`, `table-*`, `action-btn-*`, `badge-*`, `alert-*`, `chip`, `tab`) ; aucune nouvelle couleur ni nouveau style de bouton hors de `theme.css`. Pas d'emojis ni d'animations décoratives dans l'interface.
- Pages : `@extends('layouts.app')` avec `$header`, `$subheader`, `$breadcrumbs` ; entrée dans `$navGroups`. Pages sans connexion : `layouts.guest`.
- Confirmations : `openConfirmModal(...)`, jamais `alert()` / `confirm()`.
- Responsive obligatoire à 390, 768 et 1440 px, sans défilement horizontal. Tableaux remplacés par des fiches sur mobile.
- Ne jamais construire un nom de classe Tailwind dynamiquement.

## Vérifications avant de terminer

- `php artisan view:cache` et `php artisan test` (`tests/Feature/ExampleTest.php` échoue déjà : absence de `RefreshDatabase`).
- Compilation : `npm run build` demande Node 20.19+. La machine de développement a Node 18 : utiliser `npx -y node@22 node_modules/vite/bin/vite.js build`.
- `public/build/` est exclu de git et npm n'est pas disponible sur le serveur : après une modification CSS/JS, rappeler de copier `public/build/` sur le serveur.
- Archive des assets : `/c/Windows/System32/tar.exe -a -c -f build-a-deployer.zip -C public build`. Ne pas utiliser `Compress-Archive` (PowerShell 5.1), qui écrit des chemins avec `\`, mal décompressés sous Linux.
