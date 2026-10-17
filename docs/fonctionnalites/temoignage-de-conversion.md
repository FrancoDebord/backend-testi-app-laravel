# Témoignage de conversion

## But

Chaque personne peut raconter comment elle a rencontré Jésus dans un témoignage à part, mis en avant sur son profil et dans la section « Témoignages de conversion » (« Conversion stories » en anglais).

## Règles

- Un témoignage a une sorte (`kind`) : `standard` (par défaut) ou `conversion`.
- Seul un compte personnel (`account_type = individual`, ou sans type pour les anciens comptes) publie un témoignage de conversion : une organisation reçoit **403**.
- Un seul par compte, quel que soit son statut (en attente, refusé, publié, carnet). Un second envoi reçoit **409** avec l'identifiant de l'existant (`errors.conversion_testimony_id`). Un témoignage dans la corbeille ne compte plus. La vérification se fait sous verrou du compte : deux envois simultanés n'en créent qu'un.
- L'auteur peut le modifier (API ou page « Modifier ») ; il repasse en modération comme toute modification par l'auteur.
- Champs propres, facultatifs : `conversion_year` (1900 à l'année en cours), `conversion_place` (≤ 150), `conversion_verse` (référence biblique reconnue, ex. « Jean 3:16 », « John 3:16 », ≤ 100). Effacés si le témoignage redevient standard ; ignorés pour un témoignage standard.
- Même modération, mêmes visibilités, même carnet privé que les autres témoignages. Catégorie : `conversion` par défaut si elle existe.

## API (`/api/v1`)

| Méthode | Route | Accès | Rôle |
|---|---|---|---|
| POST | `/testimonies` `{ …, kind: "conversion", conversion_year?, conversion_place?, conversion_verse? }` | connecté | Créer (catégorie facultative) |
| PUT | `/testimonies/{id}` `{ kind?, conversion_year?, conversion_place?, conversion_verse? }` | auteur | Modifier, ou changer de sorte |
| GET | `/testimonies?kind=conversion` (ou `standard`) | public | Témoignages publiés de cette sorte, paginés |
| GET | `/users/{id}/conversion-testimony` | public | Témoignage de conversion **publié** du compte ; 404 sinon |
| GET | `/me/conversion-testimony` | connecté | Le mien, quel que soit son statut ; `data: null` s'il n'existe pas ; `can_create` |

`TestimonyResource` gagne `kind`, `isConversion` et `conversion` (`{ year, place, verse }` ou `null`). Le profil public (`GET /users/{id}`) et `GET /me` exposent `has_conversion_testimony` (témoignage de conversion publié).

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

- **Publier** (`/publish`) : choix « Témoignage » / « Témoignage de conversion » pour un compte personnel qui n'en a pas encore, avec les trois champs propres (`/publish?kind=conversion` présélectionne). Sinon, lien vers la page de modification.
- **Section** `/temoignages-de-conversion` (menu « Découvrir ») : cartes mises en avant (liseré jaune, badge « Conversion », année, lieu, verset).
- **Profil** : carte mise en avant au-dessus des témoignages (l'auteur voit aussi le sien en attente, avec « Modifier »).
- **Page du témoignage** : bandeau « Témoignage de conversion » avec les détails et, pour l'auteur, « Modifier ».
- **Listes** : badge « Conversion » et liseré jaune sur les cartes (grandes cartes, cartes, liste compacte).
- **Modifier** : `/temoignages-de-conversion/le-mien/modifier` (titre, texte, année, lieu, verset).
- **Visiteur** : bouton « Se connecter pour raconter ma conversion » sur la section.
- **Accueil** : bloc « Témoignages de conversion » (3 derniers publiés, lien « Tout voir »), masqué s'il n'y en a aucun.
- **Modération** (`/moderation`) : case « Témoignages de conversion » pour filtrer (`?conversion=1`), badge « Conversion » dans la liste ; la fiche affiche le badge et la ligne « Ma conversion » (année · lieu · verset).

## Données

Migration `2026_10_15_200001_add_locale_conversion_and_push_device_fields` : `testimonies.kind` (`standard` par défaut), `conversion_year`, `conversion_place`, `conversion_verse`, index `(kind, status)` et `(user_id, kind)`. Énumération `App\Enums\TestimonyKind`.

## Fichiers

`app/Services/ConversionTestimonies.php` (règles, création sous verrou, modification), `app/Http/Controllers/Api/ConversionTestimonyController.php`, `app/Http/Controllers/Web/ConversionTestimonyController.php`, `TestimonyCreator`, `Api\TestimonyController` (filtre `kind`, modification), `Web\TestimonyController` (formulaire), `resources/views/components/conversion-card.blade.php`, `resources/views/testimonies/conversions.blade.php`, `conversion-edit.blade.php`, `lang/*/conversion.php`.

## Tests

`tests/Feature/ConversionTestimonyTest.php` (11 tests) : création et champs, unicité (409, corbeille), refus aux organisations, validation, modification et retour en modération, endpoints (`kind`, `users/{id}`, `me`, `has_conversion_testimony`), section, profil, page, formulaire et modification sur le site, filtre et fiche de modération, bloc de l'accueil, invitation des visiteurs.

## Déploiement

Migration `2026_10_15_200001_add_locale_conversion_and_push_device_fields` obligatoire : sans elle, la section et l'accueil renvoient une erreur 500 (« colonne `testimonies.kind` inconnue »).
