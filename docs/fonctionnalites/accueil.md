# Page d'accueil (`/`)

## But

Présenter la plateforme selon la maquette « Témoignages de Gloire » et la charte ARISE & SHINE Krea ([interface.md](../interface.md)) : chiffres clés, témoignages récents, catégories, statistiques, plus populaires ; et, selon le rôle, les actions et la modération utiles.

## Utilisation

### Blocs (première page, sans filtre)

| Bloc | Contenu | Visible par |
|---|---|---|
| **Bandeau « Témoignages de Gloire »** | Titre, « Des vies transformées pour la gloire de Dieu », « Dieu agit encore ! » (écriture manuscrite Caveat, dès 768 px), illustration (lever de soleil, montagnes, personne les bras levés, dessinée en SVG aux couleurs de la charte) ; chiffres : témoignages publiés, utilisateurs actifs, vues totales, prières reçues | Tous |
| **Actions rapides** | Visiteur : Se connecter, Créer mon compte (orange), Explorer, Voir les directs. Membre : Ajouter un témoignage, Mes témoignages, Mes abonnements, Paramètres. Équipe : Ajouter un témoignage, Gérer les utilisateurs (administrateur), Voir la file de modération (jaune, avec le nombre en attente), Paramètres | Tous |
| **En direct** | Directs à l'antenne (bandeau fin) | Tous, s'il y en a |
| **Témoignages récents** | Filtres Tous / Vidéos / Audios / Textes, choix Grandes cartes / Liste compacte, cartes encadrées (catégorie, titre, extrait, auteur, vues, date, J'aime / commentaires / partages), 12 par page | Tous |
| **Catégories populaires** | 6 catégories actives, triées par témoignages publiés (comptés en direct) ; icône et teinte de la marque ; un clic filtre la liste | Tous |
| **Statistiques globales** | Courbes des 7 derniers jours (témoignages en bleu, inscriptions en orange), nouveaux témoignages et nouveaux utilisateurs avec l'évolution par rapport à la semaine précédente (flèche verte ou rouge), vues totales | Tous |
| **À la une** | Témoignages mis en avant de la semaine (jusqu'à 4 cartes) | Tous |
| **Shorts** | Étagère horizontale | Tous |
| **Modération rapide** | 4 plus anciens témoignages en attente (« À vérifier », lien vers la relecture), bouton « Voir toute la file » | Modérateurs, administrateurs |
| **Les plus populaires** | 5 témoignages les plus vus | Tous |
| **Verset du jour** | Encadré jaune « inspiration » | Tous, s'il existe |
| **Gestion des contenus** | 5 derniers contenus, onglets Tous / En attente (N) / Approuvés / Rejetés (`?gestion=pending#gestion`) ; tableau à partir de 768 px, fiches en dessous ; « Voir tout » vers le contenu (administrateur) ou la modération | Modérateurs, administrateurs |
| **Mes témoignages** | 5 derniers témoignages de la personne, tous statuts (hors carnet privé) | Membre connecté |

Avec un filtre (`?type=`, `?category=`) ou à partir de la page 2 : seulement la liste (« Résultats »).

### Disposition

À partir de 1280 px : colonne principale et colonne de droite (20 rem : actions, modération, plus populaires, verset). En dessous : une colonne, les actions rapides juste après le bandeau.

## Règles

| Règle | Détail |
|---|---|
| Chiffres du bandeau et statistiques | Recalculés au plus toutes les 5 minutes (cache `home.stats`, `home.activity`) |
| « Utilisateurs actifs » | Comptes au statut actif |
| « Prières reçues » | Somme des prières sur les témoignages publiés (la maquette montre une « note moyenne », qui n'existe pas dans TestiApp) |
| Évolution | `(semaine − semaine précédente) / semaine précédente`, rien si la semaine précédente est vide |
| Couleurs des catégories | `Category::presentation()` : icône selon la catégorie, bleu / orange / jaune en alternance (`display_order`) ; les emojis enregistrés en base ne sont pas affichés |

## Fonctionnement technique

| Élément | Fichier |
|---|---|
| Données | `app/Http/Controllers/Web/HomeController.php` (`CONTENT_TABS`) |
| Activité des 7 jours | `app/Support/WeeklyActivity.php` (aussi pour le tableau de bord de l'administration) |
| Présentation d'une catégorie | `Category::presentation()` |
| Page | `resources/views/home/index.blade.php` |
| Liste des témoignages récents | `resources/views/home/partials/recent.blade.php` |
| Tableau des contenus | `resources/views/home/partials/content-table.blade.php` |
| Carte encadrée | `resources/views/videos/partials/tile.blade.php` (carte par défaut de `components/testimony-list`, toutes les listes) |
| Bouton doux | `btn-soft` (`resources/css/app.css`) |

## Déploiement

Aucune migration. Recompiler et copier `public/build/` ; `php artisan view:cache`.

## Tests

`tests/Feature/HomePageTest.php` (4 tests) : blocs publics pour un visiteur, « Mes témoignages » pour un membre, modération rapide et gestion des contenus par statut pour l'équipe, filtres → liste seule. Restent valables : `FeaturedTestimoniesTest` (À la une), `VideoPageTest` (Shorts), `DisplayAndPlaybackTest` (liste compacte).

Contrôle dans Chrome (visiteur et administrateur, 390, 768, 1440 px) : aucun défilement horizontal, aucune erreur JavaScript.

## Limites connues

- L'illustration du bandeau est dessinée en SVG (pas de photo) : une vraie photo pourrait la remplacer plus tard.
- Pas de note des témoignages : le 4ᵉ chiffre est le nombre de prières.
- Pas d'onglets « Images » et « Documents » : TestiApp ne gère que vidéo, audio et texte.
