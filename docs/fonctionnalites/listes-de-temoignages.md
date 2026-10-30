# Listes de témoignages (2026-10-30)

Comme les playlists YouTube : un compte regroupe ses témoignages qui se rapportent à une même chose (par exemple « Guérison de ma mère », en plusieurs épisodes). Quand on lit un témoignage qui fait partie d'une liste, les autres témoignages de la liste sont proposés et **lus automatiquement à la suite, par défaut**.

Mettre un témoignage dans une liste reste **facultatif** : on publie toujours sans liste, comme avant.

## Règles

| Règle | Détail |
|---|---|
| Propriétaire | Une liste appartient à un compte. Seul ce compte la modifie (titre, description, ordre, ajout, retrait, suppression) : 403 sinon. |
| Contenu | Uniquement **ses propres** témoignages (403 sinon), jamais une entrée du carnet privé (422). 200 témoignages au plus par liste. Un témoignage peut être dans plusieurs listes. |
| Ordre | Colonne `position` ; ajout à la fin. Site : boutons Monter / Descendre. API : ordre complet `testimonyIds`. |
| Visibilité | La liste est ouverte à tous, mais chacun n'y voit que les témoignages qu'il peut ouvrir : sur le site, règle de la page (publié, public, accord pour le web, `Testimony::webStatusFor` = 200) ; dans l'application, `Testimony::isVisibleTo`. Une liste sans témoignage visible répond 404 (sauf à son propriétaire) et n'apparaît pas sur le profil. |
| Suppression | Supprimer une liste ne touche pas aux témoignages. Supprimer un témoignage le retire des listes (clé étrangère `cascade`). |
| Liste en cours | Celle demandée par `?list={id}` si elle contient le témoignage ; sinon la plus récemment modifiée qui le contient. Aucune liste pour une entrée du carnet privé. |

## Site

| Page | Comportement |
|---|---|
| Publier (`/publish`) | Dans « Diffusion », bloc **Liste de témoignages** (facultatif) : liste existante (`playlist_id`) ou titre d'une nouvelle liste (`playlist_title`, prioritaire, créée à la publication). Liste d'un autre compte : erreur sous le champ, rien n'est créé. Carnet privé : choix ignoré. |
| Page de lecture (`/testimonies/{id}`, `/videos/{id}`) | Panneau de la liste : titre (lien vers la liste), auteur, « 2 sur 5 », Précédent / Suivant, liste des témoignages avec celui en cours en surbrillance. Sous le lecteur sur mobile et tablette, en tête de la colonne de droite à partir de 1280 px. |
| Lecture automatique | À la fin d'une vidéo ou d'un audio, le bandeau « À suivre » (5 s) lance le **suivant de la liste** (lien avec `?list=`), au lieu d'une recommandation. Bouton « Lecture auto de la liste » activé par défaut ; réglage mémorisé à part (`playlistAutoplay` dans `localStorage`), si bien que couper la lecture auto des recommandations ne la coupe pas dans les listes. Un témoignage choisi dans le panneau démarre à l'arrivée. En fin de liste : retour à la lecture auto des recommandations. Témoignage écrit ou vidéo YouTube : pas de fin de lecture détectée, le panneau propose « Suivant ». |
| Ajouter à une liste | Sur la page de son témoignage, l'auteur a le bouton **Ajouter à une liste** : liste existante ou nouvelle liste (`POST /testimonies/{id}/listes`). |
| Mes listes (`/mes-listes`, menu « Mon espace ») | Cartes des listes (nombre de témoignages, date de modification) et bouton **Nouvelle liste**. |
| Page d'une liste (`/listes/{id}`) | Auteur, description, **Tout lire**, **Partager la liste**, témoignages numérotés. Propriétaire : Modifier, Supprimer la liste (`openConfirmModal`), Monter / Descendre / Retirer (boutons icônes), ajout d'un de ses témoignages ; ses témoignages pas encore publiés sont marqués « Visible par vous seulement ». |
| Profil | Section **Listes de témoignages** (listes avec au moins un témoignage visible). |
| Liens partagés | `/listes/*` est déclaré dans `config/applinks.php` (iOS, `apple-app-site-association`) : un lien de liste partagé s'ouvre dans l'application si elle le prend en charge ([app-links.md](app-links.md)). |

## API (application)

Réponses au format habituel `{ success, data, message }`. Liste (`playlist`) :

```json
{
  "id": "uuid", "title": "Guérison de ma mère", "description": null,
  "owner": { "id": "uuid", "displayName": "Ama" },
  "isMine": false, "count": 3, "coverUrl": "https://…", "shareUrl": "https://…/listes/uuid",
  "updatedAt": "2026-10-30T10:00:00+00:00",
  "items": [ { "id": "uuid", "title": "Partie 1", "type": "video", "isShort": false, "coverUrl": "…", "durationSec": 312, "viewsCount": 40, "publishedAt": "…" } ]
}
```

`items` vaut `null` dans les listes de listes.

