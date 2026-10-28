# Application installable (PWA)

## But

Installer le site My Testimony sur l'écran d'accueil d'un téléphone ou d'un ordinateur, comme une application : icône ARISE & SHINE Krea, ouverture en plein écran (sans barre d'adresse), barre aux couleurs Krea. Sans réseau, une page claire remplace l'erreur du navigateur.

Ajouté le 2026-10-28. Pas de bouton « Installer » dans l'interface : le navigateur propose l'installation lui-même.

| Appareil | Installation |
|---|---|
| Android (Chrome, Edge, Samsung Internet) | Menu ⋮ → « Installer l'application » (ou bandeau proposé par Chrome) |
| iPhone / iPad (Safari) | Bouton « Partager » → « Sur l'écran d'accueil » |
| Ordinateur (Chrome, Edge) | Icône d'installation dans la barre d'adresse |

## Fonctionnement

**Manifeste** `public/manifest.webmanifest` : nom « My Testimony — ARISE & SHINE Krea », nom court « My Testimony », démarrage sur `/?source=pwa`, portée `/`, `display: standalone`, couleur `#184797`, fond blanc. Raccourcis (appui long sur l'icône, Android) : Verset du jour, Publier un témoignage.

**Icônes** `public/icons/pwa/`, faites à partir de `icons/arise-shine-krea.png` sur fond blanc :

| Fichier | Taille | Usage |
|---|---|---|
| `icon-192.png` | 192 × 192 | Android, raccourcis |
| `icon-512.png` | 512 × 512 | Écran de démarrage, installation |
| `icon-maskable-512.png` | 512 × 512 | Icône adaptative Android (logo dans la zone sûre de 80 %) |
| `apple-touch-icon.png` | 180 × 180 | iPhone, iPad |

**Service worker** `public/sw.js`, à la racine pour couvrir tout le site. Il est volontairement prudent, car les pages contiennent l'état de la personne connectée (réactions, jeton CSRF, notifications) :

| Requête | Traitement |
|---|---|
| Page (navigation) | Toujours le réseau, **jamais mise en cache**. Sans réseau : `offline.html`. |
| `/build/assets/*` (CSS, JS compilés) | Cache d'abord (noms avec empreinte, donc immuables) ; 40 fichiers au plus, les plus anciens retirés |
| `/icons/*` | Cache, mis à jour en arrière-plan |
| API, `POST`, médias (`Range`), `/storage`, autres domaines | Non touchés : le navigateur fait la requête normalement |

Une nouvelle version de `sw.js` est prise en compte au chargement suivant (`skipWaiting`, `clients.claim`). Changer `VERSION` dans `sw.js` vide les anciens caches.

**Page hors ligne** `public/offline.html` : autonome (aucun fichier externe), en français, en anglais si le navigateur est en anglais ; bouton « Réessayer », rechargement automatique au retour du réseau. `noindex`.

**Gabarits** : `layouts/partials/pwa-head.blade.php` (lien du manifeste, `apple-touch-icon`, balises `apple-mobile-web-app-*`), inclus dans `layouts.app` et `layouts.guest`. La couleur `theme-color` existait déjà.

**Enregistrement** : `resources/js/app.js`, au chargement de la page, seulement en contexte sécurisé (HTTPS ou `localhost`).

**Apache** `public/.htaccess` : type `application/manifest+json` pour `.webmanifest`, `Cache-Control: no-cache` sur `sw.js` et le manifeste.

## Limites

- Aucune page n'est consultable sans réseau : la page « hors ligne » s'affiche à la place (choix volontaire : aucune donnée d'une personne laissée en cache sur l'appareil).
- Pas de notifications push sur le site : elles restent dans l'application mobile (FCM).
- L'application installée reste le site : une mise à jour du site est visible aussitôt, sans passer par une boutique.

## Fichiers

| Fichier | Rôle |
|---|---|
| `public/manifest.webmanifest` | Manifeste |
| `public/sw.js` | Service worker |
| `public/offline.html` | Page hors ligne |
| `public/icons/pwa/*` | Icônes |
| `public/.htaccess` | Type MIME, en-têtes de cache |
| `resources/views/layouts/partials/pwa-head.blade.php` | Balises du `<head>` |
| `resources/js/app.js` | Enregistrement du service worker |
| `tests/Feature/PwaTest.php` | Tests |

## Déploiement

1. Copier les fichiers ci-dessus dans `public/` et `resources/views/layouts/`.
2. Recompiler et **copier `public/build/`** (le JS a changé).
3. `php artisan optimize:clear` puis `php artisan optimize` (serveur) pour les vues.
4. Le site doit être servi en **HTTPS** (sinon le service worker n'est pas activé).

Vérification : Chrome → outils de développement → Application → Manifest (aucune erreur) et Service workers (`sw.js` activé).
