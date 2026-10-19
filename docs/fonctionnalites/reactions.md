# Réactions (j'aime, je prie…)

## But

Chaque personne peut réagir une seule fois à une publication : un **j'aime**, comptabilisé une seule fois, et un **je prie**. Réagir de nouveau ou changer de réaction ne compte jamais deux fois.

Cela vaut pour :
- les témoignages ;
- les événements ;
- les requêtes de prière ;
- les sessions de prière.

## Règles

| Règle | Détail |
|---|---|
| Groupes | **J'aime** : `like`, `love`, `fire`, `glory`, `strong`, `touched` (compteur `like_count`). **Je prie** : `pray`, `amen`, `worship` (compteur `prayer_count`). |
| Une par groupe | Au plus **une réaction « j'aime » et une « je prie »** par personne et par publication. Poser `fire` après `like` **remplace** `like` : le compteur ne bouge pas. |
| Idempotent | Envoyer deux fois la même réaction ne crée rien (réponse 200 au lieu de 201) ; ni compteur ni notification en plus. |
| Retrait | Retire la réaction du groupe ; le compteur ne descend jamais sous 0. |
| Concurrence | Transaction avec verrou sur la publication : deux appuis simultanés ne comptent qu'une fois. |
| Notification | Prévient l'auteur à la **première** réaction du groupe : type `like` ou `prayer`. Changer d'émoji ne renvoie pas de notification. |
| Requêtes de prière | « Je prie » reste le bouton dédié (`POST prayer/requests/{id}/pray`, `prayerCount`, `hasPrayed`). Les réactions de prière y sont refusées (422) ; seules les réactions « j'aime » passent par les réactions. |
| Réactions existantes | La migration `2026_10_09_100001_dedupe_testimony_reactions` garde la réaction la plus récente de chaque groupe, supprime les autres et retire leur poids du compteur. |

## API mobile (`/api/v1`)

### Témoignages

| Méthode | Route | Rôle |
|---|---|---|
| POST | `testimonies/{id}/reactions` `{ type }` | Pose la réaction (201 si nouvelle, 200 sinon). Réponse : la réaction (`id`, `type`…) suivie de l'état ci-dessous. |
| DELETE | `testimonies/{id}/reactions?group=appreciation\|prayer` (ou `?type=like`) | Retire la réaction du groupe, sans identifiant (toutes sans paramètre). Réponse : l'état. |
| DELETE | `testimonies/{id}/reactions/{reactionId}` | Retire la réaction (et son groupe). Gardé pour les anciennes versions de l'application. |

L'état est renvoyé par ces routes et par `TestimonyResource` dans toutes les listes : `isLikedByMe`, `isPrayedByMe`, `myReaction`, `myPrayerReaction`, `likesCount` / `prayersCount` (dans `stats` pour la ressource). Sur les routes publiques, la personne connectée est lue sur le garde `sanctum`, ce qui donne le bon état dans le fil « Pour vous ».

### Événements, requêtes et sessions de prière

| Méthode | Route |
|---|---|
| POST / DELETE | `events/{id}/reactions`, `prayer/requests/{id}/reactions`, `prayer/sessions/{id}/reactions` (`{ type }` ; `?group=` pour retirer) |
| POST / DELETE | `events/{id}/repost`, `prayer/requests/{id}/repost`, `prayer/sessions/{id}/repost` (`{ comment? }`) : voir [partage interne](partage-interne.md) |

Réponse et ressources (`EventResource`, `PrayerRequestResource`, `PrayerSessionResource`) :

- `reactions` : `{ likesCount, prayersCount, repostsCount, myReaction, myPrayerReaction, isRepostedByMe, canRepost }` ;
- `repost` : `{ id, comment, createdAt, user { id, displayName, initials, avatarUrl, isVerified } }`, ou `null`.

## Site

Le bouton de réaction du site bascule la réaction : un second appui la retire. La réponse ajoute `my_reactions` (la liste des réactions de la personne), `is_prayed` et `is_liked`, pour remettre à jour les boutons après un remplacement. Voir `resources/js/videos.js`.

**« Je prie », comme dans l'application** (2026-10-18, `InteractionNotifier::togglePray` de l'app) :

- un seul bouton « Je prie » (icône mains, compteur des prières) ; plus de boutons « Amen » ni « Adorer Dieu » sur la page de lecture ;
- allumé dès que la personne a une réaction du groupe prière (`pray`, ou les anciennes `amen` / `worship`), comme `isPrayedByMe` dans l'API ;
- un appui retire la prière si elle existe (quel que soit son type), sinon pose `pray` (`TestimonyReactions::togglePrayer`) : même résultat que `POST {type: pray}` / `DELETE ?group=prayer` de l'application ;
- bouton et compteur changent tout de suite, puis l'état renvoyé par le serveur fait foi ; en cas d'erreur, l'état d'avant est rétabli ;
- les cartes des listes affichent le nombre de prières à côté des « j'aime ».

