# Vidéos des témoignages sur Bunny Stream

Les vidéos des témoignages sont hébergées par **Bunny Stream** (bibliothèque `777434`, CDN `vz-5fc8c5f9-0fc.b-cdn.net`). Les fichiers vidéo ne passent **jamais** par le serveur LWS :

- l'application envoie le fichier **directement à Bunny** (protocole TUS) avec une autorisation signée par le serveur ;
- pour reprendre une ancienne vidéo, le serveur demande à Bunny d'**aller chercher lui-même** le fichier à son URL publique.

Le serveur ne fait que créer les vidéos, signer les envois, suivre leur état et fournir les URL de lecture.

## Cycle de vie (`testimonies.video_status`)

| État | Signification | Visible par |
|---|---|---|
| `uploading` | Témoignage créé, l'application envoie le fichier à Bunny | auteur (et modération) |
| `processing` | Envoi terminé, Bunny encode | auteur (et modération) |
| `ready` | Lisible (HLS, MP4) | tout le monde selon les règles habituelles (modération, visibilité) |
| `failed` | Encodage en échec, ou envoi abandonné depuis plus de 24 h | auteur (et modération) |
| `null` | Pas de vidéo Bunny : texte, audio, YouTube, ancienne vidéo LWS | règles habituelles |

Les listes publiques (`Testimony::published()`, 47 usages : fils, recommandations, profils, site) et la file de modération (`pending()`) ne montrent une vidéo Bunny qu'une fois `ready`, grâce au scope `videoPlayable()`. Exception : une ancienne vidéo LWS en cours de reprise reste visible, car son fichier d'origine (`media_url`) est toujours lu. `isVideoPlayable()` applique la même règle à la consultation d'un témoignage seul.

## Colonnes ajoutées à `testimonies`

Migration `2026_10_12_100001_add_bunny_stream_to_testimonies_table` :

| Colonne | Contenu |
|---|---|
| `bunny_video_id` | guid de la vidéo chez Bunny (index) |
| `video_status` | voir le tableau ci-dessus ; **sans valeur par défaut**, sinon tous les témoignages existants seraient masqués |
| `duration` | durée en secondes donnée par Bunny (`length`), recopiée dans `duration_sec`, qui sert au classement court/long (≤ 60 s) et à l'affichage |
| `available_resolutions` | qualités produites, telles que renvoyées par Bunny : `240p,360p,480p,720p` |

Les colonnes de l'ancien stockage (`media_url`, `renditions`) restent en place jusqu'à la fin de la reprise des anciennes vidéos.

## API (préfixe `/api/v1`, Bearer Sanctum)

### `POST /videos/upload-init`

Mêmes champs et mêmes règles de validation que `POST /testimonies` (`StoreTestimonyRequest`). `type` vaut `video` par défaut ; `media_url` et `youtube_url` sont ignorés. Le témoignage est créé (`pending`, ou carnet privé) avec `video_status = uploading`, puis la vidéo est créée chez Bunny. Si Bunny échoue, rien n'est conservé (502).

```json
{
  "success": true,
  "data": {
    "video_id": "<uuid du témoignage>",
    "testimony_id": "<uuid du témoignage>",
    "library_id": 777434,
    "bunny_video_id": "<guid>",
    "signature": "<sha256>",
    "expiration": 1760000000,
    "tus_endpoint": "https://video.bunnycdn.com/tusupload"
  }
}
```

L'application envoie le fichier à `tus_endpoint` avec les en-têtes TUS `AuthorizationSignature`, `AuthorizationExpire`, `VideoId` et `LibraryId`.

Calcul de la signature : `signature = sha256(library_id + api_key + expiration + bunny_video_id)`, avec `expiration = time() + 7200`.

### `POST /videos/{id}/upload-signature`

Réservé au propriétaire (403 sinon) et au statut `uploading` (409 sinon). Renvoie `{ signature, expiration }` pour la **même** vidéo Bunny, par exemple pour reprendre un envoi après 2 heures.

### `POST /videos/{id}/upload-complete`

Réservé au propriétaire (403 sinon). Fait passer la vidéo de `uploading` à `processing`. L'appel est sans effet si le webhook a déjà fait avancer la vidéo. Renvoie le témoignage.

### Webhook `POST /webhooks/bunny` (public)

URL à déclarer dans la bibliothèque Bunny : `https://temoignage.as-krea.com/api/v1/webhooks/bunny`.

Contenu reçu : `{ VideoLibraryId, VideoGuid, Status }`. Le serveur répond toujours 200, et ignore l'appel si la bibliothèque n'est pas la nôtre ou si le guid est inconnu.

Bunny ne signe pas ses webhooks : le contenu n'est jamais cru tel quel. Après la réponse, l'état est relu avec `GET /library/{id}/videos/{guid}`, puis traduit ainsi :

| Statut renvoyé par `getVideo` | Nouvel état |
|---|---|
| `4` (terminé), ou `3` (encodage) avec au moins une qualité disponible | `ready`, avec enregistrement de `length` et `availableResolutions` |
| `5` (erreur) ou `6` (envoi échoué) | `failed` |
| `1`, `2` ou `3` (sans qualité disponible) | `processing` |
| autre | aucun changement |

