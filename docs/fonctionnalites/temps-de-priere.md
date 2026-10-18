# Temps de prière

## But

« Je prie » doit correspondre à une vraie prière. Appuyer sur « Je prie » (une **requête de prière** ou un **témoignage**) ouvre un écran plein « Temps de prière » avec un **décompte de 5 minutes**. Ce n'est qu'à la fin du décompte que la personne peut cocher « J'ai prié pour cette requête / ce témoignage » et **valider** : la prière est alors enregistrée (compteur, notification à l'auteur).

Le **serveur** fait respecter la règle (API mobile et site) : une prière ajoutée sans temps de prière écoulé est refusée (**422**).

## Règles

| Règle | Détail |
|---|---|
| **Durée** | `config('prayer.focus_seconds')` = `PRAYER_FOCUS_SECONDS` (300 s par défaut). **0 désactive** entièrement la règle. |
| **Début** | `…/pray/start` note l'heure de début côté serveur (cache, clé par type + identifiant + personne, durée de vie **6 h**). Un nouvel appel **recommence** le décompte. |
| **Marge** | La prière est acceptée **10 s** avant la fin (latence réseau, horloges). Le temps du serveur ne fait que grandir : la pause de l'écran quand l'onglet est caché (site) reste couverte. |
| **Une prière par début** | Le début est **consommé** quand la prière est enregistrée : retirer puis remettre « Je prie » demande un nouveau temps de prière. |
| **Quand la règle s'applique** | Seulement quand l'appel **ajoute** une prière : requête de prière « Je prie » alors que la personne ne prie pas encore ; témoignage : réaction du groupe « je prie » (`pray`, `amen`, `worship`) alors que la personne n'en a aucune. |
| **Jamais bloqué** | Retirer sa prière ; prier de nouveau alors qu'on prie déjà (`{ prayed: true }`, même réaction) ; passer de `pray` à `amen` (même groupe) ; les réactions « j'aime ». |
| **Visibilité** | Commencer exige les mêmes droits que consulter : requête visible (404 sinon) et non retirée (409) ; témoignage visible (`Testimony::isVisibleTo`, 404 sinon). |

## API mobile (`/api/v1`, connecté)

| Méthode | Route | Rôle |
|---|---|---|
| POST | `prayer/requests/{id}/pray/start` | Commence le temps de prière d'une requête (30 par minute) |
| POST | `testimonies/{id}/pray/start` | Commence le temps de prière d'un témoignage (30 par minute) |
| POST | `prayer/requests/{id}/pray` | Ajoute la prière **après** le temps de prière (sinon 422) |
| POST | `testimonies/{id}/reactions` `{ type: pray\|amen\|worship }` | Idem pour un témoignage |

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
| POST | `/priere/requetes/{id}/je-prie` | `prayer.requests.pray` | Formulaire « Je prie » : sans temps écoulé, retour à la page avec le message d'erreur |
| POST | `/testimonies/{id}/reactions` | `testimonies.reactions.store` | « Je prie » (`togglePrayer`) : contrôle seulement quand la prière est ajoutée ; 422 JSON en fetch |

Écran **« Temps de prière »** : `resources/views/components/prayer-focus-modal.blade.php`, inclus une fois par page pour les personnes connectées (`layouts/partials/global-ui`). Dégradé bleu nuit plein écran, sujet de la prière (texte de la requête ; titre et extrait du témoignage), décompte circulaire 05:00, verset Jacques 5:16, case « J'ai prié… » affichée à la fin du décompte, bouton « Valider ma prière » désactivé jusque-là, croix / « Quitter sans enregistrer » (Échap) = partir sans rien enregistrer. Le décompte se met **en pause quand l'onglet est caché** (`visibilitychange`).

`resources/js/prayer-focus.js` (importé par `app.js`) :

- formulaires des requêtes `<form data-prayer-focus="{route de début}" data-prayed="0|1" data-prayer-excerpt="…">` (`prayer/partials/feed-card`, `prayer/requests/show`) : si la personne ne prie pas encore, l'envoi est intercepté → début → écran → à la validation, le formulaire part normalement. `data-prayed="1"` : retrait immédiat comme avant ;
- page d'un témoignage (`videos/partials/watch`, conteneur `data-reactions` avec `data-pray-start`, `data-pray-subject`, `data-pray-excerpt`) : `resources/js/videos.js` appelle `prayerFocus({ startUrl, kind: 'testimony', subject, excerpt })` avant l'envoi habituel. Un 422 affiche son message (`errors.prayer_focus`).

Après modification du JavaScript : `npm run build` et copie de `public/build/`.

## Transition avec l'application

Les **anciennes versions** de l'application appellent directement `…/pray` ou `…/reactions` : elles reçoivent **422** tant qu'elles ne sont pas mises à jour. Pendant la transition, mettre `PRAYER_FOCUS_SECONDS=0` dans `.env` (puis `php artisan config:clear` ou `config:cache`) désactive la règle ; la remettre à 300 une fois la nouvelle version diffusée.

## Fichiers

`config/prayer.php` · `app/Services/PrayerFocus.php` · `app/Exceptions/PrayerFocusRequired.php` (réponse 422) · `app/Services/PrayerRequests.php` (`startPrayer`, `setPrayed`) · `app/Services/TestimonyReactions.php` (`startPrayer`, `set`) · contrôleurs `Api\PrayerRequestController@startPrayer`, `Api\ReactionController@startPrayer`, `Web\PrayerRequestController@startPrayer`, `Web\TestimonyController@startPrayer` · `lang/{fr,en}/prayer_focus.php` · `resources/views/components/prayer-focus-modal.blade.php` · `resources/js/prayer-focus.js` · tests `tests/Feature/PrayerFocusTest.php` (les autres tests désactivent la règle dans `tests/TestCase.php`).

## Limites connues

- Le début est gardé dans le cache : avec `CACHE_STORE=array` (ou un cache vidé entre les deux appels), la prière est refusée ; utiliser un cache persistant (`database`, `file`, `redis`) en production.
- Les sessions de prière, les événements et le verset du jour ne sont pas concernés.
