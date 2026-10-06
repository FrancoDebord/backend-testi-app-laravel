# Sessions de prière

## But

**Prier ensemble, en direct.** Une personne programme une session (titre, présentation, sujets de prière, date, durée) ; les fidèles s'**inscrivent** (« Je serai là ») et sont prévenus. À l'heure, l'hôte **ouvre la salle** : c'est un **direct** (LiveKit) avec spectateurs, commentaires, réactions et **prise de parole à tour de rôle** (un intervenant à la fois, voir [lives-intervenants.md](lives-intervenants.md)).

Disponible sur le **site** (`/priere/sessions`, menu latéral « Sessions de prière ») et dans l'**application** (menu latéral). Une session peut être rattachée à un **événement** (bloc « Prière » de sa page).

## Choix : la salle est un direct

Plutôt que de dupliquer la vidéo, le chat et les intervenants, **ouvrir la salle crée un `LiveSession`** rattaché par `live_sessions.prayer_session_id`. Tout ce qui existe pour les directs fonctionne donc tel quel : studio de l'hôte (site `/lives/{id}/studio`, application `/lives/{id}/studio`), page de visionnage (`/lives/{id}`), commentaires, réactions, file des intervenants, modération (masquer, exclure, **couper le direct**), nettoyage automatique (`lives:cleanup`).

Différences avec un direct classique :

