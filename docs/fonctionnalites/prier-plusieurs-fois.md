# Prier plusieurs fois

## But

Une personne peut prier **plusieurs fois** pour la même publication. On voit combien de fois elle a prié. Ajouté le 2026-10-26.

Publications concernées : témoignages, requêtes de prière, verset du jour, événements et sessions de prière. Les paroles prophétiques avaient déjà leur journal de prière (une entrée par prière).

## Règles

| Règle | Détail |
|---|---|
| Nouvelle prière | Chaque prière exige un [temps de prière](temps-de-priere.md) de 5 minutes (`…/pray/start`), y compris la deuxième, la troisième… |
| Compteur de la publication | Toujours le **nombre de personnes** qui prient (`prayer_count`, `prayersCount`, `prayerCount`) : prier encore ne l'augmente pas. |
| Nombre de fois | Gardé par personne dans la colonne `prayer_times` de sa réaction (ou de sa ligne `prayer_request_prayers`). Exposé par `myPrayerTimes` (0 si la personne ne prie pas). |
| Retirer | « Retirer ma dernière prière » enlève **une** prière. Quand il n'en reste qu'une, la réaction « je prie » est retirée et le compteur de personnes baisse. Retirer n'est jamais bloqué. |
| Notification | L'auteur n'est prévenu qu'à la **première** prière de la personne, comme avant. |
| Réaction de prière | Prier encore garde la réaction déjà posée (`pray`, `amen` ou `worship`). |

## Site

| Page | Comportement |
|---|---|
| Témoignage | « Je prie » ouvre le temps de prière à chaque appui, puis ajoute une prière. Dessous : « Vous avez prié 3 fois » et **Retirer ma dernière prière** (mise à jour sans rechargement, `resources/js/videos.js`). |
| Requête de prière (sujet de prière) | Page de la requête : bouton « Je prie », puis « Prier encore ». Sous le compteur : « Vous avez prié N fois » et **Retirer ma dernière prière**. |
| Cartes des sujets de prière | Liste des requêtes, onglet « Sujets de prière » du verset du jour, « Prières récentes » de l'accueil, fils : mêmes boutons « Je prie » / « Prier encore », bouton « N prient » qui ouvre la liste de ceux qui ont prié (avec le nombre de fois), puis « Vous avez prié N fois » et **Retirer ma dernière prière** (2026-10-26, `prayer/partials/feed-card.blade.php`). Page d'un événement : le nombre de personnes de chaque requête ouvre la même liste (`prayer/partials/event-section.blade.php`). |
| Verset du jour | « Je prie », puis « Prier encore », avec la même ligne sous les boutons. |
| Popup « Qui a réagi ? » | « 3 fois » à côté de l'icône « Je prie » de la personne (à partir de 2). Voir [qui-a-reagi.md](qui-a-reagi.md). |

Composant : `resources/views/components/prayer-times.blade.php`, un formulaire avec le nombre de prières et le bouton de retrait.

Comme le second appui ne retire plus la prière, le script du temps de prière (`resources/js/prayer-focus.js`) n'utilise plus `data-prayed`.

## API (`/api/v1`) — pour l'application

Sans les nouveaux paramètres, rien ne change : les anciennes versions de l'application gardent leur comportement. Renvoyer `pray` alors qu'on prie déjà reste idempotent, et un retrait sans `last` enlève toutes les prières.

| Publication | Prier encore | Retirer la dernière prière | État |
|---|---|---|---|
| Témoignage | `POST testimonies/{id}/reactions` `{ type: pray, again: true }` | `DELETE testimonies/{id}/reactions?group=prayer&last=1` | `myPrayerTimes` (réponse et `TestimonyResource`) |
| Événement, session de prière | `POST events/{id}/reactions` `{ type: pray, again: true }` (idem `prayer/sessions`) | `DELETE …/reactions?group=prayer&last=1` | `reactions.myPrayerTimes` |
| Requête de prière | `POST prayer/requests/{id}/pray` `{ again: true }` | `POST prayer/requests/{id}/pray` `{ prayed: false, last: true }` | `myPrayerTimes` (`PrayerRequestResource`, listes comprises) |
| Verset du jour | `POST daily-verse/{id}/react` `{ type: pray, again: true }` | `POST daily-verse/{id}/react` `{ type: pray, active: false, last: true }` | `myPrayerTimes` |
| Qui a réagi ? | — | — | `people[].prayerTimes` de `GET …/{id}/reactions/people` |

`again` exige un temps de prière écoulé (**422** `prayer_focus` sinon), commencé par l'appel `…/pray/start` habituel. Si la personne ne prie pas encore, `again` vaut une première prière.

**Application** : à faire dans le dépôt Flutter. Après une première prière, le bouton « Je prie » doit envoyer `again: true` au lieu de retirer la prière. Ajouter « Retirer ma dernière prière » (`last`) et afficher `myPrayerTimes`.

## Données

Migration `2026_10_26_100001_add_prayer_times_to_reaction_tables` : colonne `prayer_times` (entier, 1 par défaut) dans `reactions`, `content_reactions`, `daily_verse_reactions` et `prayer_request_prayers`. Les prières existantes comptent pour 1. Pour une réaction « j'aime », la colonne reste à 1 et n'est pas lue.

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Services/TestimonyReactions.php` | `set(…, again)`, `removeLastPrayer`, `prayerTimes`, `state()['myPrayerTimes']` |
| `app/Services/ContentInteractions.php` | `react(…, again)`, `removeLastPrayer`, `payload()['reactions']['myPrayerTimes']` |
| `app/Services/PrayerRequests.php`, `app/Models/PrayerRequest.php` | `setPrayed(…, again)`, `removeLastPrayer`, `prayerTimesOf`, `withPrayedBy` (`my_prayer_times`) |
| `app/Services/DailyVerses.php` | `setReaction(…, again)`, `removeLastPrayer`, `prayerTimes` |
| `app/Services/ReactionPeople.php` | `prayerTimes` par personne |
| Contrôleurs `Api\ReactionController`, `Api\ContentInteractionController`, `Api\PrayerRequestController`, `Api\DailyVerseController`, `Web\TestimonyController`, `Web\PrayerRequestController`, `Web\DailyVerseController` | Paramètres `again`, `last`, `remove_last` |
| `resources/views/components/prayer-times.blade.php`, `videos/partials/watch.blade.php`, `prayer/requests/show.blade.php`, `prayer/partials/feed-card.blade.php`, `daily-verse/show.blade.php` | Affichage |
| `resources/js/videos.js`, `resources/js/prayer-focus.js` | Témoignage sans rechargement ; temps de prière à chaque appui |
| `tests/Feature/PrayMultipleTimesTest.php`, `WebPrayToggleTest.php`, `PrayerFocusTest.php`, `PrayerFocusOtherKindsTest.php` | Tests |

## Déploiement

1. `php artisan migrate --force` (colonne `prayer_times`).
2. `php artisan optimize:clear` puis `php artisan optimize`.
3. Recompiler et **copier `public/build/`** sur le serveur.
