# Vidéos des témoignages sur Bunny Stream

Les vidéos des témoignages sont hébergées par **Bunny Stream** (bibliothèque `777434`, CDN `vz-5fc8c5f9-0fc.b-cdn.net`). Les fichiers vidéo ne passent **jamais** par le serveur LWS :

- l'application **et le site** envoient le fichier **directement à Bunny** (protocole TUS) avec une autorisation signée par le serveur ;
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

URL à déclarer dans la bibliothèque Bunny : `https://temoignage.as-krea.com/api/v1/webhooks/bunny`. La route est dans le groupe `v1`, sans authentification ni CSRF.

**Contenu reçu** : `{ VideoLibraryId, VideoGuid, Status }`. Les statuts du **webhook** ne sont pas ceux de l'API : 3 = Finished, 4 = ResolutionFinished, 5 = Failed, les autres signalent une progression.

**Réponse** : toujours **200 en JSON**, `{ "ok": true, "action": "updated" | "ignored" | "not_found" }`.

| `action` | Cas |
|---|---|
| `updated` | État relu chez Bunny et témoignage modifié |
| `ignored` | GUID absent, Bunny non configuré, autre bibliothèque que `BUNNY_STREAM_LIBRARY_ID`, API Bunny refusée ou injoignable, ou aucun changement |
| `not_found` | Aucun témoignage n'a ce `bunny_video_id` |

Chaque appel est journalisé dans `storage/logs/laravel.log` (`Bunny Stream : webhook`) avec le GUID, le statut reçu, l'action et la **raison**. Une erreur de l'API Bunny est journalisée en `error`, avec la bibliothèque utilisée.

Bunny ne signe pas ses webhooks : le contenu n'est jamais cru tel quel. L'état est relu **pendant la requête** par `GET /library/{id}/videos/{guid}` (`App\Services\BunnyVideoSync::syncFromBunny`, logique unique partagée avec la commande `videos:sync-bunny-status`). Traduction des statuts de l'**API** :

| Statut de l'API (`getVideo`) | Nouvel état |
|---|---|
| `4` (terminée), ou `3` (encodage) avec au moins une résolution prête | `ready` |
| `5` (erreur) ou `6` (envoi échoué) | `failed`, et l'auteur est prévenu une seule fois (push `{ type: video_failed, testimony_id }`) |
| `1`, `2` ou `3` sans résolution | `processing` (`video_processing_at` est retenu) |
| `0` (créée, rien reçu) | aucun changement |

**Passage à `ready`** :
- `media_url` = `https://{CDN}/{guid}/playlist.m3u8` (lecture HLS) ;
- `cover_url` = `https://{CDN}/{guid}/thumbnail.jpg`, si aucune couverture n'a été envoyée ;
- `duration` et `duration_sec` reçoivent `length`, `available_resolutions` reçoit `availableResolutions` ;
- une seule notification push `{ "type": "video_ready", "testimony_id": "<uuid>" }`, grâce à une mise à jour conditionnelle ; pas de notification pour une ancienne vidéo reprise.

Une vidéo `ready` ne revient jamais en arrière. Les appels suivants complètent seulement les qualités, la durée ou le lien manquant.

### Ressource témoignage : objet `video`

```json
"video": {
  "status": "ready",
  "playback_url": "https://vz-….b-cdn.net/<guid>/playlist.m3u8",
  "mp4_url": "https://vz-….b-cdn.net/<guid>/play_720p.mp4",
  "thumbnail_url": "https://vz-….b-cdn.net/<guid>/thumbnail.jpg",
  "duration": 95,
  "hls_url": "…playlist.m3u8",
  "mp4_urls": { "240": "…", "720": "…" }
}
```

- `status` vaut `processing` (envoi en cours compris), `ready` ou `failed`.
- **Aucune URL tant que la vidéo n'est pas lisible** : `playback_url`, `mp4_url`, `thumbnail_url` et `duration` valent `null`.
- `mp4_url` est la meilleure qualité `play_XXXp.mp4` produite (240, 360, 480 ou 720) si le MP4 de secours est actif (`BUNNY_STREAM_MP4_FALLBACK`, vrai par défaut). Sinon `null`.
- `hls_url` et `mp4_urls` sont les anciens noms, gardés pour les versions de l'application déjà publiées.
- Une ancienne vidéo du serveur renvoie `status: ready`, `playback_url: null`, et `mp4_url` égal au meilleur fichier ffmpeg ou au fichier d'origine.
- `video` vaut `null` sans vidéo (texte, audio, YouTube).
- `mediaUrl` vaut la playlist HLS pour une vidéo Bunny prête, ce qui permet aux anciennes versions de l'application de la lire (ExoPlayer et AVPlayer lisent le HLS).

## Site web

