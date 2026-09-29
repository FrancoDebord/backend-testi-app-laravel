# Intervenants dans un direct (témoigner à l'antenne)

## But

Pendant une grande session de témoignages en direct, des **personnes connectées** prennent la parole à tour de rôle : **micro** activé, **caméra** si elles le souhaitent. Elles apparaissent **dans un médaillon, dans un coin de la vidéo** du diffuseur. **Une seule personne à la fois** : les autres attendent dans une **file**.

S'appuie sur les [témoignages en direct](lives.md) (LiveKit).

## Utilisation

### Spectateur connecté (page du direct)

1. Bloc **« Témoigner en direct »** : sujet facultatif (« De quoi voulez-vous témoigner ? », lu seulement par le diffuseur et les modérateurs), puis **Demander à intervenir**.
2. « Vous êtes n°3 dans la file » : garder la page ouverte. **Annuler ma demande** à tout moment.
3. À son tour, une fenêtre s'ouvre : **« C'est votre tour : vous êtes invité à l'antenne »**, avec un compte à rebours (60 s), un interrupteur **Activer ma caméra** (désactivé par défaut, aperçu immédiat) et les boutons **Refuser** / **Rejoindre l'antenne**. Si le direct est enregistré, la fenêtre le précise.
4. À l'antenne : barre **« Vous êtes à l'antenne »** avec **Micro** et **Caméra** (couper / rétablir, activer la caméra en cours de route) et **Terminer mon intervention**. Son propre médaillon (« Vous ») s'affiche dans le coin de la vidéo.
5. À la fin (volontaire, décidée par le diffuseur, exclusion ou coupure réseau) : micro et caméra sont libérés, message explicatif, et il peut refaire une demande.

Sans compte : « Connectez-vous pour demander à intervenir ».

### Diffuseur (studio) et modérateurs (page du direct)

Bloc **« Intervenants »** :

