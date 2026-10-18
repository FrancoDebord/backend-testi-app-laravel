# Témoignages vidéo par lien YouTube

## But

Permettre aux **administrateurs** (et aux profils auxquels un administrateur l'accorde) de publier un témoignage vidéo déjà hébergé sur YouTube, sans envoyer de fichier. Le site et l'application lisent la vidéo avec le lecteur YouTube.

> Depuis le 2026-10-18, le droit de publier un lien YouTube suit la règle des [types de témoignages autorisés](types-de-temoignages.md) : réglage global `testimony_type_youtube_enabled` (activé par défaut) **et** rôle administrateur, sauf exception du profil (« Autorisé » l'accorde à un non-administrateur, « Interdit » le retire même à un administrateur).

## Utilisation

- **Site** : *Publier un témoignage* → type **Lien YouTube** (4ᵉ choix, à côté de Texte, Audio, Vidéo ; administrateurs et profils autorisés) → champ « Lien de la vidéo YouTube * ».
- **Application** : afficher la carte quand `allowed_testimony_types` contient `youtube`.
- **Application** : carte **Lien YouTube** sur l'écran « Partagez votre témoignage » (administrateurs), ou bascule « Fichier vidéo / Lien YouTube » dans l'étape vidéo.
- Un compte **modérateur** ne voit pas ce choix : il faut le rôle administrateur.
- Liens acceptés : `youtube.com/watch?v=…`, `youtu.be/…`, `/shorts/…`, `/live/…`, `/embed/…`, `m.youtube.com`, ou l'identifiant de 11 caractères.
- Le témoignage suit le parcours habituel (relecture par la modération).

## Règles

| Règle | Détail |
|---|---|
| Qui | Administrateurs par défaut, ou profils autorisés par un administrateur (`TestimonyTypePolicy`) : sinon site → erreur « Seuls les administrateurs… », API → 403 `{ errors: { type: [...] } }` |
| Type | Forcé à « vidéo » ; aucun fichier envoyé ni stocké ; pas de versions allégées ni de menu Qualité |
| Miniature | Image de couverture choisie, sinon la miniature YouTube (`i.ytimg.com/vi/{id}/hqdefault.jpg`) |
| Lecture sur le site | Lecteur intégré `youtube-nocookie.com` (aucun cookie publicitaire avant la lecture), lien « Ouvrir sur YouTube » |
| Lecture dans l'application | Lecteur YouTube intégré |
| Lecture automatique du suivant | Les vidéos YouTube ne sont pas enchaînées automatiquement sur le site |
| Disponibilité | Si la vidéo est retirée ou privée sur YouTube, le lecteur l'indique ; le témoignage reste en place |

## Fonctionnement technique

| Élément | Fichier |
|---|---|
| Reconnaissance des liens, adresses | `app/Support/YouTube.php` (`parseId`, `watchUrl`, `embedUrl`, `thumbnailUrl`) |
| Colonne `testimonies.youtube_id` | Migration `2026_09_30_100001_add_youtube_id_to_testimonies_table` |
| Modèle | `Testimony::isYouTube()`, `youtubeEmbedUrl()` |
| Site | `Web\TestimonyController::store()` (`youtube_url`), `videos/partials/watch.blade.php`, `moderation/show.blade.php` |
| API | `POST /api/v1/testimonies` champ `youtube_url` ; `TestimonyResource` : `youtubeId`, `youtubeUrl` |

## Tests

`tests/Feature/TestimonyProofsAndYouTubeTest.php` : formes de liens reconnues et refusées, publication réservée aux administrateurs (site et API), type forcé, miniature, lecteur intégré, champs de l'API.
