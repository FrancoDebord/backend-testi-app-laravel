# Direct avec une caméra IP ou un encodeur

## But

Diffuser un direct depuis une **caméra IP**, une caméra de salle ou un logiciel / boîtier d'encodage (OBS, vMix, Atem…), et non seulement depuis la caméra du téléphone ou de l'ordinateur. S'appuie sur **LiveKit Ingress**.

## Utilisation

1. *Lancer un direct* → **Caméra utilisée** :
   - **Caméra de cet appareil** : fonctionnement habituel (vérifications de la caméra et du micro) ;
   - **Caméra IP ou encodeur (RTMP)** (conseillé) : le studio donne une **adresse** et une **clé de diffusion** à saisir dans la caméra ou le logiciel ;
   - **Caméra IP (adresse du flux)** : le service vidéo lit lui-même un flux joignable depuis Internet (HLS, HTTP, SRT, RTMP ; RTSP selon le service) ; l'adresse se renseigne dans un [formulaire](#formulaire-de-la-caméra-ip) (modèle, hôte, port, identifiants…).
2. Les vérifications de l'appareil sont remplacées par un mode d'emploi (encadré « Brancher une caméra IP »).
3. **Studio** : panneau « Caméra IP » (adresse et clé avec boutons Copier / Afficher, état « En attente du flux » / « Flux reçu »), **aperçu** de l'image de la caméra (son coupé dans le studio). Les boutons Micro, Caméra, Retourner sont masqués.
4. **Passer à l'antenne** s'active quand le flux arrive. Le direct ne démarre **jamais tout seul**.
5. Commentaires, intervenants, modération, enregistrement et fin du direct fonctionnent comme d'habitude. Le diffuseur peut piloter le direct depuis le studio sans publier sa propre caméra.

### Formulaire de la caméra IP

Mode **Caméra IP (adresse du flux)** : au lieu d'un seul champ, un formulaire (site et application) compose l'adresse.

| Champ | Détail |
|---|---|
| Modèle de caméra | Pré-remplit protocole (RTSP), port et chemin ; choix **Flux principal / secondaire** si connu ; aide propre au modèle |
| Protocole | RTSP (554), RTSPS (322), RTMP (1935), RTMPS (443), HTTP (80) / HTTPS (443) pour le HLS, SRT (9000) ; le port suit le protocole tant qu'il n'a pas été modifié |
| Adresse IP ou nom d'hôte | Obligatoire ; IPv4, IPv6 (mise entre crochets) ou nom (DDNS) ; sans `rtsp://`, `/`, `@` |
| Port | 1 à 65535 |
| Chemin du flux | Ex. `/stream1`, paramètres compris (`?channel=1&subtype=0`) ; sans espace |
| Identifiant, mot de passe | Encodés dans l'adresse (`@` → `%40`, `:` → `%3A`, `/` → `%2F`, `%` → `%25`…) ; mot de passe masqué, bouton œil |
| SRT | Pas d'identifiant : **Stream ID** et **phrase secrète** (10 à 79 caractères) en paramètres `streamid`, `passphrase` |