Une vidéo `ready` ne revient jamais en arrière. Les webhooks suivants mettent seulement à jour les qualités et la durée.

Quand la vidéo devient `ready`, une seule notification push est envoyée à l'auteur avec les données `{ "type": "video_ready", "testimony_id": "<uuid>" }`. L'envoi passe par `PushNotifications::dispatch`, c'est-à-dire par la file `push` puis `PushNotifier`. Les anciennes vidéos reprises par la commande de migration ne déclenchent pas de notification.

### Ressource témoignage : objet `video`

```json
"video": {
  "status": "ready",
  "hls_url": "https://vz-….b-cdn.net/<guid>/playlist.m3u8",
  "thumbnail_url": "https://vz-….b-cdn.net/<guid>/thumbnail.jpg",
  "mp4_urls": { "240": "https://…/play_240p.mp4", "360": "…", "720": "…" },
  "duration": 42
}
```

- `mp4_urls` ne contient que les qualités 240, 360, 480 et 720 présentes dans `available_resolutions`. C'est un objet, vide (`{}`) s'il n'y a aucune qualité.
- Une vidéo Bunny qui n'est pas `ready` renvoie son `status`, avec des URL `null` et `mp4_urls` vide.
- Une ancienne vidéo LWS renvoie `status: ready`, `hls_url: null`, `thumbnail_url` égal à `coverUrl`, et `mp4_urls` construit à partir des versions ffmpeg (`renditions`).
- `video` vaut `null` pour un témoignage sans vidéo : texte, audio, ou YouTube (lu avec `youtubeId`).
- Compatibilité avec les anciennes versions de l'application : pour une vidéo Bunny prête sans ancien fichier, `mediaUrl` reçoit le MP4 de meilleure qualité et `coverUrl` la miniature, si aucune couverture n'a été envoyée.

## Suppression

Quand un témoignage est supprimé, que ce soit depuis l'API, le site ou l'administration (suppression douce, aucune restauration possible), sa vidéo Bunny est supprimée après la réponse. Un échec est journalisé (`Log::warning`) sans bloquer la suppression. Cela passe par l'événement `deleted` du modèle `Testimony`.

## Tâches et commandes

- **`videos:expire-uploads {--hours=24}`** : planifiée **chaque heure** (`routes/console.php`). Les témoignages restés en `uploading` plus de 24 h après leur création passent en `failed`. Leur vidéo Bunny est supprimée, puis `bunny_video_id` est vidé si la suppression a réussi.
- **`videos:migrate-to-bunny {--dry-run} {--limit=10}`** : traite les anciennes vidéos (`type = video`, `media_url` renseigné, sans YouTube ni `bunny_video_id`), des plus anciennes aux plus récentes.
  - Pour chacune, Bunny va chercher le fichier lui-même (`POST /library/{id}/videos/fetch`). L'URL publique est recalculée à partir du chemin, avec `APP_URL`.
  - Le témoignage reçoit le guid et passe en `processing`. Le webhook le fera passer à `ready`.
  - La commande affiche un récapitulatif : envoyées, échecs, restantes.
  - Toujours lancer `--dry-run` d'abord, puis procéder par lots.
  - À vérifier sur le premier lot réel : que la réponse de `fetch` contient bien l'identifiant de la vidéo (`guid` ou `id`). Sinon, la commande signale un échec sans rien modifier.

## Code

- `App\Services\BunnyStream` : client HTTP (`https://video.bunnycdn.com`, en-tête `AccessKey`, délai de 15 s), signature TUS et URL du CDN.
- `App\Services\BunnyStreamVideos` : préparation de l'envoi, synchronisation, objet `video`, envois abandonnés, suppression silencieuse.
- `App\Services\TestimonyCreator` : création commune à `POST /testimonies` et `upload-init`.
- `Api\VideoUploadController`, `Api\BunnyWebhookController`, `InitVideoUploadRequest`.
- Commandes `ExpireVideoUploads` et `MigrateVideosToBunny`.
- Tests : `tests/Feature/BunnyStreamTest.php`.

## Configuration

`.env` → `config/services.php` (`services.bunny_stream`) :

```
BUNNY_STREAM_LIBRARY_ID=777434
BUNNY_STREAM_API_KEY=…        # clé API de la bibliothèque (jamais versionnée)
BUNNY_STREAM_CDN_HOST=vz-5fc8c5f9-0fc.b-cdn.net
```

Sans ces variables, `upload-init` répond 503.

Dans la bibliothèque Bunny, deux réglages sont nécessaires :

- activer **MP4 Fallback**, sinon les liens `play_{hauteur}p.mp4` répondent 404 ;
- déclarer l'URL du webhook.

## Reste à faire

- Les pages du **site** (lecteur, page Vidéos) lisent encore `media_url` et les versions ffmpeg. Une vidéo envoyée seulement à Bunny n'y est pas encore lisible. Prochaine étape : utiliser `hls_url` et `mp4_urls` dans `videos/partials/watch.blade.php` et dans la page d'un témoignage.
- Après la reprise de toutes les anciennes vidéos : supprimer les fichiers de `storage/app/public` et, par une nouvelle migration, les colonnes de l'ancien stockage.
