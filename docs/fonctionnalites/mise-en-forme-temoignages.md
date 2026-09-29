# Mise en forme des témoignages (gras, italique, émojis)

## But

Permettre aux auteurs de témoignages texte de mettre des passages en **gras** ou en *italique* et d'ajouter des émojis, et afficher le témoignage avec cette mise en forme.

## Utilisation

Page **Publier un témoignage** (`/publish`), champ « Votre témoignage » :

- **Gras** : bouton **B** ou Ctrl+B ; **Italique** : bouton *I* ou Ctrl+I.
- **Émojis** : bouton 😊 (32 émojis proposés), ou clavier du téléphone, ou Win + . sous Windows.
- Un texte collé depuis Word ou une page web arrive **sans mise en forme** ; le HTML éventuel reste du texte.
- Le brouillon automatique conserve la mise en forme.
- Pour un témoignage de type texte, l'envoi est bloqué avec un message si le texte est vide.

## Format stocké

Colonne `testimonies.body_text` (inchangée), en **Markdown léger** :

| Écrit dans l'éditeur | Stocké |
|---|---|
| **fidèle** | `**fidèle**` |
| *bon* | `*bon*` |
| gras et italique | `***texte***` |
| astérisque tapé au clavier | `\*` |
| émoji 🙏 | `🙏` (Unicode, base `utf8mb4`) |
| retour à la ligne | `\n` |

**Aucun HTML n'est jamais enregistré.** Ce format reste lisible dans l'application mobile même sans rendu (les astérisques y apparaissent tels quels).

## Fonctionnement technique

### Serveur : `app/Support/RichText.php`

- `RichText::toHtml(?string)` renvoie un `HtmlString`. Le texte est **d'abord entièrement échappé** (`e()`), puis seuls `<strong>` et `<em>` sont ajoutés. Un `<script>` saisi s'affiche donc comme du texte.
- `RichText::plain(?string)` renvoie le texte sans marques : même analyse, puis retrait des balises.
- Règles : les marques ne s'appliquent pas sur plusieurs lignes, ni autour d'espaces (`** a **` reste tel quel). L'italique est accepté au milieu d'un mot (`super*bien*`).

### Modèle `Testimony`

| Accesseur | Usage |
|---|---|
| `$testimony->body_html` | Affichage mis en forme : page détail (`testimonies/show`) et relecture (`moderation/show`) |
| `$testimony->body_plain` | Aperçus : cartes (`components/testimony-card`), liste de modération |

Dans une vue : `{{ $testimony->body_html }}` (un `HtmlString` n'est pas ré-échappé), dans un bloc `whitespace-pre-line` pour conserver les retours à la ligne.

### Navigateur : éditeur

- Vue : `resources/views/components/rich-editor.blade.php`. Zone `contenteditable` et champ caché `<textarea name="body_text">`, qui est la valeur réellement envoyée.
- JavaScript : `initRichEditor()` dans `resources/js/app.js`, activé sur tout élément `[data-rich-editor]` :
  - `richTextToHtml()` : Markdown vers affichage dans l'éditeur (mêmes règles que le serveur) ;
  - `richTextFromDom()` : contenu de l'éditeur vers Markdown, d'après le style **réellement affiché** de chaque morceau de texte (robuste quelle que soit la structure HTML produite par le navigateur) ;
  - gestion du collage, de la sélection pour l'insertion d'émojis, de l'état des boutons et du champ obligatoire (attribut `data-rt-required` sur le champ caché).
- Styles : `rt-btn`, `rt-emoji`, `rt-content` dans `resources/css/app.css`.

Réutiliser l'éditeur ailleurs :

```blade
<span id="mon_champ_label" class="form-label">Libellé</span>
@include('components.rich-editor', [
    'name' => 'mon_champ', 'id' => 'mon_champ', 'value' => old('mon_champ'),
    'labelId' => 'mon_champ_label', 'placeholder' => '…', 'requiredMessage' => '…',
])
```

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Support/RichText.php` | Conversion serveur |
| `app/Models/Testimony.php` | Accesseurs `body_html`, `body_plain` |
| `resources/views/components/rich-editor.blade.php` | Éditeur |
| `resources/js/app.js` | Logique de l'éditeur |
| `resources/css/app.css` | Styles de l'éditeur, polices d'émojis |
| `resources/views/testimonies/create.blade.php` | Formulaire de publication |
| `resources/views/testimonies/show.blade.php`, `moderation/show.blade.php` | Affichage mis en forme |
| `resources/views/components/testimony-card.blade.php`, `moderation/index.blade.php` | Aperçus sans marques |
| `tests/Unit/RichTextTest.php` | Tests |

## Déploiement

Recompiler et déployer les assets (`public/build/`), voir [README §4](../README.md#4-déploiement). Aucune migration.

## Limites connues

- **Application mobile** : `bodyText` est encore affiché brut (astérisques visibles). Pour y voir la mise en forme, l'afficher avec un rendu Markdown (par ex. `flutter_markdown`), qui accepte ce format tel quel.
- Un texte venu du mobile contenant `2*3*4` s'affiche avec « 3 » en italique (compromis pour accepter l'italique au milieu d'un mot). Les astérisques tapés dans l'éditeur web sont échappés et ne sont pas concernés.
- Pas de listes, de liens ni de titres : volontairement limité au gras et à l'italique.
