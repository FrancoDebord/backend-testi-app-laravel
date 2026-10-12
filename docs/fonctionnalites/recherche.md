# Recherche et autocomplétion

Pendant la saisie dans une barre de recherche (application et site), TestiApp propose des **suggestions de texte** qui complètent la saisie et quelques **résultats directs** (témoignage, compte, événement, catégorie, livre de la Bible, session de prière) qui ouvrent la page correspondante. Quand le champ est vide, les **recherches récentes** de la personne s'affichent ; elles sont gardées sur l'appareil ou dans le navigateur, jamais sur le serveur.

## Pour les utilisateurs

- Dès **2 caractères**, une liste apparaît sous la barre. Les accents et les majuscules sont ignorés : « guer » trouve « Guérison ».
- La partie saisie est **en gras** dans chaque proposition.
- Toucher (ou cliquer) une **suggestion** lance la recherche avec ce texte.
- La flèche **« Compléter »** (↖) remplit le champ avec la suggestion **sans lancer** la recherche, pour continuer à taper.
- Un **résultat direct** (icône selon le type) ouvre sa page : témoignage, profil, événement, catégorie, livre de la Bible, session de prière.
- **Recherches récentes** : affichées quand le champ est vide. On peut les retirer une à une (×) ou toutes (« Tout effacer »). Chaque barre a sa propre liste (Explorer, Communauté, Mes abonnés, carnet…).
- **Hors ligne** : seules les recherches récentes sont proposées (et, dans la Bible, les livres de la traduction téléchargée).
- **Clavier** : ↑ ↓ pour parcourir, Entrée pour choisir, → pour compléter, Échap pour fermer. La liste est lisible au lecteur d'écran (liste déroulante ARIA sur le site ; boutons annoncés et nombre de propositions annoncé dans l'application).
- **Carnet privé** : la barre du carnet ne propose que les recherches récentes de l'appareil ; rien n'est envoyé au serveur.

## Ce qui est proposé

