# Affichage des listes et réglages de lecture (site web)

## But

Reprendre sur le site ce que propose l'application mobile :

- **choisir la qualité** d'une vidéo ou d'un audio, pour économiser des données (comme sur YouTube) ;
- **lire en boucle** ou **enchaîner automatiquement** le témoignage suivant ;
- choisir entre **grandes cartes** et **liste compacte** horizontale, qu'on déplie pour voir les détails.

Les versions allégées sont produites par le serveur : voir [qualités des médias](qualites-media.md).

## Utilisation

### Page de lecture (`/videos/{id}` et `/testimonies/{id}`)

Sous le lecteur vidéo ou audio, une barre de réglages :

| Réglage | Effet |
|---|---|
| **Vitesse** | 0,75× à 2× (existait déjà ; déplacé dans cette barre) |
| **Qualité** | **Auto** (indique la version choisie, par ex. « Qualité auto (360p) »), chaque version disponible (vidéo : 720p, 480p, 360p, 240p ; audio : 128, 64, 32 kbps), puis **Qualité d'origine**. Change de fichier sans perdre la position, la vitesse ni la lecture en cours. Affiché seulement si le témoignage a des versions allégées |
| **En boucle** | Relit le témoignage à la fin |
| **Lecture auto** | À la fin, bandeau « À suivre dans 5 s : titre » avec **Lire maintenant** et **Annuler**, puis ouverture du suivant, qui démarre seul. Affiché seulement s'il existe un suivant |

Texte : aucun réglage de lecture.

### Listes

Sur l'accueil (fil « Derniers témoignages »), Explorer, Vidéos, un profil, Sauvegardes et Mes témoignages, deux boutons au-dessus de la liste : **Grandes cartes** / **Liste compacte** (icônes seules sur mobile).

Une **ligne compacte** montre la miniature, le titre, l'auteur (avec la coche d'organisation vérifiée), le type ou le statut, les vues et la date. Le **chevron** déplie : description (4 lignes), référence biblique, catégorie, durée, J'aime, prières, commentaires et un bouton **Regarder** / **Écouter** / **Lire**.

« À la une », l'étagère Shorts et les directs gardent leur présentation.

**Page d'un témoignage** : « À regarder également » (site) et « Témoignages similaires » (application) utilisent le **format compact** (lignes dépliables) ; sur le site, dans la colonne de droite, miniature réduite (`'narrow' => true` du partiel `videos/partials/row`). L'application complète avec d'autres témoignages récents si la catégorie n'en a pas assez.

## Règles

