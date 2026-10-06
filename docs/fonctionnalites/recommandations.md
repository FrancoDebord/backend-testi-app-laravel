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

Page personnelle (menu latéral « Mon fil », site et application), **comme l'accueil mais personnalisée** : **tous les témoignages publiés** (sauf les siens), sans doublon, dans cet ordre :

1. **Comptes suivis d'abord** : leurs témoignages publiés et leurs **partages** (« X a partagé », [partage-interne.md](partage-interne.md)) des **30 derniers jours**, du plus récent au plus ancien (100 au plus) ; un témoignage partagé n'apparaît qu'une fois, au moment le plus récent ;
2. puis le **classement « Pour vous »** (le même que `sort=for_you`, 200 au plus), sans ce qui précède ;
3. puis **tout le reste**, du plus récent au plus ancien.

Chaque élément porte `feedReason` = `following` (compte suivi) | `suggested` (compte non suivi, libellé « Suggestion pour vous ») | `repost` (+ `repostId`, `repostedBy` (UserResource), `repostComment`, `repostedAt` ; `user` reste l'**auteur d'origine**) ; `meta.followingCount`, `meta.total`. Les étapes 1-2 sont calculées par `Recommendations::personalFeed` et gardées 10 minutes (`reco:personal:v2:{id}`, vidé à chaque abonnement / désabonnement et, pour ses abonnés, à chaque partage) : pagination stable ; l'étape 3 est paginée par décalage. Un témoignage retiré du public ou supprimé, ou un partage annulé, disparaît à l'affichage suivant. Sans abonnement : « Comptes à suivre » en tête puis le classement « Pour vous ». **Rétrocompatible** : l'application déjà publiée affiche un `repost` comme un témoignage suivi (sans l'en-tête « X a partagé »).