- **Aperçu** de l'adresse composée, mot de passe remplacé par `••••`.
- **Adresse locale** (192.168.x.x, 10.x, 172.16-31.x, 127.x, 169.254.x, 100.64-127.x, `localhost`, `.local`, IPv6 `fc00::/7`, `fe80::/10`) : avertissement (non bloquant) — le service vidéo est sur Internet ; utiliser l'IP publique de la box ou un nom DDNS avec une redirection de port vers la caméra, ou le mode RTMP.
- **Adresse complète** : coller une adresse ; revenir au formulaire la décompose (mot de passe non encodé contenant `@` ou `/` accepté : coupure au dernier `@` avant les paramètres ; modèle reconnu d'après le chemin).
- **Mémorisation** : dernier réglage (protocole, hôte, port, chemin, identifiant, modèle, flux, Stream ID) dans le stockage sécurisé de l'application (`live_ip_camera_last`) ou le `localStorage` du navigateur (`testiapp:live:ip-camera`) ; **jamais le mot de passe ni la phrase secrète**.
- **Pas de « Tester la connexion »** : ni le téléphone ni le navigateur ne lisent le RTSP / SRT, et un essai depuis l'appareil ne dirait pas si le service vidéo (sur Internet) joint la caméra. Le test réel est l'aperçu du studio (« Flux reçu »).

Préréglages (chemins publiés par les fabricants) :

| Modèle | Port | Flux principal | Flux secondaire |
|---|---|---|---|
| Hikvision (HiLook) | 554 | `/Streaming/Channels/101` | `/Streaming/Channels/102` |
| Dahua (Imou), Amcrest | 554 | `/cam/realmonitor?channel=1&subtype=0` | `…&subtype=1` |
| Reolink | 554 | `/h264Preview_01_main` | `/h264Preview_01_sub` |
| Axis | 554 | `/axis-media/media.amp` | `/axis-media/media.amp?resolution=640x360` |
| TP-Link Tapo | 554 | `/stream1` | `/stream2` (compte de la caméra à créer dans l'application Tapo) |
| Foscam | 88 | `/videoMain` | `/videoSub` |
| ONVIF générique, Autre | 554 | chemin à saisir | — |

**Choix technique** : l'adresse est composée côté client puis envoyée dans `camera_url` (API inchangée, même validation). Le serveur la garde chiffrée et ne la renvoie au diffuseur que **masquée** (`LiveSessionResource::maskCameraUrl()`) ; elle n'est pas journalisée ni remise en session après une erreur (`dontFlash`). Les champs non secrets du site (`camera_host`, `camera_port`…) reviennent après une erreur de validation ; mot de passe et adresse complète sont à ressaisir.

### Caméra seulement RTSP sur le réseau local

La plupart des caméras IP ne publient qu'un flux RTSP local. Deux solutions :

- **OBS** (ordinateur sur le même réseau) : source « Source média » avec l'adresse RTSP, puis *Paramètres → Stream → Service personnalisé* avec l'adresse et la clé du studio ;
- **ffmpeg** : `ffmpeg -rtsp_transport tcp -i rtsp://utilisateur:motdepasse@192.168.1.20:554/stream1 -c:v libx264 -preset veryfast -b:v 3000k -g 60 -c:a aac -ar 44100 -f flv "<adresse>/<clé>"`.

Réglages conseillés : 720p, 2 à 4 Mbit/s, image clé toutes les 2 secondes, son AAC.

## Règles

| Règle | Détail |
|---|---|
| Qui | Modérateurs et administrateurs (comme tout direct) |
| Identité du flux | `host-camera-{id du direct}` : commence par `host-`, donc les spectateurs (site et application) la voient comme le diffuseur ; exclue du nombre de spectateurs |
| Démarrage | Le webhook `track_published` ne fait **pas** passer à l'antenne un flux `host-camera-` : le diffuseur vérifie l'aperçu et lance |
| Secrets | Clé de diffusion et adresse du flux (qui peut contenir un mot de passe) chiffrées en base (`encrypted`), jamais renvoyées à un spectateur : API `camera` pour le seul diffuseur, `sourceUrl` avec mot de passe masqué |
| Adresse (mode URL) | Obligatoire ; doit commencer par `rtsp(s)://`, `rtmp(s)://`, `http(s)://` ou `srt://` ; doit être joignable depuis Internet |
| Échec de création | Service vidéo injoignable ou adresse refusée : le direct est clos, message clair (503) |
| Fin du direct | Le point d'entrée (Ingress) est supprimé chez LiveKit |
| Transcodage | Activé : plusieurs qualités pour les spectateurs |

## Fonctionnement technique

| Élément | Fichier |
|---|---|
| Appels LiveKit | `LiveKitClient::createIngress()`, `deleteIngress()` (Twirp `livekit.Ingress`, droit `ingressAdmin`) |
| Création, fin | `LiveService::start()` → `connectCamera()`, `end()` → `disconnectCamera()` |
| Colonnes | `live_sessions.source`, `ingress_id`, `ingress_url`, `ingress_stream_key`, `camera_url` (migration `2026_09_30_100004_add_camera_source_to_live_sessions_table`) |
| Modèle | `LiveSession::SOURCES`, `usesExternalCamera()`, `cameraIdentity()` |
| Validation | `Web\LiveController::store()`, `Api\LiveController::store()` (`source`, `camera_url`) |
| API | `LiveSessionResource` : `source`, `camera` (diffuseur seulement : `url`, `streamKey`, `sourceUrl`) |
| Application | `models/ip_camera_config.dart` (composition, lecture, préréglages, adresses privées ; `test/ip_camera_test.dart`), `widgets/ip_camera_form.dart`, `screens/live_setup_screen.dart` |
| Vues | `lives/create.blade.php` (choix, mode d'emploi, formulaire de la caméra IP et son script), `lives/studio.blade.php` (panneau Caméra IP), `lives/partials/config.blade.php` (`source`) |
| JavaScript | `resources/js/live.js` (`initStudio` : aperçu du flux, pas de publication locale) ; `app.js` : `data-copy`, `data-reveal` |

## Déploiement

- `php artisan migrate --force`.
- **LiveKit** : Ingress doit être disponible sur le projet (LiveKit Cloud : inclus ; serveur auto-hébergé : déployer le service `livekit-ingress`). La clé d'API doit avoir le droit Ingress.
- Recompiler et copier `public/build/`.

## Tests

`tests/Feature/LiveCameraTest.php` : RTMP (point d'entrée créé avec la bonne salle et l'identité, clé chiffrée en base, visible du seul diffuseur, panneau du studio), mode URL (adresse obligatoire et vérifiée, pas de vérification de l'appareil sur le site), pas de passage automatique à l'antenne, suppression du point d'entrée à la fin, échec clair.

## Limites connues

- Le RTSP en lecture directe dépend du service LiveKit ; le mode RTMP (avec OBS ou ffmpeg si besoin) fonctionne partout.
- Contrôlé avec un service LiveKit simulé dans les tests ; **pas encore essayé avec une vraie caméra**.
- Pas de commande de la caméra (zoom, orientation) depuis le studio.
