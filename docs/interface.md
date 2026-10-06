# Interface web — charte ARISE & SHINE Krea

Toutes les pages du site suivent la charte **ARISE & SHINE Krea** : **lumineuse, arrondie, aérée, simple et chaleureuse**. Chaque couleur a un sens :

| Couleur | Sens | Part de l'écran | Usage |
|---|---|---|---|
| **Bleu** Krea Blue | confiance, structure | ~25 % | navigation, titres, action principale, éléments actifs |
| **Orange** Krea Orange | action, énergie | ~10 % | appel à l'action très important (Publier, Créer mon compte), pastilles de compteur |
| **Jaune** Krea Sun | lumière, inspiration | ~5 % | accents, nouveautés, encouragements, encadrés « inspiration » |
| **Blanc** | espace, clarté | ~60 % | fonds, cartes |

**Règles** : jamais de texte blanc sur le jaune (texte bleu foncé) ; très peu de dégradés (bleu → bleu foncé, ou orange → jaune pour une grande illustration, jamais sur les boutons) ; ombres très légères ; bordures fines ; pas d'interface sombre ou chargée. Les couleurs de message (succès, alerte, erreur) restent distinctes des couleurs de marque.

## Logo

| Fichier | Usage |
|---|---|
| `public/icons/arise-shine-krea.png` | Logo complet, fond transparent (477 × 493), dans le menu latéral (56 px de haut), l'en-tête mobile (44 px) et les pages sans connexion |
| `public/icons/arise-shine-krea-star.png` | Étoile seule : icône d'onglet du navigateur |

Couleur de la barre du navigateur mobile : `<meta name="theme-color" content="#184797">`.

**Bas du menu latéral** (`layouts/app`) : citation « Gloire à Dieu pour chaque vie transformée ! » en écriture manuscrite (`font-script`, police Caveat) avec un soleil, puis paysage de vagues (jaune clair, jaune, bleu) et soleil, dessinés en SVG aux couleurs de la charte. Décoratif (`aria-hidden`), masqué quand l'écran fait moins de 860 px de haut pour laisser la place au menu. L'ancien logo `public/icons/airid.png` n'est plus utilisé.

## 1. Jetons de style : `resources/css/theme.css`

**Source unique** des couleurs, de la police, des arrondis et des ombres (bloc `@theme` de Tailwind 4, importé par `resources/css/app.css`). Les mêmes valeurs existent en variables CSS (`--krea-blue`, `--krea-orange`, `--krea-yellow`, `--krea-text`…) pour un style hors Tailwind.

### Couleurs

