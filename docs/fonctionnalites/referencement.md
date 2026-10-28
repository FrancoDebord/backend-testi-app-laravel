# Référencement (Google) et publication sur le web

## But

Faire apparaître les témoignages dans Google, y compris dans les résultats vidéo, pour des recherches comme « témoignage de guérison », « témoignage chrétien », « témoignage de délivrance » ou « témoignage Bénin ». Seuls les témoignages que leur auteur a acceptés pour le web y figurent. Ajouté le 2026-10-27.

## Confidentialité (prioritaire)

| Règle | Détail |
|---|---|
| Publié sur le web | Approuvé, public, vidéo lisible, hors carnet, **accord de l'auteur** (`publication_web`), non supprimé : `Testimony::isOnPublicWeb()`. Seuls ces témoignages sont indexés, figurent dans les sitemaps et ont une image de partage. |
| Accord pour le web | Colonne `publication_web` (et date `publication_web_at`). Les témoignages existants le gardent : leur auteur avait déjà accepté un partage public sur la plateforme. Site : case « Publier aussi sur le web », cochée par défaut, à la publication ; l'auteur la change à tout moment dans « Sur le web », sur la page de son témoignage (`PUT /testimonies/{id}/web`). Application : champ `publicationWeb` à la publication (absent = accord donné), puis `PUT /api/v1/testimonies/{id}/web { publicationWeb?, isAnonymous? }` (auteur seulement, sans nouvelle modération ; champ absent inchangé ; aucun champ → 422 ; réponse : le témoignage) — depuis le 2026-10-28. Ne pas utiliser `PUT /testimonies/{id}` pour cela : une modification par l'auteur renvoie en modération. |
| Retrait | Décocher l'accord : la page répond **410 Gone** aux visiteurs (`publication_web_revoked_at`), sort aussitôt des sitemaps et des listes du site. L'auteur et la modération la voient encore (`noindex`). L'application n'est pas concernée. |
| Codes | `Testimony::webStatusFor()` : en attente, refusé, abonnés, privé, carnet, sans accord → **404** ; supprimé (corbeille) après publication sur le web ou accord retiré → **410** ; supprimé sans avoir été publié → 404 (rien n'est révélé). L'auteur et la modération voient leur page tant qu'elle n'est pas supprimée. Même règle pour `/testimonies/{id}` et `/videos/{id}` (et leurs commentaires). Auparavant, `/testimonies/{id}` affichait un témoignage en attente à toute personne qui avait le lien. |
| Anonymat | `is_anonymous` (case « Témoigner de façon anonyme sur le site ») : sur le **site**, « Témoignage anonyme », jamais le nom, la photo, le profil ni le bouton Suivre (page, cartes, listes, image de partage, JSON-LD, aperçus). Seuls l'auteur et la modération voient le nom (`Testimony::showsAuthorOnWeb`). L'**application** montre toujours l'auteur (choix du 2026-10-27) ; l'API renvoie `isAnonymous`. |
| Listes du site | `Testimony::published()` ajoute `publication_web = true` hors des requêtes `/api` (`Testimony::forWebContext`) : accueil, Vidéos, exploration, profils, événements, recommandations. Les listes de l'application ne changent pas. |
| Pages non indexées | Balise `robots` à `noindex, follow` par défaut sur **toutes** les pages (compte, administration, recherche, profils, carnet, prière…). Une page publique l'ouvre explicitement. |

## Pages publiques indexées

Les adresses existantes sont gardées (choix du 2026-10-27) : `/testimonies/{id}` reste l'adresse canonique d'un témoignage (lien de partage, App Links), `/lives/{id}` celle d'un direct.

| Page | Adresse | Contenu |
|---|---|---|
| Témoignage | `/testimonies/{id}` (et `/videos/{id}`, canonique vers la première) | `<h1>`, auteur ou « Témoignage anonyme », date, catégorie ; lecteur vidéo ; **texte écrit** : résumé (`resume`) et transcription (`transcription`, repliée) ; verset du thème correspondant du verset du jour (guérison → thème dont le slug contient « guerison », `Seo::themeForCategory`) ; « À regarder également » ; partage WhatsApp / Facebook ; liens vers l'application |
| Catégorie | `/temoignages/categorie/{slug}` (nouvelle) | « Témoignages de guérison », introduction modifiable (Administration → Catégories → Modifier, champ « Introduction de la page publique », `categories.intro`), témoignages publiés sur le web, les plus récents d'abord (24 par page, `rel=prev/next`), autres catégories |
| Thème du verset du jour | `/themes/{slug}` (nouvelle) | Semaine la plus récente déjà commencée qui porte ce thème : verset clé, période, versets des jours passés, derniers témoignages. Thème à venir : 404. `themes_semaine.slug`, calculé depuis la thématique. |
| Autres | `/`, `/videos`, `/lives`, `/lives/{id}` (public), `/verset-du-jour` (aujourd'hui et jours passés), `/verset-du-jour/historique`, `/temoignages-de-conversion`, `/evenements`, `/evenements/{id}` (public) | Robots `index` |

Formulaire de publication : pour un audio, une vidéo ou un lien YouTube, champs « Résumé écrit (recommandé) » et « Transcription (facultatif) ». API : `resume`, `transcription`, `publicationWeb`, `isAnonymous` (création et modification, facultatifs ; renvoyés par `TestimonyResource`).

## Balises

| Balise | Règle |
|---|---|
| `<title>` | `Seo::title()` : « Guéri du paludisme – Témoignage \| My Testimony », 60 caractères au plus (le genre de page est retiré, puis le titre coupé entre deux mots si besoin). Section Blade `page_title` ; sinon titre habituel du site. |
| `description` | `meta_description` (colonne, 160 caractères), sinon début du résumé, du texte, puis de la transcription (155 caractères) : `StructuredData::description`. |
| `canonical`, Open Graph, Twitter | `<x-share-meta>` ([apercu-de-partage.md](apercu-de-partage.md)) sur l'adresse publique (`SHARE_URL`) ; `og:type=video.other` et `og:video` (lecteur Bunny `https://iframe.mediadelivery.net/embed/{bibliothèque}/{vidéo}`, ou YouTube) pour une vidéo ; `summary_large_image`. |
| `robots` | Section `robots` ; `Seo::INDEX` = `index, follow, max-image-preview:large, max-video-preview:-1`. |
| Search Console | `GOOGLE_SITE_VERIFICATION` → `<meta name="google-site-verification">`. |
| Application iOS | `IOS_APP_STORE_ID` → `<meta name="apple-itunes-app">`. |
| `<html lang>` | Langue de la page ([langues.md](langues.md)) : `fr` pour Google, qui n'envoie pas de langue. |

## Données structurées (JSON-LD, `App\Support\StructuredData`)

- Toutes les pages : `Organization` (My Testimony, alias ARISE & SHINE Krea, logo) et `WebSite` (`components/seo-head`).
- Témoignage vidéo ou audio : `VideoObject` (`name`, `description`, `thumbnailUrl` = vignette Bunny, `uploadDate`, `duration` ISO 8601 depuis `duration_sec`, `embedUrl`, `contentUrl` MP4 720p, `transcript`, `author`) ; témoignage écrit : `Article` (`headline`, `datePublished`, `author` = nom ou « Témoignage anonyme », `image`). Plus `BreadcrumbList` (Accueil → catégorie → témoignage).
- Direct en cours : `VideoObject` + `publication` → `BroadcastEvent` (`isLiveBroadcast`, `startDate`) ; direct terminé : `VideoObject` avec sa durée.
- Catégorie : `CollectionPage` + `ItemList` des témoignages de la page.

Durée et vignette Bunny : déjà enregistrées sans appel à chaque affichage. La durée est écrite par la synchronisation Bunny quand la vidéo est prête (`BunnyVideoSync::markReady`, webhook et tâche `videos:sync-bunny-status`) ; la vignette est une adresse du CDN (`BunnyStream::thumbnailUrl`).

## Sitemaps et robots.txt (`App\Services\Sitemaps`, sans dépendance)

| Adresse | Contenu |
|---|---|
| `/sitemap.xml` | Index des fichiers ci-dessous |
| `/sitemap-pages.xml` | Accueil, Vidéos, Directs, Verset du jour, Historique, Conversions, Événements |
| `/sitemap-categories.xml` | Catégories actives |
| `/sitemap-testimonies.xml` | Témoignages publiés sur le web (`lastmod` = dernière modification) |
| `/sitemap-videos.xml` | Témoignages vidéo, balises `video:video` : `thumbnail_loc`, `title`, `description`, `player_loc` (lecteur Bunny ou YouTube) ou `content_loc`, `duration`, `publication_date` |
| `/sitemap-lives.xml` | Directs publics (en cours et terminés) |
| `/sitemap-verses.xml` | Versets du jour d'aujourd'hui et des jours passés, thèmes déjà commencés |

- **Mise à jour** : en cache jusqu'à minuit (Africa/Porto-Novo). Vidé à chaque publication, modification, retrait du web, suppression ou restauration d'un témoignage, et à chaque changement d'une catégorie, d'un direct ou d'un thème (`Sitemaps::forget`). Recalculé chaque nuit à 3 h 45 par `php artisan sitemap:generate`, via le cron du serveur.
- **`/robots.txt`** : servi par Laravel, ce qui remplace le fichier `public/robots.txt`, supprimé. Il bloque `/admin`, `/moderation`, `/api/`, `/login`, `/register`, `/forgot-password`, `/settings`, `/profile` (et `/profiles/`), `/personnes`, `/explore` (recherche), `/mes-temoignages`, `/mon-fil`, `/carnet`, `/notifications`, `/publish`, `/tableau-de-bord`, `/comptes`, `/verifications`, `/demandes-suppression` et `/actions-groupees`. Il donne aussi l'adresse du sitemap.

## Performance

- La page d'un témoignage se lit sans JavaScript : texte, résumé, transcription et verset sont rendus par le serveur. Le lecteur HLS ne charge sa bibliothèque qu'à la lecture ([videos.md](videos.md)).
- Les vignettes des listes ont `loading="lazy"` et `decoding="async"`. Les vignettes Bunny restent en JPEG, car le CDN Bunny ne les convertit pas en WebP.
- Les assets passent par Vite (minifiés) ; la police utilise `display=swap`.
- **Non fait** : le cache de pages entières, parce que les pages contiennent l'état de la personne connectée (réactions, jeton CSRF). Les sitemaps et la liste des catégories du pied de page sont, eux, en cache.

## Maillage interne et application

- **Pied de page** (`layouts/partials/seo-footer-links`) : toutes les catégories et « Directs et replays ».
- **Pages catégorie et thème** : autres catégories, derniers témoignages.
- **« Télécharger l'application »** (`components/app-banner`) : liens `APP_PLAY_STORE_URL` et `APP_STORE_URL`, sur la page d'un témoignage publié. Rien ne s'affiche sans lien.
- **`/.well-known/assetlinks.json`** existait déjà et ouvre `/testimonies/*` dans l'application ([app-links.md](app-links.md)).

## Hébergement LWS

Le `.htaccess` à la racine du site (au-dessus de `public/`) n'est pas dans le dépôt. Son contenu recommandé est donné dans le journal du 2026-10-27 et dans le compte rendu de la livraison : HTTPS, un seul domaine, aucune adresse en `/public/`. La barre finale est déjà redirigée par `public/.htaccess`. Les adresses sont sensibles à la casse : une adresse en majuscules répond 404 et ne crée donc pas de doublon.

## Fichiers

| Fichier | Rôle |
|---|---|
| `database/migrations/2026_10_27_200001_add_web_publication_fields.php` | `testimonies` : `meta_description`, `resume`, `transcription`, `is_anonymous`, `publication_web(_at, _revoked_at)` ; `categories.intro` ; `themes_semaine.slug` |
| `app/Models/Testimony.php` | `isOnPublicWeb`, `webStatusFor`, `showsAuthorOnWeb`, `webAuthorName`, `published()` sur le web, dates de l'accord, vidage des sitemaps |
| `app/Support/WebPublication.php` | Champs communs au site et à l'API |
| `app/Support/Seo.php`, `app/Support/StructuredData.php`, `config/seo.php` | Titres, thème lié, JSON-LD, réglages |
| `app/Services/Sitemaps.php`, `app/Console/Commands/GenerateSitemaps.php` | Sitemaps |
| `app/Http/Controllers/Web/PublicPagesController.php` | Catégories, thèmes, sitemaps, robots.txt |
| `resources/views/components/seo-head.blade.php`, `app-banner.blade.php`, `seo/*.blade.php`, `testimonies/partials/seo.blade.php`, `testimonies/partials/web-extras.blade.php`, `layouts/partials/seo-footer-links.blade.php` | Vues |
| `tests/Feature/SeoTest.php` | Tests |

## Déploiement

1. `php artisan migrate --force`.
2. `php artisan optimize:clear` puis `php artisan optimize`, **sur le serveur uniquement** (nouvelles routes et configuration).
3. Supprimer `public/robots.txt` sur le serveur : le déploiement FTP ne supprime pas les fichiers.
4. Copier `public/build/`.
5. Variables facultatives dans `.env` : `GOOGLE_SITE_VERIFICATION`, `APP_PLAY_STORE_URL`, `APP_STORE_URL`, `IOS_APP_STORE_ID`.
6. Search Console : ajouter la propriété du domaine et soumettre `https://temoignage.as-krea.com/sitemap.xml`.
