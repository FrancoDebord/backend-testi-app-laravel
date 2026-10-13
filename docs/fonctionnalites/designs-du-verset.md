# Designs du verset du jour

## But

Le verset du jour s'affiche dans un **design** : fond peint (dégradés, halos, formes, motifs, grain), couleur du texte et ornement. Il y en a **12**, identiques dans l'application Flutter et sur le site. Chaque jour, un **design du jour** est choisi pour tout le monde. Chacun peut en choisir un autre, ainsi que la **police**, la **taille** et l'**alignement** du texte. Ses choix suivent son compte d'un appareil à l'autre.

Une **image de partage** (1080 × 1350) est générée dans le même design pour les aperçus WhatsApp, Facebook, etc.

## Catalogue : `config/verse_designs.php`

Source unique de vérité. Les identifiants doivent rester **identiques à ceux de l'app Flutter** (`kVerseDesigns`, `VerseFont`, `VerseTextSize`, `VerseAlign`). **On ne renomme jamais un id** : pour retirer un design, `active => false`.

| Ordre | id | Nom | Ambiance (`mood`) | Rendu (thème clair) |
|---|---|---|---|---|
| 1 | `aube` | Aube | dawn | clair |
| 2 | `nuit` | Nuit étoilée | night | sombre |
| 3 | `ocean` | Océan profond | deep | sombre |
| 4 | `lumiere` | Lumière douce | light | clair |
| 5 | `braise` | Braise | warm | sombre |
| 6 | `minimal` | Minimal | minimal | clair |
| 7 | `vitrail` | Vitrail | deep | sombre |
| 8 | `crepuscule` | Crépuscule | dawn | sombre |
| 9 | `matin` | Matin clair | light | clair |
| 10 | `or` | Or doux | warm | clair |
| 11 | `encre` | Encre | deep | sombre |
| 12 | `ardoise` | Ardoise | minimal | sombre |

Le **rendu** (`tone` : `light` = texte foncé sur fond clair, `dark` = texte clair sur fond sombre) n'est pas une donnée de référence. Il est calculé d'après la luminosité du fond, et `tone_light_theme` sert seulement de contrôle : le script de génération signale tout écart.

| Réglage | Valeurs (défaut en gras) |
|---|---|
| `font` | **`lora`** (Lora), `sourceSerif` (Source Serif 4), `inter` (Inter), `nunitoSans` (Nunito Sans) |
| `text_size` | `small` (× 0,86), **`medium`** (× 1), `large` (× 1,16) |
| `text_align` | **`start`** (à gauche), `center` |

### Taille du texte

C'est la même interpolation que dans l'app :

```
len    = longueur du texte affiché, sans espaces de début ni de fin (« » compris)
t      = clamp((len − 80) / 240, 0, 1)
taille = max((max − (max − min) × t) × facteur, min)
```

| Contexte | min | max |
|---|---|---|
| Carte (accueil) | 18 | 24 |
| Plein écran | 22 | 34 |
| Image partagée | 18 | 30 |

Valeurs de contrôle (`medium`) : 80 caractères ou moins → 24 / 34 ; 200 → 21 / 28 ; 320 ou plus → 18 / 22. Interligne 1,4. Valeurs dans `text_size_range`, `length_from`, `length_span` et `line_height`.

Une fois la taille calculée :

- **carte** : la taille est utilisée telle quelle. Au-delà de 8 lignes (`card_max_lines`), le texte est coupé et « Lire en entier » apparaît ;
- **plein écran** : `verse-style.js` réduit la taille de 1 px en 1 px jusqu'à ce que le premier écran tienne, sans descendre sous 22 px. Si 22 px ne suffit pas, le premier écran s'allonge ;
- **image** : même réduction, sans descendre sous 18. L'image est dessinée à l'échelle 3 (360 × 450 px logiques, `share_scale`).

## Design du jour : `App\Services\VerseDesignOfTheDay`

C'est l'algorithme de l'app (`dailyVerseDesign`), sur le **calendrier Africa/Porto-Novo** :

```
jours = jours entre le 2024-01-01 et la date
n     = nombre de designs ACTIFS, dans l'ordre du catalogue
pas   = premier nombre de [5, 7, 3, 1] premier avec n
index = ((jours × pas) mod n + n) mod n
```

Comme le pas est premier avec `n`, deux jours consécutifs n'ont jamais le même design, et tous les designs actifs reviennent. Références vérifiées dans l'app : 2026-10-13 → `braise`, 14 → `or`, 15 → `ocean`, 16 → `crepuscule`.