| Règle | Détail |
|---|---|
| **Mode Auto** | Même logique que l'application : économiseur de données du navigateur (`navigator.connection.saveData`) ou réseau 2G → version la plus légère ; données mobiles ou 3G → au plus 360p / 64 kbps ; sinon au plus 720p / 128 kbps. Navigateur sans cette information (Safari, Firefox) : 720p / 128 kbps |
| **Choix manuel** | La version la plus haute qui ne dépasse pas le choix ; sinon la plus légère. Un choix « 480p » vaut pour les vidéos suivantes (360p si 480p n'existe pas) |
| **Version illisible** | Fichier absent ou conversion incomplète : retour **une fois** à la qualité d'origine, avec le message « Cette qualité n'est pas disponible… » |
| **Mémorisation** | Qualité vidéo, qualité audio, boucle, lecture auto : dans le navigateur (`localStorage`, clé `testiapp.playback`), valable sans compte. Lecture auto activée par défaut, boucle désactivée |
| **Suivant** | Premier élément de « À regarder également » du **même type** (vidéo → vidéo, audio → audio) ayant un fichier. Les témoignages déjà enchaînés dans l'onglet (`sessionStorage`, 50 au plus) sont évités ; si tous l'ont été, on reprend au premier. Ouvert sur la même page (`/videos/…` ou `/testimonies/…`). Jamais depuis le carnet privé |
| **Démarrage du suivant** | Le navigateur peut refuser une lecture avec le son sans clic ; la vidéo attend alors qu'on appuie sur Lecture |
| **Affichage des listes** | Cookie `feed_layout` (`cards` ou `compact`, chiffré par Laravel, un an). Valable sans compte, sur tous les appareils du même navigateur. « Afficher plus » (page Vidéos) suit le même choix |
| **Sans JavaScript** | Le lecteur natif lit la qualité d'origine ; Qualité, En boucle et Lecture auto sont masqués. Le choix d'affichage fonctionne (formulaire classique) ; les lignes compactes ne se déplient pas, mais titre et miniature restent des liens |
| **Économie de données** | Les lignes compactes ne chargent aucune vidéo pour la miniature (image de couverture ou pictogramme) ; les grandes cartes chargent toujours une image de la vidéo à l'approche de l'écran |

## Fonctionnement technique

### Qualités

`Testimony::playableRenditions()` renvoie les versions avec leur URL publique (`MediaFile::renditionsForApi`, même format que le champ `renditions` de l'API). Le partiel `watch` les place dans l'attribut `data-renditions` du lecteur, avec `data-original` (fichier d'origine), `data-kind` (`video` / `audio`) et `data-up-next` (liste `{id, url, title}` des suivants possibles). Le `<source>` reste le fichier d'origine : c'est ce qui est lu sans JavaScript. Le script remplace ensuite la source (`player.src`) par la version choisie.

Les attributs sont écrits avec `e()` et non avec `ComponentAttributeBag`, qui protège les guillemets par `\"` et casse le JSON dans un attribut HTML.

### Affichage des listes

| Élément | Rôle |
|---|---|
| `App\Support\FeedLayout` | Constantes, libellés, lecture du cookie (`current()`, `isCompact()`) |
| `POST /affichage` (`preferences.layout`, `DisplayPreferenceController`, 30/min) | Valide `layout` (`cards` / `compact`), pose le cookie, revient à la page précédente |
| `components/testimony-list` | Conteneur : grille (classes `gridClass`, littérales) ou `card divide-y` ; attribut `data-layout` |
| `videos/partials/cards` | Éléments de la liste, carte ou ligne selon le cookie (aussi pour la réponse JSON « Afficher plus ») |
| `videos/partials/row` | Ligne compacte et son panneau de détails |
| `components/layout-toggle` | Les deux boutons (`chip` / `chip-active`, `aria-pressed`) |

`Testimony::typePill()` et `Testimony::durationLabel()` regroupent la pastille de type et la durée, partagées par la carte et la ligne.

### JavaScript (`resources/js/videos.js`)

| Attribut | Comportement |
|---|---|
| `[data-player]` + `data-renditions`, `data-original`, `data-kind`, `data-testimony`, `data-up-next` | Lecteur |
| `[data-player-quality]` | Menu Qualité (masqué sans JavaScript) ; option `auto` avec `data-auto-label` |
| `[data-player-loop]`, `[data-player-autoplay]` | Bascules (`chip` / `chip-active`, `aria-pressed`) |
| `[data-up-next-banner]` avec `[data-up-next-title]`, `[data-up-next-count]`, `[data-up-next-play]`, `[data-up-next-cancel]` | Bandeau « À suivre » |
| `[data-row-toggle]` + `aria-controls` | Déplie la ligne compacte (`aria-expanded`, chevron retourné) |

## Fichiers

| Fichier | Contenu |
|---|---|
| `app/Support/FeedLayout.php` | Choix d'affichage |
| `app/Http/Controllers/Web/DisplayPreferenceController.php` | Enregistrement du choix |
| `app/Models/Testimony.php` | `typePill()`, `durationLabel()`, `playableRenditions()` |
| `routes/web.php` | `POST /affichage` |
| `resources/views/components/testimony-list.blade.php`, `layout-toggle.blade.php` | Liste et boutons d'affichage |
| `resources/views/videos/partials/row.blade.php`, `cards.blade.php`, `card.blade.php`, `watch.blade.php` | Ligne compacte, éléments, carte, page de lecture |
| `resources/views/home/index.blade.php`, `explore/index.blade.php`, `videos/index.blade.php`, `profile/show.blade.php`, `profile/saved.blade.php`, `testimonies/mine.blade.php` | Listes concernées |
| `resources/js/videos.js` | Qualité, boucle, lecture auto, lignes dépliables |

## Déploiement

Aucune migration, aucune variable d'environnement. Recompiler les assets et copier `public/build/` sur le serveur, puis `php artisan view:cache`.

Pour que le menu Qualité apparaisse, les versions allégées doivent exister : ffmpeg et la file d'attente (voir [qualités des médias](qualites-media.md#déploiement)).

## Tests

`tests/Feature/DisplayAndPlaybackTest.php` (11 tests) : cartes par défaut ; cookie posé par `POST /affichage` et valeur invalide refusée ; lignes compactes sur les six listes ; statut dans Mes témoignages ; « Afficher plus » en mode compact ; menu Qualité (libellés vidéo et audio, URL des versions, source d'origine conservée) ; pas de menu sans versions ; pas de réglages pour un texte ; suivants limités au même type et ouverts sur la même page ; pas de Lecture auto sans suivant.

Contrôle visuel dans Chrome (390, 768, 1440 px, base de démonstration) : aucun défilement horizontal, aucune erreur JavaScript ; bandeau « À suivre », Annuler, choix 240p mémorisé et retour à l'original vérifiés.

## Limites connues

- Pas de lecture adaptative en continu (HLS/DASH) : la qualité ne change pas toute seule pendant la lecture. En Auto, elle est choisie à l'ouverture selon le réseau annoncé par le navigateur.
- Le choix d'affichage et les préférences de lecture sont propres à chaque navigateur ; ils ne sont pas synchronisés avec l'application mobile.
- Pas de mode « répéter la liste » comme dans l'application : la lecture auto continue dans « À regarder également » et reprend au début quand tout a été enchaîné.