- **Qui peut ouvrir** : l'**hôte** de la session, même **simple utilisateur**. `LiveService::start()` accepte `prayer_session_id` (envoyé **uniquement** par `App\Services\PrayerSessions` ; `POST /lives` ne le reçoit pas) et vérifie que la personne est l'hôte. Les règles des directs classiques ne changent pas (modération, ou gestionnaire d'un événement).
- **Pas d'enregistrement** (`record = false`) : une prière n'est pas transformée en témoignage vidéo.
- **Visibilité** : la salle suit la session (`LiveSession::isVisibleTo()` consulte `prayerSession->isVisibleTo()`) : une session réservée aux abonnés donne une salle réservée aux abonnés (jeton spectateur refusé, 404).
- `LiveSessionResource.prayerSession` : `{ id, title }` ou `null`.

## Règles

| Règle | Détail |
|---|---|
| **Qui programme** | Tout compte **connecté et actif** (20 par minute au plus). Rattacher à un événement : ses **gestionnaires** seulement, événement **publié**. |
| **Champs** | Titre (150) · présentation (3 000) · sujets de prière (10 au plus, 150 caractères chacun) · date et heure (dans le futur) · durée 15 min à 8 h (60 par défaut) · visibilité `public` / `followers` (pas de session privée). |
| **Phases** | `upcoming` (à venir) · `live` (salle à l'antenne) · `ended` (heure de fin passée et salle fermée) · `cancelled` (annulée). Calculées par le serveur (`PrayerSession::phase()`), `ends_at` = début + durée. |
| **Inscription** | « Je serai là » / retrait, tant que la session n'est ni terminée ni annulée ; compteur `participant_count`. |
| **Ouvrir la salle** | L'hôte, de **30 minutes avant** l'heure prévue jusqu'à la **fin prévue** ; s'il en a déjà ouvert une, il la **reprend** (même direct). Un hôte ne peut avoir qu'un direct actif à la fois (règle des directs). La salle passe « à l'antenne » quand l'hôte publie sa caméra / son micro dans le studio. |
| **Prévenir les inscrits** | À la **première ouverture** : notification `prayer_session_started` (+ push, avec `live_id`). **Rappel 15 minutes avant** : commande `prayer-sessions:remind` (toutes les 5 minutes, planificateur Laravel) → `prayer_session_reminder` aux inscrits **et** à l'hôte, une seule fois (`reminder_sent_at`, remis à zéro si l'horaire change). |
| **Modifier** | L'hôte, tant que la session n'est pas terminée ni annulée. |
| **Annuler / supprimer** | L'hôte ou la **modération** ; la salle ouverte est fermée. La modération peut aussi **couper la salle** depuis la page du direct. |
| **Voir les inscrits** | L'hôte et la modération. |

## Données

| Table | Contenu |
|---|---|
| `prayer_sessions` | `host_id`, `event_id`, `title`, `description`, `topics` (JSON), `starts_at`, `duration_minutes`, `ends_at`, `visibility`, `status` (`scheduled` / `cancelled`), `participant_count`, `reminder_sent_at`, `opened_at` ; suppression douce |
| `prayer_session_participants` | `prayer_session_id`, `user_id` (unique) |
| `live_sessions.prayer_session_id` | Salle (direct) de la session |

## API mobile (`/api/v1`)

| Méthode | Route | Rôle |
|---|---|---|
| GET | `prayer/sessions` | `scope` = `upcoming` (défaut : à venir et en cours) · `joined` · `mine` · `past` · `event` (avec `event_id`). `meta` : pagination, `canCreate`, `videoConfigured`. Public (selon la visibilité). |
| GET | `prayer/sessions/{id}` | Détail |
| POST | `prayer/sessions` | `{ title, description?, topics?[], starts_at, duration_minutes?, visibility?, event_id? }` |
| PUT · DELETE | `prayer/sessions/{id}` | Modifier (hôte) · supprimer (hôte, modération) |
| POST | `prayer/sessions/{id}/cancel` | Annuler (hôte, modération) |
| POST · DELETE | `prayer/sessions/{id}/join` | « Je serai là » · retirer l'inscription |
| GET | `prayer/sessions/{id}/participants` | Inscrits (hôte, modération) |
| POST | `prayer/sessions/{id}/start` | Hôte : ouvre (ou reprend) la salle → `{ session, live, video: { url, token, identity } }`, comme `POST /lives`. 409 hors de la fenêtre d'ouverture. |

Ensuite, routes des directs : `lives/{id}/go-live`, `lives/{id}/viewer-token`, commentaires, intervenants, `lives/{id}/end`…

`PrayerSessionResource` :

```json
{
  "type": "prayer_session", "id": "uuid", "title": "…", "description": "…", "topics": ["…"],
  "startsAt": "ISO 8601", "endsAt": "ISO 8601", "durationMinutes": 60,
  "visibility": "public|followers", "visibilityLabel": "…", "status": "scheduled|cancelled",
  "phase": "upcoming|live|ended|cancelled", "phaseLabel": "À venir",
  "host": { "id": "uuid", "displayName": "…", "initials": "…", "avatarUrl": null, "isVerified": false },
  "event": { "id": "uuid", "title": "…" } | null,
  "participantCount": 24, "isRegistered": false, "isHost": false,
  "canEdit": false, "canDelete": false, "canStart": false, "opensAt": "ISO 8601",
  "live": { "id": "uuid", "status": "preparing|live" } | null,
  "webUrl": "https://…/priere/sessions/{id}", "createdAt": "ISO 8601"
}
```

`live` : salle active (une salle « en préparation » n'est montrée qu'à l'hôte et à la modération).

## Site

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/priere/sessions` `?onglet=joined\|mine\|past` | À venir / Mes inscriptions / Que j'anime / Passées |
| GET · POST | `/priere/sessions/nouvelle` `?evenement=` · `/priere/sessions` | Programmer (date et heure séparées, sujets un par ligne, durée, visibilité) |
| GET | `/priere/sessions/{id}` | Détail, inscrits (hôte), bouton de la salle |
| GET · PUT · DELETE | `/priere/sessions/{id}/modifier` · `/priere/sessions/{id}` | Modifier · supprimer |
| POST | `/priere/sessions/{id}/annuler` | Annuler |
| POST · DELETE | `/priere/sessions/{id}/inscription` | « Je serai là » · retirer |
| POST | `/priere/sessions/{id}/ouvrir` | Hôte : ouvre la salle → **studio** du direct |
| GET | `/priere/sessions/{id}/salle` | Rejoindre → **page du direct** (l'hôte est renvoyé vers son studio) |

## Application

- Menu latéral **Sessions de prière** ; routes `/prayer-sessions` (onglets), `/prayer-sessions/new?event=`, `/prayer-sessions/:id`, `/prayer-sessions/:id/edit`.
- Hôte : **Ouvrir la salle** → `POST …/start` puis **studio** `/lives/{id}/studio` (écrans des directs réutilisés). Autres : **Rejoindre la salle** → `/lives/{id}` (commentaires, demande d'intervention).
- Notifications `prayer_session_started` / `prayer_session_reminder` : ouvrent la session.
- L'ancien écran « session en direct » avec chat simulé est supprimé.

## Fichiers

`app/Models/PrayerSession.php`, `PrayerSessionParticipant.php` · `app/Services/PrayerSessions.php` · `app/Services/LiveService.php` (branche `prayer_session_id` de `start()`) · `app/Models/LiveSession.php` (`prayerSession()`, `isVisibleTo()`) · `app/Http/Controllers/Api/PrayerSessionController.php` · `app/Http/Controllers/Web/PrayerSessionController.php` · `app/Http/Resources/PrayerSessionResource.php` · `app/Console/Commands/RemindPrayerSessions.php` (+ `routes/console.php`) · vues `resources/views/prayer/sessions/`, `prayer/partials/session-card` · tests `tests/Feature/PrayerTest.php` · application `lib/features/prayer/`.

## Limites connues

- La liste des directs (`/lives`, « Directs récents ») montre aussi les salles de prière terminées.
- Les abonnés de l'hôte reçoivent aussi la notification « est en direct » des directs (`live_started`) au passage à l'antenne.
- Rappel : nécessite le planificateur (`php artisan schedule:run` chaque minute) ; sans lui, seules les notifications d'ouverture partent.
