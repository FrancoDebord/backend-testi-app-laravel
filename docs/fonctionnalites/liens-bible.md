# Liens vers un passage de la Bible

## But

Partager un verset ou un passage par un lien. Touché sur un téléphone où l'application est installée, le lien ouvre la Bible **dans l'application**, au passage demandé ; sinon il ouvre le **site**, au même passage, avec les versets mis en avant. Collé dans WhatsApp, Facebook ou Telegram, il affiche un aperçu avec la référence et le texte.

## Format

```
{SHARE_URL}/bible/{traduction}/{livre}/{chapitre}/{versets}
```

| Exemple | Ouvre |
|---|---|
| `https://temoignage.as-krea.com/bible/LSG/jean/3/16` | Jean 3:16, Louis Segond |
| `…/bible/LSG/jean/3/16-18` | Jean 3:16 à 18 |
| `…/bible/LSG/psaumes/23/1,4-6` | Psaume 23:1 et 4 à 6 (10 plages au plus, même limite dans l'application) |
| `…/bible/KJV/john/3/16` | John 3:16, King James |
| `…/bible/LSG/1-corinthiens/13` | 1 Corinthiens 13, chapitre entier (sans versets) |

- **Traduction** : code de la version tel qu'en base (`LSG`, `KJV`, `EN_NLT`… ; lettres, chiffres, `_`, `-`, `.`, 30 caractères au plus ; voir [versions-de-la-bible.md](versions-de-la-bible.md)).
- **Livre** : nom en minuscules, sans accents, espaces remplacées par des tirets. Anglais pour une traduction anglaise (`language: en` dans `config/bible.php` ; version non déclarée : Genèse nommée « Genesis » en base, `BibleReference::isEnglish`, aussi renvoyé comme `language: en` par `GET /api/v1/bible/translations`), français pour toutes les autres (y compris yoruba, fon, goun). Liste : `slug` de `GET /api/v1/bible/books`.
- **Versets** : facultatifs ; `16`, `16-18`, `16,18-20`.

`SHARE_URL` : adresse publique, comme les [liens de partage des témoignages](lien-de-partage.md).

### Écritures acceptées et lien canonique

Le serveur accepte aussi le numéro du livre (`/bible/LSG/43/3/16`), un nom anglais ou français quelle que soit la traduction, les abréviations (`jn`, `1-co`, `ps`), les accents, la traduction en minuscules et des versets dans le désordre. Il **redirige (301)** vers le lien canonique : `/bible/lsg/43/3/17,16` → `/bible/LSG/jean/3/16-17`. L'ancienne adresse du lecteur `/bible?translation=LSG&book=43&chapter=3` est redirigée de la même façon.

Passage absent de la version demandée (changement de version après une sélection, lien vers une version retirée du serveur) : redirection (302) vers le passage le plus proche, avec un avertissement en haut de la page (`session('warning')`, `alert-warning`) — jamais une page introuvable pour un livre reconnu (2026-10-26) :

| Cas | Réponse |
|---|---|
| Version absente du serveur (`/bible/XYZ/jean/3/16`) | Même passage en `LSG` (sinon première version qui a le livre) : « La version XYZ n'est pas disponible… » |
| Livre absent de la version | Même passage en `LSG` (sinon première version qui a le livre) : « Ce livre n'existe pas dans la version … » |
| Chapitre au-delà du dernier de la version (`/jean/22`, Malachie 4 dans une version en 3 chapitres) | Dernier chapitre : « Ce livre compte 21 chapitres dans la version LSG… » |
| Versets tous absents du chapitre dans cette version (`/jean/3/90`, numérotation différente) | Chapitre entier : « Ces versets n'existent pas dans ce chapitre… » |
| Plage qui dépasse la fin du chapitre (`/jean/3/35-99`) | Versets existants mis en avant |
| Livre inconnu, livre présent dans aucune version, plage à l'envers (`18-16`) | 404 |

Changement de version dans le lecteur : la sélection est gardée (`/bible/{nouvelle version}/{numéro du livre}/{chapitre}/{versets}`, puis redirection vers le nom du livre dans cette version).

## Site

Page de lecture (`resources/views/bible/reader.blade.php`), route `bible.passage` :

- titre « Jean 3:16-17 » quand des versets sont demandés ; versets mis en avant (fond `sun-50`), page placée sur le premier ;
- **sélection de versets** (2026-10-26) : toucher un verset (ou son numéro, au clavier) l'ajoute ou le retire ; versets contigus ou épars (« Jean 3:16-18, 20 »), ceux du lien ouvert déjà sélectionnés. Barre fixée en bas : référence, **Partager**, **Copier le texte**, **Copier le lien**, effacer. L'adresse de la page suit la sélection (`history.replaceState`). Un texte surligné à la souris pour le copier ne sélectionne pas le verset ;
- texte partagé ou copié : `« 16 Car Dieu… 17 Dieu… … 20 Car quiconque… »`, puis `— Jean 3:16-18, 20 (LSG)` et le lien (numéros des versets quand il y en a plusieurs, `…` entre deux passages éloignés). Plus de 10 plages : lien du chapitre ;
- bouton **Partager** de l'en-tête : la sélection, sinon le chapitre ; feuille de partage du téléphone, sinon copie dans le presse-papiers ;
- aperçu de partage : titre « Jean 3:16-17 (LSG) », texte des versets (160 caractères), image générique du site (`ShareMeta::biblePassage`) ;
- liste des chapitres et changement de traduction : liens `/bible/…` (le changement garde le chapitre et les versets).

Le verset du jour (« Lire tout le chapitre ») et les suggestions de recherche « Bible » renvoient vers ces liens.

## API

| Appel | Ajout |
|---|---|
| `GET /api/v1/bible/books?translation=` | `slug` de chaque livre (nom dans les liens) |
| `GET /api/v1/bible/{book}/{chapter}` | `shareUrl` du chapitre |
| `GET /api/v1/bible/{book}/{chapter}/{verse}` | `shareUrl` du verset |
| `GET /api/v1/bible/{book}/{chapter}/{verse}/share` | `shareUrl` (en plus de `shareText`, inchangé) |

## Application mobile

Fait le 2026-10-26 (dépôt Flutter) :

1. **AndroidManifest.xml** : `<data android:pathPrefix="/bible/"/>` dans l'`intent-filter` `autoVerify` des liens du site.
2. **app_router.dart** : routes `/bible/:id/:book/:chapter` et `/bible/:id/:book/:chapter/:verses` (sous le lecteur d'une traduction, onglet Bible) → `BibleReaderScreen(translationCode, book, chapter, verses)`. Livre : numéro, nom français ou anglais, abréviation (`lib/features/bible/bible_links.dart`, copie de `BibleReference`), à défaut nom de la traduction sans accents ni tirets. Un lien reçu pendant l'écran de démarrage est gardé et ouvert ensuite ; lecture libre en mode invité.
3. **Versets** : `16`, `16-18`, `16,18-20` ; fond jaune clair (`sunSoft`) et page placée sur le premier ; la position de lecture est mise sur ce chapitre. Traduction absente du téléphone : téléchargée d'office ; inconnue du catalogue : même passage en Louis Segond.
4. **Partager** : le lien est ajouté au texte partagé (sélection de versets, verset seul), construit sur le téléphone (hors connexion) ; action « Copier le lien du verset » ; bouton de partage du chapitre (ou des versets du lien ouvert) dans la barre du lecteur. Nom du livre en anglais si la traduction est anglaise (`language` du catalogue, sinon KJV, WEB, ASV).

Tests : `test/bible_links_test.dart` (noms, versets, lien canonique, ouverture du lecteur sur le passage).

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Support/BibleReference.php` | `bookFromSegment`, `slug`, `parseVerses`, `formatVerses`, `passagePath`, `passageUrl`, `passageLabel` |
| `app/Http/Controllers/Web/BibleController.php` | `passage` (lien), `reader` (redirection de l'ancienne adresse) |
| `routes/web.php` | Route `bible.passage` |
| `resources/views/bible/reader.blade.php` | Versets mis en avant, sélection de versets et barre d'actions (script de la page), Partager, aperçu |
| `app/Support/ShareMeta.php` | `biblePassage` |
| `app/Http/Resources/BibleBookResource.php`, `Api/BibleController.php`, `Api/BibleAnnotationController.php` | `slug`, `shareUrl` |
| `config/applinks.php` | Chemin `/bible/*` |
| `tests/Feature/BibleLinksTest.php` | Tests |

## Déploiement

`php artisan optimize:clear` puis `php artisan optimize` (route et configuration App Links), recompiler et **copier `public/build/`**. Aucune migration.