Si un design est désactivé, `n` change et la suite des designs change aussi. L'app lit donc `design_of_the_day` dans l'API plutôt que de le calculer.

**Fuseau** : `DailyVerse::today()` utilise lui aussi Africa/Porto-Novo depuis le 2026-10-13. Le verset et le design changent ensemble à minuit, heure du Bénin ; auparavant le verset changeait à minuit UTC, soit 1 h du matin à Porto-Novo.

## Préférences du membre

Table `verse_style_preferences` (une ligne par membre au plus) : `user_id` (unique, supprimée avec le compte), `design_id`, `design_chosen_on` (date), `design_always`, `font`, `text_size`, `text_align`. Modèle `VerseStylePreference`, relation `User::verseStylePreference()`, règles dans `App\Services\VerseStyles`.

**Design affiché** (`effective_design_id`) :

- le design choisi si `design_always` est vrai, **ou** s'il a été choisi aujourd'hui (`design_chosen_on` = date du jour à Porto-Novo) ;
- sinon le design du jour. C'est aussi le cas si le design choisi a été désactivé depuis.

| Où | Stockage |
|---|---|
| App | `shared_preferences` : `verse_design_id`, `verse_design_chosen_on`, `verse_design_always`, `verse_font`, `verse_text_size`, `verse_text_align`, synchronisés avec `PUT /me/verse-style` |
| Site, connecté | `PUT /verset-du-jour/style` (session + CSRF), mêmes règles que l'API |
| Site, visiteur | `localStorage`, **mêmes noms de clés** que l'app |

## API (`/api/v1`)

| Méthode | Route | Accès | Rôle |
|---|---|---|---|
| GET | `/verse-designs` | public | Catalogue : `designs` (actifs, triés : `id`, `name`, `mood`, `order`, `tone`), `fonts`, `text_sizes`, `text_aligns`, `text_size_range`, `line_height`, `defaults`. Cache 24 h. |
| GET | `/me/verse-style` | connecté | Préférences, ou valeurs par défaut s'il n'y en a pas |
| PUT | `/me/verse-style` | connecté | Mise à jour (partielle possible). Valeur inconnue du catalogue → **422**. `design_chosen_on` au format `AAAA-MM-JJ`. Un design désactivé est refusé. |

Champs ajoutés à `GET /daily-verse` et `GET /daily-verse/{id}` (les autres champs ne changent pas) :

```json
{
  "design_of_the_day": "braise",
  "user_style": {
    "design_id": "vitrail", "design_chosen_on": "2026-10-13", "design_always": false,
    "font": "lora", "text_size": "medium", "text_align": "start"
  },
  "effective_design_id": "vitrail",
  "share_image_url": "https://…/verset-du-jour/2026-10-13/image.png"
}
```

`design_of_the_day` est calculé pour la date demandée (`?date=`) ou celle du verset. Pour un visiteur, `user_style` vaut `null` et `effective_design_id` vaut `design_of_the_day`.

## Couleurs : `scripts/generate-verse-colors.php`

Les couches de chaque design sont celles de `docs/flutter/verse_design.dart`, portées en PHP dans `App\Support\VerseColors::recipes()`. On y retrouve :

- les teintes de travail (`VerseTones`) : `deep()` plafonne la luminosité HSL du bleu à 0,34 et arrondit à 8 bits ; les teintes sont obtenues par `tint` (vers le blanc) et `shade` (vers le noir) ;
- les encres : claire `#FFFFFF`, foncée `#0B2044` ;
- les accents essayés dans l'ordre : sur fond sombre `#FCCA3F`, `#F6B168`, `#A3B5D5` ; sur fond clair `#184696`, `#8C4E0D`, `#60490B`.

Le script choisit l'encre qui contraste le mieux, puis ajoute un voile si nécessaire, comme l'app (cible 4,5 + 0,15 de marge). Il prend le premier accent qui atteint cette cible, sinon l'encre. Le contraste est garanti sur quatre formats : carte, plein écran sur téléphone, plein écran sur ordinateur, image.

```bash
php scripts/generate-verse-colors.php            # palette Krea (défaut)
php scripts/generate-verse-colors.php --primary=#184797 --secondary=#F18717 --tertiary=#FCC11D --surface=#FFFFFF --on-surface=#263238
```