- **Publication** (`/testimonies/create`), quand Bunny est configuré, pour une vidéo : le fichier part **directement du navigateur vers Bunny** (`resources/js/bunny-upload.js`, `tus-js-client`) :
  1. le formulaire est envoyé **sans le fichier**, avec `bunny_upload=1`, à `POST /testimonies`. Le serveur crée le témoignage (`uploading`) et la vidéo Bunny, puis renvoie l'autorisation signée (`signature`, `expiration`, `bunny_video_id`, `library_id`, `tus_endpoint`), **jamais la clé API** ;
  2. le fichier est envoyé à `https://video.bunnycdn.com/tusupload` avec une barre de progression et une reprise automatique ; l'autorisation est renouvelée avant expiration par `POST /testimonies/{id}/video/signature` ;
  3. `POST /testimonies/{id}/video/complete` passe la vidéo en `processing`, puis redirige vers le témoignage, qui affiche « Vidéo en cours de traitement ».

  Limite : 2 Go. Les audios, et les vidéos quand JavaScript est désactivé, gardent l'envoi au serveur (100 Mo).
- **Lecture** : une vidéo Bunny prête est lue en HLS (`<video data-hls>`, `resources/js/hls-player.js`). Safari la lit nativement, les autres navigateurs avec `hls.js`, chargé à la demande. En cas d'erreur fatale, le lecteur passe au MP4 de secours. Les cartes n'utilisent un aperçu au survol que s'il existe un MP4 (`Testimony::mp4PlaybackUrl()`), et affichent sinon la vignette.
- Les anciennes vidéos du serveur (`/storage/media/…`) restent lues comme avant, jusqu'à leur reprise par `videos:migrate-to-bunny`.

## Suppression

La vidéo Bunny est supprimée à la **suppression définitive** du témoignage seulement (événement `forceDeleted`). La corbeille, elle, reste restaurable ([gestion-des-temoignages.md](gestion-des-temoignages.md)).

## Tâches et commandes

- **`videos:sync-bunny-status {--guid=}`** : filet de sécurité du webhook, planifié **toutes les 10 minutes** (`withoutOverlapping`) et **lancé aussi par `POST /api/v1/_deploy`** après les migrations.
  - Elle relit chez Bunny chaque vidéo en `uploading`, `processing`, sans état, sans `media_url`, ou `ready` sans lien HLS. Les vidéos en `failed` ne sont relues qu'avec `--guid`.
  - Elle applique la même logique que le webhook.
  - Elle alerte les **super-administrateurs** (à défaut, les administrateurs) **une seule fois** par vidéo restée en traitement plus de 30 minutes : notification `video_stuck` et push, `video_alerted_at` renseigné.
  - Avec `--guid` inconnu en base, elle indique ce que Bunny connaît de la vidéo et les témoignages candidats, aussi dans `laravel.log`.
- **`videos:expire-uploads {--hours=24}`** : chaque heure, les envois jamais terminés passent en `failed` et leur vidéo Bunny est supprimée.
- **`videos:migrate-to-bunny {--dry-run} {--limit=10}`** : reprise des anciennes vidéos (Bunny va chercher le fichier). Le fichier d'origine reste lu tant que la vidéo Bunny n'est pas prête ; ensuite `media_url` devient la playlist HLS.

## Code

- `App\Services\BunnyStream` : client HTTP (en-tête `AccessKey`, délai de 15 s), signature TUS, URL du CDN. Les valeurs du `.env` sont nettoyées (espaces, `https://`, barre finale).
- `App\Services\BunnyVideoSync` : synchronisation unique (webhook et commande), transitions, notifications, alerte des vidéos bloquées.
- `App\Services\BunnyStreamVideos` : préparation de l'envoi, objet `video`, MP4 disponibles, envois abandonnés, suppression silencieuse.
- `Api\VideoUploadController` (API Sanctum, et routes web `testimonies.video.signature` et `testimonies.video.complete` en session), `Api\BunnyWebhookController`, `Web\TestimonyController::store` (`bunny_upload`).
- Commandes `SyncBunnyStatus`, `ExpireVideoUploads`, `MigrateVideosToBunny`.
- Front : `resources/js/bunny-upload.js`, `resources/js/hls-player.js` (paquets npm `tus-js-client` et `hls.js`).
- Migration `2026_10_15_100001_add_video_tracking_to_testimonies_table` : `video_processing_at`, `video_alerted_at`.
- Tests : `tests/Feature/BunnyStreamTest.php`, `tests/Feature/BunnyVideoSyncTest.php`.

## Configuration

`.env` → `config/services.php` (`services.bunny_stream`) :

```
BUNNY_STREAM_LIBRARY_ID=777434
BUNNY_STREAM_API_KEY=…                          # clé API de la BIBLIOTHÈQUE Stream (pas la clé du compte)
BUNNY_STREAM_CDN_HOST=vz-5fc8c5f9-0fc.b-cdn.net  # sans https:// ; BUNNY_STREAM_CDN_HOSTNAME accepté aussi
BUNNY_STREAM_MP4_FALLBACK=true                   # false si « MP4 Fallback » n'est pas activé dans la bibliothèque
```

Sans les trois premières variables, l'envoi direct est désactivé (`upload-init` répond 503), et le webhook et la commande ignorent tout. Après toute modification du `.env` sur le serveur, il faut vider le cache de configuration : un nouveau déploiement suffit, car `_deploy` lance `optimize:clear`.

Dans la bibliothèque Bunny :
- activer **MP4 Fallback** (liens `play_XXXp.mp4`) ;
- déclarer l'URL du webhook.

## Reste à faire

- Après la reprise de toutes les anciennes vidéos : supprimer les fichiers de `storage/app/public/media` et, par une nouvelle migration, les colonnes de l'ancien stockage (`renditions`).