| Source | Suggestions de texte | Résultat direct |
|---|---|---|
| Titres de témoignages **publiés et publics** (auteur actif) | le titre (s'il commence par la saisie) ou l'expression qui complète la saisie (« guéri mon fils ») | oui (`testimony`) |
| Catégories actives | le nom | oui (`category`) |
| Noms des comptes **actifs et non privés** | le nom | oui (`user`) |
| Titres d'événements publiés (reportés et annulés compris, pas ceux masqués par la modération) | l'expression | oui (`event`) |
| Livres de la Bible (LSG, sinon la première traduction installée) | le nom | oui (`bible`) |
| Sessions de prière **publiques**, à venir ou du jour, non annulées | l'expression | oui (`prayer_session`) |
| Recherches populaires (validées au moins 3 fois) | la recherche | non |

Ordre : le texte **commence** par la saisie, puis un **mot** commence par la saisie, puis le texte la **contient** (à partir de 3 caractères) ; à rang égal, les plus populaires (mentions « j'aime », abonnés, participants…) d'abord.

Jamais proposés : e-mail, téléphone, comptes suspendus, bannis ou privés, témoignages en attente, refusés, réservés aux abonnés ou du carnet privé, sessions réservées aux abonnés, événements masqués.

## API

### `GET /api/v1/search/suggest`

Public (invités acceptés), 120 requêtes par minute.

| Paramètre | Valeurs | Défaut |
|---|---|---|
| `q` | texte saisi (200 caractères au plus, les 60 premiers sont utilisés) | — |
| `scope` | `all`, `testimonies`, `users`, `events`, `prayer`, `bible` | `all` |
| `limit` | 1 à 15 (nombre de suggestions de texte) | 8 |

Portées : `testimonies` = recherches populaires + catégories + témoignages ; `users` = comptes ; `events` = événements ; `prayer` = sessions de prière ; `bible` = livres de la Bible ; `all` = tout.

Moins de 2 caractères (après normalisation) : listes vides.

```json
{
  "success": true,
  "data": {
    "query": "guer",
    "scope": "all",
    "suggestions": [
      { "text": "Guérison miraculeuse de ma mère", "source": "testimony", "highlight": [0, 4] },
      { "text": "guéri mon fils", "source": "testimony", "highlight": [0, 4] }
    ],
    "results": [
      {
        "type": "category", "id": "guerison", "label": "Guérison", "sublabel": "Catégorie",
        "avatar": null, "verified": false, "highlight": [0, 4],
        "url": "https://…/explore?category=guerison", "route": "/explore/category/guerison"
      },
      {
        "type": "bible", "id": "5", "label": "Deutéronome", "sublabel": "Bible · 34 chapitres",
        "url": "https://…/bible?translation=LSG&book=5", "route": "/bible/LSG?book=5", "book": 5, "translation": "LSG"
      }
    ]
  },
  "message": ""
}
```

- `highlight` : `[début, longueur]` en caractères du texte, partie qui correspond à la saisie (ou `null`).
- `results[].type` : `testimony`, `user`, `event`, `category`, `bible`, `prayer_session` ; 6 résultats au plus, 3 par type.
- `url` : page du site ; `route` : chemin de l'application (`/testimony/{id}`, `/users/{id}`, `/events/{id}`, `/prayer-sessions/{id}`, `/explore/category/{slug}`, `/bible/{traduction}?book=` — l'application ouvre le lecteur sur le livre avec `book`).
- `avatar` : photo du compte ou couverture du témoignage ; `verified` : compte vérifié.

### `POST /api/v1/search/log`

Public, 30 requêtes par minute. Corps `{ "q": "…" }`. Enregistre une recherche **validée** (lancée), **sans identifiant de personne**, pour les recherches populaires. Ignoré : moins de 3 caractères, texte contenant « @ » ou une suite de chiffres (e-mail, téléphone). La page Explorer du site enregistre aussi ses recherches (première page).

## Fonctionnement

- Service : `App\Services\SearchSuggestions` ; contrôleur : `App\Http\Controllers\Api\SearchController`.
- **Normalisation** : chaque caractère est plié (`Str::ascii`, minuscules, ponctuation → espace) en gardant sa position d'origine, ce qui permet de renvoyer `highlight` sur le texte affiché.
- **Lexiques en cache (10 min)** par source : 3 000 derniers témoignages publiés, 3 000 comptes actifs non privés les plus suivis, 1 500 événements, 500 sessions, catégories, livres, 500 recherches populaires. Une recherche en base (`LIKE 'saisie%'` ou `'% saisie%'`) complète les témoignages, comptes et événements au-delà de ces plafonds (sur MySQL, la collation `utf8mb4_unicode_ci` ignore déjà les accents).
- **Revérification** : les comptes, témoignages, événements et sessions retenus sont relus en base à chaque calcul ; un compte suspendu ou un témoignage retiré disparaît aussitôt, même si le lexique date de quelques minutes.
- **Cache de 60 s** par portée, limite et préfixe normalisé : la réponse ne dépend pas de la personne (aucune donnée personnelle), elle est donc partagée ; en-tête `Cache-Control: public, max-age=60`.
- Table `search_queries` (migration `2026_10_07_300001_create_search_queries_table`) : `normalized` (unique), `query`, `hits`, `last_searched_at`. Aucune colonne de personne ni d'adresse IP.

## Site

Composant `resources/views/components/search-autocomplete.blade.php` (script en ligne, aucune recompilation), inclus une fois par `layouts/app`. Il s'active sur tout `<input data-search-autocomplete="portée">` :

- `data-search-autocomplete` : `all`, `testimonies`, `users`, `events`, `prayer`, `bible` ou `local` (récentes seulement, sans appel au serveur) ;
- `data-search-recent-key` : liste de récentes propre à la barre (clé `localStorage` `testiapp.search.recent.<clé>`) ;
- `data-search-submit="first-result"` : Entrée sans choix ouvre le premier résultat (livres de la Bible) ;
- `data-search-translation` : traduction conservée à l'ouverture d'un livre.

Anti-rebond 250 ms, requête obsolète annulée (`AbortController`), rôle `combobox` + `listbox`, `aria-activedescendant`, annonce du nombre de propositions (`role="status"`). La soumission reste celle du formulaire de la page.

Barres branchées : en-tête (toutes pages, `all`), Explorer (`all`), Vidéos (`testimonies`), Communauté, Mes abonnés, Mes abonnements (`users`), Événements (`events`), carnet privé (`local`), Bible « Aller à un livre » (`bible`, nouveau champ dans la colonne des livres), administration : utilisateurs (`users`) et contenu (`testimonies`).

## Application

- Widget `SearchAutocomplete` (`lib/shared/widgets/search_autocomplete_field.dart`) : enveloppe la barre existante (`fieldBuilder`) et garde son comportement de soumission (`onSubmitted`) ; `SearchAutocompleteField` = champ standard prêt à l'emploi.
- Données : `lib/shared/search/search_suggestions.dart` (modèles, `searchSuggestFetcherProvider` avec `CancelToken`, `searchLoggerProvider`, récentes dans le stockage sécurisé `search_recent_<clé>`, 8 au plus).
- Anti-rebond 250 ms ; requête précédente annulée et réponse obsolète ignorée ; hors ligne (`isOnlineProvider`) ou erreur : récentes seulement.
- Barres branchées : recherche de l'accueil et d'Explorer (`all`, recherche en direct conservée), Communauté, Mes abonnés, Mes abonnements (`users`), carnet privé (`local`), administration des utilisateurs (`users`), lecteur de la Bible (loupe → « Aller à un livre » : livres de la traduction proposés aussi hors ligne, « Jean 3 » ouvre le chapitre).
- Pas encore de barre de recherche dans l'application pour les événements, les requêtes et sessions de prière ni les téléchargements : le widget s'y branche avec `scope: SearchScope.events` / `prayer` quand elles seront ajoutées.

## Tests

- Serveur : `tests/Feature/SearchSuggestTest.php` (préfixe, accents et casse, portées, visibilité, compte suspendu malgré le lexique en cache, recherches populaires anonymes, validation, branchement des barres du site, positions du surlignage).
- Application : `test/search_autocomplete_test.dart` (anti-rebond, réponse obsolète, sélection, « Compléter », résultat direct, récentes, hors ligne, clavier, 320 px × 1,3).