| Route | Accès | Effet |
|---|---|---|
| `GET /playlists/mine` | connecté | Mes listes (tous mes témoignages comptés, `items: null`). |
| `GET /users/{id}/playlists` | public | Listes d'un compte ayant au moins un témoignage visible. |
| `GET /playlists/{id}` | public | Liste et témoignages visibles, dans l'ordre (404 si aucun, sauf propriétaire). |
| `POST /playlists` | connecté | `{ title, description?, testimonyIds? }` → 201 avec la liste. |
| `PUT /playlists/{id}` | propriétaire | `{ title?, description?, testimonyIds? }` (`testimonyIds` = nouvel ordre complet). |
| `DELETE /playlists/{id}` | propriétaire | Supprime la liste. |
| `POST /playlists/{id}/testimonies` | propriétaire | `{ testimonyId }` : ajout à la fin (sans effet s'il y est déjà). |
| `DELETE /playlists/{id}/testimonies/{testimonyId}` | propriétaire | Retrait. |
| `POST /testimonies` | connecté | Champs facultatifs `playlist_id` ou `playlist_title` (même règle que le site ; 422 `errors.playlist_id` pour une liste d'un autre compte). Aussi pris en compte par `POST /videos/upload-init` (Bunny). |
| `GET /testimonies/{id}?list={playlistId}` | comme avant | Nouveau champ `playlist` : `null` hors liste, sinon `{ id, title, description, owner, position, count, previousId, nextId, autoplay: true, items[] }`. |

Erreurs : 403 (pas le propriétaire, témoignage d'un autre compte), 422 (carnet privé, liste pleine, titre vide), 404.

## Code

| Élément | Rôle |
|---|---|
| Migration `2026_10_30_100001_create_testimony_playlists_tables` | Tables `testimony_playlists` (uuid, `user_id`, `title` 120, `description` 1000) et `testimony_playlist_items` (`playlist_id`, `testimony_id`, `position`, unique par paire). |
| `App\Models\TestimonyPlaylist`, `Testimony::playlists()` | Modèle et relation. |
| `App\Services\TestimonyPlaylists` | Règles communes : création, ajout, retrait, ordre, choix à la publication (`existingForComposer`, `publishInto`), témoignages visibles (`playable`), liste en cours (`contextFor`), formats de l'API. |
| `Web\PlaylistController`, `Api\PlaylistController` | Site et API. |
| `WatchPage::data(…, $listId)` | `playlist`, `myPlaylists`, `inPlaylistIds` pour `videos/partials/watch.blade.php`. |
| Vues | `playlists/mine`, `playlists/show`, `playlists/partials/{form-modal,composer-fields}`, `videos/partials/playlist-panel`, section du profil. |
| `resources/js/videos.js` | `data-up-next-mode="playlist"` : suivant de la liste, réglage `playlistAutoplay` ; clic dans le panneau → lecture au chargement. |
| Langues | `lang/{fr,en}/playlists.php`, `profile.show.playlists`. |
| Tests | `tests/Feature/TestimonyPlaylistsTest.php`. |

## Application mobile (à faire dans le dépôt Flutter)

1. **Publication** : bloc facultatif « Liste de témoignages » (liste existante via `GET /playlists/mine`, ou titre d'une nouvelle liste) ; envoyer `playlist_id` ou `playlist_title` avec `POST /testimonies` (et `POST /videos/upload-init`). Ne pas proposer le bloc pour le carnet privé.
2. **Lecteur** : lire `data.playlist` de `GET /testimonies/{id}` (passer `?list=` quand on vient d'une liste). Afficher sous le lecteur le titre, « position sur count », Précédent / Suivant et la liste `items`, celui en cours en surbrillance.
3. **Lecture automatique** : à la fin de la vidéo ou de l'audio, compte à rebours puis ouverture de `nextId` avec `?list={playlist.id}`. Interrupteur « Lecture auto de la liste » activé par défaut (`autoplay: true`), mémorisé localement à part de la lecture auto ordinaire. Sans `nextId` : comportement actuel.
4. **Mes listes** : écran listant `GET /playlists/mine`, création, renommage, description, suppression (confirmation), ordre par glisser-déposer (`PUT /playlists/{id}` avec `testimonyIds`), retrait.
5. **Ajouter à une liste** : dans le menu d'un de ses témoignages (`POST /playlists/{id}/testimonies` ou nouvelle liste avec `POST /playlists` + `testimonyIds`).
6. **Liens partagés** : le serveur déclare `/listes/*` pour iOS (`apple-app-site-association`) ; dans l'application, ajouter le chemin côté Android (`AndroidManifest.xml`) et ouvrir l'écran de la liste pour `/listes/{id}` (`app_router.dart`, Android et iOS).
7. **Profil** : onglet ou section « Listes » (`GET /users/{id}/playlists`) ; écran d'une liste (`GET /playlists/{id}`) avec « Tout lire » et partage de `shareUrl`.
8. **Prier plusieurs fois** : remplacer le texte « Retirer ma dernière prière » par un bouton icône (annuler, `Icons.undo`) avec info-bulle et libellé d'accessibilité (`Semantics` / `tooltip`), sans changer l'appel (`last` / `remove_last`).
