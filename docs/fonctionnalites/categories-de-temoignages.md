# Catégories (types) de témoignages : nom anglais

Les catégories de témoignages, appelées « types de témoignages » dans l'application et sur le site (Guérison, Délivrance, Conversion…), sont les lignes de la table `categories` (`App\Models\Category`). Depuis le 2026-10-20, chacune peut avoir un **nom anglais** facultatif, affiché quand le site ou l'application est en anglais. À ne pas confondre avec les [types de témoignages autorisés](types-de-temoignages.md) (texte, audio, vidéo, YouTube).

## Données

| Colonne | Rôle |
|---|---|
| `name` | Nom français (obligatoire, 50 caractères) |
| `name_en` | Nom anglais facultatif (100 caractères), `null` si absent |
| `slug` | Identifiant stable (`guerison`…) ; c'est lui que porte un témoignage (`category` dans l'API) |
| `icon`, `color`, `display_order`, `testimony_count`, `is_active` | Inchangés |

Migration `2026_10_20_100001_add_name_en_to_categories` : ajoute `name_en` et le remplit pour les slugs connus (sans écraser une valeur déjà saisie). Le `CategorySeeder` utilise la même liste (`Category::ENGLISH_NAMES`) :

| Slug | Nom anglais |
|---|---|
| `guerison` | Healing |
| `delivrance` | Deliverance |
| `conversion` | Conversion |
| `salut` | Salvation |
| `mariage` | Marriage |
| `famille` | Family |
| `finances` | Finances |
| `provision` | Provision |
| `miracles` | Miracles |
| `protection`, `protection_divine` | Divine protection |
| `ministere` | Ministry |
| `emploi` | Employment & Career |
| `etudes` | Studies |
| `autre` | Other |

## Nom affiché

`Category::displayName()` (et l'accesseur `$category->localized_name`) : `name_en` quand la langue courante (`app()->getLocale()`) est l'anglais et que `name_en` est renseigné, sinon `name`. La langue vient de `Accept-Language` pour l'API et du choix de langue (ou du compte) sur le site ([langues](langues.md)).

Utilisé partout où une catégorie est affichée sur le site : cartes, lignes et tuiles de témoignages, page d'un témoignage, filtres de l'accueil, d'Explorer, de Vidéos et de la modération, formulaires (publier un témoignage, modifier, lancer un direct, témoignage officiel d'un événement), tableaux de bord, image de partage d'un témoignage, suggestions de recherche (`SearchSuggestions`, lexique des catégories mis en cache par langue).

## API

`CategoryResource`, renvoyée par `GET /api/v1/categories`, `GET /api/v1/categories/{slug}` et les routes `admin/categories` :

```json
{
  "id": "…",
  "name": "Healing",
  "nameFr": "Guérison",
  "nameEn": "Healing",
  "slug": "guerison",
  "icon": "🙏",
  "color": "#10B981",
  "testimonyCount": 12,
  "displayOrder": 1,
  "isActive": true
}
```

- `name` : nom dans la langue de la requête (même règle que `displayName()`) ;
- `nameFr` : nom français enregistré ; `nameEn` : nom anglais ou `null`.

Un témoignage garde `category` = slug ; l'application retrouve le nom par la liste des catégories. Aucune autre ressource de l'API ne renvoie de nom de catégorie.

### Administration (administrateurs)

- `POST /api/v1/admin/categories` : `name` (obligatoire, ≤ 50), `name_en` (facultatif, ≤ 100), `slug`, `icon`, `color`, `display_order` ;
- `PUT /api/v1/admin/categories/{id}` : accepte aussi `name_en` ; chaîne vide ou `null` → nom anglais effacé ; clé absente → inchangé.

## Site

*Administration › Catégories* (`/admin/categories`) : champ **« Nom anglais (facultatif) »** / « English name (optional) » dans le formulaire d'ajout et dans la fenêtre de modification (vide = effacé), colonne « Anglais » dans le tableau et ligne dans les fiches mobiles. Textes : `lang/{fr,en}/admin.php` (`categories.*`).

## Tests

`tests/Feature/CategoryNamesTest.php` : `displayName()` selon la langue et repli, `GET categories` avec `Accept-Language: en` / `fr`, création et modification par l'API d'administration (`name_en`, effacement par chaîne vide, validation), formulaire du site (ajout, effacement, libellés FR / EN), page Explorer en anglais, seeder.