| Jeton Tailwind | Valeur | Nom | Usage |
|---|---|---|---|
| `primary-600` | `#184797` | Krea Blue | bouton principal, entrée active du menu, titres, liens, focus |
| `primary-700` | `#103675` | Blue Dark | survol du bouton principal, texte très important, texte sur le jaune |
| `primary-50` | `#EAF1FC` | Blue Light | fonds bleus légers (survol, sélection, message d'information) |
| `accent-500` | `#F18717` | Krea Orange | bouton `btn-cta` |
| `accent-600` | `#D96F0B` | Orange Dark | survol de `btn-cta`, texte de `badge-orange` |
| `accent-50` | `#FFF1E2` | Orange Light | fonds, badges |
| `sun-400` | `#FCC11D` | Krea Sun | bouton `btn-accent`, étoiles, illustrations |
| `sun-50` / `sun-200` / `sun-700` | `#FFF8D9` / `#FDE7A0` / `#8A6500` | Yellow Light | encadré `card-insight`, `badge-yellow` |
| `slate-900` | `#263238` | Text | texte courant |
| `slate-500` | `#667085` | Muted | sous-textes |
| `slate-200` / `slate-300` | `#E4E7EC` / `#D0D5DD` | Border | bordures des cartes / des champs |
| `slate-50` | `#F8FAFC` | Background | fond des pages |
| `success-500` / `-50` | `#12B76A` / `#ECFDF3` | Succès | pastille, message |
| `warning-500` / `-50` | `#F79009` / `#FFFAEB` | Alerte | pastille, message |
| `error-500` / `-50` | `#D92D20` / `#FEF3F2` | Erreur | pastille, message, champ en erreur |

Variables CSS du guide de la charte (§27), dans `:root` : `--krea-blue`, `--krea-blue-dark`, `--krea-blue-light`, `--krea-orange`, `--krea-orange-dark`, `--krea-orange-light`, `--krea-yellow`, `--krea-yellow-light`, `--white`, `--text`, `--text-muted`, `--border`, `--background`, `--success(-bg)`, `--warning(-bg)`, `--error(-bg)`, `--info(-bg)`, `--font-family`, les dégradés autorisés `--krea-gradient-blue` et `--krea-gradient-sun`, et les arrondis **`--krea-radius-xs` à `--krea-radius-pill`** (préfixés : les noms `--radius-*` appartiennent à Tailwind).

Les neutres `slate-*` de Tailwind sont **redéfinis** sur les gris de la charte : les pages existantes (`text-slate-500`, `border-slate-200`…) suivent la charte sans être réécrites. `primary-*` (bleu), `accent-*` (orange) et `sun-*` (jaune) ont chacun une gamme de 50 à 900.

### Typographie

Police **Plus Jakarta Sans** (400, 500, 600, 700), chargée depuis `fonts.bunny.net` dans `layouts/app` et `layouts/guest`, avec repli sur les polices du système et des émojis. Écriture manuscrite **Caveat** (600, 700) pour les citations décoratives : classe `font-script` (jeton `--font-script`). Texte courant 14–16 px, interligne 1,6.

| Niveau | Style |
|---|---|
| `text-h1` | 32 px sur mobile, 48 px dès 768 px, gras 700, interligne 1,1, bleu (bandeaux d'accueil) |
| `text-h2` | 28 px, 36 px dès 768 px, gras 700, interligne 1,2, bleu |
| `text-h3` | 24 px, gras 700, interligne 1,3, bleu |
| `text-h4` | 20 px, semi-gras 600, texte courant |
| `text-body` / `text-secondary` / `text-small` | 16 px / 1,6 · 14 px gris / 1,5 · 12 px gris 500 |
| Titre de page (`$header`) | 24 px (28 px dès 640 px), gras 700, `primary-600` |
| `h1`, `h2`, `h3` | bleu par défaut (`primary-600`, `h3` des cartes en `primary-700`), interligne 1,25 |
| Titre de carte (`card-title`) | 16 px, gras 700, `primary-600` |
| Texte secondaire | 14 px, `slate-500` |
| Petit texte | 12 px, `slate-500` |
| Boutons | 15 px, semi-gras 600 (13 px avec `btn-sm`) |

### Arrondis et ombres

| Jeton | Valeur | Usage |
|---|---|---|
| `rounded-xs` | 6 px | très petits éléments |
| `rounded-md` | 8 px | petits éléments |
| `rounded-lg` | 10 px | **boutons, champs** |
| `rounded-xl` | 16 px | **cartes** |
| `rounded-2xl` | 20 px | grandes cartes (`card-brand`) |
| `rounded-full` | 999 px | badges, filtres, avatars |
| `shadow-card` | `0 4px 20px rgb(16 54 117 / 8 %)` | cartes |
| `shadow-soft` | `0 1px 3px rgb(16 54 117 / 6 %)` | boutons, entrée active du menu |

Espacements : grille de 4 px (classes Tailwind habituelles) ; padding de carte conseillé 24 px (`p-6`), 16 px entre cartes, 64 px entre sections. **Largeur du contenu** : 1280 px au plus (`max-w-[1280px]` dans `layouts/app`), marges 16 px sur mobile, 24–32 px sur ordinateur.

Pastilles d'information (8 px) : `success-500` favorable · `warning-500` à surveiller · `error-500` défavorable · `slate-400` neutre.

### Application mobile

Même charte dans l'application (`testi_app`) :

| Élément | Fichier |
|---|---|
| Palette (`primary`, `primaryDark`, `primarySoft`, `secondary` orange, `sun`, neutres, messages, dégradés de catégories) | `lib/core/theme/app_colors.dart` |
| Police Plus Jakarta Sans embarquée (400 à 800, licence `assets/fonts/OFL.txt`), `AppFonts.family` ; titres gras en bleu | `pubspec.yaml`, `lib/core/theme/app_text_styles.dart` |
| Thème Material : boutons 48 px arrondis 10 px, champs 48 px (bordure `#D0D5DD`, focus bleu), cartes 16 px, onglets bleus, bouton flottant orange | `lib/main.dart` (`_buildTheme`) |
| Barre de navigation : icône active bleue sur fond bleu clair arrondi 12 px, inactive `#667085` | `lib/shared/widgets/scaffold_with_bottom_nav.dart` |
| Logo (`assets/images/arise_shine_krea.png`) : écran de démarrage sur fond blanc, en-tête de l'accueil | `splash_screen.dart`, `home_screen.dart` |

Les couleurs écrites en dur dans les écrans (violets, gris Tailwind, rouge / vert / ambre) ont été remplacées par celles de la charte.

## 2. Composants CSS

Définis dans `resources/css/app.css`. À réutiliser tels quels : ne pas créer de nouvelles couleurs ni de nouveaux styles de bouton.

| Besoin | Classe(s) |
|---|---|
| Action principale | `btn-primary` (bleu, **un seul par zone**) |
| Appel à l'action très important | `btn-cta` (orange : Publier un témoignage, Nouveau témoignage, Créer mon compte, S'inscrire ; un seul par page, avec parcimonie) |
| Taille des boutons | 48 px de haut, 20 px de marge intérieure, 15 px / 600 ; `btn-sm` : 36 px, 13 px |
| Accent inspiration | `btn-accent` (jaune, texte bleu foncé : Découvrir) |
| Autres boutons | `btn-soft` (fond bleu clair, texte bleu foncé : actions secondaires des « Actions rapides »), `btn-secondary` (contour bleu), `btn-ghost` ; taille réduite : ajouter `btn-sm` |
| Carte de témoignage encadrée | `videos/partials/tile` (accueil : miniature, catégorie, titre, auteur, J'aime / commentaires / partages) ; `components.testimony-list` avec `'variant' => 'tile'` |
| Carte | `card` (blanche, bordure fine, ombre légère, 16 px) ; `card-brand` (fond bleu, texte blanc, accent `text-sun-400`) ; `card-orange` (fond orange clair, titre orange, ponctuelle) ; `card-insight` (fond jaune clair, bordure `#FDE7A0`, avec une icône soleil ou étoile) |
| Titre de carte ou de section | `card-title` (bleu, gras) |
| Libellé de groupe (menu latéral) | `section-title` (petites capitales grises) |
| Formulaire | `form-label`, `form-input` (champ, liste, zone de texte, fichier ; 48 px de haut, bordure `#D0D5DD`, focus bleu avec halo bleu clair de 3 px, `aria-invalid="true"` → bordure rouge, `data-valid="true"` → bordure verte), `form-hint`, `form-error` |
| Tableau | `table-th`, `table-td`, tableau placé dans un conteneur `overflow-x-auto` |
| Actions de ligne | `action-btn-view`, `action-btn-edit`, `action-btn-delete`, `action-btn-pdf`, `action-btn-success` |
| Badges de statut | `badge-draft`, `badge-pending`, `badge-validated`, `badge-active`, `badge-rejected`, `badge-suspended`, `badge-banned`, `badge-expired`, `badge-live` (pastille rouge « En direct »), `badge-neutral` |
| Étiquettes de marque | `badge-blue`, `badge-orange`, `badge-yellow` (catégorie, nouveauté ; pilule 6 × 10 px, 12 px / 600) |
| Filtres / bascules | `chip`, `chip-active` (bleu) |
| Accordéon | `accordion` > `details.accordion-item` > `summary.accordion-summary` (`accordion-icon`, `accordion-chevron` avec `group-open:rotate-180`) + `accordion-panel` ; balises natives, sans JavaScript (voir [Pourquoi témoigner ?](fonctionnalites/pourquoi-temoigner.md)) |
| Onglets | `tab`, `tab-active` (souligné bleu) |
| Messages | `alert-success`, `alert-error`, `alert-info` (bleu clair), `alert-warning` |
| Chargement | `spinner` |
| Éditeur de texte | `rt-btn`, `rt-emoji`, `rt-content` (voir [mise en forme](fonctionnalites/mise-en-forme-temoignages.md)) |
| Texte long issu de Markdown | `doc-content` (voir [documentation en ligne](fonctionnalites/documentation-en-ligne.md)) |

**Menu latéral** : entrée active sur fond bleu plein, texte et icône blancs ; autres entrées en gris foncé, icône bleue, survol bleu clair. **Sélection** (carte de choix, pastille « Épingler », page courante de la pagination) : bordure ou fond `primary-600`, jamais le gris foncé.

Les enums `TestimonyStatus` et `UserAccountStatus` renvoient la bonne classe de badge via `badgeClass()` :

```blade
<span class="{{ $testimony->status->badgeClass() }}">{{ $testimony->status->label() }}</span>
```

**Texte masqué sur mobile** : ne pas utiliser `sr-only sm:not-sr-only`. La classe `.sr-only` de Font Awesome (feuille hors des couches Tailwind) l'emporte et le texte reste invisible à toutes les largeurs. Écrire `<span class="hidden sm:inline" aria-hidden="true">Libellé</span>` et mettre `aria-label="Libellé"` sur le bouton.

**Tailwind ne compile que les classes écrites en entier** : ne jamais construire un nom de classe (`bg-{{ $couleur }}-500`) ; écrire les classes complètes dans un tableau PHP ou une condition.

## 3. Composants Blade

| Fichier | Utilisation |
|---|---|
| `components/avatar.blade.php` | `@include('components.avatar', ['user' => $user, 'size' => 'xs|sm|md|lg|xl'])` : photo ou initiales |
| `components/empty-state.blade.php` | `@include('components.empty-state', ['title' => …, 'text' => …, 'actionUrl' => …, 'actionLabel' => …])` |
| `components/switch.blade.php` | Interrupteur. `'sendFalse' => true` envoie `0` quand il est décoché (nécessaire si le contrôleur doit pouvoir désactiver la valeur) |
| `components/testimony-card.blade.php` | Carte de témoignage des grilles (accueil, exploration, profil…), présentation « plateforme vidéo ». Options `'short' => true` (vertical), `'large' => true` (mise en avant). Grille conseillée : `grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4` |
| `components/rich-editor.blade.php` | Éditeur gras / italique / émojis |
| `videos/partials/card.blade.php` | Carte « plateforme vidéo » (miniature, durée, auteur, vues) : `@include('videos.partials.card', ['testimony' => $t, 'short' => false])`. Voir [page Vidéos](fonctionnalites/videos.md) |
| `components/testimony-list.blade.php` | **Liste de témoignages à utiliser pour toute nouvelle liste** : grandes cartes ou lignes compactes dépliables selon le choix de la personne. `@include('components.testimony-list', ['items' => $list, 'routeName' => 'testimonies.show'])` (options `listId`, `gridClass` littérale, `tab`). Voir [affichage et lecture](fonctionnalites/affichage-et-lecture.md) |
| `components/country-select.blade.php` | Choix du pays : `@include('components.country-select', ['name' => 'country', 'id' => 'country', 'value' => old('country', …)])`. `<select>` classique (sans JavaScript), transformé en liste avec recherche et drapeaux (`public/flags/{code}.svg`, flag-icons, MIT). Liste et codes ISO : `App\Support\Countries` ; valider avec `Rule::in(Countries::all())`, ou `Countries::allowed($valeurActuelle)` pour garder une ancienne valeur |
| `components/follow-button.blade.php` | Bouton « Suivre » : `@include('components.follow-button', ['user' => $u, 'following' => bool, 'primary' => false, 'small' => true])`. Rien pour son propre compte ; lien de connexion sans compte ; bascule sans rechargement (`data-follow-form`) et mise à jour des `[data-follower-count="{id}"]`. Voir [abonnements](fonctionnalites/abonnements.md) |
| `components/encouragement.blade.php` | Message pour inciter à témoigner (verset ou invitation de `config/encouragements.php`, bouton Témoigner) : `<x-encouragement variant="banner\|feed" :index … />`. Les listes l'insèrent tous les 8 témoignages avec l'option `encourage`. Voir [encouragements](fonctionnalites/encouragements.md) |
| `components/why-testify.blade.php` | « Pourquoi témoigner ? » : raisons bibliques en accordéon (`config/encouragements.php`, `why_testify`) : `<x-why-testify collapsible :open="0" :action="false" />`. Voir [Pourquoi témoigner ?](fonctionnalites/pourquoi-temoigner.md) |
| `components/user-picker.blade.php` | Recherche d'une personne (comptes personnels actifs) pour désigner un gestionnaire : fonctionne sans JavaScript (`?personne=`), recherche à la frappe avec (`initUserPicker`, route `users.search`). Voir [événements](fonctionnalites/evenements.md) |
| `components/layout-toggle.blade.php` | Boutons « Grandes cartes / Liste compacte », à placer au-dessus d'une `testimony-list` |
| `vendor/pagination/tailwind.blade.php` | Pagination (`{{ $items->links() }}`) |

## 4. Structure d'une page

Layout principal : `layouts/app.blade.php` (menu latéral + barre supérieure + pied de page).

```blade
@extends('layouts.app')
@section('title', 'Titre court')
@php
    $header      = 'Titre de la page';
    $subheader   = 'Sous-titre explicatif';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Section', 'url' => route('…')],
        ['label' => 'Page actuelle'],
    ];
    // $fullBleed = true;   // page sans marges (ex. lecteur Bible)
@endphp

@section('headerActions') {{-- boutons en haut à droite --}} @endsection
@section('content') … @endsection
@push('scripts') … @endpush
```

- **Menu latéral** : ajouter la page dans `$navGroups` en haut de `layouts/app.blade.php`. Chaque groupe a une condition `visible` (connexion, `canModerate()`, `isAdmin()`), et chaque entrée a `label`, `icon` (Font Awesome), `url` et `active`.
- **Pages sans connexion** (connexion, inscription, mot de passe oublié) : `@extends('layouts.guest')`. Le layout affiche le panneau de marque (dès 1024 px) ou un bandeau illustré, puis la carte du formulaire (`card p-6 sm:p-8`, titre `text-h3`, sous-titre `text-secondary`). Paysage vagues et soleil : `@include('layouts.partials.krea-landscape', ['class' => '…', 'viewBox' => '0 50 400 150'])` (le cadrage est facultatif).
- **Messages** : `session('success')`, `session('status')`, `session('error')` et `withErrors(['error' => …])` sont affichés automatiquement en haut du contenu.

## 5. Comportements JavaScript (`resources/js/app.js`)

Tout s'active par attributs HTML, sans code supplémentaire dans la page.

| Besoin | Mise en œuvre |
|---|---|
| **Confirmation** (jamais `confirm()`) | `<button type="button" onclick="openConfirmModal('id-du-form', 'Message', 'Titre', 'Libellé', 'fa-icone')">`. Le formulaire est ensuite envoyé avec `requestSubmit()` (validation HTML respectée). Pour un texte venant de la base, utiliser `@js(...)` |
| **Chargement d'un formulaire POST** | Automatique (voile + bouton désactivé). Texte : `data-loading-label="Enregistrement…"` sur le `<form>`. Désactiver : `data-no-loading` |
| **Chargement d'un filtre GET** | `data-loading-inline` sur le `<form>` : indicateur sur le bouton seulement |
| **Modale** | Conteneur `data-modal id="…" hidden` ; ouvrir avec `data-modal-open="id"`, fermer avec `data-modal-close` ou Échap |
| **Bloc repliable** (filtres mobiles…) | `data-toggle="id-cible"` sur le bouton ; la cible porte la classe `hidden` (par ex. `hidden md:block`) |
| **Brouillon automatique** | `data-autosave="clé"` sur le `<form>` (enregistré dans le navigateur). `data-has-old="1"` si le serveur a renvoyé la saisie (pas de restauration). Exclure un champ : `data-autosave-ignore`. Zone d'état : `[data-autosave-status]` |
| **Blocage hors connexion** | `data-online-only` sur le `<form>` ; bandeau optionnel `data-offline-notice hidden` |
| **Engagement obligatoire** | Case `data-required-check` + bouton d'envoi `data-submit-guard disabled` |
| **Personne / organisation** (inscription) | `data-account-type-form` sur le `<form>` (radios `account_type`) ; blocs `data-account-section="individual"` / `"organization"` ; champs `data-section-required` rendus obligatoires dans le bloc visible ; mentions `data-account-nojs-hint` masquées. Sans JavaScript, tout reste visible (voir [comptes organisation](fonctionnalites/comptes-organisation.md#inscription-depuis-le-site)) |
| **Afficher un mot de passe ou une clé** | `<button type="button" data-reveal="id-du-champ" data-reveal-label="le mot de passe" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>` : bascule le champ entre masqué et visible, met à jour l'icône et le libellé (« la clé » par défaut) |
| **Message éphémère** | `window.flash('Texte', 'success|error|warning|info')` |
| **Liste avec recherche** (pays) | `<select data-country-select>` : remplacé par un champ `role="combobox"` qui filtre sans tenir compte des accents (« cote » → Côte d'Ivoire), pays commençant par la saisie en premier ; flèches, Entrée, Échap ; champ vidé = aucun pays. Le `<select>` masqué reste celui qui est envoyé, et le `<label for>` désigne le champ de recherche |
| **Ligne dépliable** (liste compacte) | Bouton `data-row-toggle` avec `aria-controls="id"` et `aria-expanded="false"` ; panneau `id="…" hidden` ; icône `data-row-icon` retournée à l'ouverture (`resources/js/videos.js`) |
| **Réglages de lecture** | Attributs `data-player-*` et `data-up-next-*` de la page de lecture : voir [affichage et lecture](fonctionnalites/affichage-et-lecture.md#javascript-resourcesjsvideosjs) |

## 6. Responsive

Chaque page est vérifiée à **390 px, 768 px et 1440 px** : aucun défilement horizontal de la page.

- Grilles : `grid-cols-1`, puis `sm:` / `md:` / `lg:` ; éléments longs en `flex-wrap` et `min-w-0`.
- **Tableaux** : liste de fiches compactes sur mobile (`md:hidden`) et tableau à partir de `md` (`hidden md:block`).
- **Filtres nombreux** : repliés derrière un bouton « Filtres » sur mobile, avec le nombre de filtres actifs.
- Le menu latéral devient un tiroir sous `lg` (bouton ☰ de la barre supérieure).

## 7. Ajouter une page : liste de contrôle

1. Vue qui étend `layouts.app` avec `$header`, `$subheader` et `$breadcrumbs`.
2. Entrée dans `$navGroups`, avec la bonne condition de visibilité.
3. Contrôle d'accès côté serveur (middleware `role:` ou `abort_unless`) et validation de toutes les règles, même celles déjà vérifiées en JavaScript.
4. Composants existants uniquement ; textes affichés avec `{{ }}`.
5. Confirmation par `openConfirmModal` pour toute action sensible.
6. Vérification à 390, 768 et 1440 px.
7. `npm run build`, `php artisan view:cache`, `php artisan test`.
8. Mise à jour de la documentation (voir [README](README.md#6-maintenir-cette-documentation)).
