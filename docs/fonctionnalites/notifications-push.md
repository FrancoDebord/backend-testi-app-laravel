# Notifications push (FCM)

## But

Prévenir l'utilisateur sur son téléphone, même application fermée, à chaque notification de l'application : commentaire, réponse, réaction, nouvel abonné, témoignage approuvé ou refusé, nouveau témoignage d'un compte suivi, direct lancé par un compte suivi, organisation vérifiée ou refusée. Le serveur envoie les push par **Firebase Cloud Messaging, API HTTP v1**, sans dépendance Composer supplémentaire.

Sans configuration (`FIREBASE_CREDENTIALS` vide), rien ne change : les notifications restent visibles dans l'application et aucun push n'est envoyé.

## Règles

- **Toute** notification enregistrée dans `app_notifications` part aussi en push vers **tous les appareils** du destinataire (`device_tokens`, relation `User::deviceTokens()`).
- Appareils : `POST /api/v1/devices/token` ou son alias `POST /api/v1/device-tokens` `{ token, platform?: android|ios|web }` (connexion requise) enregistre le jeton par `updateOrCreate` sur `token` : un jeton déjà connu sous un autre compte (même téléphone) est **réattribué** à l'utilisateur connecté ; `platform` est facultative (la valeur connue est gardée si elle est omise) ; `last_used_at` est mis à jour. `DELETE /api/v1/devices/token` ou `DELETE /api/v1/device-tokens` `{ token }` le supprime (à la déconnexion), seulement s'il appartient à l'utilisateur. Routes réservées au titulaire du compte (refusées en agissant pour une organisation).
- Pas de push si FCM n'est pas configuré, si le destinataire n'a aucun appareil, s'il a désactivé la catégorie dans ses réglages, ou s'il est **l'auteur de l'action** (`actor_id` = destinataire, ex. modérateur qui approuve son propre témoignage) : on n'est jamais prévenu de sa propre action.
- Le push est envoyé en file d'attente (file `push`), après validation de la transaction, ou juste après la réponse HTTP avec `PUSH_DELIVERY=after_response`. Un échec d'envoi n'empêche jamais la création de la notification dans l'application ni ne fait échouer la requête API : `PushNotifier` capture et journalise toute exception.
- Un jeton d'appareil refusé par FCM comme **périmé** (`UNREGISTERED`, application désinstallée) ou **mal formé** (`INVALID_ARGUMENT` portant sur le jeton) est supprimé de `device_tokens`. Les autres erreurs ne suppriment rien (un mauvais projet configuré ne doit pas effacer tous les jetons).

### Préférences (`user_settings`)

