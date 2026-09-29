# Notifications push (FCM)

## But

Prévenir l'utilisateur sur son téléphone, même application fermée, à chaque notification de l'application : commentaire, réponse, réaction, nouvel abonné, témoignage approuvé ou refusé, nouveau témoignage d'un compte suivi, direct lancé par un compte suivi, organisation vérifiée ou refusée. Le serveur envoie les push par **Firebase Cloud Messaging, API HTTP v1**, sans dépendance Composer supplémentaire.

Sans configuration (`FIREBASE_CREDENTIALS` vide), rien ne change : les notifications restent visibles dans l'application et aucun push n'est envoyé.

## Règles

- **Toute** notification enregistrée dans `app_notifications` part aussi en push vers **tous les appareils** du destinataire (`device_tokens`, alimenté par `POST /api/v1/devices/token`).
- Pas de push si FCM n'est pas configuré, si le destinataire n'a aucun appareil, ou s'il a désactivé la catégorie dans ses réglages.
- Le push est envoyé en file d'attente, après validation de la transaction. Un échec d'envoi n'empêche jamais la création de la notification dans l'application.
- Un jeton d'appareil refusé par FCM comme **périmé** (`UNREGISTERED`, application désinstallée) ou **mal formé** (`INVALID_ARGUMENT` portant sur le jeton) est supprimé de `device_tokens`. Les autres erreurs ne suppriment rien (un mauvais projet configuré ne doit pas effacer tous les jetons).

### Préférences (`user_settings`)

