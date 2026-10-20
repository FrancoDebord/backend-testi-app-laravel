# Aperçu de partage (WhatsApp, Facebook, Telegram, X, LinkedIn)

## But

Quand un lien de témoignage (`https://temoignage.as-krea.com/testimonies/{id}`, le `share_url` partagé par l'application) est collé dans WhatsApp, Facebook, Telegram, X ou LinkedIn, l'aperçu ressemble à celui d'un lien YouTube : **grande image 1200 × 630** en haut, **titre en gras**, courte description, puis le domaine.

Même traitement pour les pages **événement**, **requête de prière** (publique) et **direct**. Le verset du jour a déjà son propre aperçu ([designs-du-verset.md](designs-du-verset.md)).

## Ce que voient les réseaux sociaux

| Page | Image (1200 × 630 JPEG) | Titre | Description |
|---|---|---|---|
| Témoignage vidéo (Bunny, fichier) | miniature recadrée, **bouton lecture** central (disque sombre translucide, triangle blanc), **durée** en bas à droite si connue | « Titre - Auteur » | début du texte (160 car.) ou « Témoignage de {auteur} sur My Testimony » |
| Témoignage YouTube | miniature `maxresdefault`, sinon `hqdefault`, + bouton lecture ; balises `og:video` (lecteur `https://www.youtube.com/embed/{id}`) | idem | idem |
| Témoignage texte / audio avec couverture | la couverture recadrée ; audio : pastille « 🎧 Audio » (écouteurs dessinés) | idem | idem |
| Témoignage sans image | **carte de la marque** : dégradé bleu #184797 → #0B2B66, rayons de soleil orange / jaune, libellé « TÉMOIGNAGE · CATÉGORIE » (ou VIDÉO / AUDIO), titre (3 lignes max, « … »), « Par {auteur} », pied « My Testimony · ARISE & SHINE Krea » ; vidéo : bouton lecture à droite | idem | idem |
| Événement | 1re image de couverture (+ lecture si direct YouTube), sinon miniature YouTube + lecture, sinon carte « ÉVÉNEMENT · TYPE », titre, date, lieu | titre | « Type · date · lieu — début de la description » |
| Requête de prière publique | carte « REQUÊTE DE PRIÈRE », extrait entre guillemets, « Par {auteur} » (**jamais si anonyme**), « Priez avec nous sur My Testimony » | « Requête de prière - Auteur » (sans auteur si anonyme) | début de la requête |
| Direct visible sans compte | carte « EN DIRECT » / « DIRECT TERMINÉ », titre, hôte, bouton lecture ; pastille rouge « En direct » pendant le direct | « Titre - Hôte » | description ou « Direct de {hôte} sur My Testimony » |
| Contenu non public | **balises génériques du site** + image générique (`/partage/site.jpg`) : rien du titre, du texte ni de l'auteur | « My Testimony — témoignages qui fortifient la foi » | présentation du site |

« Non public » : témoignage du carnet, réservé aux abonnés, en attente / refusé, vidéo pas encore prête ; événement brouillon ou masqué ; requête « abonnés » / privée / retirée ; direct en préparation ou salle d'une session de prière réservée. Règle : `isVisibleTo(null)` (visible sans compte), car les robots ne sont jamais connectés.

Les textes des images sont dessinés dans la langue par défaut du site (`APP_LOCALE`, français) ; les balises suivent la langue de la page (`og:locale` `fr_FR` ou `en_US`, avec `og:locale:alternate`).

## Balises produites

Composant Blade `<x-share-meta>` (`resources/views/components/share-meta.blade.php`), poussé dans `@stack('meta')` du `<head>` (juste après `<title>`, avant les feuilles de style) :

`link rel=canonical`, `meta name=description`, `og:type` (`video.other` pour une vidéo / un direct, `article` sinon, `website` pour le générique), `og:site_name` « My Testimony · ARISE & SHINE Krea », `og:title`, `og:description`, `og:url`, `og:locale` (+ `alternate`), `og:image`, `og:image:secure_url`, `og:image:type` `image/jpeg`, `og:image:width` 1200, `og:image:height` 630, `og:image:alt`, YouTube : `og:video:url`, `og:video:secure_url`, `og:video:type` `text/html`, `og:video:width` 1280, `og:video:height` 720 ; `twitter:card` `summary_large_image`, `twitter:title`, `twitter:description`, `twitter:image`, `twitter:image:alt`.

```blade
@push('meta')
    <x-share-meta :meta="\App\Support\ShareMeta::testimony($testimony)" />
@endpush
{{-- ou, pour une autre page : --}}
<x-share-meta title="…" description="…" url="…" image="…" type="article" />
```

Toutes les adresses (`og:url`, `og:image`, canonique) sont **absolues en https sur `SHARE_URL`** (comme `share_url`). La description est du texte brut : sans `**gras**`, `*italique*` ni jetons de mention (`@[Nom](user:…)` → `@Nom`), coupée entre deux mots à 160 caractères.

## Images : routes et cache

En production, tout ce qui est sous `/storage/…` répond **403** (Apache) : les images d'aperçu passent donc par des routes Laravel.

| Route | Nom |
|---|---|
| `GET /partage/temoignages/{id}.jpg` | `share-images.testimony` |
| `GET /partage/evenements/{id}.jpg` | `share-images.event` |
| `GET /partage/requetes/{id}.jpg` | `share-images.prayer-request` |
| `GET /partage/directs/{id}.jpg` | `share-images.live` |
| `GET /partage/site.jpg` | `share-images.site` |

- Publiques, **sans session, cookies, CSRF ni « agir au nom de »** (rien ne bloque les robots) ; contenu non public ou inexistant : **404**.
- `Content-Type: image/jpeg`, `Cache-Control: public, max-age=2592000` (30 jours) ; image de secours : `max-age=3600`.
- L'adresse dans `og:image` porte `?v={empreinte}` : elle **change dès que le titre, l'image, la durée, l'auteur ou la catégorie changent** (empreinte incluant `updated_at`), et les réseaux recharguent l'image.
- **Génération paresseuse** au premier appel, puis cache disque `storage/app/share-images/{testimonies|events|prayer-requests|lives|site}/{id}-{empreinte}.jpg` (dossier modifiable par `config('share_images.root')`, utilisé par les tests). Les anciennes versions du même contenu sont supprimées à la régénération.
- Miniature distante : délai **5 s maximum** par requête (connexion 3 s) et budget total de ~6 s ; échec (hôte injoignable, 404, image illisible ou minuscule comme le 120 × 90 gris de YouTube) → carte de la marque, enregistrée en `.fb.jpg` et **retentée après une heure**.
- **Bunny** : son CDN refuse les requêtes sans `Referer` ; toutes les requêtes envoient `Referer: {APP_URL}/`. Vidéo Bunny prête sans `cover_url` : `https://{BUNNY_CDN_HOST}/{guid}/thumbnail.jpg`.
- Couverture hébergée sur le site (`…/storage/…` du domaine `APP_URL` ou `SHARE_URL`) : lue **directement sur le disque public**, sans requête HTTP.
- JPEG qualité 82 : 40 à 120 Ko en pratique (< 300 Ko). Police : Inter (`resources/fonts/ttf/inter-400|600.ttf`, déjà utilisée par l'image du verset ; Plus Jakarta Sans n'existe pas en TTF dans le dépôt). Les émojis du titre sont retirés de l'image (GD ne sait pas les dessiner) mais conservés dans les balises.
- Forcer la recréation de toutes les images : incrémenter `ShareImage::VERSION`, ou vider `storage/app/share-images/`.

## Robots

- `testimonies.show`, `videos.show`, `events.show`, `prayer.requests.show`, `lives.show` répondent **200 sans connexion**, sans redirection (pas de page de consentement ni d'âge) pour `facebookexternalhit`, `WhatsApp`, `Twitterbot`, `TelegramBot`, `LinkedInBot` (testé).
- `public/robots.txt` n'interdit rien (`Disallow:` vide) : `/partage/` est accessible.
- En mode maintenance (`php artisan down`), tout répond 503, aperçus compris.

## Tester un aperçu

1. **Facebook** : <https://developers.facebook.com/tools/debug/> → coller le lien → « Scrape Again » pour recharger après une modification. Affiche les balises lues et les erreurs (image trop lente, 403…).
2. **LinkedIn** : <https://www.linkedin.com/post-inspector/>. **X** : coller le lien dans un brouillon de post.
3. **Telegram** : envoyer le lien à `@WebpageBot` pour vider son cache.
4. **WhatsApp** garde l'aperçu en cache (souvent plusieurs jours) **par adresse exacte** : pour voir une nouvelle version, partager le lien avec un paramètre en plus, par ex. `https://temoignage.as-krea.com/testimonies/{id}?v=2`.
5. Vérification rapide : `curl -A "facebookexternalhit/1.1" https://temoignage.as-krea.com/testimonies/{id} | grep og:` puis ouvrir l'adresse `og:image`.

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Services/ShareImage.php` | Outils de dessin (carte de la marque, recadrage, bouton lecture, durée, pastille), téléchargement (Referer, délais, fichiers locaux), cache disque |
| `app/Services/TestimonyShareImage.php` | Image d'un témoignage (choix de la source, empreinte) |
| `app/Services/PageShareImages.php` | Images des événements, requêtes de prière, directs et image générique |
| `app/Support/ShareMeta.php` | Valeurs des balises par type de page, règles de visibilité, extrait de texte |
| `resources/views/components/share-meta.blade.php` | Composant `<x-share-meta>` |
| `app/Http/Controllers/Web/ShareImageController.php` | Routes `/partage/…` |
| `lang/{fr,en}/share.php` | Textes des balises et des images |
| `tests/Feature/SharePreviewTest.php` | Tests (balises, images, robots, non-fuite) |

## Limites connues

- La page `/testimonies/{id}` elle-même reste accessible pour un témoignage en attente ou « abonnés » (comportement antérieur, seul le carnet renvoie 404) : son `<title>` et son contenu sont donc visibles par qui a le lien ; seules les balises d'aperçu sont neutralisées.
- Les images sont générées dans la langue par défaut (une seule image par contenu).
- Pas d'image d'aperçu pour les sessions de prière ni les profils (balises absentes).