Il écrit `config/verse_design_colors.php` (pour l'image et les tests) et `resources/css/verse-designs.css` (une classe `.verse-design--{id}` par design). Ces deux fichiers ne se modifient pas à la main. Après une régénération : recompiler les assets et vider `storage/app/public/verse-images/`.

Résultat avec la palette Krea : `nuit` (voile 0,26) et `crepuscule` (voile 0,18) reçoivent un voile. Aucun accent n'atteint la cible sur `nuit`, `ocean`, `braise`, `vitrail`, `crepuscule` et `ardoise` : la référence y prend donc la couleur du texte.

**Différences avec l'app**, de rendu seulement (les couleurs sont les mêmes) :

- les formes organiques sont des disques flous sur le site, et des polygones générés par un tirage déterministe propre au serveur pour l'image ;
- les étoiles et le grain sont tirés différemment.

## Site

- **Carte de l'accueil** : `<x-verse-of-the-day-card />` (`App\View\Components\VerseOfTheDayCard`), dans le design affiché.
- **Page plein écran** `/verset-du-jour/{date?}` :
  - le premier écran est entièrement occupé par le design : verset, référence, J'aime / Commentaires / Partager, vignette « Aa » en bas à droite pour ouvrir le sélecteur, indicateur « Faire défiler » ;
  - au défilement, sur des cartes blanches : Je prie, Écouter, Mode lecture, Design et texte, puis les versions et les onglets (inchangés) ;
  - le mode lecture reprend le design affiché.
- **Sélecteur** (`<dialog id="verse-style-panel">`) :
  - onglet **Designs** : 12 vignettes avec un aperçu réel, le design affiché coché, la case « Toujours utiliser ce design » ;
  - onglet **Texte** : police, taille, alignement ;
  - le changement se fait en fondu, ou instantanément si le système demande moins d'animations.
- **Polices** embarquées dans `resources/fonts/*.woff2` (polices variables 400 à 600, sous-ensemble latin, licence OFL), chargées par `@font-face` dans `resources/css/verse.css`, sans service externe.
- **CSS** :
  - `resources/css/verse.css` : surface `.verse-design`, couches `__bg::before` (halos et formes floutés) et `__bg::after` (motif, grain SVG `feTurbulence`, voile), classes `verse-font--*` et `verse-align--*`, actions `.verse-action` aux couleurs du design ;
  - `--verse-long` (plus grand côté) est renseigné par `ResizeObserver`.
- **JS** : `resources/js/verse-style.js`, chargé à la demande par `app.js` quand la page contient `#verse-style-config`.
- **Aperçu des réseaux sociaux** : `og:title`, `og:description`, `og:image` (1080 × 1350), `twitter:card=summary_large_image` (pile `@stack('meta')` du layout).

## Image de partage

`GET /verset-du-jour/{AAAA-MM-JJ}/image.png?design={id}` (`daily-verse.image`, 60 par minute) :

- sans `design`, ou avec un design inconnu ou désactivé, l'image utilise le design du jour de cette date ;
- `App\Services\VerseShareImage` utilise Intervention Image 3 avec GD (pas de navigateur : hébergement mutualisé LWS) ;
- le dégradé et les halos sont calculés au 1/6 de la résolution puis agrandis ; formes, motifs, grain, voile et texte sont dessinés à pleine résolution ;
- polices TTF de `resources/fonts/ttf` (Lora pour le verset et la référence, Inter pour les libellés), retour à la ligne automatique ;
- la référence est sous le verset, « ARISE & SHINE Krea · TestiApp » en bas ;
- cache : `storage/app/public/verse-images/{date}-{id}.png`, générée une seule fois (environ 1 à 2 s). Les images d'une date sont supprimées quand son verset change (texte, référence, date). Toutes le sont si un verset est activé ou désactivé, ou s'il n'a pas de date, car le verset de repli d'une date en dépend.

## Tests

`php artisan test --filter=VerseDesignTest` :

- dates de référence de l'app, stabilité, jamais deux jours identiques (400 jours), 10 designs actifs (30 jours, au moins 8 designs) ;
- catalogue et rendus ;
- préférences (valeurs par défaut, enregistrement, mise à jour partielle, 422 pour chaque champ, route du site) ;
- design affiché (toujours, aujourd'hui, hier, design désactivé) ;
- changement à minuit à Porto-Novo (23 h 30 UTC = lendemain) ;
- contraste ≥ 4,5 pour les 12 designs (texte et référence) ;
- taille du texte (valeurs de contrôle) ;
- image (PNG 1080 × 1350, cache, suppression) ;
- page et carte.

## Limites connues

- Le design du jour ne correspond à celui de l'app que si les deux connaissent les mêmes designs actifs. D'où la lecture de `design_of_the_day` par l'app.
- Le visiteur voit d'abord le design par défaut, puis son choix (`localStorage`) en fondu au chargement de la page.
- L'image de partage utilise toujours la police Lora, alignée à gauche, quels que soient les réglages du membre.
