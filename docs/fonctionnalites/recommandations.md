# Recommandations et fil « Pour vous »

## But

- Ne pas présenter tous les témoignages à une nouvelle personne : **les plus récents et les plus vus**, en alternance.
- Recommander automatiquement des témoignages selon **le témoignage en cours**, les **centres d'intérêt** de la personne et les **comptes qu'elle suit**.

## Utilisation

| Où | Ce qui change |
|---|---|
| Application, accueil | Fil « Pour vous », chargé 20 par 20 au défilement (et non plus une seule liste) |
| Site et application, page d'un témoignage | « À regarder également » / « Témoignages similaires » : recommandations automatiques ; la lecture automatique du suivant (site) les utilise |
| Site, accueil | « Témoignages récents » reste chronologique (son nom l'indique) |

## Règles

### Signaux de la personne connectée (180 derniers jours)

| Signal | Poids |
|---|---|
| Témoignage sauvegardé | 4 |
| Réaction (J'aime, Prière…) | 3 |
| Commentaire | 2 |
| Lecture (historique `testimony_views`, 3 au plus par témoignage) | 1 |
| Témoignage publié par la personne | 1 |

Les poids sont additionnés par catégorie puis ramenés entre 0 et 1 : ce sont les **centres d'intérêt**. Les **comptes suivis** et les témoignages **déjà vus** complètent les signaux. Lecture enregistrée à chaque vue comptée (site, une par fenêtre de 6 h) et à chaque ouverture dans l'application.

### Fil « Pour vous » (`GET /api/v1/testimonies?sort=for_you&page=&limit=`)

| Personne | Classement |
|---|---|
| Visiteur ou compte sans signal | Un récent, un très vu, un récent… (sans doublon) |
| Personne avec signaux | Score = compte suivi (3) + 3 × intérêt de la catégorie + popularité (échelle logarithmique des vues) + 1,5 × fraîcheur (2 : moins de 7 jours, 1 : moins de 30 jours) − 3 si déjà vu |

La liste classée compte 200 témoignages au plus ; au-delà, les autres suivent du plus récent au plus ancien, sans doublon. Filtres `type` et `category` possibles. Liste recalculée au plus toutes les 10 minutes par personne ; suivre ou ne plus suivre un compte est pris en compte tout de suite pour les recommandations.

### Mon fil (`GET /api/v1/feed?page=&limit=`, connecté ; site `/mon-fil`)

Page personnelle (menu latéral « Mon fil », site et application) : les témoignages des **comptes suivis**, du plus récent au plus ancien, avec **une suggestion tous les trois** — témoignages de comptes **non suivis** proches de ses centres d'intérêt (catégories, popularité, fraîcheur ; déjà vus repoussés ; jamais les siens). Sans abonnement : suggestions seulement, avec une invitation à suivre des comptes. Chaque élément porte `feedReason` = `following` | `suggested` (libellé « Suggestion pour vous ») ; `meta.followingCount`. Liste calculée par `Recommendations::personalFeed` (cache 10 minutes, `reco:personal:{id}`, vidé à chaque abonnement / désabonnement). Des messages pour inciter à témoigner y sont insérés ([encouragements.md](encouragements.md)).

### Directs, événements et requêtes de prière dans les fils

| Fil | En tête | Insérés entre les témoignages |
|---|---|---|
| « Pour vous » (accueil de l'application ; « Témoignages récents » du site, sans filtre) | directs à l'antenne (application : bandeau « En direct » ; site : bloc « En direct · Maintenant ») | requêtes de prière **publiques** récentes (`GET /prayer/requests?scope=feed`, 10) |
| Mon fil | directs à l'antenne visibles de la personne | en alternance : **événements à venir** des comptes suivis (`GET /events?scope=following`, 10) et leurs **requêtes de prière** publiques ou réservées aux abonnés, **sans les anonymes** (`scope=following`, 10) |
| Page d'un événement | — | bloc « Prière » ([requetes-de-priere.md](requetes-de-priere.md)) |

Règle de placement (identique site / application) : un élément inséré après le 3e témoignage, puis tous les 6 ; les messages « Témoigner » restent tous les 8. Numérotation selon la position dans le fil entier (pages suivantes comprises) ; les éléments en trop attendent la suite du fil ; avec moins de 3 témoignages (ou aucun), ils suivent la liste. Pas d'insertion dans les shorts ni quand un filtre (type, catégorie) est actif sur l'accueil.

Fichiers : application `lib/shared/content/feed_mix.dart` (`mixFeed`, `interleave`), `lib/features/feed/feed_insert_card.dart`, `lib/features/live/widgets/live_now_strip.dart` ; site `videos/partials/cards.blade.php` (variable `inserts`), `feed/partials/insert.blade.php`, `lives/partials/now.blade.php`, `Web\FeedController::followedInserts()`. Tests : `tests/Feature/FeedInsertsTest.php`, application `test/feed_mix_test.dart` et `test/home_layout_test.dart` (débordements à 320 px).

### Recommandations d'un témoignage (`GET /api/v1/testimonies/{id}/recommendations?limit=10`, 30 au plus)

Candidats : même catégorie, même auteur, comptes suivis, catégories préférées, plus vus, plus récents. Score : même catégorie (4), même auteur (3), compte suivi (3), 3 × intérêt, même type (1,5), mots-clés communs (1 par mot, 2 au plus), popularité, fraîcheur ; −4 si déjà vu. Le témoignage en cours n'est jamais proposé.

## Fonctionnement technique

| Élément | Fichier |
|---|---|
| Moteur | `app/Services/Recommendations.php` (`interests`, `followedIds`, `seenIds`, `isNewcomer`, `recordView`, `forTestimony`, `feed`) |
| Historique de lecture | Table `testimony_views` (migration `2026_09_30_100003_create_testimony_views_table`) ; `ViewCounter::record()` et `Api\TestimonyController::show()` |
| Page d'un témoignage (site) | `WatchPage::recommendations()` |
| API | `Api\TestimonyController::index()` (`sort=for_you`), `::recommendations()` |
| Cache | `reco:interests:{id}`, `reco:follows:{id}`, `reco:feed:{id|newcomer}:{type}:{catégorie}` (10 min) |

## Tests

`tests/Feature/RecommendationsTest.php` : nouveau venu (récent puis très vu, pagination sans doublon), intérêts et comptes suivis, recommandations (même catégorie d'abord, déjà vu repoussé, témoignage en cours exclu, API, page du site), historique de lecture et centres d'intérêt.

## Limites connues

- Pas d'apprentissage automatique : règles pondérées, faciles à ajuster dans `Recommendations.php`.
- Les personnes non connectées n'ont pas d'historique : elles reçoivent le fil « nouveau venu ».
