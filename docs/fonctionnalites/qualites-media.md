# Qualités des médias (audio et vidéo)

## But

Permettre à l'application mobile de lire un témoignage audio ou vidéo dans une qualité adaptée à la connexion (240p sur réseau faible, 720p en Wi-Fi…). Après chaque envoi, et pour chaque enregistrement de direct devenu témoignage vidéo, le serveur produit avec **ffmpeg** des versions allégées du fichier, en arrière-plan. Le fichier original n'est jamais modifié et reste toujours lisible (`mediaUrl`).

Le **site web** utilise les mêmes versions : menu **Qualité** de la page de lecture (Auto, versions, origine), voir [affichage et lecture](affichage-et-lecture.md).

**Menu toujours visible** (depuis le 2026-09-29) : sans versions allégées, le site affiche « Qualité d'origine » (désactivé) avec la raison — « Autres qualités en préparation » (conversion en attente ou en cours) ou « Seule la qualité d'origine est disponible » ; l'application indique la même chose dans sa feuille « Qualité ». L'API expose `renditionsStatus` (`done`, `pending`, `processing`, `failed`, `none`, ou `null`) dans le détail d'un témoignage (relation `Testimony::mediaFile()`).

### Diagnostic : `php artisan media:check`

Si aucune vidéo ne propose de qualités, lancer :

```bash
php artisan media:check
```

La commande vérifie ffmpeg et ffprobe, l'activation de la conversion, la file d'attente (tâches en attente ou en échec) et compte les fichiers par état. Elle indique quoi corriger (« ffmpeg introuvable… », « relancez php artisan media:transcode --missing »). Code de retour 1 s'il reste un problème.

Cas constaté le 2026-09-29 sur une base de développement : **toutes les conversions en échec**, car ffmpeg n'était pas installé sur la machine qui avait lancé `media:transcode --missing`. Une fois ffmpeg installé : `php artisan media:transcode --missing`.

## Versions produites

| Type | Versions | Format |
|---|---|---|
| Vidéo | 240p (400 kbit/s), 360p (800), 480p (1 200), 720p (2 500) | MP4 H.264 (`veryfast`, CRF 28, débit plafonné), son AAC 96 kbit/s, `+faststart` (lecture avant la fin du téléchargement) |
| Audio | 32k, 64k, 128k | M4A AAC |

Règles :

- **Vidéo** : seules les hauteurs inférieures ou égales à la source sont produites ; la plus basse (240p) l'est toujours. La « hauteur » est le **petit côté** de l'image : une vidéo verticale de téléphone 1080×1920 donne un 360p de 360 px de large (la rotation enregistrée par le téléphone est prise en compte).
- **Audio** : les débits non inférieurs à celui de la source sont ignorés (une source à 96 kbit/s donne 32k et 64k). Si aucun débit n'est inférieur, aucune version n'est produite et le fichier est tout de même marqué traité.
- Les versions sont écrites dans le même dossier et sur le même disque que l'original : `media/videos/abc.mp4` → `media/videos/abc_360p.mp4`, `media/audios/xyz.mp3` → `media/audios/xyz_64k.m4a` (disque `public`) ; `lives/2026/09/{id}.mp4` → `lives/2026/09/{id}_360p.mp4` (enregistrement de direct, bucket S3).
- Échelle, débits et délai sont réglables dans `config/media.php`.

## API mobile

Chaque témoignage (`TestimonyResource`) expose `renditions`, trié par débit croissant, **`[]`** s'il n'y en a pas (témoignage texte, conversion en cours ou en échec) :

```json
"mediaUrl": "https://…/storage/media/videos/abc.mp4",
"renditions": [
  {"quality": "240p", "height": 240, "bitrate": 400, "url": "https://…/storage/media/videos/abc_240p.mp4"},
  {"quality": "360p", "height": 360, "bitrate": 800, "url": "https://…/storage/media/videos/abc_360p.mp4"}
]
```

Pour l'audio : `{"quality": "64k", "bitrate": 64, "url": "…"}` (pas de `height`). `bitrate` est en kbit/s. Les URL sont absolues, construites à l'affichage (`MediaFile::urlFor`) :

