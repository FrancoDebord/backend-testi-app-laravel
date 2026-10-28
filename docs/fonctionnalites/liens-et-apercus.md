# Liens cliquables et aperçus de liens

## But

Une adresse web écrite ou collée dans un texte devient un **lien cliquable** à l'affichage. Sous une description, un commentaire ou une réponse, le **premier lien** est montré en **aperçu** : miniature, site, titre et début de la description de la page (comme sur WhatsApp ou Facebook).

Ajouté le 2026-10-28 sur le site. Le texte enregistré ne change pas : la détection se fait à l'affichage, donc les textes déjà publiés en profitent aussi.

## Où

| Texte | Lien cliquable | Aperçu |
|---|---|---|
| Témoignage écrit (texte mis en forme) | oui (déjà le cas, [texte-riche-et-apercu.md](texte-riche-et-apercu.md)) | oui, sous le texte |
| Description d'un témoignage vidéo / audio | oui (déjà le cas) | oui, sous la description |
| Commentaires et réponses d'un témoignage | oui | oui, mis à jour après une modification sans rechargement |
| Requête de prière (page) | oui | oui |
| Messages d'encouragement d'une requête | oui | oui |
| Session de prière (description) | oui | oui |
| Événement : description, commentaires | oui | oui |
| Événement : avis | oui | non |
| Commentaires du verset du jour | oui | oui |
| Direct (description) | oui | oui |
| Bio du profil | oui | non |

Les cartes et extraits (fil, listes, cartes de requêtes et de sessions, cartes de la communauté) restent en texte simple : ils sont souvent eux-mêmes des liens et coupés.

## Règles

| Règle | Détail |
|---|---|
| Adresses reconnues | `https://…`, `http://…` et `www.…` (ouvert en `https://`). Hôte avec au moins un point ; jamais d'identifiants (`user:pass@`) ; jamais `javascript:` ni autre schéma. Ponctuation finale (`.` `,` `;` `:` `!` `?` guillemets) et parenthèse fermante non ouverte laissées hors du lien. Pas de lien après `@` ou `/` (adresse e-mail, chemin) |
| Lien | Libellé coupé à 60 caractères (adresse complète dans le lien). Lien externe : nouvel onglet, `rel="nofollow noopener noreferrer ugc"`. Lien vers le site lui-même (`APP_URL`, `SHARE_URL`) : même onglet |
| Mentions | Les mentions « @ » restent des liens vers le profil ; les adresses entre elles sont aussi rendues ([mentions.md](mentions.md)) |
| Aperçu | Seulement le **premier** lien du texte, hors images envoyées sur le site (`RichText::isOwnImage`). Rien si la page n'a ni titre ni image |
| Miniature | `og:image` (ou `twitter:image`), en **https** seulement (sinon icône de lien). Image affichée depuis le site d'origine (`referrerpolicy="no-referrer"`) ; introuvable → icône de lien |
| YouTube | Miniature `i.ytimg.com` sans lire la page ; titre et chaîne par oEmbed |

## Fonctionnement

1. À l'affichage, `<x-link-preview :text="…" />` cherche la première adresse.
2. **Aperçu déjà en cache** : la carte est rendue tout de suite par le serveur (aucune requête du navigateur). **Page connue sans aperçu** : rien.
3. **Pas encore cherché** : un bloc caché (`data-link-preview-url`) est rempli par `resources/js/link-previews.js` (`GET /liens/apercu?url=`) quand le texte approche de l'écran, deux requêtes à la fois, une seule par adresse dans la page. Sans aperçu, le bloc est retiré. Les commentaires ajoutés sans rechargement (publication, « Voir les réponses ») sont pris en compte (`MutationObserver`).

### Lecture de la page (serveur)

`App\Services\LinkPreviews::get()` lit les balises `og:title`, `og:description`, `og:image`, `og:site_name`, `twitter:*`, `description` et `<title>`.

**Sécurité** (le serveur ne doit jamais servir à lire son propre réseau) :

