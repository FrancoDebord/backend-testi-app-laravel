# « J'aime » sur les commentaires

## But

Permettre d'aimer un commentaire, comme sur les réseaux sociaux, partout où l'on commente : sous un **témoignage**, sous un **événement** et sous le **verset du jour**. L'auteur du commentaire est prévenu.

## Règles

| Règle | Détail |
|---|---|
| Un « j'aime » par personne | Une ligne `comment_likes` par personne et par commentaire (index unique) ; aimer deux fois ne compte qu'une fois |
| Retirer | Idempotent ; le compteur `likes_count` ne descend jamais sous 0 |
| Son propre commentaire | Autorisé (sans notification) |
| Visibilité | 404 si le commentaire n'existe pas, si son témoignage / événement n'est pas visible de la personne, ou si un commentaire du verset du jour est retiré (sauf pour son auteur et la modération) |
| Notification | Type `comment_liked` (« {Nom} a aimé votre commentaire »), envoyée à l'auteur la **première fois** que cette personne aime ce commentaire : retirer puis remettre « j'aime » ne renvoie rien (même règle que `prayer_request_prayed`) |
| Push | Préférence **push_likes** (même réglage que les réactions « j'aime ») |
| Visiteurs | Voient le nombre ; `isLikedByMe` vaut toujours `false` ; le bouton du site mène à la connexion |

## API mobile (`/api/v1`, `auth:sanctum`, 60 requêtes / min)

| Méthode | Route | Commentaire |
|---|---|---|
| `POST` / `DELETE` | `comments/{id}/like` | d'un témoignage |
| `POST` / `DELETE` | `events/{id}/comments/{commentId}/like` | d'un événement |
| `POST` / `DELETE` | `daily-verse/{id}/comments/{commentId}/like` | du verset du jour (`{id}` numérique) |

Réponse (toutes) :

```json
{ "success": true, "data": { "likesCount": 3, "isLikedByMe": true }, "message": "Commentaire aimé." }
```

Erreurs : `401` sans connexion, `404` `{ "success": false, "message": "Commentaire introuvable." }`.

Les ressources des commentaires gagnent deux clés (les autres sont inchangées) :

| Ressource | Clés |
|---|---|
| `CommentResource` (`GET testimonies/{id}/comments`, réponses comprises) | `likesCount` (existait, désormais toujours entier), `isLikedByMe` |
| `EventCommentResource` (`GET events/{id}/comments`, création) | `likesCount`, `isLikedByMe` |
| `DailyVerseCommentResource` (`GET daily-verse/{id}/comments`, création) | `likesCount`, `isLikedByMe` |

`isLikedByMe` est lu en **une requête par page** (`CommentLikes::preload()`), jamais une requête par commentaire.

Notification (`GET notifications`) : `type = comment_liked`, `commentId` (nouvelle clé de `NotificationResource`), et selon le contenu `testimonyId`, `eventId` ou `dailyVerseId`. Le push contient `type`, `comment_id` et `testimony_id` / `event_id` / `daily_verse_id`.

## Site

- Bouton cœur + nombre (`aria-pressed`) sous chaque commentaire : page d'un témoignage (`/videos/{id}`, `/testimonies/{id}`, réponses comprises), page d'un événement (onglet Commentaires), page du verset du jour (onglet Commentaires).
- `resources/js/comment-likes.js` envoie `POST` (aimer) ou `DELETE` (retirer) en `fetch` avec le jeton CSRF, met l'affichage à jour aussitôt puis le corrige avec la réponse du serveur.
- Routes web (session, `auth`, mêmes réponses JSON que l'API, contrôleur partagé `Api\CommentLikeController`) : `comments.like` / `comments.unlike` (`/comments/{id}/like`), `events.comments.like` / `events.comments.unlike` (`/evenements/{id}/commentaires/{commentId}/like`), `daily-verse.comments.like` / `daily-verse.comments.unlike` (`/verset-du-jour/{id}/commentaires/{commentId}/like`).

## Données

| Table | Colonnes |
|---|---|
| `comment_likes` | `id`, `likeable_type` (`testimony_comment`, `event_comment` ou `verse_comment`), `likeable_id` (36), `user_id` (suppression en cascade), horodatage ; unique (`likeable_type`, `likeable_id`, `user_id`) |
| `comments.likes_count` | existait déjà |
| `event_comments.likes_count`, `daily_verse_comments.likes_count` | ajoutées : entier non signé, 0 par défaut |

Migration `2026_10_18_100001_create_comment_likes_table`.

## Fichiers

| Élément | Fichier |
|---|---|
| Service (aimer, retirer, état, préchargement, notification) | `app/Services/CommentLikes.php` (une instance par requête : `AppServiceProvider::scoped`) |
| Modèle | `app/Models/CommentLike.php` |
| Contrôleur (API et site) | `app/Http/Controllers/Api/CommentLikeController.php` |
| Notification | `NotificationType::CommentLiked`, `PushNotifications::PREFERENCES`, `lang/{fr,en}/notifications.php`, `lang/{fr,en}/comment_likes.php` |
| Bouton | `resources/views/components/comment-like.blade.php`, `resources/js/comment-likes.js` |

## Déploiement

`php artisan migrate`, `php artisan optimize:clear`, recompilation des assets (`npm run build`) et copie de `public/build/`.

## Tests

`tests/Feature/CommentLikesTest.php` : idempotence, son propre commentaire, compteur jamais négatif, `likesCount` / `isLikedByMe` dans les listes (réponses comprises, une seule requête, visiteur), notification unique, préférence push, 404 (inexistant, témoignage invisible, événement masqué, commentaire retiré), événements, verset du jour, routes et boutons du site.