- disque `public` : `asset('storage/…')`, elles suivent `APP_URL` ;
- enregistrements de directs (disque `recordings`) : `LIVEKIT_RECORDING_PUBLIC_URL` + chemin, exactement comme le `mediaUrl` de l'enregistrement (`LiveService::recordingUrl` utilise la même fonction), par exemple `https://videos.exemple.org/lives/2026/09/{id}_360p.mp4`.

La réponse de `POST /api/v1/media/upload` est inchangée (`{id, url, type}`).

## Fonctionnement technique

### Déroulement

1. **Envoi** : `MediaController::upload` (API) ou `Web\TestimonyController::store` (site) enregistre le fichier et une ligne `media_files`, puis `MediaFile::queueTranscoding()` met `processing_status` à `pending` et place `App\Jobs\TranscodeMediaJob` dans la file d'attente (audio et vidéo seulement, si `MEDIA_TRANSCODING_ENABLED`). Un échec de mise en file n'empêche jamais l'envoi.
   **Enregistrement de direct** : quand LiveKit signale la fin de l'enregistrement et que le témoignage vidéo est créé (`LiveService::handleEgress`, webhook ou `lives:cleanup`), `MediaFile::forRecording()` crée (ou retrouve, si le webhook est rejoué) la ligne `media_files` du fichier (`disk` = `recordings`, `path` = `live_sessions.recording_path`, `url` = `media_url` du témoignage, durée et taille données par LiveKit), puis la conversion est mise en file de la même façon, après validation de la transaction.
