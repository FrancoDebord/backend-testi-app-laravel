# Pourquoi témoigner ?

## But

Expliquer, Bible à l'appui, pourquoi partager ce que Dieu a fait : un bloc **« Pourquoi témoigner ? »** présente 7 raisons, chacune avec ses versets (Louis Segond 1910), en **accordéon** : on ouvre une raison pour lire ses versets, l'ouverture d'une raison referme la précédente.

| Raison | Versets |
|---|---|
| C'est l'appel du Seigneur | Marc 5:19, Ésaïe 43:10, Actes 1:8 |
| Le témoignage donne la victoire | Apocalypse 12:11, Psaume 107:2 |
| Il rend gloire à Dieu | Psaume 105:1-2, Psaume 9:2, Matthieu 5:16 |
| Il fortifie et console les autres | Psaume 66:16, 2 Corinthiens 1:4 |
| Il conduit d'autres à la foi | Jean 4:39, 1 Pierre 3:15 |
| Il transmet la foi aux générations | Psaume 78:4, Psaume 145:4 |
| On ne peut pas se taire | Actes 4:20, Psaume 40:10, Psaume 118:17 |

## Contenu

`config/encouragements.php`, clé `why_testify` : liste de `{ title, summary, icon (Font Awesome), verses: [{ ref, text }] }`. Ajouter, retirer ou réordonner une raison se fait uniquement là (puis `php artisan config:cache` si la configuration est mise en cache).

## Composant

`resources/views/components/why-testify.blade.php` :

```blade
<x-why-testify />                      {{-- carte ouverte, raisons repliées, bouton « Témoigner » --}}
<x-why-testify collapsible />          {{-- carte repliée sur une ligne (pages chargées) --}}
<x-why-testify :open="0" />            {{-- première raison ouverte --}}
<x-why-testify :action="false" />      {{-- sans bouton --}}
```

- Balises natives `<details>` / `<summary>` : aucun JavaScript, utilisable au clavier (Entrée / Espace) et par les lecteurs d'écran. L'attribut `name` commun rend les raisons exclusives (navigateurs récents ; ailleurs plusieurs peuvent rester ouvertes).
- Bouton **Témoigner** : Publier si connecté, sinon page de connexion.
- Styles : `accordion`, `accordion-item`, `accordion-summary`, `accordion-icon`, `accordion-chevron`, `accordion-panel`, `why-testify-head`, `why-testify-verse` (`resources/css/app.css`). Raison ouverte : fond bleu très clair et pastille bleue ; versets en italique avec un filet jaune et la référence en bleu.
- Responsive par **requêtes de conteneur** (`@container`, `@sm:` / `@md:` / `@lg:`) : le bloc s'adapte à la place disponible (colonne de 20 rem de l'accueil, page entière, mobile 390 px) ; le nombre de versets n'apparaît qu'à partir de 24 rem de large.

## Où

| Page | Présentation |
|---|---|
| Accueil (colonne de droite, sous le verset du jour ; en bas sur mobile) | carte ouverte, bouton Témoigner |
| Publier | carte repliée, sans bouton, sous le verset Psaume 66:16 |
| Mes témoignages | liste vide : carte ouverte, première raison dépliée ; sinon carte repliée sous la pagination |
| Lecture d'un témoignage (`/testimonies/{id}`, `/videos/{id}`) | carte repliée avant les commentaires (pas pour une entrée du carnet privé) |
| Inscription | carte repliée sous le formulaire, sans bouton |

## Application

Même bloc dans l'application : `WhyTestifyCard` (`lib/shared/widgets/why_testify_card.dart`), contenu `whyTestifyReasons` dans `lib/shared/content/encouragements.dart` — **copie de `why_testify` : modifier les deux fichiers ensemble.**

```dart
const WhyTestifyCard()                                  // carte ouverte, bouton « Témoigner »
const WhyTestifyCard(collapsible: true)                 // carte repliée sur une ligne
const WhyTestifyCard(initiallyOpen: 0)                  // première raison ouverte
const WhyTestifyCard(showAction: false)                 // sans bouton
```

Mêmes règles que le site : raisons exclusives (en ouvrir une referme la précédente), animation à l'ouverture, bouton **Témoigner** vers Publier (invité : demande de compte), nombre de versets affiché si la carte fait au moins 420 px de large, états « bouton / déplié » annoncés aux lecteurs d'écran.

| Écran | Présentation |
|---|---|
| Accueil (entre « À la une » et le fil) | carte repliée, bouton Témoigner |
| Publier | carte repliée, sans bouton, sous le verset Psaume 66:16 |
| Mes témoignages (onglet Tous) | liste vide : carte ouverte, première raison dépliée ; sinon carte repliée en fin de liste |
| Lecture d'un témoignage | carte repliée avant les commentaires |
| Inscription | carte repliée sous le formulaire, sans bouton |

Tests : `test/why_testify_test.dart`.

Voir aussi [messages pour inciter à témoigner](encouragements.md).