- interrupteur **Accepter les demandes** (ouvert par défaut). Le fermer n'enlève ni la file ni l'intervenant en cours ;
- **intervenant en cours** : nom, état (« Invité · en attente de sa réponse » ou « À l'antenne · caméra / micro seulement »), sujet annoncé, puis **Annuler l'invitation** ou **Terminer** (confirmation) ;
- **file** dans l'ordre d'arrivée : position, nom, heure, sujet, **Inviter** (désactivé tant que quelqu'un est invité ou à l'antenne) et **✕** (retirer de la file) ;
- message éphémère à chaque nouvelle demande.

Le diffuseur **entend** l'intervenant et le voit dans le médaillon ; l'écho est traité par l'annulation d'écho du navigateur (casque conseillé).

### Tous les spectateurs

Le médaillon (vidéo ou initiales, nom, micro coupé ou non) apparaît dans le coin inférieur droit de la vidéo du diffuseur, tant que l'intervenant est à l'antenne.

## Règles

| Règle | Détail |
|---|---|
| **Qui peut demander** | Toute personne connectée, compte actif, non exclue du direct. Pas le diffuseur ni les modérateurs (ils parlent déjà) |
| **Quand** | Direct **à l'antenne** et demandes ouvertes |
| **Une demande à la fois** | Une seule demande en cours par personne (en attente, invitée ou à l'antenne). 3 demandes au plus par 5 minutes |
| **Une personne à l'antenne** | Une seule personne **invitée ou à l'antenne** à la fois, garanti côté serveur (verrou sur le direct : deux modérateurs ne peuvent pas inviter en même temps) |
| **Invitation** | Expire après **60 s** sans réponse (`livekit.stage_invite_timeout`) : la place se libère |
| **Sujet** | 200 caractères au plus ; jamais envoyé à l'ensemble des spectateurs |
| **Qui gère** | Le diffuseur, les modérateurs et les administrateurs |
| **Exclusion** | Exclure quelqu'un du direct le retire de la file et de l'antenne, et l'empêche de redemander |
| **Fin du direct** | Toutes les demandes sont closes |
| **Coupure** | L'intervenant qui ferme la page ou perd la connexion est retiré (webhook `participant_left`, vérification de secours toutes les 5 minutes par `lives:cleanup`) |
| **Enregistrement** | L'intervenant fait partie de l'enregistrement (mise en page « speaker » de LiveKit : la personne qui parle en grand, l'autre en vignette) |

## Fonctionnement technique

### Aucun jeton « intervenant »

Le spectateur reste sur **sa connexion de spectateur** (jeton en lecture seule, invisible). Quand il accepte, Laravel vérifie l'invitation et l'identité de la connexion, puis ouvre ses droits par l'API serveur LiveKit :

```
UpdateParticipant(room, identity, permission = {
  can_publish: true, can_publish_sources: [CAMERA, MICROPHONE],
  can_subscribe: true, can_publish_data: false, hidden: false })
```

À la fin, Laravel remet les droits de spectateur (`can_publish: false`, `hidden: true`) : LiveKit **dépublie immédiatement** micro et caméra, même si l'application ne coopère pas. Aucun jeton de diffusion ne circule, donc aucun jeton ne peut être réutilisé. Pas de partage d'écran ni d'envoi de données.

L'identité fournie doit commencer par `user-{id de la personne}-` (format des jetons spectateurs), sinon **422**.

### États (`live_speakers.status`)

```
waiting ──invite──▶ invited ──accept──▶ on_stage ──(fin)──▶ done
   │                  │ 60 s ▶ expired                 ended_reason : left · removed · disconnected · banned · live_ended
   ├─ cancelled (retrait) / declined (refus du diffuseur)
```

### Données

| Table / colonne | Contenu |
|---|---|
| `live_speakers` | `live_session_id`, `user_id`, `status`, `message` (sujet), `identity` (participant LiveKit à l'antenne), `camera`, `invited_at`, `started_at`, `ended_at`, `ended_reason`, `handled_by` |
| `live_sessions.speakers_enabled` | Demandes ouvertes (défaut : oui) |

### Code

| Élément | Fichier |
|---|---|
| Règles | `app/Services/LiveStage.php` (état, demande, retrait, acceptation, invitation, refus, fin, exclusion, fin du direct, webhook, rattrapage) |
| Modèle | `app/Models/LiveSpeaker.php` (`toPayload()` : sujet seulement pour le personnel et l'intéressé) |
| Appel LiveKit | `LiveKitClient::updateParticipant()` |
| Actions JSON | `app/Http/Controllers/Api/LiveController.php` (`stage`, `requestStage`, `withdrawStage`, `acceptStage`, `inviteSpeaker`, `declineSpeaker`, `removeSpeaker`, `stageSettings`), partagé web / mobile |
| Liaisons | `LiveService` (fin du direct, exclusion, statistiques, `cleanup`), `LiveKitWebhookController` (`participant_left`) |
| Vues | `resources/views/lives/partials/stage.blade.php` (blocs, fenêtre d'invitation), `stage-pip.blade.php` (médaillon), `show.blade.php` et `studio.blade.php` (barre de l'intervenant) |
| JavaScript | `resources/js/live.js`, `initStage()` : état, file, invitation, publication, médaillon. La vidéo du diffuseur (`host-…`) va dans le lecteur principal, celle de l'intervenant dans le médaillon |

## API mobile

Préfixe `/api/v1` ; 🔒 = connexion Sanctum. Chaque action renvoie l'**état** ci-dessous.

| Méthode | Route | Rôle |
|---|---|---|
| GET | `lives/{id}/stage` | État (voir ci-dessous) |
| POST 🔒 | `lives/{id}/stage/requests` | `{ message? }` : demander à intervenir → 201 |
| DELETE 🔒 | `lives/{id}/stage/requests/mine` | Retirer sa demande, refuser l'invitation, ou quitter l'antenne |
| POST 🔒 | `lives/{id}/stage/accept` | `{ identity, camera? }` : `identity` = celle renvoyée par `viewer-token` (connexion en cours) |
| POST 🔒 | `lives/{id}/stage/{speakerId}/invite` | Inviter (diffuseur/modération) |
| POST 🔒 | `lives/{id}/stage/{speakerId}/decline` | Retirer de la file ou annuler l'invitation (diffuseur/modération) |
| POST 🔒 | `lives/{id}/stage/{speakerId}/remove` | Terminer l'intervention (diffuseur/modération) |
| POST 🔒 | `lives/{id}/stage/settings` | `{ enabled }` : ouvrir / fermer les demandes (diffuseur/modération) |

**État** :

```json
{
  "enabled": true,
  "current": { "id": "…", "status": "on_stage", "camera": true, "startedAt": "…", "user": { "id", "displayName", "initials", "avatarUrl" } },
  "queueCount": 3,
  "queue": [ { "id", "status": "waiting", "position": 1, "message": "Guérison", "createdAt", "user": { … } } ],
  "mine": { "id", "status": "waiting", "position": 2, "message": "…" },
  "canRequest": false,
  "refusal": "Vous avez déjà une demande en cours.",
  "inviteTimeout": 60
}
```

`queue` vaut `null` sauf pour le diffuseur et les modérateurs. `mine` : demande de la personne connectée (`expiresAt` quand elle est invitée). `current.message` n'est donné qu'au personnel et à l'intéressé. `GET lives/{id}/stats` contient aussi `stage: { enabled, current, queueCount }`, et le direct expose `speakersEnabled`.

Codes : **403** (droits, compte, demandes fermées, exclusion) · **409** (déjà une demande, place occupée, plus d'invitation, direct pas à l'antenne) · **410** (invitation expirée) · **422** (sujet trop long, identité qui n'est pas celle de la personne) · **429** (trop de demandes).

### Message temps réel

`{ type: "stage", event, speaker?, reason? }`, `event` ∈ `requested`, `invited`, `on_stage`, `ended` (avec `reason`), `cancelled`, `declined`, `expired`, `settings`. **Recharger l'état** (`GET …/stage`) à chaque message ; si `event = invited` et `speaker.user.id` est la personne connectée, ouvrir l'invitation.

### Application mobile (Flutter, `testi_app`) — faite le 2026-09-29

| Élément | Fichier de l'application |
|---|---|
| Modèles `LiveSpeaker`, `LiveStageState`, message `LiveStageEvent` | `lib/features/live/models/live_models.dart` |
| Appels (`stage`, `requestStage`, `withdrawStage`, `acceptStage`, `speakerAction`, `stageSettings`) | `lib/features/live/data/live_repository.dart`, routes dans `lib/core/app_constants.dart` |
| Logique : vidéo du diffuseur (`host-…`) seule dans le lecteur principal, intervenant dans `guestVideo` ; demande, acceptation (attente de `permissions.canPublish`, puis micro et caméra), fin (droits retirés ou message `ended` pour soi : micro et caméra coupés) ; file et actions du personnel | `lib/features/live/controllers/live_room_controller.dart` |
| Médaillon, barre « À l'antenne », bouton « Témoigner » / « Intervenants » (nombre en attente), feuille de demande ou de gestion de la file, fenêtre « C'est votre tour » (compte à rebours, caméra facultative) | `lib/features/live/widgets/live_stage_widgets.dart`, `live_viewer_screen.dart`, `live_studio_screen.dart` |
| Tests | `test/live_room_controller_test.dart` |

**Correction** au passage : l'écran spectateur affichait en grand la **dernière vidéo reçue**, quel que soit le participant. Avec un intervenant, les spectateurs mobiles auraient vu l'intervenant en plein écran, puis un écran noir à son départ.

## Déploiement

- `php artisan migrate --force` (migration `2026_09_29_100001_create_live_speakers_table`) ;
- recompiler et copier `public/build/` ;
- webhook LiveKit déjà configuré pour les directs : il transmet aussi `participant_left` (aucun réglage à ajouter). Sans webhook, `lives:cleanup` rattrape les coupures toutes les 5 minutes ;
- `php artisan view:cache`.

## Tests

`tests/Feature/LiveStageTest.php` (14 tests) : demande unique, direct et demandes ouverts, file réservée au personnel, retrait, une seule personne à la fois, droits LiveKit ouverts sur la bonne connexion (identité vérifiée), expiration, refus, fin volontaire ou décidée (droits retirés), exclusion, fin du direct, coupure par webhook, rattrapage par `lives:cleanup`, pages web.

Vérifié de bout en bout le 2026-09-29 avec **LiveKit Cloud** et deux navigateurs Chrome (caméra et micro simulés) : demande → file → invitation → caméra → antenne (le diffuseur voit la vidéo de l'intervenant et reçoit son son) → fin par le diffuseur (médaillons retirés, micro et caméra libérés, nouvelle demande possible). Aucune erreur JavaScript ; aucun défilement horizontal à 390, 768 et 1440 px.

## Limites connues

- **Application mobile** : faite (voir ci-dessus), mais **pas encore essayée sur un téléphone** avec un vrai direct : vérifiée par l'analyse du code et les tests automatiques seulement.
- La **rediffusion** utilise la mise en page « speaker » de LiveKit : le médaillon n'y est pas reproduit à l'identique (personne qui parle en grand). Une mise en page d'enregistrement sur mesure est possible plus tard.
- Pas de coupure du micro de l'intervenant par le diffuseur : il met fin à l'intervention (**Terminer**). L'intervenant peut couper son micro lui-même.
- Le contrôle de bout en bout a porté sur le diffuseur et l'intervenant ; le médaillon chez un troisième spectateur utilise le même code que chez le diffuseur.