2. **Conversion** (`TranscodeMediaJob`, 2 essais, délai `MEDIA_TRANSCODE_TIMEOUT`) : `processing` → analyse `ffprobe` (durée, dimensions, rotation, débit audio) → une commande `ffmpeg` par version (via `Illuminate\Support\Facades\Process`) → enregistrement de `renditions`, `duration_sec`, `width`, `height` sur `media_files`, statut `done`. Pour un disque non local, voir ci-dessous.
3. **Témoignages** : la tâche recopie `renditions` (et `duration_sec` s'il vaut 0) sur les témoignages dont `media_url` est l'URL du fichier, ou pointe vers le même chemin : `…/storage/{path}` pour le disque `public` (utile si `APP_URL` a changé), `…/{path}` pour un enregistrement (utile si `LIVEKIT_RECORDING_PUBLIC_URL` a changé). `MediaFile::findForUrl()` reconnaît ces mêmes formes.
4. **Création après conversion** : si le fichier est déjà converti quand le témoignage est créé ou que son `media_url` change (`Api\TestimonyController::store` / `update`), les versions et la durée sont reprises immédiatement.

### États (`media_files.processing_status`)

| Valeur | Sens |
|---|---|
| `none` | Jamais demandé (image, conversion désactivée, fichier antérieur) |
| `pending` | En file d'attente |
| `processing` | Conversion en cours |
| `done` | Terminé (`renditions` peut être vide pour un audio déjà léger) |
| `failed` | Échec : ffprobe/ffmpeg absent ou en erreur, fichier illisible ou absent, disque inutilisable (S3 sans le paquet `league/flysystem-aws-s3-v3`), téléchargement ou envoi impossible. Détails dans `storage/logs/laravel.log` (« Conversion média ») |

Une version en échec est ignorée (les autres sont conservées) ; les fichiers partiels sont supprimés. Lors d'une nouvelle conversion, les anciennes versions qui ne font plus partie de la série sont effacées.

### Fichiers sur un disque non local (enregistrements de directs sur S3)

Les enregistrements de directs sont déposés par LiveKit dans le bucket S3 configuré (`LIVEKIT_RECORDING_S3_*`, voir [lives.md](lives.md)). Laravel y accède par le disque **`recordings`** de `config/filesystems.php`, construit avec ces mêmes variables. Tout disque dont le pilote n'est pas `local` suit ce déroulement :

1. création d'un dossier de travail `media.temp_directory/tc-{uuid}` (`MEDIA_TEMP_DIR`, défaut `storage/app/transcode`) ;
2. téléchargement de l'original **par flux** (`readStream`, jamais chargé en mémoire) ;
3. `ffprobe` et `ffmpeg` sur la copie locale ;
4. envoi de chaque version **par flux** (`writeStream`) à côté de l'original sur le même disque, puis suppression immédiate de la copie locale de cette version ;
5. suppression du dossier de travail dans tous les cas (`finally`, et `failed()`). Les dossiers `tc-*` laissés par une tâche tuée (délai dépassé) sont effacés par la tâche suivante.

Visibilité des versions : celle du disque (`LIVEKIT_RECORDING_S3_VISIBILITY`). Laisser vide pour Cloudflare R2 ou Backblaze B2 (lecture publique par domaine ou bucket public, comme l'original déposé par LiveKit) ; mettre `public` pour un bucket AWS dont la lecture publique repose sur les ACL.

Si le disque ne peut pas être ouvert (paquet S3 absent, disque inconnu), le fichier passe en `failed` sans exception : l'enregistrement reste lisible depuis `mediaUrl`.

### Base de données

Migration `2026_09_28_100001_add_renditions_to_media_files_and_testimonies.php` :

- `media_files` : `renditions` (JSON), `processing_status` (`string(20)`, défaut `none`), `width`, `height` ;
- `testimonies` : `renditions` (JSON).

Format stocké d'une version : `{quality, height?, bitrate, disk, path, size_bytes}` (chemin relatif au disque, l'URL est calculée à l'affichage).

### Rattrapage des fichiers existants

```bash
php artisan media:transcode --missing        # fichiers jamais convertis ou en échec
php artisan media:transcode                  # tout reconvertir (nouvelle échelle…)
php artisan media:transcode --id={uuid}      # un seul fichier media_files
php artisan media:transcode --missing --sync # sans file d'attente (conversion immédiate, longue)
```

Sans `--id`, la commande crée d'abord la ligne `media_files` manquante des témoignages audio/vidéo dont le fichier est sur le disque `public` (anciens envois, site web) ou est un enregistrement de direct (`media_url` commençant par `LIVEKIT_RECORDING_PUBLIC_URL`, ou témoignage lié à un direct par `live_sessions.testimony_id`), puis met les conversions en file d'attente. Pour les enregistrements, l'existence du fichier est vérifiée par la tâche (absent → `failed`). Elle fonctionne même si `MEDIA_TRANSCODING_ENABLED=false`.

## Déploiement

1. **Installer ffmpeg** (fournit aussi `ffprobe`) :
   - Debian/Ubuntu : `sudo apt install ffmpeg` ;
   - hébergement sans accès root : binaire statique (https://johnvansickle.com/ffmpeg/ ou https://ffmpeg.org/download.html), puis indiquer son chemin dans `FFMPEG_BINARY` / `FFPROBE_BINARY` ;
   - vérifier : `ffmpeg -version` et `ffprobe -version` avec l'utilisateur qui exécute PHP.
2. **Variables d'environnement** (`.env`) :

   | Variable | Défaut | Rôle |
   |---|---|---|
   | `FFMPEG_BINARY` | `ffmpeg` | Chemin de ffmpeg |
   | `FFPROBE_BINARY` | `ffprobe` | Chemin de ffprobe |
   | `MEDIA_TRANSCODING_ENABLED` | `true` | `false` pour ne plus convertir à l'envoi (serveur sans ffmpeg) |
   | `MEDIA_TRANSCODE_TIMEOUT` | `1800` | Durée maximale d'une conversion, en secondes |
   | `DB_QUEUE_RETRY_AFTER` | `90` | **À porter au-dessus du délai** (ex. `1900`), sinon une conversion longue est relancée en parallèle |
   | `QUEUE_CONNECTION` | `database` | Laisser `database` (avec `sync`, la conversion se ferait pendant l'envoi) |
   | `MEDIA_TEMP_DIR` | `storage/app/transcode` | Dossier de travail des fichiers S3 : prévoir l'espace d'un original et d'une version |
   | `LIVEKIT_RECORDING_S3_VISIBILITY` | vide | `public` pour un bucket AWS lu par ACL ; vide pour R2 / B2 |

   La clé `LIVEKIT_RECORDING_S3_KEY` doit permettre la **lecture et l'écriture** du bucket (les versions y sont ajoutées).

3. **Enregistrements de directs** : installer le pilote S3 de Laravel, absent du projet : `composer require league/flysystem-aws-s3-v3 "^3.0"` (en local, puis déployer `composer.json`, `composer.lock` et `vendor/`, ou lancer `composer install --no-dev` sur le serveur). Sans lui, les enregistrements passent en `failed` et restent lus dans leur qualité d'origine ; les envois (disque `public`) ne sont pas concernés.
4. **Migration** : `php artisan migrate --force`, puis `php artisan config:clear` (ou `optimize:clear`).
5. **Lancer le traitement de la file d'attente** en permanence :

   ```bash
   php artisan queue:work --tries=2 --timeout=1900
   ```

   À surveiller par Supervisor ou systemd (redémarrage automatique). Après chaque déploiement : `php artisan queue:restart` pour que le processus charge le nouveau code. Sans processus permanent possible (hébergement mutualisé), tâche cron chaque minute : `php artisan queue:work --stop-when-empty --timeout=1900`.
6. **Rattrapage** des médias déjà publiés, enregistrements de directs compris : `php artisan media:transcode --missing`.

Sans ffmpeg, rien ne casse : les fichiers passent en `failed`, `renditions` reste `[]` et l'application lit l'original.

## Tests

`tests/Feature/MediaTranscodingTest.php` : `Storage::fake('public')`, `Process::fake()` (sortie ffprobe simulée ; le faux ffmpeg écrit le fichier de sortie), `Queue::fake()` pour l'envoi. Couvre : mise en file à l'envoi (pas pour les images ni si désactivé), échelle vidéo et rotation, débits audio, échec ffmpeg/ffprobe, reprise à la création du témoignage, JSON de l'API, commande de rattrapage. Disque non local : `Storage::fake('recordings')` (le pilote déclaré reste `s3`, donc le déroulement « téléchargement / envoi » est exercé) ; versions envoyées sur le disque, URL `LIVEKIT_RECORDING_PUBLIC_URL`, dossier de travail vidé, échec sans fichier partiel, disque inutilisable, rattrapage des témoignages de directs.

`tests/Feature/LiveRecordingTest.php` : la fin d'un enregistrement crée une seule ligne `media_files` (webhook rejoué) et met la conversion en file.

## Fichiers

| Fichier | Rôle |
|---|---|
| `config/media.php` | Exécutables, activation, échelle, débits, délai, dossier de travail |
| `config/filesystems.php` | Disque `recordings` (bucket S3 des enregistrements de directs) |
| `database/migrations/2026_09_28_100001_add_renditions_to_media_files_and_testimonies.php` | Colonnes |
| `app/Jobs/TranscodeMediaJob.php` | Analyse et conversion (disque local ou non local), mise à jour des témoignages |
| `app/Models/MediaFile.php` | États, `queueTranscoding()`, `findForUrl()`, `forRecording()`, `urlFor()`, `renditionsForApi()` |
| `app/Services/LiveService.php` | Ligne `media_files` et mise en file à la fin d'un enregistrement de direct |
| `app/Services/LiveKit/EgressInfo.php` | Taille du fichier enregistré (`sizeBytes()`) |
| `app/Http/Controllers/Api/MediaController.php` | Mise en file à l'envoi |
| `app/Http/Controllers/Api/TestimonyController.php` | Reprise des versions à la création / modification |
| `app/Http/Controllers/Web/TestimonyController.php` | Ligne `media_files` + mise en file pour les envois du site |
| `app/Http/Resources/TestimonyResource.php` | Champ `renditions` |
| `app/Console/Commands/TranscodeMedia.php` | Commande `media:transcode` |
