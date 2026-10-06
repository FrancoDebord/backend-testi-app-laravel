# Partage interne (« X a partagé »)

## But

Comme un transfert WhatsApp ou un partage Facebook : une personne connectée **partage un témoignage sur TestiApp**, avec un **commentaire facultatif**, pour le faire connaître à ses abonnés. L'**auteur d'origine reste toujours affiché** : le partage montre « X a partagé » au-dessus de la carte du témoignage original (son auteur, son lien) ; le contenu n'est **jamais recopié** sous le nom du partageur.

À ne pas confondre avec le [lien de partage](lien-de-partage.md) (partage **hors** de TestiApp : copier le lien, WhatsApp…), qui reste disponible.

## Utilisation

| Où | Comment |
|---|---|
| Application, page d'un témoignage | Bouton **Partager** → feuille de partage → **« Partager sur TestiApp »** : commentaire facultatif (500 caractères), **Partager avec mes abonnés** ; une fois partagé, **Mettre à jour mon partage** ou **Annuler mon partage**. Invité : invitation à créer un compte. |
| Site, page d'un témoignage | Bouton **« Partager sur TestiApp »** (avec le nombre de partages) → fenêtre avec le commentaire ; **Partagé sur TestiApp** une fois fait (mettre à jour, **Annuler le partage**). |
| Mon fil des abonnés | En-tête **« X a partagé · il y a… »**, le commentaire, puis la carte du témoignage d'origine. |
| Profil du partageur | Section **« Partages »** (site : 12 derniers ; application : 10 derniers, profil public). |
| Auteur d'origine | Notification **« X a partagé votre témoignage « titre » »** (type `share`, push « réactions »). |

## Règles

| Règle | Détail |
|---|---|
| Qui | Toute personne connectée. |
| Quoi | Un témoignage **publié et public** (approuvé, `visibility = public`). Refusé (404) pour un témoignage en attente, réservé aux abonnés, du carnet privé ou supprimé. |
| Son propre témoignage | Refusé (422 « Vous ne pouvez pas partager votre propre témoignage. »). |
| Unicité | **Un partage par personne et par témoignage** (index unique). Partager de nouveau ne crée rien : le commentaire est remplacé (réponse 200 au lieu de 201) ; ni compteur ni notification en plus. |
| Compteur | `testimonies.repost_count` (+1 à la création, −1 à l'annulation) ; API `stats.repostsCount`, `isRepostedByMe`. Distinct de `sharesCount` (partages hors de TestiApp). |
| Annulation | `DELETE` : le partage disparaît des fils et du profil. |
| Témoignage devenu non public / supprimé | Le partage n'est plus montré (filtré à l'affichage, Mon fil et profil) ; il réapparaît si le témoignage redevient public. |
| Mon fil | Les partages des comptes suivis (30 derniers jours) se mêlent à leurs témoignages, du plus récent au plus ancien ; un témoignage n'apparaît qu'une fois (au partage le plus récent). Les partages de ses propres témoignages par d'autres n'apparaissent pas dans son fil. Voir [recommandations.md](recommandations.md#mon-fil-get-apiv1feedpagelimit-connecté--site-mon-fil). |

## API mobile (`/api/v1`)

| Méthode | Route | Rôle |
|---|---|---|
| POST | `testimonies/{id}/repost` `{ comment? }` (connecté, 30/min) | Partager (201) ou modifier le commentaire (200) → `{ id, testimonyId, comment, repostedAt, repostedBy, repostsCount }` |
| DELETE | `testimonies/{id}/repost` (connecté) | Annuler → `{ repostsCount }` ; 404 si aucun partage |
| GET | `users/{id}/reposts?limit=20&page=` (public) | Partages d'un compte, les plus récents d'abord (format ci-dessous) |
| GET | `feed` (connecté) | Mon fil : les partages des comptes suivis y figurent avec `feedReason: repost` |

Élément « partage » (Mon fil et `users/{id}/reposts`) : un `TestimonyResource` complet (son `user` = **auteur d'origine**) + `feedReason: "repost"`, `repostId`, `repostedBy` (UserResource du partageur), `repostComment` (ou `null`), `repostedAt` (ISO 8601).

## Site

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/testimonies/{id}/repost` `{ comment? }` (`testimonies.repost`) | Partager / modifier, retour à la page avec un message |
| DELETE | `/testimonies/{id}/repost` (`testimonies.unrepost`) | Annuler |

Vues : `videos/partials/watch.blade.php` (bouton et fenêtre `repost-modal`), `feed/partials/repost-header.blade.php` (en-tête « X a partagé »), `videos/partials/cards.blade.php` (variable `reposts`), `profile/show.blade.php` (section « Partages »).

## Données

| Table | Contenu |
|---|---|
| `testimony_reposts` | `id` (UUID), `user_id`, `testimony_id`, `comment` (500, facultatif), `created_at`, `updated_at` ; unique (`user_id`, `testimony_id`) ; suppression en cascade avec le compte ou le témoignage |
| `testimonies.repost_count` | Nombre de partages internes |

Migration `2026_10_06_200001_create_testimony_reposts_table`.

## Fichiers

Serveur : `app/Models/TestimonyRepost.php`, `Testimony::reposts()`, `app/Services/Reposts.php` (règles communes), `RepostException`, `Api\RepostController` (`store`, `destroy`, `ofUser`, `feedItem`), `Web\RepostController`, `Recommendations::personalFeed` (partages dans Mon fil), `TestimonyResource` (`stats.repostsCount`, `isRepostedByMe`).
Application : `lib/features/repost/repost.dart` (`RepostInfo`, `RepostRepository`, `repostedByMeProvider`), `lib/features/repost/repost_widgets.dart` (`RepostHeader`, `showRepostSheet`, `RepostSheet`, `UserRepostsSection`), feuille de partage de `testimony_detail_screen.dart`, `personal_feed_screen.dart`, profil public `user_profile_screen.dart`.

## Tests

`tests/Feature/PersonalFeedAndRepostTest.php` : création, unicité, commentaire modifié, notification, compteur, `isRepostedByMe`, annulation ; refus (le sien, réservé aux abonnés, en attente) ; affichage dans Mon fil d'un abonné avec l'auteur d'origine, profil, disparition quand le témoignage n'est plus public ; site (bouton, partage, Mon fil, profil, annulation). Application : `test/personal_feed_test.dart` (en-tête « X a partagé » sans débordement, feuille « Partager sur TestiApp »).

## Limites connues

- Les **cartes du fil** n'ont pas de menu : le partage interne se fait depuis la page du témoignage (application et site).
- L'application ne sait pas, à l'ouverture de la feuille, si le témoignage est déjà partagé (sauf pendant la session) : « Partager » met alors simplement à jour le commentaire, et « Annuler mon partage » est toujours proposé.
- Pas de « partage d'un partage » : on partage toujours le témoignage d'origine.
- Les abonnés voient un nouveau partage tout de suite (cache vidé pour 2 000 abonnés au plus ; au-delà, sous 10 minutes).
