# Texte riche et aperçu avant publication

## But

- Rédiger un témoignage texte mis en forme, avec la mise en forme visible pendant la saisie : gras, italique, barré, titres, citations, listes, liens et images.
- Voir son témoignage tel qu'il apparaîtra (texte, audio, vidéo ou YouTube) **avant** de le publier.

Ajouté le 2026-10-27 sur le site (page « Publier un témoignage », `/publish`).

## Format du texte

Le texte reste enregistré en **Markdown léger** dans `testimonies.body_text`. Aucun HTML n'est stocké. Le format est le même que celui de l'application mobile (`lib/shared/utils/rich_text_utils.dart`), complété par les liens et les images.

| Écriture | Rendu | Barre d'outils |
|---|---|---|
| `**gras**` | **gras** | Gras (Ctrl+B) |
| `__italique__` (et `*italique*`, anciens textes) | *italique* | Italique (Ctrl+I) |
| `~~barré~~` | ~~barré~~ | Barré |
| `# Titre` (ligne) | titre (h3, bleu Krea) | Titre |
| `> citation` (lignes) | citation (bordure jaune) | Citation |
| `- élément` / `1. élément` | liste à puces / numérotée | Listes |
| `[texte](https://…)` et adresses `https://…` nues | lien, ouvert dans un nouvel onglet (`rel="nofollow noopener noreferrer ugc"`) | Lien (Ctrl+K) : panneau avec l'adresse ; coller une adresse sur un texte sélectionné en fait un lien |
| `![légende](URL)` (ligne) | image, avec sa légende | Image : envoyée au serveur puis insérée au curseur |
| `\*` | astérisque | — |

Les émojis et les mentions « @ » ([mentions.md](mentions.md)) restent disponibles.

**Sécurité**
- Le texte est échappé avant la mise en forme.
- Les liens ne sont faits que d'adresses `http(s)`, donc jamais de `javascript:`.
- Une image n'est affichée que si elle a été envoyée sur ce serveur (hôte du site), directement dans `/storage/testimony-images/` (site) ou `/storage/media/images/` (application mobile, `POST /api/v1/media/upload`, depuis le 2026-10-28) : `RichText::IMAGE_DIRS`. Jamais `media/audios`, `media/videos`, un sous-dossier ni un autre site : la ligne n'affiche alors rien.

**Rendu** : `App\Support\RichText::toHtml`. Les paragraphes deviennent des `<p>` et un retour à la ligne devient `<br>`. Les conteneurs utilisent la classe `rich-text` (`resources/css/app.css`) : page du témoignage, description d'une vidéo, modération, aperçu. `RichText::plain` donne le texte sans mise en forme, une ligne par ligne et sans les images (cartes, aperçus de partage).

## Images

| Règle | Détail |
|---|---|
| Envoi | `POST /publish/images` (connecté, 20 par minute) avec `image` : JPEG, PNG, WebP ou GIF, 5 Mo au plus. Réponse 201 `{ url }`. |
| Stockage | `storage/app/public/testimony-images/`, servi par `/storage/…` comme les autres fichiers. |
| Nombre | 10 images au plus par témoignage (`RichText::MAX_IMAGES`). Le site refuse la publication au-delà (erreur sous le texte), et l'éditeur bloque avant l'envoi. |
| Retrait | Supprimer l'image dans l'éditeur la retire du texte. Le fichier reste sur le serveur. |

## Aperçu avant publication

Le bouton **Aperçu**, à côté de « Publier », ouvre une fenêtre qui montre le témoignage comme il sera publié. Rien n'est enregistré ni envoyé, sauf le texte, pour sa mise en forme.

- **Vidéo / audio** : le fichier choisi est lu directement depuis l'appareil (`URL.createObjectURL`), avec l'image de couverture comme affiche. Sans fichier, la fenêtre l'indique.
- **YouTube** : le lecteur YouTube de la vidéo (adresse reconnue : `watch?v=`, `youtu.be/`, `shorts/`, `embed/`, `live/`).
- **Texte** : mis en forme par le serveur (`POST /publish/apercu`, réponse `{ html }`), exactement comme sur la page publiée, mentions comprises.
- Les autres éléments sont aussi montrés : titre, catégorie, visibilité, auteur, verset biblique et mots-clés.
- **Modifier** referme la fenêtre. **Publier** envoie le formulaire, avec les mêmes contrôles que le bouton « Publier » (engagement, champs obligatoires).

## Application mobile

À faire dans le dépôt Flutter :
- l'API renvoie `bodyText` en Markdown léger. Les liens `[texte](url)` et les images `![légende](url)` y apparaissent tant que l'application ne les affiche pas ;
- l'application écrit l'italique `__italique__`. Le site lit les deux écritures et enregistre maintenant `__italique__` ;
- l'aperçu avant publication de l'application est indépendant de celui du site.

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Support/RichText.php` | Rendu (`toHtml`), texte brut (`plain`), images (`imageUrls`, `isOwnImage`, `MAX_IMAGES`) |
| `app/Http/Controllers/Web/TestimonyComposerController.php` | `storeImage`, `preview` |
| `app/Http/Controllers/Web/TestimonyController.php` | Limite d'images à la publication |
| `resources/views/components/rich-editor.blade.php`, `lang/{fr,en}/rich_editor.php` | Barre d'outils, panneau de lien, envoi d'image |
| `resources/js/app.js` | `richTextToHtml`, `richTextFromDom`, `initRichEditor` |
| `resources/views/testimonies/create.blade.php` | Bouton et fenêtre d'aperçu (script de la page) |
| `resources/css/app.css` | `.rich-text`, `.rt-content`, `.rt-input` |
| `tests/Unit/RichTextTest.php`, `tests/Feature/TestimonyComposerTest.php` | Tests |

## Déploiement

1. Aucune migration.
2. `php artisan optimize:clear` puis `php artisan optimize` (routes `testimonies.images.store`, `testimonies.preview`).
3. Recompiler et **copier `public/build/`** sur le serveur.

Les images sont enregistrées dans `storage/app/public/testimony-images/` : ce dossier doit être accessible en écriture, comme `covers/`.
