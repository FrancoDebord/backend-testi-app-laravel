# Lecture des audios dans le navigateur

## Problème constaté (2026-11-05)

Les témoignages audio enregistrés depuis l'application ne se lisaient pas sur le site dans Chrome et Edge : le lecteur restait à 0:00, puis « L'enregistrement ne peut pas être lu pour le moment ».

Diagnostic sur la production (`/videos/01a18331-…`, Chrome sans interface) :

- le fichier est bien servi (`206 Partial Content`, plages d'octets, `video/mp4`) ;
- son contenu est un MP4 son seul (AAC LC, 44,1 kHz, mono), mais **le premier échantillon fait 2 octets** : `0x1208`, c'est-à-dire la configuration du codec (AudioSpecificConfig), écrite par l'enregistreur du téléphone comme si c'était du son ;
- Chrome refuse alors tout le fichier : `PIPELINE_ERROR_DECODE: Failed to send audio packet for decoding … size=2`. Les lecteurs Android l'ignorent, d'où un fichier lisible dans l'application mais pas sur le site.

Second défaut : finfo annonce ces fichiers `video/mp4`. `POST /api/v1/media/upload` les rangeait donc dans `media/videos/` avec le type `video`, et la conversion en qualités (`TranscodeMediaJob`) ne pouvait pas aboutir (« aucune piste vidéo »).

## Correction

`App\Support\Mp4AudioRepair` (PHP seul, sans ffmpeg) :

- `needsRepair($chemin)` : fichier MP4 avec **une seule piste, de son**, dont le premier échantillon est identique, octet pour octet, à la configuration lue dans `esds` ;
- `repair($chemin)` : retire cet échantillon des tables (`stsz`, `stsc`, `stts`, `stco`/`co64`, durée `mdhd`) et décale les positions si `moov` précède les données. Les données audio ne sont pas modifiées. Fichier écrit à côté puis renommé (jamais à moitié réécrit). Tout autre cas (vidéo, fichier sain, structure inattendue : `ctts`, `stss`…) est laissé tel quel ;
- `isAudioOnly($chemin)` : MP4 avec une piste son et aucune piste vidéo ;
- `repairStored($disque, $chemin)` : appelé après chaque envoi (disque local, extensions `mp4`, `m4a`, `m4b`, `mov`, `3gp`, `3g2`). Un échec ne fait jamais échouer l'envoi.

Appliqué à l'envoi :

| Envoi | Effet |
|---|---|
| `POST /api/v1/media/upload` (application) | MP4 sans image → type `audio`, dossier `media/audios/`, conversion audio (32k / 64k / 128k) ; fichier réparé |
| Publication sur le site (`Web\TestimonyController::store`) | Fichier réparé ; MP4 sans image enregistré `audio/mp4` / `audio` dans `media_files` |
| Parole prophétique avec audio (`Web\ProphecyController`) | Fichier réparé |

### Fichiers déjà en ligne : `php artisan media:repair-audio`

```bash
php artisan media:repair-audio --dry-run   # liste, sans rien modifier
php artisan media:repair-audio
```

- examine les MP4 de `storage/app/public/media/` et répare ceux qui ont le défaut ;
- donne une nouvelle adresse aux liens de ces fichiers (`…/fichier.mp4?v=1762358400` dans `testimonies.media_url`, `media_files.url`, `prophecies.audio_url`) : `/storage` est servi avec `Cache-Control: max-age=604800`, un navigateur qui a gardé la version cassée en cache charge ainsi la version réparée ;
- reclasse en `audio` les `media_files` de type `video` sans piste vidéo, et remet leur conversion en file d'attente s'ils n'ont pas été convertis.

La commande peut être relancée sans risque : un fichier réparé n'est plus concerné.

## Vérifications faites

- Deux audios de production, copiés en local : avant réparation, Chrome renvoie l'erreur de décodage ; après, lecture normale (durées 14,4 s et 19,5 s, une trame de 23 ms en moins).
- Tests `AudioRepairTest` (MP4 synthétiques : `moov` avant ou après les données, fichier sain, vidéo, fichier non MP4, envoi API, commande).

## À suivre

- **Application** (dépôt Flutter) : l'enregistreur Android écrit la configuration AAC comme premier échantillon. Le serveur répare désormais chaque envoi, mais corriger l'enregistrement (réglages de l'encodeur ou mise à jour du paquet d'enregistrement) évite le problème pour tout autre lecteur.
- **Conversion en qualités** : sur la production, les fichiers étaient encore `renditionsStatus: pending` plusieurs jours après l'envoi, ce qui laisse penser que la file d'attente ne tourne pas. Lancer `php artisan media:check` (voir [qualites-media.md](qualites-media.md)).
- **En-têtes** : le proxy de l'hébergeur renvoie les fichiers de `/storage` en `Transfer-Encoding: chunked`, sans `Content-Length`. Chrome s'en accommode ; si Safari (iPhone) pose problème, vérifier ce point chez l'hébergeur.
