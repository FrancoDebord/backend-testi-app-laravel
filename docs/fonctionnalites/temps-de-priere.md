# Temps de prière

## But

« Je prie » doit correspondre à une vraie prière. Appuyer sur « Je prie » (une **requête de prière**, un **témoignage**, le **verset du jour**, un **événement**, une **session de prière**) ou sur « J'ai prié » (une **parole prophétique** du carnet) ouvre un écran plein « Temps de prière » avec un **décompte de 5 minutes**. Ce n'est qu'à la fin du décompte que la personne peut cocher « J'ai prié pour cette requête / ce témoignage » et **valider** : la prière est alors enregistrée (compteur, notification à l'auteur).

Le **serveur** fait respecter la règle (API mobile et site) : une prière ajoutée sans temps de prière écoulé est refusée (**422**).

## Règles

| Règle | Détail |
|---|---|
| **Durée** | `config('prayer.focus_seconds')` = `PRAYER_FOCUS_SECONDS` (300 s par défaut). **0 désactive** entièrement la règle. |
| **Début** | `…/pray/start` note l'heure de début côté serveur (cache, clé par type + identifiant + personne, durée de vie **6 h**). Un nouvel appel pendant l'**heure** qui suit **garde le premier début** (réponse : même `startedAt`) ; au-delà, il recommence. Avant le 2026-10-19, un appel en double ou tardif remettait le décompte à zéro : « encore 4 min 54 s » alors que l'écran avait fini. Chaque refus « trop tôt » est journalisé (`elapsed`, `required`). |
| **Marge** | La prière est acceptée **10 s** avant la fin (latence réseau, horloges). Le temps du serveur ne fait que grandir : la pause de l'écran quand l'onglet est caché (site) reste couverte. |
| **Une prière par début** | Le début est **consommé** quand la prière est enregistrée : retirer puis remettre « Je prie », ou **prier encore** (`again`, [prier-plusieurs-fois.md](prier-plusieurs-fois.md)), demande un nouveau temps de prière. |
| **Quand la règle s'applique** | Seulement quand l'appel **ajoute** une prière : requête de prière « Je prie » alors que la personne ne prie pas encore ; témoignage, événement, session de prière : réaction du groupe « je prie » (`pray`, `amen`, `worship`) alors que la personne n'en a aucune ; verset du jour : réaction `pray` alors que la personne ne l'a pas (`like` et `amen` ne sont **pas** concernés) ; parole prophétique : **chaque** « J'ai prié » (chaque appel ajoute une entrée au journal de prière). |
| **Jamais bloqué** | Retirer sa prière (requête, réaction, verset, entrée du journal d'une parole) ; renvoyer la même prière sans `again` alors qu'on prie déjà (`{ prayed: true }`, `{ active: true }`, même réaction, idempotent) ; retirer sa dernière prière (`last`) ; passer de `pray` à `amen` (même groupe) ; les réactions « j'aime » ; les réactions 🙏 pendant un direct (`lives/{id}/reactions`, non concernées). |
| **Visibilité** | Commencer exige les mêmes droits que consulter : requête visible (404 sinon) et non retirée (409) ; témoignage visible (`Testimony::isVisibleTo`, 404 sinon) ; événement / session visible (`ContentInteractions::find`, 404 sinon) ; verset actif (404 sinon) ; parole prophétique : seulement la sienne (404 pour toute autre personne). |
| **Clé du début** | Par sujet : `prayer_request`, `testimony`, `daily_verse`, `prophecy`, `event`, `prayer_session` (`PrayerFocus::KINDS`) + identifiant + personne. La réaction « je prie » d'une requête de prière reste refusée (422 `use_pray`) : le bouton dédié `prayer/requests/{id}/pray` et son début s'appliquent. |

## API mobile (`/api/v1`, connecté)

| Méthode | Route | Rôle |
|---|---|---|
| POST | `prayer/requests/{id}/pray/start` | Commence le temps de prière d'une requête (30 par minute) |
| POST | `testimonies/{id}/pray/start` | Commence le temps de prière d'un témoignage (30 par minute) |
| POST | `daily-verse/{id}/pray/start` | Commence le temps de prière du verset (identifiant numérique ; 30 par minute) |
| POST | `prophecies/{id}/pray/start` | Commence le temps de prière d'une parole prophétique (la sienne ; 30 par minute) |
| POST | `events/{id}/pray/start` | Commence le temps de prière d'un événement (30 par minute) |
| POST | `prayer/sessions/{id}/pray/start` | Commence le temps de prière d'une session de prière (30 par minute) |
| POST | `prayer/requests/{id}/pray` | Ajoute la prière **après** le temps de prière (sinon 422) |
| POST | `testimonies/{id}/reactions` `{ type: pray\|amen\|worship }` | Idem pour un témoignage |
| POST | `daily-verse/react` `{ type: pray }`, `daily-verse/{id}/react` `{ type: pray, active? }` | Idem pour le verset (seulement quand « prie » est ajouté : bascule ou `active: true`) |
| POST | `prophecies/{id}/prayers` `{ note?, prayed_at? }` | « J'ai prié » : idem, à chaque entrée du journal |
| POST | `events/{id}/reactions`, `prayer/sessions/{id}/reactions` `{ type: pray\|amen\|worship }` | Idem pour un événement ou une session |

Réponse de `…/pray/start` (200) :

```json
{ "success": true, "data": { "startedAt": "2026-10-18T10:00:00+00:00", "requiredSeconds": 300, "confirmableAt": "2026-10-18T10:05:00+00:00" }, "message": "Temps de prière commencé." }
```

Prière refusée (422, textes selon `Accept-Language`) :

```json
{
  "success": false,
  "message": "Prenez le temps de prier avant de valider votre prière.",
  "errors": { "prayer_focus": ["Votre temps de prière n'est pas terminé : encore 3 min 20 s."] },
  "remaining_seconds": 200
}
```

Sans début : `remaining_seconds` = durée complète et message « Commencez par le temps de prière… ».

## Site

| Méthode | Route | Nom | Rôle |
|---|---|---|---|
| POST | `/priere/requetes/{id}/je-prie/commencer` | `prayer.requests.pray.start` | Début (même JSON que l'API) |
| POST | `/testimonies/{id}/priere/commencer` | `testimonies.prayer.start` | Début (même JSON que l'API) |
| POST | `/verset-du-jour/{id}/je-prie/commencer` | `daily-verse.pray.start` | Début (même JSON que l'API) |
| POST | `/carnet/paroles/{id}/prieres/commencer` | `prophecies.pray.start` | Début (même JSON que l'API) |
| POST | `/verset-du-jour/{id}/reagir` `type=pray` | `daily-verse.react` | « Je prie » du verset : contrôle seulement quand la prière est ajoutée ; sans temps écoulé, retour à la page avec le message |
| POST | `/carnet/paroles/{id}/prieres` | `prophecies.pray` | « J'ai prié » : idem |
| POST | `/priere/requetes/{id}/je-prie` | `prayer.requests.pray` | Formulaire « Je prie » : sans temps écoulé, retour à la page avec le message d'erreur |
| POST | `/testimonies/{id}/reactions` | `testimonies.reactions.store` | « Je prie » (`togglePrayer`) : contrôle seulement quand la prière est ajoutée ; 422 JSON en fetch |

Écran **« Temps de prière »** : `resources/views/components/prayer-focus-modal.blade.php`, inclus une fois par page pour les personnes connectées (`layouts/partials/global-ui`). Dégradé bleu nuit plein écran, sujet de la prière (texte de la requête ; titre et extrait du témoignage), décompte circulaire 05:00, verset Jacques 5:16, case « J'ai prié… » affichée à la fin du décompte, bouton « Valider ma prière » désactivé jusque-là, croix / « Quitter sans enregistrer » (Échap) = partir sans rien enregistrer. Le décompte se met **en pause quand l'onglet est caché** (`visibilitychange`).

`resources/js/prayer-focus.js` (importé par `app.js`) :

- formulaires `<form data-prayer-focus="{route de début}" data-prayed="0|1" data-prayer-focus-kind="prayer_request|daily_verse|prophecy" data-prayer-subject="…" data-prayer-excerpt="…">` : requêtes (`prayer/partials/feed-card`, `prayer/requests/show`), « Je prie » du verset (`daily-verse/show` : sujet = référence, extrait = texte affiché), « J'ai prié » d'une parole (`journal/prophecies/show`, toujours `data-prayed="0"`). Si la personne ne prie pas encore, l'envoi est intercepté → début → écran → à la validation, le formulaire part normalement (la note de la parole est gardée). `data-prayed="1"` : retrait immédiat comme avant. Textes de l'écran selon le sujet : `data-label-{request|testimony|verse|prophecy|event|session}` et `data-confirm-…` du composant (`prayer_focus.modal.for_*`, `confirm_*`) ;
- page d'un témoignage (`videos/partials/watch`, conteneur `data-reactions` avec `data-pray-start`, `data-pray-subject`, `data-pray-excerpt`) : `resources/js/videos.js` appelle `prayerFocus({ startUrl, kind: 'testimony', subject, excerpt })` avant l'envoi habituel. Un 422 affiche son message (`errors.prayer_focus`).

Après modification du JavaScript : `npm run build` et copie de `public/build/`.

## Transition avec l'application

Les **anciennes versions** de l'application appellent directement `…/pray` ou `…/reactions` : elles reçoivent **422** tant qu'elles ne sont pas mises à jour. Pendant la transition, mettre `PRAYER_FOCUS_SECONDS=0` dans `.env` (puis `php artisan config:clear` ou `config:cache`) désactive la règle ; la remettre à 300 une fois la nouvelle version diffusée.

Les événements et sessions de prière n'ont pas de bouton « Je prie » sur le site (réactions seulement dans l'application) : leurs routes de début sont dans l'API.

## Fichiers

`config/prayer.php` · `app/Services/PrayerFocus.php` · `app/Exceptions/PrayerFocusRequired.php` (réponse 422) · `app/Services/PrayerRequests.php` (`startPrayer`, `setPrayed`) · `app/Services/TestimonyReactions.php` (`startPrayer`, `set`) · `app/Services/DailyVerses.php` (`startPrayer`, `setReaction`) · `app/Services/Prophecies.php` (`startPrayer`, `pray`) · `app/Services/ContentInteractions.php` (`startPrayer`, `react`) · contrôleurs `Api\PrayerRequestController@startPrayer`, `Api\ReactionController@startPrayer`, `Api\DailyVerseController@startPrayer`, `Api\ProphecyController@startPrayer`, `Api\ContentInteractionController@startPrayer`, `Web\PrayerRequestController@startPrayer`, `Web\TestimonyController@startPrayer`, `Web\DailyVerseController@startPrayer`, `Web\ProphecyController@startPrayer` · `lang/{fr,en}/prayer_focus.php` · `resources/views/components/prayer-focus-modal.blade.php` · `resources/js/prayer-focus.js` · tests `tests/Feature/PrayerFocusTest.php`, `tests/Feature/PrayerFocusOtherKindsTest.php` (les autres tests désactivent la règle dans `tests/TestCase.php`).

## Limites connues

- Le début est gardé dans le cache : avec `CACHE_STORE=array` (ou un cache vidé entre les deux appels), la prière est refusée ; utiliser un cache persistant (`database`, `file`, `redis`) en production.
- Les réactions 🙏 pendant un direct ne sont pas concernées.
- Paroles prophétiques : `prayed_at` (prière notée après coup) n'exempte pas du temps de prière.
