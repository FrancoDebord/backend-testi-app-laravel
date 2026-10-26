# Requêtes de prière

## But

Chacun peut **confier un besoin de prière** à la communauté (« Priez les uns pour les autres », Jacques 5:16), et les autres :

- **prient** pour lui (« Je prie », compteur) ;
- laissent un **message d'encouragement** (verset facultatif) ;
- et, quand Dieu répond, l'auteur marque la requête **exaucée** et peut **témoigner**.

Disponible sur le **site** (`/priere/requetes`, menu latéral « Requêtes de prière ») et dans l'**application** (menu latéral). Une requête peut être **rattachée à un événement** : elle apparaît alors sur la page de l'événement (bloc « Prière »). Elle peut aussi être **confiée depuis le témoignage de quelqu'un** (bouton « Confier un sujet de prière » sur la page du témoignage) : par exemple une personne qui vit la même épreuve que celle racontée demande la prière, et sa requête renvoie au témoignage.

## Règles

| Règle | Détail |
|---|---|
| **Publication** | **Directe**, sans relecture préalable (contrairement aux témoignages). La modération intervient après coup, sur signalement. Tout compte **actif** connecté peut publier (20 par minute au plus). |
| **Texte** | 10 à 2 000 caractères. |
| **Visibilité** | `public` (tout le monde, même sans compte) · `followers` (l'auteur, ses abonnés, la modération) · `private` (l'auteur **seul**, modération comprise : un carnet de prière personnel). Toute autre personne reçoit **404**. |
| **Anonyme** | `is_anonymous` : le nom et la photo sont **masqués pour les autres** (`author: null`, « Anonyme »). Ils restent visibles de l'**auteur** et de la **modération**. Le fil `following` n'affiche jamais de requête anonyme (la lister « parmi les comptes que je suis » trahirait l'anonymat). |
| **Je prie** | Une ligne par personne (`prayer_request_prayers`, nombre de prières dans `prayer_times`) ; compteur `prayer_count` = nombre de personnes. Prier encore (`again`) et retirer la dernière prière (`last`) : [prier-plusieurs-fois.md](prier-plusieurs-fois.md). L'auteur est **prévenu une seule fois par personne** (notification `prayer_request_prayed`, « {Nom} prie pour votre requête de prière », push selon la préférence « prières ») : retirer puis remettre « Je prie » ne renvoie rien, et prier pour sa propre requête ne notifie pas. |
| **Temps de prière** | Ajouter sa prière exige un temps de prière de 5 minutes commencé par `POST prayer/requests/{id}/pray/start` (sinon **422**). Retirer sa prière ou renvoyer `{ prayed: true }` alors qu'on prie déjà n'est jamais bloqué. Voir [Temps de prière](#temps-de-prière) et [temps-de-priere.md](temps-de-priere.md). |
| **Encouragements** | Messages de 2 à 1 000 caractères + référence biblique facultative. Possibles si la requête est visible, non retirée, et pas privée (sauf pour l'auteur). L'auteur de la requête est **prévenu** (notification `prayer_encouragement`, push selon la préférence « prières »). Suppression : l'auteur du message, l'auteur de la requête, la modération. |
| **Exaucée** | L'auteur seul : `status = answered`, `answered_at`, note facultative (`answer_note`). Remise « en attente » possible tant qu'aucun témoignage n'est rattaché. **Témoigner** ouvre Publier ; le témoignage peut ensuite être rattaché par `PUT /prayer/requests/{id}` `{ testimony_id }` (témoignage de l'auteur ; la requête passe exaucée). |
| **Événement** | `event_id` : événement **publié** et visible (ou géré par l'auteur). |
| **Depuis un témoignage** | `source_testimony_id`, à la création seulement : témoignage **publié** (approuvé, public, vidéo prête), de n'importe quel auteur, y compris soi (sinon **422** « Témoignage introuvable. »). Distinct de `testimony_id` (témoignage de l'exaucement, écrit par l'auteur de la requête). Le lien « Après le témoignage « … » » n'est montré que tant que le témoignage reste publié : retiré du public, mis à la corbeille ou supprimé, il disparaît et la requête reste. L'auteur du témoignage n'est pas prévenu. |
| **Signalement** | Toute personne connectée sauf l'auteur, une fois par requête (raisons : contenu inapproprié, propos haineux, spam, autre ; précision facultative). Au **3e signalement non traité**, la requête est **retirée automatiquement** en attendant la modération. |
| **Modération** | Modérateurs et administrateurs : **retirer** (motif montré à l'auteur), **rétablir** / **garder** (classe les signalements), **supprimer**. Une requête retirée n'est plus visible que de son auteur (avec le motif) et de la modération. |
| **Suppression** | L'auteur ou la modération (suppression douce). |

## Données

| Table | Contenu |
|---|---|
| `prayer_requests` | `user_id`, `event_id`, `daily_verse_id`, `source_testimony_id`, `body`, `visibility`, `is_anonymous`, `status` (`open` / `answered`), `answered_at`, `answer_note`, `testimony_id`, `prayer_count`, `message_count`, `report_count`, `hidden_at`, `hidden_by`, `hidden_reason` ; suppression douce |
| `prayer_request_prayers` | `prayer_request_id`, `user_id` (unique) |
| `prayer_request_messages` | `prayer_request_id`, `user_id`, `body`, `bible_reference` ; suppression douce |
| `prayer_request_reports` | `prayer_request_id`, `reporter_id` (unique), `reason`, `comment`, `reviewed_at` |

Les signalements des témoignages (`reports`) sont propres aux témoignages : les requêtes ont leur table, et leur page de modération (`/priere/moderation`).

## API mobile (`/api/v1`)

| Méthode | Route | Rôle |
|---|---|---|
| GET | `prayer/requests` | Liste paginée (20 par défaut, `limit` ≤ 50). `scope` = `all` (défaut : tout ce que je peux voir) · `feed` (publiques récentes, pour « Pour vous ») · `following` (comptes que je suis, publiques + abonnés, **sans anonymes**, pour « Mon fil ») · `mine` · `event` (avec `event_id`). `status` = `open` / `answered`. `meta` : `currentPage`, `lastPage`, `total`, `perPage`, `scope`, `canCreate`. Public (selon la visibilité). |
| GET | `prayer/requests/{id}` | Détail (404 si non visible) |
| POST | `prayer/requests` | `{ body, visibility?, is_anonymous?, event_id?, source_testimony_id? }` (connecté) |
| PUT · DELETE | `prayer/requests/{id}` | Modifier (`body`, `visibility`, `is_anonymous`, `event_id`, `testimony_id`) · supprimer |
| POST | `prayer/requests/{id}/pray/start` | Commence (ou recommence) le temps de prière : `{ startedAt, requiredSeconds, confirmableAt }` |
| POST | `prayer/requests/{id}/pray` | « Je prie » : bascule, ou `{ prayed: true/false }` ; `{ again: true }` prier encore ; `{ prayed: false, last: true }` retirer la dernière prière ; renvoie la requête (`myPrayerTimes`). Ajout : temps de prière écoulé exigé (422 sinon) |
| POST · DELETE | `prayer/requests/{id}/answered` | Exaucée `{ note? }` · de nouveau en attente |
| GET · POST | `prayer/requests/{id}/messages` | Encouragements (du plus ancien au plus récent) · `{ message, bible_reference? }` |
| DELETE | `prayer/requests/{id}/messages/{messageId}` | Supprimer un encouragement |
| POST | `prayer/requests/{id}/report` | `{ reason: inappropriate_content\|hate_speech\|spam\|other, comment? }` |
| GET | `prayer/moderation?filter=reported\|hidden` | File de modération (modérateurs), avec `reports` |
| POST | `prayer/requests/{id}/hide` · `/restore` | Retirer `{ reason? }` · rétablir (modérateurs) |

`PrayerRequestResource` (autonome, utilisable telle quelle dans les fils) :

```json
{
  "type": "prayer_request", "id": "uuid", "body": "…",
  "visibility": "public|followers|private", "visibilityLabel": "Tout le monde", "isAnonymous": false,
  "author": { "id": "uuid", "displayName": "…", "name": "…", "initials": "MA", "avatarUrl": null, "avatar": null, "isVerified": false } | null,
  "status": "open|answered", "isAnswered": false, "answeredAt": null, "answerNote": null, "testimonyId": null,
  "prayerCount": 12, "messageCount": 3, "hasPrayed": false,
  "event": { "id": "uuid", "title": "…" } | null,
  "sourceTestimony": { "id": "uuid", "title": "…" } | null,
  "isMine": false, "isHidden": false, "hiddenReason": null, "reportCount": null,
  "canEdit": false, "canDelete": false, "canReport": true, "canModerate": false, "canMessage": true,
  "webUrl": "https://…/priere/requetes/{id}", "createdAt": "ISO 8601", "updatedAt": "ISO 8601"
}
```

`author` vaut `null` pour une requête anonyme (sauf pour son auteur et la modération). Message d'encouragement : `{ id, body, bibleReference, author { id, displayName, initials, avatarUrl, isVerified }, isMine, canDelete, createdAt }`.

## Site

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/priere/requetes` `?onglet=miennes&statut=exaucees` | Toutes / mes requêtes, filtre « Exaucées » ; verset Jacques 5:16 |
| GET · POST | `/priere/requetes/nouvelle` `?evenement=` `?temoignage=` · `/priere/requetes` | Confier une requête (visibilité, anonyme ; rattachée à l'événement si `?evenement=`, au témoignage publié si `?temoignage=`, rappelé en haut du formulaire ; « Annuler » ramène au témoignage) |
| GET · DELETE | `/priere/requetes/{id}` | Détail (« Je prie », encouragements, exaucement, signalement) · supprimer |
| POST | `/priere/requetes/{id}/je-prie/commencer` | Temps de prière (JSON, nom `prayer.requests.pray.start`) |
| POST | `/priere/requetes/{id}/je-prie` | Ajoute une prière (après l'écran « Temps de prière ») ; `last=1` retire la dernière |
| POST · DELETE | `/priere/requetes/{id}/exaucee` | Exaucée `{ note? }` · remettre en attente |
| POST · DELETE | `/priere/requetes/{id}/messages` · `/messages/{message}` | Encourager · supprimer un message |
| POST | `/priere/requetes/{id}/signaler` | Signaler |
| GET | `/priere/moderation` `?filtre=retirees` | Modération : signalées / retirées (menu Modération → « Requêtes de prière signalées ») |
| POST | `/priere/moderation/{id}/retirer` · `/retablir` | Retirer (motif) · rétablir / garder |

**Partiel réutilisable** : `@include('prayer.partials.feed-card', ['request' => $prayerRequest])` (carte avec « Je prie » et encouragements, pour les fils ; le badge « Exaucée » se place sous la ligne « Requête de prière · date », jamais à droite du nom, pour ne pas écraser le texte dans les cartes étroites). Charger `user`, `event` et `withPrayedBy($viewer)`. Page d'un événement : `prayer.partials.event-section` (sessions à venir + 3 dernières requêtes, « Confier une requête »).

## Temps de prière

« Je prie » ouvre un écran plein « Temps de prière » (décompte de 5 min, verset Jacques 5:16, case « J'ai prié pour cette requête », « Valider ma prière ») sur le site comme dans l'application. Le serveur compte le temps depuis `…/pray/start` (marge de 10 s) et refuse l'ajout trop tôt :

```json
{ "success": false, "message": "…", "errors": { "prayer_focus": ["Votre temps de prière n'est pas terminé : encore 3 min 20 s."] }, "remaining_seconds": 200 }
```

Les anciennes versions de l'application, qui appellent directement `…/pray`, reçoivent 422 jusqu'à leur mise à jour : `PRAYER_FOCUS_SECONDS=0` désactive la règle pendant la transition. Détails : [temps-de-priere.md](temps-de-priere.md).

## Application

- Menu latéral **Requêtes de prière** ; routes `/prayer-requests` (onglets Toutes / Mes requêtes, filtre Exaucées), `/prayer-requests/new?event=`, `/prayer-requests/:id`. Lecture libre en mode invité ; confier, prier, encourager et signaler demandent un compte.
- Carte réutilisable **`PrayerRequestFeedCard(request: …, onChanged: …)`** (`lib/features/prayer/widgets/prayer_widgets.dart`) : « Je prie » directement depuis la carte. Une page d'un scope : **`prayerRequestsPageProvider((PrayerRequestScope.feed, null))`** (`lib/features/prayer/providers/prayer_providers.dart`).
- Page d'un événement : bloc **Prière** (`EventPrayerSection`).
- Notifications `prayer_encouragement` : ouvrent la requête. `prayer_request_prayed` (porte `prayerRequestId`) : ouvre aussi la requête dans l'application.

## Fichiers

Page d'un témoignage (`resources/views/videos/partials/watch.blade.php`) : bouton « Confier un sujet de prière » (témoignage publié et public ; la connexion est demandée au clic). La requête (page et carte des fils) affiche « Après le témoignage « … » ».

`app/Models/PrayerRequest.php` (relation `sourceTestimony`, publiés seulement), `PrayerRequestPrayer.php`, `PrayerRequestMessage.php`, `PrayerRequestReport.php` · `app/Services/PrayerRequests.php` (règles communes) · `app/Http/Controllers/Api/PrayerRequestController.php` · `app/Http/Controllers/Web/PrayerRequestController.php` · `app/Http/Resources/PrayerRequestResource.php` · vues `resources/views/prayer/requests/`, `prayer/partials/feed-card`, `prayer/partials/event-section`, `prayer/moderation` · migration `2026_10_05_400001_create_prayer_tables` · tests `tests/Feature/PrayerTest.php`, `PrayerFocusTest.php` · application `lib/features/prayer/`, `test/prayer_test.dart`.

## Limites connues

- Le témoignage écrit après « Témoigner » n'est pas rattaché automatiquement à la requête (Publier ne connaît pas encore `prayer_request_id`) : rattachement par `PUT /prayer/requests/{id}` `{ testimony_id }`.
- Pas de modification du texte sur le site (l'API le permet).