| Type de notification | Réglage | Sans réglage enregistré |
|---|---|---|
| `comment`, `reply`, `mention` | `push_comments` | envoyé |
| `like` (toutes les réactions) | `push_likes` | envoyé |
| `prayer` (réservé, aucun type ne l'utilise encore) | `push_prayers` | envoyé |
| `testimony_approved`, `testimony_rejected`, `pending_correction` | `push_approval` | envoyé |
| `new_followed_testimony`, `live_started` | `push_new_followed` | envoyé |
| `follow`, `share`, `organization_verified`, `organization_rejected` | aucun : toujours envoyé | envoyé |

Ces correspondances reprennent le filtre de l'application (`_shouldShow` dans `lib/services/fcm_service.dart`), qui ne filtre que l'affichage au premier plan ; le serveur filtre désormais aussi les push reçus application fermée.

### Titres et textes

| Type | Titre |
|---|---|
| `like` | Nouvelle réaction |
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
| autre | `payload.title` de la notification, sinon « Témoignages » |

Texte : le champ `message` de la notification (déjà rédigé en français par le code qui la crée), sinon `payload.body`, sinon une phrase par défaut ; coupé à 200 caractères.

## Message envoyé

`POST https://fcm.googleapis.com/v1/projects/{projet}/messages:send` :

```json
{
  "message": {
    "token": "…",
    "notification": {"title": "Nouveau commentaire", "body": "Paul a commenté votre témoignage"},
    "data": {"type": "comment", "testimony_id": "…", "comment_id": "…", "actor_id": "…", "notification_id": "…"},
    "android": {"priority": "high", "notification": {"channel_id": "testi_notifications", "sound": "default"}},
    "apns": {"headers": {"apns-priority": "10"}, "payload": {"aps": {"sound": "default"}}}
  }
}
```

- `data` ne contient que des chaînes (exigence FCM) ; les champs vides sont omis.
- `live_started` (direct lancé par un compte suivi, voir [lives](lives.md)) ajoute `live_id` ; texte « {Nom} est en direct : {titre} ». L'API des notifications (`GET notifications`) expose aussi `liveId`.
- L'application lit `type` et `testimony_id` : `comment`, `reply`, `mention`, `like` ouvrent le témoignage, les autres types ouvrent la liste des notifications (`_handleFcmNavigation` dans `lib/main.dart`). `comment_id`, `actor_id` et `notification_id` sont fournis pour un usage futur.
- `testi_notifications` est le canal Android créé par l'application (réglable par `FCM_ANDROID_CHANNEL`).
- La commande `push:test` envoie `type = test` (l'application ouvre alors la liste des notifications).

## Fonctionnement technique

1. **Création** : `AppNotification::create(...)` (commentaires, réactions, abonnements, modération, comptes organisation…) déclenche `App\Observers\AppNotificationObserver::created` (enregistré dans `AppServiceProvider`, après validation de la transaction).
2. **Filtre et texte** : `App\Services\PushNotifications::dispatchFor()` vérifie la configuration, les préférences et la présence d'un appareil, construit titre, texte et données, puis met en file `App\Jobs\SendPushNotificationJob`.
3. **Envoi** : la tâche (3 essais, attente 30 s puis 120 s) appelle `App\Services\FcmClient::send()` pour chaque jeton de l'utilisateur. Elle n'est relancée que si **aucun** appareil n'a été servi et qu'une erreur temporaire (429, 5xx, jeton OAuth2 indisponible) s'est produite : un appareil ne reçoit jamais deux fois la même notification.
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

## Configuration

`config/services.php` › `fcm` :

| Variable | Rôle |
|---|---|
| `FIREBASE_CREDENTIALS` | Chemin du fichier JSON du compte de service Firebase (absolu, ou relatif à la racine du projet). Vide = pas de push. |
| `FIREBASE_PROJECT_ID` | Facultatif : sinon `project_id` du fichier JSON. |
| `FCM_ENABLED` | Vide = activé dès que le fichier est lisible ; `false` pour couper les push sans retirer le fichier. |
| `FCM_ANDROID_CHANNEL` | Facultatif, défaut `testi_notifications` (canal créé par l'application). |
| `FCM_TIMEOUT` | Facultatif, délai HTTP en secondes (défaut 10). |

## Déploiement

1. Console Firebase du projet de l'application › Paramètres du projet › **Comptes de service** › « Générer une nouvelle clé privée ».
2. Déposer le fichier JSON sur le serveur **hors du dossier `public/`** et hors de git (ex. `storage/app/private/firebase.json`, droits en lecture pour l'utilisateur PHP seulement).
3. `.env` : `FIREBASE_CREDENTIALS=/chemin/absolu/firebase.json`, puis `php artisan config:cache` et `php artisan queue:restart`.
4. Un processus `queue:work` doit tourner (déjà nécessaire pour les [qualités des médias](qualites-media.md)). Avec `QUEUE_CONNECTION=sync`, les push partent pendant la requête (plus lent).
5. Vérifier : `php artisan push:test adresse@exemple.com` (ou l'identifiant de l'utilisateur) après s'être connecté une fois dans l'application sur le téléphone. La commande affiche le résultat par appareil : `sent`, `invalid_token` (jeton supprimé), `retry`, `failed`.

Aucune migration, aucune recompilation des assets.

## Limites connues

- L'application web (Flutter web) envoie `platform = web`, refusé par `POST devices/token` (`android` ou `ios` seulement) : pas de push sur le web.
- L'application mobile n'ouvre pas encore le direct depuis un push `live_started` : elle ouvre la liste des notifications (cas par défaut de `_handleFcmNavigation`).

## Tests

`tests/Feature/PushNotificationTest.php` (11 tests) : compte de service factice avec une clé RSA générée à la volée (`storage/framework/testing/fcm`), requêtes FCM et OAuth2 simulées (`Http::fake`) ; vérifie le JWT signé, le corps du message, le cache du jeton d'accès, la suppression des jetons `UNREGISTERED` / mal formés, l'absence d'envoi sans configuration, la mise en file par l'observateur, les préférences, les abonnés prévenus à l'approbation et la commande `push:test`. `tests/Feature/LiveStartNotificationTest.php` (4 tests) couvre `live_started`.

`tests/Feature/WebModerationNotificationTest.php` (4 tests) : approbation sur le site web (auteur prévenu, abonné prévenu une seule fois, push en file via `Queue::fake`), rejet (auteur prévenu avec le motif, abonnés non prévenus), carnet privé ignoré, et même résultat par l'API.

## Fichiers

- `app/Services/FcmClient.php` — authentification OAuth2 et envoi HTTP v1
- `app/Services/PushNotifications.php` — préférences, titres, textes, données
- `app/Services/FollowerNotifications.php` — notifications (et push) aux abonnés d'un auteur
- `app/Jobs/SendPushNotificationJob.php` — envoi à tous les appareils d'un utilisateur
- `app/Observers/AppNotificationObserver.php` — branchement sur la création des notifications
- `app/Console/Commands/SendTestPush.php` — commande `push:test {user}`
- `config/services.php` (`fcm`), `.env.example`
