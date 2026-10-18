# Types de témoignages autorisés

## But

Laisser les administrateurs choisir quels types de témoignages peuvent être publiés — **texte**, **audio**, **vidéo**, **lien YouTube** — pour toute la plateforme, avec des exceptions par profil (autoriser ou interdire un type à une personne précise).

## Règle effective

| Type | Autorisé si |
|---|---|
| `text`, `audio`, `video` | exception du profil, sinon réglage global |
| `youtube` | exception du profil, sinon (réglage global **et** compte administrateur) |

- Les quatre réglages globaux sont **activés par défaut** : rien ne change tant qu'un administrateur ne les modifie pas. Les liens YouTube restent réservés aux administrateurs par défaut ; un administrateur peut les **accorder à un profil** précis (exception « Autorisé »).
- Exception « Interdit » : le type est refusé à ce profil même si le réglage global est activé (y compris pour un administrateur).
- Compte administré (`X-Act-As`, compte choisi sur le site) : ce sont les droits **du compte au nom duquel on publie** qui s'appliquent.
- Le **carnet privé** suit la même règle. **Modifier** un témoignage existant n'est jamais bloqué.
- Type demandé : `youtube` dès qu'un `youtube_url` est envoyé, sinon `type` ; une vidéo Bunny (`videos/upload-init`) est de type `video`.

## Refus

`POST /api/v1/testimonies`, `POST /api/v1/videos/upload-init` et le formulaire du site (`POST /testimonies` appelé en JSON) répondent **403** :

```json
{
  "success": false,
  "message": "La publication de témoignages audio est désactivée.",
  "errors": { "type": ["La publication de témoignages audio est désactivée."] }
}
```

Noms des types : `texte`, `audio`, `vidéo`, `YouTube` (anglais : « Publishing audio testimonies is disabled. »). Pour un lien YouTube refusé parce que le compte n'est pas administrateur (aucune exception, réglage global activé), `message` reste « Seuls les administrateurs peuvent publier une vidéo YouTube. ». Formulaire classique du site : retour au formulaire avec l'erreur sous le choix du type (et sous le lien YouTube). Aucun témoignage n'est créé, aucun appel à Bunny n'est fait.

## API

| Méthode | Route | Réponse `data` |
|---|---|---|
| `GET` | `/me`, `/users/me`, `/auth/me` (et connexion, inscription) | ressource utilisateur « owner » avec `allowed_testimony_types: ["text","audio","video"]` |
| `GET` | `/me/publishing` | `{ "allowed": [...], "global": { "text": bool, "audio": bool, "video": bool, "youtube": bool } }` |
| `GET` | `/admin/settings` | groupe `publishing` : `testimony_type_text_enabled`, `…_audio_enabled`, `…_video_enabled`, `…_youtube_enabled` |
| `PUT` | `/admin/settings` | `{ "testimony_type_audio_enabled": false }` (route générique existante) |
| `GET` | `/admin/users/{id}/publishing` | `{ "global": {...}, "overrides": { "text": true/false/null, ... }, "allowed": [...] }` |
| `PUT` | `/admin/users/{id}/publishing` | corps : une ou plusieurs clés `text`, `audio`, `video`, `youtube` à `true`, `false` ou `null` (retour au réglage global) ; clés absentes inchangées ; même réponse que le `GET` |

Les routes `/admin/*` sont réservées aux **administrateurs** (403 pour les modérateurs). Chaque changement d'exception est inscrit au journal d'audit (`user.testimony_types`, avant / après).

## Site

- **Administration › Paramètres** : section **Publication** avec les quatre interrupteurs.
- **Administration › Utilisateurs › fiche** : bloc « Types de témoignages autorisés », choix à trois états par type (**Par défaut** / **Autorisé** / **Interdit**) et état effectif (route `admin.users.publishing.update`, `PUT /admin/users/{id}/publication`).
- **Publier** : seuls les types autorisés sont proposés ; si aucun ne l'est, un court message remplace le formulaire.

## Données

| Élément | Détail |
|---|---|
| `app_settings` | `testimony_type_text_enabled`, `testimony_type_audio_enabled`, `testimony_type_video_enabled`, `testimony_type_youtube_enabled` (type `boolean`, groupe `publishing`, valeur `1`) |
| `users.testimony_type_overrides` | JSON nullable `{ "text": true/false/null, ... }` ; null ou clé absente = suit le réglage global ; jamais dans `$fillable` |

Migration `2026_10_18_200001_add_testimony_type_settings` (crée la colonne et les quatre réglages sans écraser une valeur existante ; aussi dans `AppSettingSeeder`).

## Fichiers

| Élément | Fichier |
|---|---|
| Règle | `app/Services/TestimonyTypePolicy.php` (`allowed`, `allows`, `ensureAllowed`, `overrides`, `updateOverrides`, `global`) |
| Application de la règle | `TestimonyCreator::create()` (API et upload-init), `Web\TestimonyController::store()` et `create()` |
| API | `app/Http/Controllers/Api/PublishingController.php`, `UserResource` (`allowed_testimony_types`) |
| Site | `admin/settings.blade.php`, `admin/users/show.blade.php`, `Web\AdminController::updatePublishing()`, `testimonies/create.blade.php` |
| Textes | `lang/{fr,en}/publishing.php` |

Les anciens réglages `allow_audio` / `allow_video` (groupe `features`) ne sont lus par aucun code et ne participent pas à cette règle.

## Déploiement

`php artisan migrate`, puis `php artisan optimize:clear` (les réglages sont mis en cache une heure ; `PUT /admin/settings` vide le cache de la clé modifiée).

## Tests

`tests/Feature/TestimonyTypePolicyTest.php` : réglages présents dans `GET /admin/settings` et modifiables, `allowed_testimony_types` et `/me/publishing`, réglage coupé → 403 (API, carnet, site en formulaire et en JSON, upload-init sans appel à Bunny, message anglais), exceptions « Autorisé » / « Interdit » / retour au global, modification jamais bloquée, YouTube réservé aux administrateurs puis accordé à un profil, page Publier, routes d'administration (modérateur 403, audit), pages d'administration du site, compte administré (`X-Act-As`).
