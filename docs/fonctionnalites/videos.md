# Page Vidéos

## But

Offrir une page publique, simple et centrée sur le contenu, pour **trouver** un témoignage (vidéo, short, direct, audio ou texte), le **regarder**, **réagir**, **commenter** et **découvrir** d'autres publications.

Parcours visé : je vois les vidéos → je clique → je regarde → j'aime → je commente → je découvre une autre vidéo.

## Pages

| Adresse | Contenu | Accès |
|---|---|---|
| `/videos` | Liste avec recherche, onglets de type, tri et catégories | Tout le monde |
| `/videos/{id}` | Lecture : lecteur, informations, réactions, description, commentaires, « À regarder également » | Tout le monde pour un contenu publié (voir *Règles*) |

Menu latéral : **Découvrir → Vidéos**. Lien aussi dans le pied de page.

## Règles

| Règle | Détail |
|---|---|
| **Contenu listé** | Témoignages **approuvés et publics** uniquement (portée `Testimony::published()`) |
| **Lecture** | Publié et public : tout le monde · réservé aux abonnés : les abonnés de l'auteur · en attente ou rejeté : **seulement l'auteur et la modération** (bandeau « Aperçu ») · privé ([carnet](carnet-prive.md)) : **seulement l'auteur**, jamais la modération. Sinon, **404** (`Testimony::isVisibleTo()`) |
| **Types (onglets)** | Tout · **Vidéos** (vidéos de plus de 60 s ou de durée inconnue) · **Shorts** (vidéos de 1 à 60 s, `Testimony::SHORT_MAX_SECONDS`) · **Directs** (directs en cours + rediffusions : témoignages liés à `live_sessions.testimony_id`) · **Audios** · **Textes** |
| **Tri** | Plus récentes (date de publication) · Plus vues · Plus aimées |
| **Recherche** | Titre, texte/description, catégorie (identifiant ou nom), nom de l'auteur. 100 caractères au plus |
| **Directs en cours** | Affichés en tête des onglets « Tout » et « Directs » (première page, sans recherche ni catégorie) ; ils ouvrent la page du direct |
| **Vues** | **Une vue par personne et par témoignage toutes les 6 heures** (`App\Services\ViewCounter`) : compte connecté, sinon session (sinon adresse IP). Vidéo et audio : vue comptée au **premier lancement de la lecture** ; texte : à l'ouverture. La page témoignage `/testimonies/{id}` suit désormais la même règle (elle comptait chaque affichage) |
| **Durée inconnue** | Fichiers envoyés depuis le site (sans durée) : la durée lue par le lecteur est enregistrée à la première lecture ; la vidéo peut alors apparaître dans « Shorts » |
| **J'aime et réactions** | Système existant (`reactions`, une réaction de chaque type par personne, bascule) : J'aime, Prière, Amen, Adorer, Feu |
| **Commentaires** | Personnes connectées · 2 000 caractères · 10 par minute · affichés du plus récent au plus ancien, 10 par page |
| **Réponses** | **Un seul niveau** : répondre à une réponse la rattache au commentaire principal (avec « @Nom » prérempli). Réponses chargées à la demande (« Voir les N réponses »), 20 par page |
| **Modifier** | Uniquement l'auteur du commentaire (`CommentPolicy::update`) |
| **Supprimer** | L'auteur du commentaire, ou un modérateur/administrateur, comme dans l'API (`CommentPolicy::delete`). Supprimer un commentaire principal supprime ses réponses (suppression douce) |
| **Compteur de commentaires** | `testimonies.comment_count` compte commentaires **et** réponses (même règle que l'API) ; `comments.replies_count` pour chaque commentaire principal |
| **Notifications** | Nouveau commentaire → l'auteur du témoignage ; réponse → aussi l'auteur du commentaire (table `app_notifications`, comme l'API) |
| **Recommandations** | Jusqu'à 10 : même type et même catégorie, puis même type récents, puis les plus vus (3 requêtes au plus) |

## Utilisation

### Liste (`/videos`)

1. Barre de recherche, puis onglets de type, puis tri et catégories (pastilles `chip`).
2. Grille de cartes : miniature (image de couverture ; pour une vidéo sans couverture, première image chargée à l'approche de l'écran), durée, pastille « Short », « Audio », « Texte » ou « Rediffusion », titre, courte description, auteur et avatar, catégorie, vues, date, commentaires. **Toute la carte est cliquable** ; le nom et l'avatar de l'auteur mènent à son profil.
3. Survol à la souris (hors « mouvement réduit ») : courte **prévisualisation muette** de la vidéo après 0,6 s.
4. **Afficher plus** ajoute les 24 cartes suivantes sans recharger la page (sans JavaScript : lien vers la page suivante).
5. États : « Aucune vidéo disponible pour le moment. » · « Aucune vidéo ne correspond à votre recherche. » (avec « Effacer la recherche »).

### Lecture (`/videos/{id}`)

- **Vidéo** : lecteur HTML5 natif (lecture, pause, volume, plein écran, barre de progression, clavier). Un short s'affiche au format vertical. Message clair si le fichier ne peut pas être lu.
- **Audio** : couverture (ou pictogramme) et lecteur audio.
- **Réglages de lecture** sous le lecteur (vidéo et audio) : **Vitesse** (0,75× à 2×), **Qualité** (Auto, versions allégées, origine), **En boucle**, **Lecture auto** du suivant avec bandeau « À suivre ». Détails : [affichage et lecture](affichage-et-lecture.md).
- **Texte** : le témoignage mis en forme (gras, italique, émojis) et la durée de lecture estimée.
- Sous le lecteur : titre, auteur (avatar, abonnés), **Partager** (partage natif du téléphone, sinon copie du lien `share_url`), **Sauvegarder**, réactions avec compteurs, puis la description (repliée au-delà de 280 caractères, « Afficher plus »), verset biblique, mots-clés.
- **Commentaires** : « Ajouter un commentaire… » → **Publier**. Le commentaire apparaît immédiatement. Pour chaque commentaire : Répondre, Modifier et Supprimer (avec confirmation `openConfirmModal`) selon les droits.
- **À regarder également** : colonne de droite à partir de 1 280 px, sous les commentaires en dessous.

## Présentation commune aux autres pages

Sans changer les adresses, le reste du site reprend cette présentation :

| Page | Adresse | Présentation |
|---|---|---|
| Accueil | `/` | Pastilles type + catégories (`type`, `category`), verset du jour ; sur la première page sans filtre : **En direct** (4), **À la une** (une grande carte + 4), étagère **Shorts** (12, lien « Tout voir » vers `/videos?tab=shorts`) ; puis « Derniers témoignages » |
| Explorer | `/explore` | Recherche, onglets de type, pastilles de tri et de catégorie ; mêmes paramètres qu'avant |
| Témoignage | `/testimonies/{id}` | **Même page de lecture** que `/videos/{id}` (partiel `videos/partials/watch`), liens « À regarder également » vers `/testimonies/{id}`. Les règles d'accès de cette page sont inchangées ; la vue est comptée à l'ouverture (une par personne toutes les 6 h) |
| Profil | `/profiles/{id}` | Présentation « chaîne » : bandeau, avatar, statistiques, S'abonner / Modifier le profil, grille de témoignages |
| Sauvegardes, Mes témoignages | `/profile/saved`, `/mes-temoignages` | Grille de cartes ; sur « Mes témoignages », la pastille indique le statut (en attente, refusé…) |
| Directs | `/lives` | Cartes de direct (statut, durée, diffuseur) |
| Barre du haut | toutes les pages | Champ de recherche vers Explorer (loupe sur mobile) |

Chaque liste (sauf « À la une », Shorts et directs) propose aussi une **liste compacte** dépliable : voir [affichage et lecture](affichage-et-lecture.md).

Carte unique : `components/testimony-card` (ouvre `/testimonies/{id}`) inclut `videos/partials/card` (ouvre `/videos/{id}` par défaut). Paramètres `short` et `large` : **vrais booléens uniquement**, car un `@include` voit les variables de la vue parente.

## Mise en œuvre

| Élément | Fichier |
|---|---|
| Routes | `routes/web.php`, bloc « Vidéos » |
| Liste, lecture, vue | `app/Http/Controllers/Web/VideoController.php` (`index`, `show`, `recordView`) |
| Données de la page de lecture | `app/Services/WatchPage.php` (réactions de la personne, sauvegarde, commentaires paginés, recommandations), utilisé aussi par `TestimonyController::show` |
| Commentaires | `app/Http/Controllers/Web/CommentController.php` (`store`, `update`, `destroy`, `replies`) |
| Validation | `app/Http/Requests/CommentRequest.php` |
| Droits | `app/Policies/CommentPolicy.php` (découverte automatique par Laravel) |
| Vues | `app/Services/ViewCounter.php` (cache, `Cache::add` atomique) |
| Portées et règles | `app/Models/Testimony.php` : `shorts()`, `longVideos()`, `liveReplays()`, `search()`, `isShort()`, `isVisibleTo()`, `viewsLabel()`, `readingMinutes()`, relation `liveSession()` |
| Pages | `resources/views/videos/index.blade.php`, `resources/views/videos/show.blade.php` et `resources/views/testimonies/show.blade.php` (même partiel `watch`) |
| Partiels | `resources/views/videos/partials/` : `watch` (page de lecture), `card` (carte), `cards` (page de cartes), `live-card` (direct), `recommendation`, `comment`, `comments` |
| JavaScript | `resources/js/videos.js` (importé par `app.js`) : afficher plus, miniatures différées, prévisualisation, lecteur et vues, réactions, sauvegarde, partage, commentaires |

### Routes

| Méthode | Adresse | Nom | Accès |
|---|---|---|---|
| GET | `/videos` | `videos.index` | Public. Avec `Accept: application/json` : `{ html, next }` (cartes suivantes) |
| GET | `/videos/{id}` | `videos.show` | Public (règles de lecture). Avec `?comments=N` et `Accept: application/json` : `{ html, next }` (commentaires suivants) |
| POST | `/videos/{id}/view` | `videos.view` | Public, 60/min. Corps `{ duration }` facultatif. Réponse `{ counted, views, label }` |
| GET | `/comments/{comment}/replies` | `comments.replies` | Public. `{ html, next }` |
| POST | `/videos/{id}/comments` | `videos.comments.store` | Connecté, 10/min. `{ body, parent_id? }` → 201 `{ html, parentId, replies, count }` |
| PUT | `/comments/{comment}` | `comments.update` | Auteur du commentaire, 20/min. `{ body }` → `{ body }` |
| DELETE | `/comments/{comment}` | `comments.destroy` | Auteur ou modération → `{ count, parentId, replies }` |

Les réactions et la sauvegarde réutilisent `testimonies.reactions.store` et `testimonies.save`. Chaque action renvoie du JSON pour une requête `Accept: application/json`, sinon redirige : la page fonctionne aussi sans JavaScript.

### Sécurité et performances

- Texte des commentaires toujours affiché avec `{{ }}` (échappé), y compris dans le HTML renvoyé après publication ; CSRF sur toutes les actions ; validation serveur (`CommentRequest`) ; droits vérifiés par la Policy et par `isVisibleTo()` (impossible de commenter un contenu non visible).
- Relations chargées d'avance (`user`, `category`, `liveSession`) : pas de requêtes N+1 ; pagination des cartes (24), des commentaires (10) et des réponses (20) ; réponses chargées seulement à la demande ; miniatures `loading="lazy"` ; vidéo `preload="metadata"`.
- Les fichiers restent servis par les adresses publiques déjà utilisées (`media_url`) ; aucun chemin interne du stockage n'est exposé.

### Aucune migration

Tout repose sur les tables existantes : `testimonies`, `comments` (`parent_id`, `replies_count`), `reactions`, `saved_testimonies`, `categories`, `live_sessions`, `app_notifications`. Les vues déjà comptées sont mémorisées dans le cache Laravel (`CACHE_STORE`).

## Tests

`tests/Feature/VideoPageTest.php` : contenu listé, onglets, recherche, états vides, « afficher plus », accès aux contenus non publiés, lecteur et recommandations, vues uniques et durée, commentaires, réponses sur un niveau, modification et suppression selon les droits, compteurs, J'aime.
