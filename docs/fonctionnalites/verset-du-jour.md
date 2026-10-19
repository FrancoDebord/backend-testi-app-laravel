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
| **Choix** | Verset actif programmé ce jour-là (`scheduled_date`, jour du calendrier **Africa/Porto-Novo** depuis le 2026-10-13 : le verset change à minuit au Bénin, en même temps que le [design du jour](designs-du-verset.md)). Sinon, un verset actif choisi **d'après la date** (même verset toute la journée, pour que commentaires et requêtes restent rattachés au verset affiché). Auparavant ce repli était tiré au hasard à chaque appel. |
| **Référence** | Texte libre saisi par l'équipe (« Jérémie 29 : 11 », « Jean 3:16-17 »). `App\Support\BibleReference` la reconnaît : noms français avec ou sans accents, noms anglais, abréviations courantes (« Jn », « Ps », « 1 Co »…), séparateurs `:` `.` `,`, plages `16-18`, `16–18`, `16 à 18`. Une plage qui déborde sur le chapitre suivant est réduite à son premier chapitre. |
| **Référence non reconnue** | Seul le **texte du jour** (saisi par l'équipe) est proposé ; pas de versions ni de témoignages liés. |

## Versions de la Bible

- Traductions proposées : celles **installées** sur le serveur (`bible_books`), français d'abord. Nom et langue : `config/bible.php`.
- Pour chaque traduction, les versets de la plage présents dans `bible_verses` ; **`available: false`** si aucun (version non sélectionnable, barrée dans l'application, grisée sur le site). Une plage partiellement présente montre les versets présents.
- Le **texte du jour** reste toujours disponible (version par défaut). Les accolades d'édition de certaines sources (KJV : « {is} ») sont retirées.
- **Version préférée** : application → stockage sécurisé de l'appareil (`daily_verse_translation`) ; site → session (`?version=KJV`, `?version=ORIGINAL` pour revenir au texte du jour). Si la version préférée n'a pas ce passage, le texte du jour s'affiche avec un message.
- **Comparer** : toutes les versions l'une sous l'autre (site `?comparer=1`).
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
| GET | `/daily-verse/versions?date=` · `/daily-verse/{id}/versions?translation=` | public | Le passage dans chaque version |
| POST | `/daily-verse/{id}/react` `{ type: like\|pray\|amen, active? }` | connecté | Bascule (ou fixe) une réaction ; renvoie le verset. `POST/DELETE /daily-verse/react` (verset du jour) restent disponibles. Ajouter `pray` exige le [temps de prière](temps-de-priere.md) (2026-10-19, sinon 422) ; `like`, `amen` et les retraits ne sont jamais bloqués. |
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
    { "code": "LSG", "name": "Louis Segond 1910", "language": "fr", "langName": "Français", "available": true,
      "text": "Car Dieu a tant aimé… Dieu, en effet, …", "verses": [{ "verse": 16, "text": "…" }, { "verse": 17, "text": "…" }] },
    { "code": "BBE", "name": "BBE", "language": null, "langName": null, "available": false, "text": null, "verses": [] }
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

## Tests

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
