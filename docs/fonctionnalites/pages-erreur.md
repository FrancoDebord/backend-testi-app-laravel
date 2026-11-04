# Pages d'erreur et gestion des exceptions

## But

Une adresse inconnue, un contenu supprimé, un accès refusé ou une panne ne doivent jamais afficher la page brute de Laravel, ni un message technique (SQL, chemins de fichiers, messages en anglais). Le site affiche une page aux couleurs de la charte qui explique le problème et propose où aller ; l'API répond toujours en JSON, dans le format commun.

Ajouté le 2026-11-05.

## Site

Laravel affiche `resources/views/errors/{code}.blade.php` avec le **vrai code HTTP** (pas de redirection : un moteur de recherche doit voir le 404, et l'adresse reste visible pour la corriger). Tous ces fichiers incluent `errors/page.blade.php`.

| Code | Titre | Actions proposées |
|---|---|---|
| 404, 410 | Page introuvable / Contenu retiré | Accueil, page précédente, champ de recherche (vers `/recherche`), liens Explorer, Requêtes de prière, Événements, Directs |
| 401, 403 | Connexion requise / Accès refusé | « Se connecter » si la personne n'est pas connectée, sinon Accueil ; page précédente |
| 419 | Session expirée (jeton CSRF) | « Recharger la page » (page précédente), Accueil |
| 429 | Trop de requêtes | Accueil, page précédente |
| 405, 413, 422, 402 | Libellé propre à chaque code | Accueil, page précédente |
| 500, 502, 503, 504 | Erreur du serveur, maintenance, délai dépassé | Accueil, page précédente |
| Autres 4xx / 5xx | `4xx.blade.php`, `5xx.blade.php` | Accueil, page précédente |

- **Message précis** : un `abort(403, __('privacy.admin.super_admin_only'))` de l'application s'affiche sous le texte général (encadré d'information). Les messages du framework (« The route … could not be found », « No query results for model », « This action is unauthorized. »…) sont remplacés par le texte traduit. Une erreur 5xx n'affiche **jamais** le message de l'exception.
- **Langue** : celle du visiteur (sélecteur, compte, puis navigateur), y compris pour une adresse inconnue qui n'a traversé aucun middleware.
- **Page précédente** : seulement si elle est sur ce site et différente de la page en erreur.
- **Page autonome** : elle n'utilise pas `layouts.guest` (en-tête SEO, menus, lecture des réglages) pour s'afficher même si la base de données ou les assets sont indisponibles. `noindex`. Responsive 390 / 768 / 1440 px.
- **Développement** (`APP_DEBUG=true`) : une exception interne garde la page de diagnostic de Laravel. Les codes HTTP (404, 403…) affichent toujours la page de la charte.

## API

Toute erreur d'une adresse `api/*` (même sans en-tête `Accept: application/json`) ou d'une requête qui attend du JSON répond :

```json
{ "success": false, "data": null, "message": "Ressource introuvable", "errors": null }
```

- `message` : message précis de l'`abort()` s'il existe, sinon `api.unauthenticated` (401), `api.forbidden` (403), `api.not_found` (404), ou le titre traduit du code (`errors.{code}.title`, « Trop de requêtes », « Server error »…), dans la langue d'`Accept-Language`.
- Les en-têtes de l'exception sont gardés (`Retry-After` d'un 429).
- Les erreurs de validation (422 avec `errors`) et l'absence de connexion (401) gardent leur format.
- Erreur interne en développement (`APP_DEBUG=true`) : réponse de diagnostic de Laravel.

## Code

| Élément | Rôle |
|---|---|
| `bootstrap/app.php` (`withExceptions`) | `shouldRenderJsonWhen` (`api/*`), langue appliquée, réponse JSON commune ; `HttpResponseException`, validation et authentification exclues |
| `App\Support\HttpErrors` | `status()`, `copy()` (titre et texte, repli `4xx` / `5xx`), `publicMessage()` (filtre des messages techniques), `apiMessage()` |
| `resources/views/errors/page.blade.php` | Page commune ; `{code}.blade.php`, `4xx`, `5xx` l'incluent |
| `lang/{fr,en}/errors.php` | Titres, textes, libellés des actions |

Tests : `tests/Feature/ErrorPagesTest.php`.

## Ajouter un code

Ajouter `errors.{code}` dans `lang/fr/errors.php` et `lang/en/errors.php`, créer `resources/views/errors/{code}.blade.php` contenant `@include('errors.page')`, et si besoin une icône dans le `match` de `page.blade.php`.
