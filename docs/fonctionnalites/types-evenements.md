# Types d'événements

Les types d'événements (croisade, conférence, séminaire, camp, tournée, concert de louange, retraite, autre…) ne sont plus une énumération figée : les **administrateurs** les ajoutent, renomment (français / anglais), réordonnent, activent / désactivent et suppriment, sur le site et dans l'application mobile (2026-10-19). Voir aussi [Événements chrétiens](evenements.md).

## Règles

| Règle | Détail |
|---|---|
| **Nom court** (`slug`) | Enregistré dans `events.type` (chaîne, sans clé étrangère). Tiré du nom français (`Str::slug`, « Camp de jeunes » → `camp-de-jeunes`, suffixe `-2`, `-3`… s'il existe déjà) ou saisi (`^[a-z0-9-]+$`, 40 car., unique). **Jamais modifiable** ensuite. |
| **Nom** | `name_fr` obligatoire (80 car.), `name_en` facultatif : en anglais, `name_en` s'il est renseigné, sinon `name_fr`. |
| **Icône** | Une clé de la liste ci-dessous (défaut `calendar`) ; le site l'affiche avec la classe Font Awesome correspondante, l'application avec sa propre icône. |
| **Ordre** | `position` (1, 2, 3…) : ordre des listes, des filtres et du formulaire. |
| **Actif / inactif** | Un type inactif n'est plus proposé à la création d'un événement ni dans les filtres ; un événement qui l'utilise déjà **le garde** (et peut être modifié sans en changer). Il doit toujours rester **au moins un type actif** (désactivation ou suppression du dernier : 422). |
| **Suppression** | Type inutilisé : supprimé. Type utilisé par des événements : il faut choisir un **type de remplacement** actif (`replace_with`) ; les événements (supprimés compris) y passent, puis le type est supprimé. Sans remplaçant valide : 409. |
| **Libellé inconnu** | Un événement dont le type n'existe plus (slug introuvable) affiche le slug « humanisé » (`camp-jeunes` → « Camp jeunes ») et l'icône `calendar`. |

Types de départ (migration, mêmes slugs que l'ancienne énumération `App\Enums\EventType`, supprimée) :

| Ordre | Slug | Français | Anglais | Icône |
|---|---|---|---|---|
| 1 | `crusade` | Croisade d'évangélisation | Evangelistic crusade | `bullhorn` |
| 2 | `conference` | Conférence | Conference | `microphone` |
| 3 | `seminar` | Séminaire | Seminar | `chalkboard` |
| 4 | `camp` | Camp | Camp | `campground` |
| 5 | `tour` | Tournée | Tour | `route` |
| 6 | `worship` | Concert de louange | Worship concert | `music` |
| 7 | `retreat` | Retraite spirituelle | Spiritual retreat | `dove` |
| 8 | `other` | Autre événement | Other event | `calendar` |

### Icônes autorisées

| Clé | Font Awesome (site) | Clé | Font Awesome (site) |
|---|---|---|---|
| `bullhorn` | `fa-bullhorn` | `users` | `fa-users` |
| `microphone` | `fa-microphone` | `hands` | `fa-hands-praying` |
| `chalkboard` | `fa-chalkboard-user` | `star` | `fa-star` |
| `campground` | `fa-campground` | `book` | `fa-book-bible` |
| `route` | `fa-route` | `heart` | `fa-heart` |
| `music` | `fa-music` | `globe` | `fa-earth-africa` |
| `dove` | `fa-dove` | `cross` | `fa-cross` |
| `calendar` | `fa-calendar-days` | `fire` | `fa-fire` |
| `church` | `fa-church` | `child` | `fa-children` |
| | | `graduation` | `fa-graduation-cap` |

Liste dans `App\Models\EventType::ICONS` (validation `Rule::in`).

## Site

- *Administration › Types d'événements* (`/admin/types-evenements`, administrateurs) : ajout (nom FR / EN, identifiant facultatif, choix de l'icône parmi les icônes affichées, actif), liste avec **glisser-déposer** ou flèches **Monter / Descendre**, **Modifier** (fenêtre : noms, icône, actif ; l'identifiant est affiché, non modifiable), **Activer / Désactiver**, **Supprimer** (fenêtre avec « Remplacer par » quand des événements utilisent le type). Lien dans le menu Administration et dans les actions rapides du tableau de bord.
- Formulaire d'événement : types actifs dans l'ordre (plus le type actuel s'il a été désactivé). Pastilles de filtre (`/evenements`, `/evenements/passes`) avec icône ; badges des cartes et de la page de l'événement : nom et icône de la table.

| Route | Méthode | Adresse |
|---|---|---|
| `admin.event-types.index` | GET | `/admin/types-evenements` |
| `admin.event-types.store` | POST | `/admin/types-evenements` |
| `admin.event-types.update` | PUT | `/admin/types-evenements/{id}` |
| `admin.event-types.toggle` | POST | `/admin/types-evenements/{id}/toggle` |
| `admin.event-types.move` | POST | `/admin/types-evenements/{id}/move` (`direction` = `up` \| `down`) |
| `admin.event-types.reorder` | PUT | `/admin/types-evenements/ordre` (`ids[]`, JSON ou formulaire) |
| `admin.event-types.destroy` | DELETE | `/admin/types-evenements/{id}` (`replace_with`) |

## API (`/api/v1`)

| Méthode | Route | Accès | Rôle |
|---|---|---|---|
| GET | `event-types` | public | Types **actifs** dans l'ordre ; `?all=1` : tous (inactifs compris) **pour un administrateur** (ignoré sinon) |
| POST | `admin/event-types` | administrateurs | `{ name_fr, name_en?, icon?, slug?, is_active? }` → 201 |
| PUT | `admin/event-types/{id}` | administrateurs | `{ name_fr?, name_en?, icon?, is_active?, position? }` → élément (le `slug` envoyé est ignoré) |
| PUT | `admin/event-types/reorder` | administrateurs | `{ ids: [int…] }` → liste complète (inactifs compris), positions 1..n ; les types absents de `ids` suivent dans leur ordre actuel |
| DELETE | `admin/event-types/{id}` | administrateurs | `replace_with?` (slug d'un autre type actif) |

Modérateurs et utilisateurs : 403 sur `admin/*`.

Élément :

```json
{ "id": 1, "slug": "crusade", "name": "Croisade d'évangélisation", "nameFr": "Croisade d'évangélisation",
  "nameEn": "Evangelistic crusade", "icon": "bullhorn", "position": 1, "isActive": true, "eventsCount": 12 }
```

`name` suit la langue de la requête (`Accept-Language: en`) ; `eventsCount` = événements (tous statuts, hors supprimés) utilisant le type.

Erreurs :

- 409 (type utilisé, `replace_with` absent ou invalide) : `{ "success": false, "message": "…", "errors": { "events": ["…"] }, "eventsCount": 3 }` ;
- 422 : validation (`errors.name_fr`, `errors.slug`, `errors.icon`…), dernier type actif (`errors.is_active` à la désactivation, `errors.event_type` à la suppression).

Événements :

- `POST|PUT events` : `type` = slug d'un type **actif** (422 `errors.type` sinon) ; en modification, le type déjà enregistré reste accepté même désactivé.
- `EventResource` : `type` (slug), `typeLabel` (nom localisé ; slug inconnu → slug humanisé) et **`typeIcon`** (clé d'icône, nouveau).
- `GET events` : `meta.types` = types actifs `[{ value, label, icon }]` ; le filtre `?type=` accepte tout type existant (actif ou non).

## Données

Table `event_types` (migration `2026_10_19_500001_create_event_types_table`, qui insère les 8 types de départ) : `id`, `slug` (40, unique), `name_fr` (80), `name_en` (80, nullable), `icon` (30, défaut `calendar`), `position` (smallint non signé), `is_active` (défaut vrai), horodatage.

Lecture : `EventType::cached()` (`Cache::rememberForever('event_types')`, vidé à chaque enregistrement / suppression et après un réordonnancement) ; `labelFor()`, `iconFor()`, `faClass()`, `options()`, `activeTypes()`, `knownSlug()` ; `Event::typeLabel()`, `typeIcon()`, `typeFaIcon()`.

## Fichiers

- `app/Models/EventType.php`, `app/Services/EventTypes.php` (règles, format JSON, création, modification, suppression avec remplacement, ordre), `EventTypeInUseException.php`
- `app/Http/Controllers/Api/EventTypeController.php`, `app/Http/Controllers/Web/EventTypeController.php`
- `resources/views/admin/event-types/index.blade.php`, `partials/icon-picker.blade.php`
- `lang/{fr,en}/event_types.php`, `layout.nav.event_types`
- tests `tests/Feature/EventTypesTest.php`