- `http` / `https` seulement, ports 80 et 443, sans identifiants, adresse de 2 048 caractères au plus ;
- toutes les IP de l'hôte doivent être **publiques** (ni privées, ni locales, ni réservées : `127.0.0.1`, `10.*`, `192.168.*`, `169.254.*` dont les métadonnées cloud, `::1`…) ; la connexion se fait sur l'IP vérifiée (`CURLOPT_RESOLVE`), pas de seconde résolution DNS ;
- redirections suivies à la main (3 au plus), chacune revérifiée ;
- 4 s au plus, 512 Ko de HTML lus au plus, seulement `text/html`.

**Cache** (magasin par défaut, `CACHE_STORE`) : aperçu trouvé gardé 7 jours, page sans aperçu ou injoignable 6 heures. Clé `link-preview:` + sha1 de l'adresse.

## Routes

| Méthode | Adresse | Accès | Réponse |
|---|---|---|---|
| GET | `/liens/apercu?url=` (`link-preview`) | Ouvert (pages publiques), 30 par minute | `{ data: { url, title, description, image, siteName, host } \| null }` |
| GET | `/api/v1/link-preview?url=` | Connecté (Sanctum), 60 par minute | `{ success, data: { … } \| null, message }` |

`url` (dans la réponse) est l'adresse finale après redirections. 422 sans `url`.

## Application mobile

À faire dans le dépôt Flutter : l'API renvoie les textes tels qu'écrits (aucun champ ajouté). L'application doit :

- détecter les adresses à l'affichage (même règle : `https://`, `http://`, `www.`) et les ouvrir avec `url_launcher`, en plus des mentions (`MentionText`) ;
- afficher l'aperçu du premier lien des commentaires, réponses, descriptions et requêtes avec `GET /api/v1/link-preview?url=`, en gardant la réponse en mémoire.

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Support/Linkify.php` | `html()` (texte échappé, adresses en liens), `escape()`, `urls()`, `firstUrl()` |
| `app/Services/Mentions.php` | `html()` : mentions + liens (`Linkify::escape` entre les mentions) |
| `app/Services/LinkPreviews.php` | `get()` (lecture, cache), `cached()` (sans réseau), `isAllowedUrl()` |
| `app/Http/Controllers/Web/LinkPreviewController.php`, `app/Http/Controllers/Api/LinkPreviewController.php` | Routes ci-dessus |
| `resources/views/components/link-preview.blade.php` | Carte d'aperçu |
| `resources/js/link-previews.js` (importé par `app.js`) | Remplissage des aperçus pas encore en cache, image introuvable |
| `resources/js/videos.js`, `Web\CommentController::update` | Commentaire modifié : texte (`html`) et aperçu (`preview`) remplacés |
| Vues | `videos/partials/comment`, `videos/partials/watch`, `prayer/requests/show`, `prayer/sessions/show`, `events/show`, `daily-verse/show`, `lives/show`, `profile/show` |
| `tests/Feature/LinkPreviewTest.php` | Tests (rendu, adresses internes refusées, redirection, cache, YouTube, routes, composant) |

## Déploiement

Aucune migration ni variable d'environnement. `php artisan optimize:clear` puis `php artisan optimize` (routes `/liens/apercu`, `/api/v1/link-preview`), puis **copier `public/build/`** (JS modifié). Le serveur doit pouvoir faire des requêtes HTTPS sortantes (déjà le cas pour FCM et Bunny) et l'extension PHP `dom` doit être active (présente par défaut).

## Limites connues

- Commentaires des **directs** (`resources/js/live.js`) : texte simple, sans lien ni aperçu.
- Le texte mis en forme des témoignages ne rend pas encore les adresses `www.…` sans `https://` (`RichText`, aligné sur l'éditeur) ; l'aperçu, lui, les reconnaît.
- Pas d'aperçu pendant la saisie : il apparaît une fois le texte publié.
- La miniature est chargée depuis le site d'origine par le navigateur de la personne (ce site voit son adresse IP).
- Un site qui refuse les robots ou exige JavaScript ne donne pas d'aperçu (le lien reste cliquable).
