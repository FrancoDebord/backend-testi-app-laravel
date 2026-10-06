# Témoignages en direct (lives)

## But

Diffuser un témoignage **en vidéo, en direct, depuis un téléphone**, et permettre à tous de le regarder sur le site web ou dans l'application mobile, de **commenter** et de **réagir** en temps réel.

> **Intervenants** : des personnes connectées peuvent témoigner à l'antenne, une à la fois, en médaillon. Voir [lives-intervenants.md](lives-intervenants.md).

## Règles

| Règle | Détail |
|---|---|
| **Qui peut diffuser** | Les **modérateurs** et **administrateurs** dont le compte est actif ; l'**organisateur d'un événement** (organisation vérifiée) pour le direct de **son** événement publié (`event_id`, voir [evenements.md](evenements.md)) |
| Un direct à la fois | Un diffuseur ne peut pas lancer un second direct tant que le premier n'est pas terminé (il est renvoyé vers son studio) |
| **Qui peut regarder** | Tout le monde, même sans compte |
| **Qui peut commenter et réagir** | Les personnes connectées, avec un compte actif, non exclues du direct |
| Visibilité | Un direct « en préparation » n'est visible que du diffuseur et des modérateurs ; il devient public au passage à l'antenne |
| Commentaires | Désactivables au lancement · 500 caractères maximum · **5 par 30 secondes** et par personne · le diffuseur et les modérateurs peuvent **toujours écrire**, même si les commentaires sont désactivés pour le public |
| **Commentaire épinglé** | Le diffuseur et les modérateurs épinglent **un** commentaire visible (le leur ou celui d'un spectateur) en haut du direct, et le désépinglent. Masquer ce commentaire ou exclure son auteur le désépingle |
| Réactions | J'aime, Prière, Amen, Adorer, Feu · **20 par 10 secondes** et par personne |
| **Modération** | Le diffuseur, les modérateurs et les administrateurs peuvent **masquer un commentaire**, **exclure une personne** du direct (ses commentaires sont masqués, elle ne peut plus commenter ni réagir) et **couper le direct** |
| Protection | Le diffuseur et les modérateurs ne peuvent pas être exclus |

## Caméra IP ou encodeur (2026-09-30)

Un direct peut aussi être diffusé depuis une caméra IP, OBS ou un encodeur (RTMP, ou adresse du flux) : voir [lives-camera-ip.md](lives-camera-ip.md).

## Qui regarde, commentaires, message épinglé (2026-09-29)

- **Qui regarde** : tout le monde (même sans compte) peut ouvrir la liste des spectateurs en touchant le compteur (œil) du direct : comptes connectés (nom, photo, lien vers le profil) et nombre de visiteurs non connectés. Le diffuseur n'y figure pas ; une personne ouverte dans plusieurs onglets n'apparaît qu'une fois. `GET /lives/{id}/viewers` (web) et `GET /api/v1/lives/{id}/viewers` → `{ total, people: [{ id, displayName, initials, avatarUrl, isVerified }], anonymous }`, relu au plus toutes les 10 s (`LiveService::viewers()`), 30 appels par minute.
- **Message épinglé du diffuseur** : un message **écrit par le diffuseur** et épinglé ne peut être **retiré, remplacé ni masqué** que par le diffuseur. Un modérateur ou un administrateur qui regarde le direct est un spectateur : réponse **403** (`LiveService::ensureCanReplacePin()`), et le site comme l'application masquent le bouton. Un message de spectateur épinglé reste gérable par la modération.
- **Zone des commentaires** plus haute (site : 16 à 40 rem ; application : 40 % de l'écran spectateur, 34 % dans le studio). Messages en bulles, message du diffuseur mis en évidence avec la pastille « Diffuseur ». **Zone d'écriture** : champ qui grandit avec le texte (jusqu'à 4 lignes), bouton d'envoi rond, compteur de caractères, option « Épingler » en pastille ; Entrée envoie, Maj+Entrée va à la ligne (site).

## Utilisation

### Diffuser (depuis le navigateur du téléphone)

1. Menu **Modération → Lancer un direct** (`/lives/create`).
2. **Vérifications automatiques**, qui doivent toutes réussir pour continuer (contrôlé aussi par le serveur) :
   - connexion sécurisée **HTTPS** (obligatoire pour la caméra) ;
   - navigateur compatible (WebRTC) ;
   - connexion Internet (type de réseau affiché, alerte si lent) ;
   - **caméra** et **micro** autorisés et disponibles, avec un message précis en cas de refus, d'absence ou d'appareil déjà utilisé ;
   - aperçu de la caméra, indicateur de niveau du micro, choix caméra avant ou arrière ;
   - batterie (conseil, non bloquant).
3. Titre, présentation, catégorie, commentaires autorisés ou non → **Ouvrir le studio**.
4. Dans le **studio** (`/lives/{id}/studio`) : aperçu, puis **Passer à l'antenne**. Pendant le direct :
   - couper ou rétablir le micro et la caméra, changer de caméra ;
   - chronomètre, nombre de spectateurs, état de la connexion, **qualité du réseau** ;
   - commentaires en direct avec modération ;
   - **Terminer**, avec confirmation.
   - L'écran reste allumé (Wake Lock) et une alerte prévient si l'on quitte la page.
5. **Coupure réseau** : reconnexion automatique. Si la page est fermée, revenir au studio dans les **5 minutes** reprend le direct ; au-delà, il est clôturé automatiquement.

### Regarder

- Menu **Découvrir → Directs** (`/lives`) : directs en cours et directs récents (durée, audience, commentaires).
- Page du direct (`/lives/{id}`) : vidéo, bouton « Activer le son » si le navigateur bloque la lecture audio, réactions, commentaires.
- Les modérateurs y disposent aussi des boutons Masquer, Exclure et **Couper le direct**.

## Architecture

```
Téléphone du diffuseur ──(vidéo WebRTC)──▶ LiveKit ──(vidéo)──▶ Spectateurs (web, mobile)
        │                                    ▲   │
        │ jetons, passage à l'antenne, fin   │   │ webhooks (antenne, fin de salle)
        ▼                                    │   ▼
     Laravel ──(API serveur : créer/fermer la salle, diffuser les messages)──┘
        ▲
        │ commentaires, réactions, modération (contrôlés, enregistrés, puis rediffusés)
   Spectateurs
```

- **La vidéo ne passe pas par le serveur Laravel** : elle transite par **LiveKit**, service WebRTC libre, en version **Cloud** hébergée ou installée sur un serveur dédié.
- **Laravel pilote** : il vérifie les droits, délivre des **jetons d'accès signés** à durée limitée, crée et ferme les salles, et enregistre commentaires, réactions et exclusions.
- **Commentaires et réactions passent toujours par Laravel** : contrôles, limites et enregistrement. Laravel les rediffuse ensuite à tous via l'API LiveKit (`SendData`). Les jetons des spectateurs **n'autorisent aucun envoi** dans la salle (`canPublish` et `canPublishData` désactivés) : impossible de contourner la modération.
- Les spectateurs sont **invisibles** entre eux (`hidden`). L'audience est calculée par le serveur toutes les 10 secondes.
- **Solutions de secours** : les pages rafraîchissent l'audience toutes les 15 s et les commentaires toutes les 30 s, en plus du temps réel, pour ne rien manquer si un message se perd.

### Statuts

`preparing` (en préparation) → `live` (en direct) → `ended` (terminé)

Motif de fin (`end_reason`) : `host` (diffuseur) · `moderator` · `connection` (salle fermée après une coupure) · `abandoned` (resté en préparation plus de 30 min).

### Abonnés prévenus au démarrage

Au passage `preparing` → `live` (`LiveService::goLive`, appelé par `POST lives/{id}/go-live` ou par le webhook `track_published` du diffuseur), chaque **abonné** du diffuseur (table `follows`) reçoit une notification `live_started` : « {Nom} est en direct : {titre} », avec `payload.live_id`. Elle part aussi en push (titre « En direct », voir [notifications push](notifications-push.md)).

- **Une seule fois par direct** : le changement de statut est une mise à jour conditionnelle (`WHERE status = 'preparing'`) ; seul l'appel qui l'effectue notifie. Un webhook rejoué, un second `go-live` ou l'arrivée simultanée de l'appel de l'application et du webhook ne notifient pas deux fois.
- Ni le diffuseur ni les non-abonnés ne sont notifiés. Rien n'est envoyé tant que le direct est « en préparation ».
- Un échec de notification n'empêche jamais le direct (erreur journalisée).
- Insertion par lots de 50 : `App\Services\FollowerNotifications::notify()`.

### Enregistrement → témoignage vidéo

Un direct peut être **enregistré** pour être revu plus tard comme un **témoignage vidéo**, publié seulement **après relecture** par la modération (circuit habituel).

1. **Au lancement** : option « Enregistrer le direct », cochée par défaut. Elle n'est proposée que si le stockage est configuré.
2. **Au passage à l'antenne** : Laravel demande à LiveKit (service **Egress**, `StartRoomCompositeEgress`) d'enregistrer la salle en MP4 720p, mise en page « speaker ». Seuls l'image et le son du diffuseur sont filmés : les spectateurs, invisibles, et les commentaires ne figurent pas dans la vidéo. Une pastille **REC** s'affiche dans le studio et chez les spectateurs, avec la mention « Ce direct est enregistré ».
3. **À la fin du direct** : arrêt propre de l'enregistrement (`StopEgress`). LiveKit finalise le fichier et le dépose dans le **stockage S3** configuré, chemin `lives/AAAA/MM/{id-du-direct}.mp4`.
4. **Fichier prêt** (webhook `egress_ended`, ou à défaut la vérification toutes les 5 min de `lives:cleanup`) : Laravel crée un **témoignage vidéo en attente de relecture** :

   | Champ | Valeur |
   |---|---|
   | Auteur | le diffuseur |
   | Titre, texte | titre et présentation du direct |
   | Catégorie | celle du direct, sinon `autre` |
   | Vidéo (`media_url`) | `LIVEKIT_RECORDING_PUBLIC_URL` + chemin du fichier |
   | Durée | durée réelle de l'enregistrement |
   | Statut / visibilité | `pending` (à relire) / publique |
   | Mots-clés | `direct` |

   Le fichier est aussi enregistré dans `media_files` (disque `recordings`) et ses **versions allégées** (240p à 720p) sont mises en file d'attente, comme pour une vidéo envoyée : voir [qualites-media.md](qualites-media.md). Elles sont déposées dans le même bucket, à côté de l'original (`lives/AAAA/MM/{id}_360p.mp4`), et lues depuis `LIVEKIT_RECORDING_PUBLIC_URL`. Demande le paquet `league/flysystem-aws-s3-v3` et une clé S3 en lecture/écriture.

5. **Après validation** en modération : le témoignage est publié, et un bouton **Rediffusion** apparaît dans « Directs récents » et sur la page du direct.

État de l'enregistrement (`recording_status`) :

| Valeur | Signification |
|---|---|
| *(vide)* | Pas encore à l'antenne |
| `recording` | Enregistrement en cours |
| `processing` | Direct terminé, fichier en cours de finalisation |
| `ready` | Témoignage vidéo créé (`testimony_id`) |
| `too_short` | Moins de 20 s (`LIVEKIT_RECORDING_MIN_DURATION`) : aucun témoignage créé |
| `failed` | Échec (détail dans `recording_error`). **Le direct n'est jamais interrompu** par un problème d'enregistrement |

Un même webhook reçu deux fois ne crée qu'un seul témoignage.

## API mobile

Préfixe `/api/v1`, format `{ success, data, message }`. Authentification Sanctum (`Authorization: Bearer …`) pour les routes marquées 🔒.

| Méthode | Route | Rôle |
|---|---|---|
| GET | `lives` | `active` (en cours), `recent` (terminés), `canGoLive`, `configured`, `recordingConfigured` |
| GET | `lives/{id}` | Détail (dont `pinnedComment` et `recording: { enabled, status, label, durationSec, replayTestimonyId }`) + `liveStats` (spectateurs, réactions…). `replayTestimonyId` : témoignage à ouvrir pour la rediffusion, une fois publié |
| POST | `lives/{id}/viewer-token` | Accès spectateur : `{ url, token, identity }` (connexion facultative) |
| GET | `lives/{id}/comments` | 100 derniers commentaires visibles |
| GET | `lives/{id}/stats` | `{ status, viewers, peakViewers, commentCount, reactions, pinnedComment }` |
| POST 🔒 | `lives` | Lancer un direct (modérateur/admin) : `title`, `description?`, `category_slug?`, `comments_enabled?`, `record?` (défaut : oui si le stockage est configuré) → `{ live, video: { url, token } }` |
| POST 🔒 | `lives/{id}/host-token` | Accès diffuseur (reprise du direct) |
| POST 🔒 | `lives/{id}/go-live` | Passer à l'antenne, une fois la caméra publiée |
| POST 🔒 | `lives/{id}/end` | Terminer (diffuseur) ou couper (modérateur) |
| POST 🔒 | `lives/{id}/comments` | `{ body }` |
| DELETE 🔒 | `lives/{id}/comments/{commentId}` | Masquer (modération) |
| POST 🔒 | `lives/{id}/comments/{commentId}/pin` | Épingler (diffuseur/modération) : remplace l'éventuel commentaire épinglé |
| DELETE 🔒 | `lives/{id}/pin` | Désépingler (diffuseur/modération) |
| POST 🔒 | `lives/{id}/bans` | `{ user_id }` : exclure (modération) |
| POST 🔒 | `lives/{id}/reactions` | `{ type }` : `like`, `pray`, `amen`, `worship` (« Adorer »), `fire` |

Codes d'erreur : **403** (droits, compte, exclusion) · **404** (introuvable ou pas encore public) · **409** (direct déjà en cours ou non démarré) · **410** (terminé) · **422** (commentaire vide ou trop long, réaction inconnue) · **429** (trop de commentaires ou de réactions) · **503** (service vidéo non configuré ou injoignable).

### Messages temps réel (sujet `live`, JSON)

Reçus via l'évènement `DataReceived` du SDK LiveKit :

| `type` | Contenu |
|---|---|
| `status` | `{ status: "live" }` : le direct commence |
| `comment` | `{ comment: { id, body, createdAt, user: { id, displayName, initials, avatarUrl } } }` |
| `comment_hidden` | `{ id }` |
| `user_banned` | `{ userId }` : retirer ses commentaires |
| `reaction` | `{ reaction, counts: { like, pray, amen, worship, fire } }` |
| `comment_pinned` | `{ comment }` : afficher ce commentaire épinglé en haut |
| `comment_unpinned` | `{}` : retirer le commentaire épinglé |
| `recording` | `{ status: "recording" }` : afficher l'indicateur « REC » |
| `ended` | `{ reason }` : afficher « Direct terminé » et se déconnecter |
| `stage` | `{ event, speaker?, reason? }` : intervenants, voir [lives-intervenants.md](lives-intervenants.md#message-temps-réel) |

### Intégration Flutter (à faire dans l'application)

SDK officiel `livekit_client` :

- **Regarder** : `viewer-token`, puis `Room().connect(url, token)` ; afficher la piste vidéo reçue (`VideoTrackRenderer`) ; écouter `DataReceivedEvent` ; envoyer commentaires et réactions **via l'API ci-dessus** (jamais via la salle).
- **Diffuser** (modérateurs/admins) : `POST lives` → `connect` avec le jeton reçu → `localParticipant.setCameraEnabled(true)` et `setMicrophoneEnabled(true)` → `POST lives/{id}/go-live`. Déclarer caméra et micro (`AndroidManifest.xml`, `Info.plist`). Mêmes vérifications préalables que sur le web.

## Fichiers

| Fichier | Rôle |
|---|---|
| `config/livekit.php` | Connexion LiveKit, durées, limites anti-abus |
| `database/migrations/2026_09_25_100001_create_live_tables.php` | Tables `live_sessions`, `live_comments`, `live_bans` |
| `app/Models/LiveSession.php`, `LiveComment.php`, `LiveBan.php` · `app/Enums/LiveStatus.php` | Modèles, droits (`canBeModeratedBy`, `isVisibleTo`) |
| `app/Services/LiveService.php` | **Toutes les règles** (démarrage, jetons, commentaires, réactions, modération, fin, nettoyage) |
| `app/Services/LiveKit/LiveKitClient.php` | Jetons JWT, API serveur LiveKit (salles, enregistrement), vérification des webhooks |
| `app/Services/LiveKit/EgressInfo.php` | Lecture de l'état d'un enregistrement (formats API et webhook) |
| `database/migrations/2026_09_25_300001_add_recording_to_live_sessions_table.php` | Colonnes d'enregistrement et lien `testimony_id` |
| `tests/Feature/LiveRecordingTest.php` | 6 tests : démarrage, désactivation, échec sans coupure, création du témoignage, trop court / échec, reprise sans webhook |
| `app/Http/Controllers/Api/LiveController.php` | Actions JSON, partagées par l'API mobile et les pages web |
| `app/Http/Controllers/Api/LiveKitWebhookController.php` | Webhooks LiveKit |
| `app/Http/Controllers/Web/LiveController.php` | Pages web |
| `app/Http/Resources/LiveSessionResource.php` | Format JSON d'un direct |
| `app/Console/Commands/CleanupLives.php` | `php artisan lives:cleanup` (planifiée toutes les 5 min dans `routes/console.php`) |
| `resources/views/lives/*` | Liste, lancement (vérifications), studio, page spectateur, commentaires |
| `resources/js/live.js` | Vérifications, studio, lecteur, temps réel (chargé seulement sur ces pages) |
| `tests/Feature/LiveSessionTest.php` | 18 tests : droits, jetons, commentaires, limites, modération, webhooks, nettoyage |
| `app/Services/FollowerNotifications.php` | Notification des abonnés du diffuseur au démarrage |
| `tests/Feature/LiveStartNotificationTest.php` | 4 tests : abonnés prévenus une seule fois (API, webhook, rejeu), ni diffuseur ni non-abonnés, préférence push |

## Mise en service

1. **Créer un projet LiveKit** : [LiveKit Cloud](https://cloud.livekit.io) (offre gratuite pour démarrer, puis facturation à l'usage : vérifier les tarifs), ou installation sur un serveur dédié (ports UDP ouverts nécessaires, impossible sur un hébergement mutualisé).
2. Dans le `.env` du serveur :
   ```
   LIVEKIT_URL=wss://<projet>.livekit.cloud
   LIVEKIT_API_KEY=…
   LIVEKIT_API_SECRET=…
   ```
3. Dans le projet LiveKit : **Settings → Webhooks**, ajouter `https://<domaine>/api/v1/livekit/webhook`, signé avec la même clé.
4. Déployer les fichiers, puis :
   ```bash
   php artisan migrate --force
   php artisan optimize:clear
   ```
5. Copier le nouveau `public/build/` (CSS et JS modifiés, nouveau fichier `live-*.js`).
6. **Tâche planifiée** : si l'hébergement le permet, ajouter la tâche cron Laravel `* * * * * php /chemin/artisan schedule:run`. Sans elle, les webhooks suffisent en temps normal ; les directs restés « en préparation » ne seraient simplement pas clôturés automatiquement.
7. **HTTPS obligatoire** : les navigateurs refusent la caméra sur une adresse `http://` (sauf `localhost`).
8. **Enregistrement** (facultatif, conseillé) : créer un espace de stockage compatible S3, par exemple **Cloudflare R2** (pas de frais de sortie pour la lecture des vidéos), AWS S3 ou Backblaze B2. La lecture des vidéos doit être **publique**, via un domaine ou l'URL publique du bucket. Puis, dans le `.env` :
   ```
   LIVEKIT_RECORDING_S3_KEY=…            # clé d'accès (écriture) au bucket
   LIVEKIT_RECORDING_S3_SECRET=…
   LIVEKIT_RECORDING_S3_BUCKET=testiapp-videos
   LIVEKIT_RECORDING_S3_REGION=auto      # R2 : auto · AWS : ex. eu-west-3
   LIVEKIT_RECORDING_S3_ENDPOINT=https://<compte>.r2.cloudflarestorage.com   # vide pour AWS S3
   LIVEKIT_RECORDING_S3_PATH_STYLE=true  # R2 / B2 : true
   LIVEKIT_RECORDING_PUBLIC_URL=https://videos.<domaine>   # adresse publique de lecture des fichiers
   ```
   Sans ces variables, les directs fonctionnent normalement, mais sans enregistrement : l'option est affichée « Indisponible ».

Test local : serveur de développement LiveKit (`livekit-server --dev`) et variables `LIVEKIT_*` pointant vers `ws://127.0.0.1:7880`.

## Limites connues

- **Enregistrement** : il nécessite un stockage S3. Chaque minute enregistrée est facturée par LiveKit (Egress) : vérifier les tarifs. L'enregistrement de bout en bout n'a pas encore pu être testé avec un vrai stockage ; LiveKit Cloud accepte bien la requête (vérifié le 2026-09-25).
- La vidéo de rediffusion n'a **pas d'image de couverture** : elle peut être ajoutée ensuite si besoin.
- **Diffusion depuis une salle** (église, studio, caméra, OBS) : non prévue pour l'instant. LiveKit accepte un flux RTMP ou WHIP (Ingress), à ajouter plus tard.
- **Application mobile** : les écrans restent à réaliser, avec l'API ci-dessus.
- Le fichier `live-*.js` pèse environ 150 Ko compressé : il n'est chargé que sur les pages de direct.
