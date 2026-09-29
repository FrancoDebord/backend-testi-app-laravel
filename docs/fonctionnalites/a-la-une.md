# Témoignages « à la une »

## But

Mettre en avant les nouveaux témoignages : ceux publiés depuis **moins d'une semaine** sont automatiquement « à la une », en plus de ceux mis en avant manuellement.

## Règle

Un témoignage est à la une s'il est **publié** (approuvé par la modération **et** public) et que :

- il a été **publié depuis moins de 7 jours**, **ou**
- il est **mis en avant manuellement** (colonne `is_featured`, sans limite de durée).

« Publié » se compte à partir de la **date d'approbation** (`approved_at`). Si elle est absente (anciens témoignages, données de démonstration), la date de création est utilisée. Un témoignage soumis il y a dix jours et approuvé hier est donc à la une pendant 7 jours à partir d'hier.

Les témoignages privés, réservés aux abonnés, en attente ou rejetés ne sont jamais à la une.

## Où cela apparaît

| Endroit | Comportement |
|---|---|
| Page d'accueil, section « À la une » | Les 3 premiers, du plus récemment publié au plus ancien |
| Cartes et page détail | Mention « À la une » |
| API `GET /api/v1/testimonies/featured` | Jusqu'à 10 témoignages, du plus récemment publié au plus ancien |
| API, chaque témoignage | `isFeatured` : à la une maintenant (règle complète) · `isPinned` : mis en avant manuellement |

**Changement de tri** : la section « À la une » et l'API `featured` étaient triées par nombre de vues. Elles sont désormais triées par date de publication, pour que les témoignages de la semaine (encore peu vus) soient visibles.

## Fonctionnement technique

Dans `app/Models/Testimony.php` :

| Élément | Rôle |
|---|---|
| `FEATURED_RECENT_DAYS = 7` | Durée de mise à la une automatique (modifier cette constante pour changer la règle) |
| `scopeFeatured()` | Requête : `published()` + (`is_featured` ou publié depuis moins de 7 jours) |
| `isCurrentlyFeatured()` | Même règle pour un témoignage déjà chargé (vues, API) |
| `publishedAt()` | `approved_at`, sinon `created_at` |
| `scopeLatestPublished()` | Tri du plus récemment publié au plus ancien (`COALESCE(approved_at, created_at)`) |

Aucune tâche planifiée : la règle est évaluée à chaque requête, un témoignage sort donc de la une automatiquement au bout de 7 jours.

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Models/Testimony.php` | Règle, portées de requête, constante |
| `app/Http/Controllers/Web/HomeController.php` | Section « À la une » de l'accueil |
| `app/Http/Controllers/Api/TestimonyController.php` | `featured()` |
| `app/Http/Resources/TestimonyResource.php` | `isFeatured`, `isPinned` |
| `resources/views/components/testimony-card.blade.php`, `testimonies/show.blade.php` | Mention « À la une » |
| `tests/Feature/FeaturedTestimoniesTest.php` | Tests |

## Déploiement

Aucune migration, aucun asset à recompiler. Déployer les fichiers ci-dessus, puis `php artisan optimize:clear` et `php artisan view:cache`.

## Limites connues

- La durée de 7 jours est une constante du code, pas encore un réglage de l'administration.
- Côté application mobile, `isFeatured` inclut désormais les témoignages récents : si l'app affiche un badge « À la une », il apparaîtra sur tous les témoignages de la semaine. Utiliser `isPinned` pour ne signaler que la mise en avant manuelle.