| Type de notification | Réglage | Sans réglage enregistré |
|---|---|---|
| `comment`, `reply`, `mention` | `push_comments` | envoyé |
| `like` (J'aime et autres réactions) | `push_likes` | envoyé |
| `prayer` (« Je prie » sur un témoignage), `prayer_encouragement`, `prayer_request_prayed` (« Je prie » sur une requête de prière) | `push_prayers` | envoyé |
| `testimony_approved`, `testimony_rejected`, `pending_correction` | `push_approval` | envoyé |
| `new_followed_testimony`, `live_started` | `push_new_followed` | envoyé |
| `follow`, `share`, `organization_verified`, `organization_rejected` | aucun : toujours envoyé | envoyé |

Ces correspondances reprennent le filtre de l'application (`_shouldShow` dans `lib/services/fcm_service.dart`), qui ne filtre que l'affichage au premier plan ; le serveur filtre désormais aussi les push reçus application fermée.

### Titres et textes

| Type | Titre |
|---|---|
| `like` | Nouvelle réaction |
| `prayer` | Nouvelle prière (« {Nom} prie pour votre témoignage ») |
| `comment` | Nouveau commentaire |
| `reply` | Nouvelle réponse |
| `mention` | Vous avez été mentionné |
| `follow` | Nouvel abonné |
| `share` | Témoignage partagé |
| `testimony_approved` | Témoignage approuvé |
| `testimony_rejected` | Témoignage non publié |
| `new_followed_testimony` | Nouveau témoignage |
| `pending_correction` | Correction demandée |
| `organization_verified` | Organisation vérifiée |
| `organization_rejected` | Vérification refusée |
| `live_started` | En direct |
| `prayer_request_prayed` | Nouvelle prière (« {Nom} prie pour votre requête de prière ») |
| autre | `payload.title` de la notification, sinon « Témoignages » |

Texte : le champ `message` de la notification (déjà rédigé en français par le code qui la crée), sinon `payload.body`, sinon une phrase par défaut ; coupé à 200 caractères.

## Message envoyé

`POST https://fcm.googleapis.com/v1/projects/{projet}/messages:send` :

```json
{
  "message": {
    "token": "…",
    "notification": {"title": "Nouveau commentaire", "body": "Paul a commenté votre témoignage"},
    "data": {"type": "comment", "id": "…", "testimony_id": "…", "comment_id": "…", "actor_id": "…", "notification_id": "…"},
    "android": {"priority": "high", "notification": {"channel_id": "testi_notifications", "sound": "default"}},
    "apns": {"headers": {"apns-priority": "10"}, "payload": {"aps": {"sound": "default"}}}
  }
}
```

- `data` ne contient que des chaînes (exigence FCM) ; les champs vides sont omis.
- `id` : identifiant de l'objet principal à ouvrir, dans cet ordre : `prayer_request_id`, `prayer_session_id`, `event_id`, `live_id`, `daily_verse_id`, `testimony_id`, sinon l'auteur pour `follow`. Ex. « Je prie » sur une requête : `{"type": "prayer_request_prayed", "id": "<requête>", "prayer_request_id": "<requête>", …}`. Les clés détaillées restent envoyées (l'application les lit).
- `video_ready` (vidéo Bunny Stream de l'auteur prête, voir [videos-bunny-stream](videos-bunny-stream.md)) : push seul, sans notification dans l'application ; `data` vaut exactement `{"type": "video_ready", "testimony_id": "<uuid>"}`. Texte « Votre vidéo « {titre} » est prête. »
- `live_started` (direct lancé par un compte suivi, voir [lives](lives.md)) ajoute `live_id` ; texte « {Nom} est en direct : {titre} ». L'API des notifications (`GET notifications`) expose aussi `liveId`.
- L'application lit `type` et `testimony_id` : `comment`, `reply`, `mention`, `like` ouvrent le témoignage, les autres types ouvrent la liste des notifications (`_handleFcmNavigation` dans `lib/main.dart`). `comment_id`, `actor_id` et `notification_id` sont fournis pour un usage futur.
- Mention dans un commentaire du verset du jour : `daily_verse_id` (API des notifications : `dailyVerseId`), pour ouvrir la page du verset.
- `testi_notifications` est le canal Android créé par l'application (réglable par `FCM_ANDROID_CHANNEL`).
- La commande `push:test` envoie « Test ✅ » avec `type = test` (l'application ouvre alors la liste des notifications), directement par `PushNotifier` (sans file), et affiche le projet Firebase utilisé puis le résultat par appareil, avec l'erreur FCM en cas d'échec (ex. `failed  (HTTP 403 SenderId mismatch)`, `FcmClient::lastError()`). Un `SenderId mismatch` est expliqué : le jeton vient d'un autre projet Firebase que celui de la clé de compte de service. Si FCM n'est pas configuré, elle en donne la raison exacte (variable vide, fichier introuvable avec le chemin cherché, fichier invalide, `FCM_ENABLED=false`).

## Fonctionnement technique

1. **Création** : `AppNotification::create(...)` (commentaires, réactions, abonnements, modération, comptes organisation…) déclenche `App\Observers\AppNotificationObserver::created` (enregistré dans `AppServiceProvider`, après validation de la transaction).
2. **Filtre et texte** : `App\Services\PushNotifications::dispatchFor()` vérifie la configuration, l'auteur de l'action, les préférences et la présence d'un appareil, construit titre, texte et données, puis `PushNotifications::dispatch()` met en file `App\Jobs\SendPushNotificationJob` (file `services.fcm.queue`) ou, avec `PUSH_DELIVERY=after_response`, l'exécute après la réponse (`dispatch(...)->afterResponse()`, sans nouvel essai).
3. **Envoi** : la tâche (3 essais, attente 30 s puis 120 s) appelle `App\Services\PushNotifier::sendToUser($user, $titre, $texte, $donnees)`, qui lit les jetons de l'utilisateur et appelle `FcmClient::sendMulticast()` : un seul jeton = une requête ; plusieurs = requêtes **en parallèle** (`Http::pool` ; l'API v1 n'a pas d'envoi groupé, l'ancien point d'accès « batch » est fermé). `sendToUser` ne lève jamais d'exception et renvoie un résumé (`sent`, `invalid`, `retry`, `failed`, `disabled`, `error`, `results` par jeton) ; les appareils servis reçoivent `last_used_at = now()`. La tâche n'est relancée que si **aucun** appareil n'a été servi et qu'une erreur temporaire (429, 5xx, réseau, jeton OAuth2 indisponible) s'est produite : un appareil ne reçoit jamais deux fois la même notification.
4. **Authentification** : `FcmClient` signe un JWT RS256 (`openssl_sign`) avec la clé du compte de service (portée `https://www.googleapis.com/auth/firebase.messaging`, adresse `token_uri` du fichier JSON), l'échange contre un jeton d'accès OAuth2 et le garde en cache (`Cache`) jusqu'à 5 minutes avant son expiration. Un 401 de FCM vide le cache et refait un essai.

`AppNotification::insert()` (insertion groupée) ne déclenche pas l'observateur : appeler alors `PushNotifications::dispatchFor()` pour chaque ligne. Pour prévenir les abonnés d'un auteur, utiliser `App\Services\FollowerNotifications::notify($auteur, $type, $donnees, $message)`, qui le fait déjà (lots de 50, UUID générés, auteur exclu). Les clés `testimony_id`, `testimony_title`, `comment_id`, `actor_avatar` de `$donnees` remplissent les colonnes du même nom, les autres (ex. `live_id`) la colonne JSON `payload`.

### Modération (API et site web)

`Api\ModerationController` et `Web\ModerationController` créent les mêmes notifications ; les entrées du carnet privé sont ignorées (404) :

| Action | Destinataire | Type | Texte |
|---|---|---|---|
| Approbation | auteur | `testimony_approved` | Votre témoignage "…" a été approuvé |
| Approbation | chaque abonné de l'auteur (`FollowerNotifications`) | `new_followed_testimony` | « Nom de l’auteur » a partagé un nouveau témoignage : "…" |
| Rejet | auteur | `testimony_rejected` | Votre témoignage "…" a été rejeté : motif |

Motif du rejet : libellé français (`RejectionReason::label()`, ex. « Spam ») sur le site web ; l'API écrit la valeur brute avec une majuscule (`ucfirst`, ex. « InappropriateContent »), « Non conforme » sans motif.

### Fiabilité (audit du 2026-10-15)

Le serveur utilise son propre client FCM HTTP v1 (`FcmClient`, sans `kreait/firebase-php`). Points vérifiés :

| Point | État |
|---|---|
| Enregistrement / mise à jour du jeton | `POST devices/token` (ou `device-tokens`) `{ token, platform? }` : jeton réattribué au compte connecté, `last_used_at` mis à jour, **rattaché à la session de l'application** (`device_tokens.access_token_id`, nouveau) |
| Jetons invalides | Supprimés sur `UNREGISTERED` (404) et `INVALID_ARGUMENT` portant sur le jeton ; jamais sur `SENDER_ID_MISMATCH` (erreur de configuration) |
| Déconnexion | `POST auth/logout` supprime les jetons enregistrés avec cette session, et celui envoyé dans `{ device_token }` (ou `{ token }`) ; les autres appareils gardent leurs push. `auth/refresh` reporte le rattachement sur la nouvelle session. **Nouveau** : suppression du compte = suppression de ses jetons (suppression douce, pas de cascade) |
| Données | `data.type`, `data.id` (objet principal) et les identifiants détaillés (`testimony_id`, `event_id`, `live_id`, `prayer_request_id`…), tous en chaînes |
| Langue | Titre et texte dans la langue du destinataire (`users.locale`) : [langues.md](langues.md) |
| Android / iOS | Canal `FCM_ANDROID_CHANNEL` (défaut `testi_notifications`), son `default`, priorité haute ; APNs son `default`, priorité 10 |
| File d'attente | `SendPushNotificationJob`, file `push`, 3 essais ; ou `after_response` |
| Journal | Chaque appareil non servi : `FCM : notification non délivrée.` (niveau warning) avec `user_id`, début du jeton, résultat et erreur FCM, en plus des messages de `FcmClient` |

### Notification de test

- **Application** : `POST /me/push-test` (connecté, 5 par minute, réservé au titulaire du compte) envoie tout de suite, sans file, une notification `type = test` à tous mes appareils et renvoie le résultat par jeton (début du jeton seulement) :

```json
{ "success": true, "message": "Notification envoyée à 1 appareil(s) sur 2.",
  "data": { "total": 2, "sent": 1, "disabled": false, "error": null,
    "devices": [
      { "id": 12, "platform": "android", "token": "fK3x9Qe1abcd…", "result": "sent", "error": null, "last_used_at": "2026-10-15T09:12:00+01:00" },
      { "id": 9, "platform": "ios", "token": "c8Pq0zLm2xyz…", "result": "invalid_token", "error": "HTTP 404 Requested entity was not found.", "last_used_at": null } ] } }
```

  `result` : `sent`, `invalid_token` (jeton supprimé), `retry`, `failed`, `disabled` (push non configurés).
- **Back-office** : fiche d'un membre (`/admin/users/{id}`), section « Notifications », bouton « Envoyer une notification de test » visible des seuls **super-administrateurs** (`POST /admin/users/{id}/push-test`, 403 sinon) ; le résultat par appareil s'affiche sur la fiche, l'action est inscrite au journal d'audit (`user.push_test`).
- Code commun : `PushNotifier::sendTest()`.

## Configuration

`config/services.php` › `fcm` :

| Variable | Rôle |
|---|---|
| `FIREBASE_CREDENTIALS` | Chemin du fichier JSON du compte de service Firebase (absolu, ou relatif à la racine du projet). Vide = pas de push. |
| `FIREBASE_PROJECT_ID` | Facultatif : sinon `project_id` du fichier JSON. |
| `FCM_ENABLED` | Vide = activé dès que le fichier est lisible ; `false` pour couper les push sans retirer le fichier. |
| `FCM_ANDROID_CHANNEL` | Facultatif, défaut `testi_notifications` (canal créé par l'application). |
| `FCM_TIMEOUT` | Facultatif, délai HTTP en secondes (défaut 10), appliqué à la connexion **et** à la réponse (le délai de connexion de Laravel est sinon fixé à 10 s). 30 conseillé sur un réseau lent : à 10 s, l'obtention du jeton OAuth2 échouait depuis le poste de développement (`cURL error 28`). |
| `PUSH_DELIVERY` | `queue` (défaut) : file d'attente ; `after_response` : envoi juste après la réponse HTTP, sans file ni nouvel essai (repli sans cron). |
| `PUSH_QUEUE` | Nom de la file des push (défaut `push`), à inclure dans `queue:work --queue=push,default`. |

## Déploiement

1. Projet Firebase : **`as-krea`** (diminutif d'ARISE & SHINE Krea ; dans les textes affichés, écrire la marque en entier). Console Firebase › `as-krea` › Paramètres du projet › **Comptes de service** › « Générer une nouvelle clé privée » (clé actuelle : `storage/app/as-krea-firebase-adminsdk-fbsvc-13c56c0cb4.json`). L'application mobile doit utiliser le même projet (`google-services.json`, `firebase_options.dart`) ; un jeton obtenu avec un autre projet est refusé (`SenderId mismatch`, résultat `failed`, jeton conservé).
2. Déposer le fichier JSON sur le serveur **hors du dossier `public/`** et hors de git (ex. `storage/app/firebase-credentials.json`, chemin de `.env.example`, exclu par `.gitignore` ; droits en lecture pour l'utilisateur PHP seulement).
3. `.env` : `FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json`, `PUSH_DELIVERY=queue`, `PUSH_QUEUE=push`, puis `php artisan migrate --force` et `php artisan config:cache`.
4. Traitement de la file. Serveur avec processus permanent : `php artisan queue:work --queue=push,default --timeout=1900` (Supervisor). **Mutualisé LWS** (pas de processus permanent) : tâche cron chaque minute
   ```
   * * * * * cd /chemin/du/projet && php artisan queue:work --queue=push,default --stop-when-empty --max-time=50 --timeout=1900 >> /dev/null 2>&1
   ```
   Le worker vide la file puis s'arrête avant la minute suivante ; délai d'un push : moins d'une minute. La file `push` passe avant `default` (conversions vidéo). Sans cron à la minute : `PUSH_DELIVERY=after_response` (envoi après la réponse HTTP, sans nouvel essai). Avec `QUEUE_CONNECTION=sync`, les push partent pendant la requête (plus lent).
5. Vérifier : `php artisan push:test adresse@exemple.com` (ou l'identifiant de l'utilisateur) après s'être connecté une fois dans l'application sur le téléphone. La commande affiche le résultat par appareil : `sent`, `invalid_token` (jeton supprimé), `retry`, `failed`.

Migration `2026_10_10_000001_add_last_used_at_to_device_tokens_table` (colonne `last_used_at`, `platform` facultative). Aucune recompilation des assets.

2026-10-15 : migration `2026_10_15_200001_add_locale_conversion_and_push_device_fields` (`device_tokens.access_token_id`). Les jetons enregistrés avant n'ont pas de session : ils sont supprimés à la déconnexion seulement si l'application envoie `device_token`.

## Limites connues

- `POST devices/token` accepte `platform = web` (Flutter web) : le jeton est enregistré et reçoit les push comme les autres ; l'affichage dépend du service worker de l'application web.
- `prayer_request_prayed` : l'application ouvre la requête de prière (`prayer_request_id`), depuis la notification push comme depuis la liste des notifications ; affichée selon la préférence « prières ».
- L'application mobile n'ouvre pas encore le direct depuis un push `live_started` : elle ouvre la liste des notifications (cas par défaut de `_handleFcmNavigation`).

## Tests

`tests/Feature/PushNotificationTest.php` (18 tests) : compte de service factice avec une clé RSA générée à la volée (`storage/framework/testing/fcm`), requêtes FCM et OAuth2 simulées (`Http::fake`) ; vérifie le JWT signé, le corps du message, le cache du jeton d'accès, la suppression des jetons `UNREGISTERED` / mal formés, l'absence d'envoi sans configuration, la mise en file par l'observateur, les préférences, les abonnés prévenus à l'approbation, la commande `push:test` (dont l'explication d'un `SenderId mismatch`), l'envoi parallèle à plusieurs appareils (`last_used_at`, jeton périmé supprimé), l'absence d'exception quand OAuth2 échoue, l'absence de push pour sa propre action, la file `push`, le mode `after_response` et les routes `device-tokens` (réattribution, plateforme facultative). `tests/Feature/LiveStartNotificationTest.php` (4 tests) couvre `live_started`.

`tests/Feature/PushReliabilityTest.php` (6 tests) : rattachement du jeton à la session, déconnexion (jetons de cet appareil seulement), `auth/refresh`, suppression du compte, `POST /me/push-test` (résultat par jeton, `UNREGISTERED` et `INVALID_ARGUMENT` supprimés, échecs journalisés, canal, son, langue), sans appareil ou sans configuration, bouton réservé aux super-administrateurs.

`tests/Feature/WebModerationNotificationTest.php` (4 tests) : approbation sur le site web (auteur prévenu, abonné prévenu une seule fois, push en file via `Queue::fake`), rejet (auteur prévenu avec le motif, abonnés non prévenus), carnet privé ignoré, et même résultat par l'API.

## Fichiers

- `app/Services/FcmClient.php` — authentification OAuth2 et envoi HTTP v1
- `app/Services/PushNotifications.php` — préférences, titres, textes, données
- `app/Services/FollowerNotifications.php` — notifications (et push) aux abonnés d'un auteur
- `app/Services/PushNotifier.php` — `sendToUser()` : envoi immédiat à tous les appareils, résumé, jamais d'exception
- `app/Jobs/SendPushNotificationJob.php` — tâche de file (file `push`) qui appelle `PushNotifier`
- `app/Http/Controllers/Api/DeviceTokenController.php` — `devices/token` et `device-tokens`
- `app/Observers/AppNotificationObserver.php` — branchement sur la création des notifications
- `app/Console/Commands/SendTestPush.php` — commande `push:test {user}` (e-mail ou identifiant ; titre par défaut « Test ✅ », envoi immédiat sans file)
- `config/services.php` (`fcm`), `.env.example`
