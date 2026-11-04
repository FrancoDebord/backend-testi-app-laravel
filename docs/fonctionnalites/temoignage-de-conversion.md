# Témoignage de conversion

## But

Chaque personne peut raconter comment elle a rencontré Jésus dans un témoignage à part, mis en avant sur son profil et dans la section « Témoignages de conversion » (« Conversion stories » en anglais). Un compte organisation raconte à la place la **genèse de son ministère** (voir plus bas).

## Règles

- Un témoignage a une sorte (`kind`) : `standard` (par défaut) ou `conversion`.
- Tout compte peut en publier un : témoignage de conversion pour une personne (`account_type = individual`, ou sans type pour les anciens comptes), genèse du ministère pour une organisation.
- Un seul par compte, quel que soit son statut (en attente, refusé, publié, carnet). Un second envoi reçoit **409** avec l'identifiant de l'existant (`errors.conversion_testimony_id`). Un témoignage dans la corbeille ne compte plus. La vérification se fait sous verrou du compte : deux envois simultanés n'en créent qu'un.
- L'auteur peut le modifier (API ou page « Modifier ») ; il repasse en modération comme toute modification par l'auteur.
- Champs propres, facultatifs : `conversion_year` (1900 à l'année en cours), `conversion_place` (≤ 150), `conversion_verse` (référence biblique reconnue, ex. « Jean 3:16 », « John 3:16 », ≤ 100). Effacés si le témoignage redevient standard ; ignorés pour un témoignage standard.
- Même modération, mêmes visibilités, même carnet privé que les autres témoignages. Catégorie : `conversion` par défaut si elle existe (`ministere` pour une organisation).

## Comptes organisation : genèse du ministère

Ajouté le 2026-11-04. Pour une organisation, on ne parle pas de conversion : on demande le témoignage de la genèse du ministère (comment il est né).

- Même enregistrement : `kind = conversion`, mêmes colonnes, même règle d'un seul par compte. `conversion_year` est l'année de fondation, `conversion_verse` le verset fondateur.
- Seuls les libellés changent, selon le type de compte de l'auteur : clés `conversion.genesis.*` de `lang/*/conversion.php` (repli sur `conversion.*` pour les clés absentes). Code : `ConversionTestimonies::label($clé, $auteur)`, `Testimony::conversionLabel($clé)`, `Testimony::conversionIcon()` (`fa-church` au lieu de `fa-dove`), `Testimony::isMinistryGenesis()`.
- Libellés : « Genèse du ministère » (badge, choix au moment de publier, profil, page), « Année de fondation », « Verset fondateur », « Fondé en 1998 », « Raconter la genèse de notre ministère », « Modifier la genèse de notre ministère ». Messages d'erreur (409, année hors limites) et `message` de `/me/conversion-testimony` adaptés aussi.
- Portées : `Testimony::ministryGenesis()` (auteur organisation) et `Testimony::personalConversion()` (les autres).
- Section : onglets « Conversions » (`/temoignages-de-conversion`) et « Genèse des ministères » (`?onglet=ministeres`). Le bloc de l'accueil ne montre que les conversions des personnes.

## API (`/api/v1`)

| Méthode | Route | Accès | Rôle |
|---|---|---|---|
| POST | `/testimonies` `{ …, kind: "conversion", conversion_year?, conversion_place?, conversion_verse? }` | connecté | Créer (catégorie facultative) |
| PUT | `/testimonies/{id}` `{ kind?, conversion_year?, conversion_place?, conversion_verse? }` | auteur | Modifier, ou changer de sorte |
| GET | `/testimonies?kind=conversion` (ou `standard`) | public | Témoignages publiés de cette sorte, paginés ; `&author_type=individual` (conversions) ou `organization` (genèses des ministères) |
| GET | `/users/{id}/conversion-testimony` | public | Témoignage de conversion (ou genèse) **publié** du compte ; 404 sinon |
| GET | `/me/conversion-testimony` | connecté | Le mien, quel que soit son statut ; `data: null` s'il n'existe pas ; `can_create` (vrai aussi pour une organisation) |

`TestimonyResource` gagne `kind`, `isConversion`, `isMinistryGenesis` (témoignage de conversion d'une organisation : afficher « Genèse du ministère ») et `conversion` (`{ year, place, verse }` ou `null`). Le profil public (`GET /users/{id}`) et `GET /me` exposent `has_conversion_testimony` (témoignage de conversion publié).

```json
GET /api/v1/me/conversion-testimony
{
  "success": true,
  "data": {
    "id": "9b1c…", "title": "Comment j'ai rencontré Jésus", "type": "text",
    "kind": "conversion", "isConversion": true,
    "conversion": { "year": 2012, "place": "Cotonou", "verse": "Jean 3:16" },
    "status": "pending", "category": "conversion", …
  },
  "can_create": false,
  "message": ""
}
```

## Site

- **Publier** (`/publish`) : choix « Témoignage » / « Témoignage de conversion » (« Genèse du ministère » pour une organisation) pour un compte qui n'en a pas encore, avec les trois champs propres (`/publish?kind=conversion` présélectionne). Sinon, lien vers la page de modification.
- **Section** `/temoignages-de-conversion` (menu « Découvrir ») : onglets « Conversions » et « Genèse des ministères » (`?onglet=ministeres`) ; cartes mises en avant (liseré jaune, badge « Conversion » ou « Genèse du ministère », année, lieu, verset).
- **Profil** : carte mise en avant au-dessus des témoignages (l'auteur voit aussi le sien en attente, avec « Modifier »).
- **Page du témoignage** : bandeau « Témoignage de conversion » avec les détails et, pour l'auteur, « Modifier ».
- **Listes** : badge « Conversion » et liseré jaune sur les cartes (grandes cartes, cartes, liste compacte).
- **Modifier** : `/temoignages-de-conversion/le-mien/modifier` (titre, texte, année, lieu, verset).
- **Visiteur** : bouton « Se connecter pour raconter ma conversion » sur la section.
- **Accueil** : bloc « Témoignages de conversion » (3 derniers publiés des personnes, lien « Tout voir »), masqué s'il n'y en a aucun.
- **Modération** (`/moderation`) : case « Témoignages de conversion » pour filtrer (`?conversion=1`), badge « Conversion » dans la liste ; la fiche affiche le badge et la ligne « Ma conversion » (année · lieu · verset).

## Données

Migration `2026_10_15_200001_add_locale_conversion_and_push_device_fields` : `testimonies.kind` (`standard` par défaut), `conversion_year`, `conversion_place`, `conversion_verse`, index `(kind, status)` et `(user_id, kind)`. Énumération `App\Enums\TestimonyKind`.

## Fichiers

`app/Services/ConversionTestimonies.php` (règles, création sous verrou, modification), `app/Http/Controllers/Api/ConversionTestimonyController.php`, `app/Http/Controllers/Web/ConversionTestimonyController.php`, `TestimonyCreator`, `Api\TestimonyController` (filtre `kind`, modification), `Web\TestimonyController` (formulaire), `resources/views/components/conversion-card.blade.php`, `resources/views/testimonies/conversions.blade.php`, `conversion-edit.blade.php`, `lang/*/conversion.php`.

## Tests

`tests/Feature/ConversionTestimonyTest.php` (11 tests) : création et champs, unicité (409, corbeille), genèse du ministère des organisations (libellés, catégorie, onglet, filtre `author_type`), validation, modification et retour en modération, endpoints (`kind`, `users/{id}`, `me`, `has_conversion_testimony`), section, profil, page, formulaire et modification sur le site, filtre et fiche de modération, bloc de l'accueil, invitation des visiteurs.

## Déploiement

Migration `2026_10_15_200001_add_locale_conversion_and_push_device_fields` obligatoire : sans elle, la section et l'accueil renvoient une erreur 500 (« colonne `testimonies.kind` inconnue »).
