# Verset du jour en plein écran

## But

Un clic sur le **verset du jour** (accueil du site, bannière de l'application) l'ouvre en **plein écran**, dans l'esprit de « Proclamer » des paroles prophétiques : grand texte sur fond bleu de la charte (#184797 → bleu nuit), référence en jaune (#FCC11D), liseré orange → jaune. Autour du verset, chacun peut :

- le **lire dans les différentes versions de la Bible** installées (choix mémorisé, « Comparer » toutes les versions) ;
- **aimer** et **prier** (réactions existantes), **partager**, l'**écouter** (synthèse vocale), le lire en **mode lecture** (texte seul, très grand) ;
- **confier un sujet de prière** inspiré par le verset (requête de prière rattachée) ;
- **commenter** (mentions « @ » comprises) ;
- voir les **témoignages qui citent ce verset** et **témoigner sur ce verset**.

Lecture publique ; les actions demandent un compte (invité : connexion proposée).

## Le verset du jour

| Règle | Détail |
|---|---|
| **Choix** | Verset actif programmé ce jour-là (`scheduled_date`, jour du calendrier **Africa/Porto-Novo** depuis le 2026-10-13 : le verset change à minuit au Bénin, en même temps que le [design du jour](designs-du-verset.md)). Sinon (2026-10-27) le verset du **même jour et du même mois** de l'année la plus proche (2028-03-29 → 2027-03-29). Sinon, un verset actif choisi **d'après la date** (même verset toute la journée, pour que commentaires et requêtes restent rattachés au verset affiché). Auparavant ce repli était tiré au hasard à chaque appel. Aucun verset actif : 404 « Aucun verset programmé. ». |
| **Cache** | Le verset trouvé pour une date est gardé en cache jusqu'à minuit (Africa/Porto-Novo), puis recalculé. Tout enregistrement ou suppression d'un verset invalide ce cache (`DailyVerse::forDate`). |
| **Référence** | Texte libre saisi par l'équipe (« Jérémie 29 : 11 », « Jean 3:16-17 »). `App\Support\BibleReference` la reconnaît : noms français avec ou sans accents, noms anglais, abréviations courantes (« Jn », « Ps », « 1 Co »…), séparateurs `:` `.` `,`, plages `16-18`, `16–18`, `16 à 18`. Une plage qui déborde sur le chapitre suivant est réduite à son premier chapitre. |
| **Référence non reconnue** | Seul le **texte du jour** (saisi par l'équipe) est proposé ; pas de versions ni de témoignages liés. |

## Langue du verset (2026-10-27)

Le verset du jour s'affiche directement dans la langue de l'application ou du site.

| Langue | Texte affiché |
|---|---|
| Français | Texte du jour (Louis Segond, saisi ou importé). |
| Anglais | Première traduction anglaise installée qui contient le passage : `KJV`, puis `WEB`, `BBE`, `ASV` (`DailyVerses::TRANSLATIONS_BY_LANGUAGE`), sinon toute traduction anglaise installée (`language: en`, ou noms de livres anglais). Référence dans cette traduction (« John 3:16 »). Aucune : texte du jour en français. |

- **Langue utilisée** :
  - application : l'en-tête `Accept-Language` qu'elle envoie déjà, c'est-à-dire la langue choisie, sinon celle du système (`AppLanguage.system`, par défaut) ;
  - site : la langue de la page (sélecteur, compte, puis navigateur ; voir [langues.md](langues.md)).
- **API** (`GET /daily-verse`, `/daily-verse/{id}`, réactions, `/daily-verse/history`) :
  - `verseText` et `reference` sont dans cette langue ;
  - champs ajoutés : `language` (`fr` | `en`), `textTranslation` (code, `null` pour le texte du jour), `textTranslationName`, `originalText`, `originalReference`.
  - L'application affiche donc le verset traduit sans mise à jour.
- **Site** : page du verset (version par défaut quand la personne n'en a pas choisi) et carte « Verset du jour » de l'accueil. Un choix de version fait sur la page (`?version=`) reste prioritaire.
- **Code** : `DailyVerses::localized()`, `translationFor()`.
- **Limite** : l'image de partage du verset (aperçu des réseaux sociaux) reste en français.

## Versions de la Bible

- Traductions proposées : celles **installées** sur le serveur (`bible_books`), français d'abord. Nom et langue : `config/bible.php`.
- Pour chaque traduction, les versets de la plage présents dans `bible_verses` ; **`available: false`** si aucun (version non sélectionnable, barrée dans l'application, grisée sur le site). Une plage partiellement présente montre les versets présents.
- Le **texte du jour** reste toujours disponible (version par défaut). Les accolades d'édition de certaines sources (KJV : « {is} ») sont retirées.
- **Version préférée** : application → stockage sécurisé de l'appareil (`daily_verse_translation`) ; site → session (`?version=KJV`, `?version=ORIGINAL` pour revenir au texte du jour). Si la version préférée n'a pas ce passage, le texte du jour s'affiche avec un message.
- **Comparer** : toutes les versions l'une sous l'autre (site `?comparer=1`), chacune avec sa référence.
- **Nom du livre dans la langue de la version** (2026-10-22) : chaque version porte `bookName` et `reference`, construits avec le nom du livre de **sa** traduction (`bible_books.name` : « Jean 3:16 » en LSG, « John 3:16 » en KJV ; nom français si la traduction n'a pas ce livre). Le site affiche la référence de la version choisie (premier écran, mode lecture, partage, « Témoigner »). `parsed.bookName` / `parsed.label` restent en français (référence du texte du jour). API : `GET /daily-verse/{id}/versions` et `GET /daily-verse?translation=` (`translation.reference`). Application : même règle (`VerseVersion.reference`, et hors connexion le nom du livre de la Bible téléchargée) ; une Bible téléchargée garde les noms reçus au téléchargement. Pour que les noms soient justes, chaque traduction doit être importée avec les noms de sa langue (voir [versions de la Bible](versions-de-la-bible.md)) ; le lecteur `/bible` et `GET /bible/…` les utilisent déjà.
- **Hors connexion (application)** : le passage est relu dans les **Bibles téléchargées** sur l'appareil (même analyse de la référence, `lib/features/daily_verse/models/verse_reference.dart`). Sans Bible téléchargée : texte du jour + invitation à en télécharger une. Le verset lui-même vient du cache du jour (`dailyVerseProvider`) ; s'il n'y a ni réseau ni cache, le verset de secours (Jérémie 29:11, `id = 0`) s'affiche et la participation est indiquée « disponible une fois en ligne ».

## Participation

| | Règles |
|---|---|
| **Sujets de prière** | Requête de prière ordinaire (`prayer_requests`) avec **`daily_verse_id`** : mêmes règles que les requêtes (visibilité, anonymat, « Je prie », encouragements, signalement, modération) — voir [requetes-de-priere.md](requetes-de-priere.md). La liste du verset suit la visibilité de chaque requête. |
| **Commentaires** | 2 à 1 000 caractères, compte actif, 20 par minute. Mentions « @ » ([mentions.md](mentions.md)) : la personne mentionnée est prévenue (`mention`, payload `daily_verse_id`). Suppression : l'auteur ou la modération (suppression douce). **Signalement** : une fois par personne, pas sur son propre commentaire ; au **3e signalement**, le commentaire est **retiré** (visible seulement de son auteur, avec la mention « Retiré », et de la modération). |
| **Témoignages liés** | Témoignages **publiés et publics** dont la référence (`bible_ref`) ou le verset saisi (`bible_verse`) cite un passage qui **recoupe** celui du jour (même livre, même chapitre, versets qui se chevauchent). 20 au plus, récents d'abord (300 candidats examinés). « **Témoigner sur ce verset** » : application → Publier avec la référence pré-remplie (`bibleVerseToInsertProvider`) ; site → `/publish?verset=…&reference=…`. |

## Données

| Table | Contenu |
|---|---|
| `prayer_requests.daily_verse_id` | Verset de rattachement (nul si aucun ; mis à nul si le verset est supprimé) |
| `daily_verse_comments` | `daily_verse_id`, `user_id`, `body` (jetons de mention `@[Nom](user:id)`), `report_count`, `hidden_at` ; suppression douce |
| `daily_verse_comment_reports` | `daily_verse_comment_id`, `reporter_id` (unique), `reason` |

Migration : `2026_10_06_400001_create_daily_verse_participation`.

## API mobile (`/api/v1`)

| Méthode | Route | Accès | Rôle |
|---|---|---|---|
| GET | `/daily-verse?date=AAAA-MM-JJ&translation=KJV` | public | Verset (du jour par défaut). Ajoute `parsed` (livre, chapitre, versets), `translation` (le passage dans cette version, si demandé), `counts.comments`, `counts.prayerRequests` (publiques), `webUrl`. |
| GET | `/daily-verse/{id}` | public | Même format pour un verset donné |
| — | (champs ajoutés le 2026-10-27) | | `weekTheme` `{ year, week, theme, keyVerse, period, startDate, endDate }` ou `null`, `version` (« LSG 1910 »), `dayName` (« Dimanche ») |
| GET | `/daily-verse/weeks/{year}/{week}` | public | Thème de la semaine et ses versets : `{ year, week, theme, keyVerse, period, startDate, endDate, verses: [{ id, date, dayName, reference, verseText, version }] }` ; 404 si la semaine n'existe pas |
| GET | `/daily-verse/versions?date=` · `/daily-verse/{id}/versions?translation=` | public | Le passage dans chaque version |
| POST | `/daily-verse/{id}/react` `{ type: like\|pray\|amen, active? }` | connecté | Bascule (ou fixe) une réaction ; renvoie le verset. `POST/DELETE /daily-verse/react` (verset du jour) restent disponibles. Ajouter `pray` exige le [temps de prière](temps-de-priere.md) (2026-10-19, sinon 422) ; `like`, `amen` et les retraits ne sont jamais bloqués. `again: true` : prier encore ; `{ active: false, last: true }` : retirer la dernière prière ; `myPrayerTimes` ([prier-plusieurs-fois.md](prier-plusieurs-fois.md)). |
| POST | `/daily-verse/{id}/pray/start` | connecté | Commence le temps de prière avant « Je prie » : `{ startedAt, requiredSeconds, confirmableAt }`. Site : `POST /verset-du-jour/{id}/je-prie/commencer` (`daily-verse.pray.start`), le bouton « Je prie » ouvre l'écran « Temps de prière ». |
| GET | `/daily-verse/{id}/comments?limit=30` | public | Commentaires, récents d'abord (`meta` de pagination) |
| POST | `/daily-verse/{id}/comments` `{ body, mention_ids? }` | connecté | 201 |
| DELETE | `/daily-verse/{id}/comments/{commentId}` | auteur, modération | |
| POST | `/daily-verse/{id}/comments/{commentId}/report` `{ reason }` | connecté | `inappropriate_content`, `hate_speech`, `spam`, `other` |
| GET | `/daily-verse/{id}/prayer-requests?limit=20` | public | Requêtes rattachées visibles (`PrayerRequestResource`, `meta.canCreate`). Aussi : `GET /prayer/requests?scope=verse&daily_verse_id=`. |
| POST | `/prayer/requests` `{ body, visibility?, is_anonymous?, daily_verse_id }` | connecté | Création d'une requête rattachée (`dailyVerseId` dans la ressource) |
| GET | `/daily-verse/{id}/testimonies` | public | Témoignages liés (`TestimonyResource`) |

Versions (`GET /daily-verse/{id}/versions`) :

```json
{
  "id": 12,
  "reference": "Jean 3 : 16-17",
  "parsed": { "book": 43, "chapter": 3, "verseStart": 16, "verseEnd": 17, "bookName": "Jean", "label": "Jean 3:16-17" },
  "original": { "code": null, "name": "Texte du jour", "text": "Car Dieu a tant aimé le monde…" },
  "defaultTranslation": "LSG",
  "versions": [
    { "code": "LSG", "name": "Louis Segond 1910", "language": "fr", "langName": "Français",
      "bookName": "Jean", "reference": "Jean 3:16-17", "available": true,
      "text": "Car Dieu a tant aimé… Dieu, en effet, …", "verses": [{ "verse": 16, "text": "…" }, { "verse": 17, "text": "…" }] },
    { "code": "KJV", "name": "King James Version", "language": "en", "langName": "English",
      "bookName": "John", "reference": "John 3:16-17", "available": true, "text": "For God so loved the world…", "verses": [] },
    { "code": "BBE", "name": "BBE", "language": null, "langName": null,
      "bookName": "John", "reference": "John 3:16-17", "available": false, "text": null, "verses": [] }
  ]
}
```

Commentaire :

```json
{ "id": "uuid", "body": "Merci @Paul", "mentions": [{ "id": "uuid", "displayName": "Paul" }],
  "user": { "id": "uuid", "displayName": "Marie", "initials": "M", "avatarUrl": null, "isVerified": false },
  "isMine": false, "isHidden": false, "canDelete": false, "canReport": true, "createdAt": "2026-10-06T08:00:00+00:00" }
```

## Site

- Depuis le 2026-10-13, le premier écran (et la carte de l'accueil) prend le design du jour ou celui choisi par le membre ; J'aime, Commentaires et Partager y restent, tandis que Je prie, Écouter et Mode lecture passent dans la première carte blanche. Voir [designs-du-verset.md](designs-du-verset.md).
- `GET /verset-du-jour` et `/verset-du-jour/{AAAA-MM-JJ}` (`daily-verse.show`) : bloc immersif, versions (`?version=`, `?comparer=1`), onglets `?onglet=prieres|commentaires|temoignages`, mode lecture (`<dialog>`), partage (feuille du système ou copie), écoute (`speechSynthesis`, bouton masqué si le navigateur ne sait pas lire), lien « Lire tout le chapitre » vers `/bible`.
- Actions (connecté) : `POST /verset-du-jour/{id}/reagir` (`type`), `/prieres`, `/commentaires`, `DELETE /commentaires/{comment}`, `POST /commentaires/{comment}/signaler`.
- Accueil : le bloc « Verset du jour » (`home/index.blade.php`) est un lien vers la page.
- Fichiers : `Web\DailyVerseController`, `resources/views/daily-verse/show.blade.php`, composant `<x-verse-of-the-day-card />` ; designs : `resources/css/verse.css`, `resources/css/verse-designs.css` (généré), `resources/js/verse-style.js`.

## Application

- `lib/features/daily_verse/` : `openDailyVerse(context)` pousse `DailyVerseScreen` (route plein écran, fondu + léger glissement, sur le navigateur racine). Bannière de l'accueil : tap sur le verset (ou sur la ligne repliée) → plein écran ; le chevron déplie / replie toujours la bannière.
- Barre du haut : fermer, écouter (`ttsEngineProvider`, langue de la version), partager, mode lecture. Sous le verset : J'aime / Je prie (`dailyVerseProvider`), puces des versions + « Comparer », puis onglets **Sujets de prière** (`PrayerRequestFeedCard`, feuille `VersePrayerSheet`), **Commentaires** (`MentionTextField` / `MentionText`), **Témoignages**.
- Fournisseurs : `dailyVerseVersionsProvider`, `dailyVerseCommentsProvider`, `dailyVersePrayerRequestsProvider`, `dailyVerseTestimoniesProvider` (sans nouvel essai automatique : bouton « Réessayer »), `preferredVerseTranslationProvider`.
- `DailyVerse.fromJson` lit maintenant `counts.likes` / `counts.prayers` / `myReactions` (les compteurs de la bannière restaient à 0).

## Importer des versets du jour par thème (`verse:import`, 2026-10-22)

Source : `storage/dailyverse/versets_du_jour.json` (424 versets, Louis Segond, 18 thèmes, de « Le témoignage en justice et le faux témoignage » à « Pierre, Jean, Jude, Apocalypse »). Chaque entrée : `id`, `testament`, `theme`, `livre`, `chapitre`, `versets` (« 16 » ou « 15-16 », 2 versets au plus), `nb_versets`, `reference`, et depuis le 2026-10-22 **`texte`** (texte Louis Segond 1910 tiré de `storage/bible/french.json`).

```bash
# Contrôle sans écrire
php artisan verse:import storage/dailyverse/versets_du_jour.json --dry-run

# Import, un verset par jour à partir d'une date, dans l'ordre du fichier (thème après thème)
php artisan verse:import storage/dailyverse/versets_du_jour.json --start=2026-10-23
```

| Option | Effet |
|---|---|
| `--start=AAAA-MM-JJ` | Programme (`scheduled_date`) un verset par jour à partir de cette date, en sautant les dates déjà prises. Les versets qui ont déjà une date la gardent. Sans `--start` : aucune date, les versets entrent dans la rotation des versets actifs |
| `--bible-file=storage/bible/french.json` | Texte pris dans un fichier Bible « un objet par verset » quand l'entrée n'a pas de `texte` |
| `--translation=LSG` | Sinon, texte pris dans `bible_verses` (vérifier que `LSG` est bien la Louis Segond, voir [versions de la Bible](versions-de-la-bible.md)) |
| `--save-texts` | Écrit les textes trouvés dans le fichier (champ `texte`) : le fichier suffit ensuite pour importer ailleurs |
| `--dry-run` | Contrôle et rapport, sans écriture |

- **Thème** : enregistré tel quel dans `daily_verses.theme` (colonne passée de 50 à 150 caractères, migration `2026_10_22_100001_widen_daily_verses_theme`). `themeLabel()` (API `themeLabel`, historique, page) traduit les anciens codes (`faithfulness`…) et renvoie sinon le libellé importé.
- **Référence** : contrôlée par `BibleReference::parse`, qui accepte désormais les livres d'un seul chapitre sans numéro de chapitre (« Jude 24-25 », « 3 Jean 3 », « Philémon 6 » = chapitre 1) quand la chaîne entière est la référence ; la recherche de références dans un texte libre (`findAll`) n'est pas modifiée.
- **Doublons** : un verset de même référence est mis à jour (texte, thème, actif) ; ses réactions, commentaires et sa date sont gardés. Relancer la commande ne crée rien de plus. Une erreur (référence non reconnue, texte introuvable, référence en double dans le fichier) annule tout l'import.
- **Fait en local le 2026-10-22** : 413 versets créés, 11 mis à jour (déjà présents, gardent leur date de juin), programmés du 23/10/2026 au 09/12/2027.

**En ligne (sans SSH)** : la migration `2026_10_22_100002_import_versets_du_jour`, lancée une fois par `POST /_deploy`, appelle `verse:import` sur le fichier déployé avec `--start` = lendemain du déploiement (Africa/Porto-Novo). Les textes étant dans le fichier, ni `storage/bible/` ni la table `bible_verses` ne servent. Elle ne fait rien pendant les tests ni si le fichier manque, et échoue (déploiement à relancer) si l'import échoue. Avec SSH, la commande ci-dessus fait la même chose.

**Effet à connaître** : tant qu'un jour n'a pas de verset programmé, le verset affiché est choisi d'après la date parmi **tous** les versets actifs. Ajouter des versets change donc le verset du **jour de l'import** et celui des jours passés non programmés dans l'historique (`/verset-du-jour/historique`). Commentaires, réactions et requêtes restent rattachés à leur verset. À partir du lendemain, chaque jour a son verset programmé.

## Importer une année de versets par semaine thématique (`versets:import`, 2026-10-27)

### Commande à lancer (sur le serveur, après `php artisan migrate --force`)

1. Déposer le fichier dans `storage/dailyverse/`.
2. Lancer :

```bash
# Calendrier du 27 octobre 2026 au 31 décembre 2027 (431 versets, 63 semaines)
php artisan versets:import dailyverse/versets_du_jour_2026_2027.json

# Année 2027 seule (365 versets) : fichier par défaut, sans argument
php artisan versets:import

# Repartir de zéro sur la période du fichier (confirmation demandée)
php artisan versets:import dailyverse/versets_du_jour_2026_2027.json --fresh
```

- **Chemin** : relatif au dossier `storage/` (`dailyverse/…`), ou absolu.
- **Résultat** : barre de progression, puis « 63 semaine(s) et 431 verset(s) importés, du 2026-10-27 au 2027-12-31. ».
- **Relancer** la même commande met à jour sans rien dupliquer.
- **Aujourd'hui** : importer un fichier qui commence aujourd'hui remplace aussi le verset du jour en cours. Ses commentaires et réactions restent rattachés à la même ligne, mais sous le nouveau texte. Pour l'éviter, importer la veille du premier jour du fichier.

### Format

`version_biblique`, `semaines: [{ annee, semaine, debut, fin, thematique, verset_cle, periode|null, versets: [{ date, jour, reference, texte }] }]`.

- **Année d'une semaine** : son champ `annee`, sinon le champ `annee` du fichier (`versets_du_jour_2027.json`). Un fichier peut ainsi couvrir plusieurs années, et un même numéro de semaine exister pour deux années différentes (semaines 44 à 53 de 2026 et de 2027).
- Les semaines sont numérotées dans leur année, du dimanche au samedi. La première et la dernière d'un fichier peuvent être incomplètes.

| Règle | Détail |
|---|---|
| Où | Dans le verset du jour existant (`daily_verses`) : chaque date reçoit son verset (`scheduled_date`, `reference`, `verse_text`, `version`), son thème (`theme`, `week_number`, `theme_semaine_id`). Le site et l'application l'affichent sans changement, avec réactions, commentaires, designs et versions. |
| Thèmes | Table `themes_semaine` (`annee`, `numero_semaine` : unique par année, `thematique`, `verset_cle`, `periode`, `date_debut`, `date_fin`), modèle `ThemeSemaine` (`versets()`). |
| Remplacement | Le verset déjà programmé à une date du fichier est **remplacé** (même ligne : ni doublon, ni changement d'identifiant). |
| Idempotent | Relancer la commande met à jour (`updateOrCreate` des thèmes, une ligne par date). Le tout dans une transaction : en cas d'erreur, rien n'est écrit. |
| Validation | Avant toute écriture : clés présentes, année de chaque semaine, numéros de semaine 1 à 53 sans doublon dans une même année, dates `AAAA-MM-JJ` valides, dans l'année et dans la semaine, aucune date en double, référence de 100 caractères au plus. Erreurs listées (15 au plus), code de sortie 1. |
| `--fresh` | Supprime d'abord les versets programmés **sur la période du fichier** (première → dernière date) **avec leurs réactions et commentaires**, et les thèmes de ses semaines ; une confirmation est demandée. Les jours antérieurs ne sont jamais touchés. |
| Version | « Louis Segond 1910 (…) » → `LSG 1910` (défaut). |
| Affichage | Barre de progression, puis « 53 semaine(s) et 365 verset(s) importés pour 2027. » |

Fichiers : `app/Console/Commands/ImportVersetsDuJour.php`, `app/Models/ThemeSemaine.php`, `app/Models/DailyVerse.php` (`themeSemaine`, `scopePourDate`, `dayName`, `forDate`), `app/Http/Resources/ThemeSemaineResource.php`, migrations `2026_10_27_100001_create_themes_semaine_table`, `2026_10_27_100002_add_theme_semaine_to_daily_verses_table`, tests `tests/Feature/VersetsDuJourImportTest.php` (fixture `tests/fixtures/versets_du_jour_test.json`).

## Tests

- Import : `php artisan test --filter=ImportDailyVersesTest` (textes depuis un fichier Bible ou `bible_verses`, thème, `--save-texts` puis import depuis le fichier seul, pas de doublon, `--start` et dates prises, erreur sans écriture, livres d'un chapitre).
- Import par semaine : `php artisan test --filter=VersetsDuJourImportTest` (nombre de lignes, second import sans doublon, verset déjà programmé remplacé, `--fresh` et sa confirmation, fichier invalide, verset du jour au fuseau de Porto-Novo, repli sur le même jour d'une autre année, 404, semaine et ses 7 versets).
- Serveur : `php artisan test --filter=DailyVerseTest` (analyse des références, versions complètes / partielles / absentes, référence non reconnue, réactions, commentaires et droits, mentions, signalements et retrait, requêtes liées et visibilité, témoignages liés, stabilité du verset sans programmation, pages du site).
- Application : `flutter test test/daily_verse_test.dart` (analyse des références, lecture des réponses, changement de version, version absente, « Comparer », onglets, hors connexion, verset de secours, mode lecture, 320 / 390 px × 1 / 1,3 sans débordement).

## Limites connues

- Les témoignages liés sont retrouvés par analyse du texte (pas d'index) : 300 témoignages candidats au plus, ceux qui contiennent le numéro du chapitre.
- Mentions : pas d'autocomplétion « @ » dans le formulaire du site (le texte « @Nom » reste simple). La notification de mention porte `dailyVerseId` (push : `daily_verse_id`) ; l'application ne s'en sert pas encore pour ouvrir le verset.
- La réaction « J'aime / Je prie » de l'accueil de l'application passe par `POST/DELETE /daily-verse/react` (verset du jour). Depuis le 2026-10-06, ces routes sont **idempotentes** : réagir deux fois ne renvoie plus 409. `POST /daily-verse/{id}/react { type, active }` reste préférable.
- `POST /daily-verse/share` et `POST /testimonies/{id}/share` (compteurs de partage) acceptent les **invités** (30 par minute) ; un témoignage non publié ou privé renvoie 404.
- Pas de file de modération dédiée aux commentaires signalés : retrait automatique au 3e signalement, suppression par la modération depuis la page.


## Historique des versets (2026-10-19)

- API `GET /api/v1/daily-verse/history?page=&limit=` (public, `limit` ≤ 50, 15 par défaut) : un élément par jour, du plus récent (aujourd'hui) au plus ancien, depuis le premier verset enregistré (366 jours au plus) — `{ date, id, reference, verseText, theme, themeLabel, counts: { likes, prayers, comments, testimonies, prayerRequests }, myReactions, webUrl }`, `meta` (`currentPage`, `lastPage`, `total`, `perPage`). Le verset d'un jour est celui affiché ce jour-là (programmé, sinon choisi d'après la date). Nombre de témoignages liés mis en cache 10 minutes (`DailyVerses::testimonyCount`).
- Site : page « Versets précédents » `/verset-du-jour/historique` (`daily-verse.history`), alignée sur l'écran de l'application (`VerseHistoryScreen`) : un jour par carte, du plus récent au plus ancien ; **aujourd'hui** mis en avant (liseré jaune, « Aujourd'hui », ouvre `/verset-du-jour`) ; date · thème, verset, référence ; « J'aime », prières, commentaires et témoignages (pluriels accordés), réactions de la personne connectée en couleur ; un clic ouvre le verset du jour concerné (`/verset-du-jour/{date}`). Textes : `lang/*/daily_verse_history.php` (mêmes libellés que l'application).
- **Menu** (2026-10-19, comme le menu de l'application) : groupe « Découvrir » → « Verset du jour » (`daily-verse.show`) et « Versets précédents » (`daily-verse.history`). Le premier écran du verset du jour a un bouton « Versets précédents » à côté de « Fermer ».
- Application : menu latéral « Verset du jour » et « Versets précédents » ; un jour passé s'ouvre dans la page du verset (mêmes onglets, « J'aime » et « Je prie » sur ce verset via `POST /daily-verse/{id}/react`).
- Tests : `tests/Feature/DailyVerseHistoryTest.php`.