Des messages pour inciter à témoigner y sont insérés ([encouragements.md](encouragements.md)), ainsi que le bandeau **« Comptes à suivre »** ([abonnements.md](abonnements.md)) après les 6 premiers éléments (en tête si l'on ne suit personne).

### Directs, événements et requêtes de prière dans les fils

| Fil | En tête | Insérés entre les témoignages |
|---|---|---|
| « Pour vous » (accueil de l'application ; « Témoignages récents » du site, sans filtre) | directs à l'antenne visibles de la personne (application : bandeau « En direct » ; site : bloc « En direct · Maintenant ») ; bandeaux **« Événements à venir »** (application) et **« Sessions de prière à venir »** (application et site, `GET /prayer/sessions?scope=upcoming`, masqué si vide) ; « Comptes à suivre » | requêtes de prière **publiques** récentes (`GET /prayer/requests?scope=feed`, 10) |
| Mon fil | directs à l'antenne visibles de la personne | `GET /feed/inserts` : **événements à venir**, **sessions de prière programmées**, **requêtes de prière**, **paroles prophétiques publiques** — celles des **comptes suivis d'abord**, puis celles des **autres comptes** |
| Page d'un événement | — | bloc « Prière » ([requetes-de-priere.md](requetes-de-priere.md)) |

**`GET /api/v1/feed/inserts`** (connecté ; `App\Services\FeedInserts`, partagé avec le site) : un seul appel plutôt que de nouveaux `scope` sur quatre listes, pour que le site et l'application appliquent exactement la même règle.

```json
{ "success": true, "data": {
  "following": { "events": [EventResource], "prayerSessions": [PrayerSessionResource],
                 "prayerRequests": [PrayerRequestResource], "prophecies": [PublicProphecyResource] },
  "others":    { "events": [], "prayerSessions": [], "prayerRequests": [], "prophecies": [] } } }
```

| Source | Comptes suivis (`following`) | Autres comptes (`others`, jamais les siens) |
|---|---|---|
| Événements | publiés, à venir, organisés par un compte suivi | publiés, à venir, des autres organisateurs |
| Sessions de prière | à venir ou en cours, animées par un compte suivi (publiques ou réservées aux abonnés) | à venir ou en cours, publiques |
| Requêtes de prière | publiques ou réservées aux abonnés, **sans les anonymes** (`scope=following`) | publiques (`scope=feed`, les anonymes restent anonymes), sans celles déjà listées |
| Paroles prophétiques | rendues publiques avec un témoignage d'accomplissement **publié et public**, par un compte suivi | idem, des autres comptes |

5 éléments au plus par source et par groupe. `PublicProphecyResource` : `type: prophecy`, `id`, `title`, `receivedOn`, `givenBy`, `bodyText`, `audioUrl`, `audioDuration`, `dueOn`, `fulfilledOn`, `user`, `testimony { id, title, type, coverUrl, publishedAt }` (jamais les rappels ni le journal de prière ; une parole privée n'apparaît jamais).

**Ordre d'insertion** (identique site / application : `FeedInserts::mixed()`, `mixInserts()`) : les éléments des comptes suivis, les sources en alternance (événement, session, requête, parole, événement…), puis ceux des autres comptes, dans le même ordre. **Placement** : un élément inséré après le 3e témoignage, puis tous les 6 ; les messages « Témoigner » restent tous les 8. Numérotation selon la position dans le fil entier (pages suivantes comprises) ; les éléments en trop attendent la suite du fil ; avec moins de 3 témoignages (ou aucun), ils suivent la liste. Pas d'insertion dans les shorts ni quand un filtre (type, catégorie) est actif sur l'accueil.

**« À la une »** (accueil) : du plus récemment publié au plus ancien. Application : liste de `GET /testimonies/featured` (`latestPublished`, 10), repli sur les témoignages « à la une » du fil chargé triés par date ; site : `featured()->latestPublished()`.

Fichiers : application `lib/shared/content/feed_mix.dart` (`mixFeed`, `interleave`, `mixInserts`), `lib/features/feed/feed_inserts.dart` (`feedInsertsProvider`), `lib/features/feed/feed_insert_card.dart`, `lib/features/feed/personal_feed_screen.dart`, `lib/features/prayer/widgets/upcoming_prayer_sessions_strip.dart`, `lib/features/prophecies/widgets/public_prophecy_feed_card.dart`, `lib/features/live/widgets/live_now_strip.dart`, `featuredFromApiProvider` ; site `App\Services\FeedInserts`, `Api\FeedController`, `Web\FeedController`, `videos/partials/cards.blade.php` (variables `inserts`, `reposts`, `suggestedAt`), `feed/partials/insert.blade.php`, `prayer/partials/upcoming-strip.blade.php`, `lives/partials/now.blade.php`. Tests : `tests/Feature/PersonalFeedAndRepostTest.php`, `FeedInsertsTest.php`, application `test/personal_feed_test.dart`, `test/feed_mix_test.dart` et `test/home_layout_test.dart` (débordements à 320 et 390 px, police ×1,3).

### Recommandations d'un témoignage (`GET /api/v1/testimonies/{id}/recommendations?limit=10`, 30 au plus)

Candidats : même catégorie, même auteur, comptes suivis, catégories préférées, plus vus, plus récents. Score : même catégorie (4), même auteur (3), compte suivi (3), 3 × intérêt, même type (1,5), mots-clés communs (1 par mot, 2 au plus), popularité, fraîcheur ; −4 si déjà vu. Le témoignage en cours n'est jamais proposé.

## Fonctionnement technique

| Élément | Fichier |
|---|---|
| Moteur | `app/Services/Recommendations.php` (`interests`, `followedIds`, `seenIds`, `isNewcomer`, `recordView`, `forTestimony`, `feed`, `personalFeed`) ; éléments insérés de Mon fil : `app/Services/FeedInserts.php` |
| Historique de lecture | Table `testimony_views` (migration `2026_09_30_100003_create_testimony_views_table`) ; `ViewCounter::record()` et `Api\TestimonyController::show()` |
| Page d'un témoignage (site) | `WatchPage::recommendations()` |
| API | `Api\TestimonyController::index()` (`sort=for_you`), `::recommendations()`, `::personalFeed()` (`GET /feed`) ; `Api\FeedController::inserts()` (`GET /feed/inserts`) |
| Cache | `reco:interests:{id}`, `reco:follows:{id}`, `reco:feed:{id|newcomer}:{type}:{catégorie}`, `reco:personal:v2:{id}` (10 min) |

## Tests

`tests/Feature/PersonalFeedAndRepostTest.php` : Mon fil (comptes suivis d'abord puis tous les autres, pagination sans doublon), éléments insérés (sessions, paroles prophétiques ; suivis d'abord), partage interne. `tests/Feature/RecommendationsTest.php` : nouveau venu (récent puis très vu, pagination sans doublon), intérêts et comptes suivis, recommandations (même catégorie d'abord, déjà vu repoussé, témoignage en cours exclu, API, page du site), historique de lecture et centres d'intérêt.

## Limites connues

- Pas d'apprentissage automatique : règles pondérées, faciles à ajuster dans `Recommendations.php`.
- Les personnes non connectées n'ont pas d'historique : elles reçoivent le fil « nouveau venu ».