L'API ne change pas : `amen` et `worship` restent acceptés et comptés comme des prières.

## Temps de prière

**Événements et sessions de prière** (2026-10-19) : une nouvelle réaction du groupe « je prie » sur `events/{id}/reactions` ou `prayer/sessions/{id}/reactions` exige aussi un temps de prière commencé par `POST events/{id}/pray/start` ou `POST prayer/sessions/{id}/pray/start` (même JSON, même 422, mêmes exceptions : changer d'émoji, retirer, « j'aime »). Requêtes de prière : la réaction « je prie » reste refusée, le bouton dédié et `prayer/requests/{id}/pray/start` s'appliquent. Les réactions 🙏 des directs ne sont pas concernées.

Depuis le 2026-10-18, une **nouvelle** réaction du groupe « je prie » (`pray`, `amen`, `worship`) sur un témoignage n'est acceptée qu'après un temps de prière de 5 minutes commencé par `POST testimonies/{id}/pray/start` (site : `POST /testimonies/{id}/priere/commencer`). Sinon : **422** `{ success: false, message, errors: { prayer_focus: [...] }, remaining_seconds }`. Passer de `pray` à `amen`, renvoyer la même réaction, retirer sa prière et les réactions « j'aime » ne sont jamais bloqués. Sur le site, « Je prie » ouvre l'écran « Temps de prière » avant l'envoi ; un second appui (retrait) reste immédiat. `PRAYER_FOCUS_SECONDS=0` désactive la règle (transition : les anciennes versions de l'application reçoivent 422). Détails : [temps-de-priere.md](temps-de-priere.md).

## Application

| Élément | Comportement |
|---|---|
| Cartes, page d'un témoignage, lecteurs vidéo et audio, vidéos courtes | L'état du serveur fait foi (`InteractionNotifier`). Le cœur plein et la réaction choisie s'affichent dès le chargement. Un deuxième appui retire la réaction au lieu d'en envoyer une nouvelle. Les compteurs viennent de la réponse du serveur. En cas d'échec, l'état précédent revient. |
| Événements, requêtes et sessions de prière | Barre `ContentReactionBar` : **J'aime** (appui long : choisir une autre réaction), **Je prie** (sauf requêtes) et **Partager**. Elle apparaît sur la page de chaque publication et sous les éléments insérés dans les fils. |

Les réactions de l'application (`glory`, `strong`, `touched`) ont leur propre valeur. Auparavant, « Gloire ! » était envoyée comme `amen` et comptée comme une prière.

## Données

| Table | Contenu |
|---|---|
| `reactions` | Réactions aux témoignages, unique (`user_id`, `testimony_id`, `type`) ; une par groupe imposée par `TestimonyReactions`. |
| `content_reactions` | Réactions aux événements, requêtes et sessions : `user_id`, `subject_type` (`event`, `prayer_request`, `prayer_session`), `subject_id`, `type` ; unique (`user_id`, `subject_type`, `subject_id`, `type`). |
| `events`, `prayer_sessions` | `like_count`, `prayer_count`, `repost_count`. |
| `prayer_requests` | `like_count`, `repost_count` (`prayer_count` existait déjà). |

Migrations :
- `2026_10_09_100001_dedupe_testimony_reactions` ;
- `2026_10_09_100002_create_content_reactions_and_reposts_tables`.

## Fichiers

**Serveur**
- `app/Enums/ReactionType.php` (`group()`, `valuesOfGroup()`) ;
- `app/Services/TestimonyReactions.php` ;
- `app/Services/ContentInteractions.php` ;
- `Api\ReactionController` ;
- `Api\ContentInteractionController` ;
- `Web\TestimonyController::storeReaction` ;
- `TestimonyResource`.

**Application**
- `lib/features/home/providers/home_providers.dart` (`TestimonyInteractions`, `InteractionNotifier`, `reactionTypeFromApi`) ;
- `lib/features/repost/content_interactions.dart`.

## Tests

- Serveur : `tests/Feature/ReactionsAndSharesTest.php`, `tests/Feature/PrayerFocusTest.php` (temps de prière).
- Application : `test/reactions_shares_test.dart`.

## Limites connues

- Les « j'aime » des **commentaires** (témoignages, événements, verset du jour) sont enregistrés par le serveur depuis le 2026-10-18 : voir [« J'aime » sur les commentaires](jaime-commentaires.md) (`comment_liked`, préférence `push_likes`).
- Après un déploiement, lancez `php artisan optimize:clear`. Sinon la liste des routes mise en cache ignore les nouvelles routes (`…/reactions`, `…/repost`), qui répondent 404. L'application affiche alors « Le serveur ne prend pas encore en charge cette action ».
- Le site n'a pas encore de boutons de réaction ni de partage interne pour les événements, requêtes et sessions de prière : l'API et l'application seulement.
