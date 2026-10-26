# Qui a réagi ? (effectif et personnes par réaction)

## But

Savoir combien de personnes ont réagi à une publication, réaction par réaction, et qui : combien ont aimé et qui, combien prient et qui, etc. Ajouté le 2026-10-26.

Publications concernées : témoignages, verset du jour, requêtes de prière, événements et sessions de prière (ces deux dernières : API seulement, le site n'a pas encore leurs boutons de réaction).

## Règles

| Règle | Détail |
|---|---|
| Effectifs | Nombre de **personnes** au total (« Toutes »), par groupe (`likesCount` : j'aime, love, feu… ; `prayersCount` : je prie, amen, adorer) et par réaction présente (`types`, dans l'ordre de `ReactionType`). |
| Personnes | Une ligne par personne, la plus récente d'abord, avec **toutes** ses réactions (dans l'onglet « J'aime », on voit aussi qu'elle prie). 30 par page. |
| Comptes | Seuls les comptes **actifs** sont comptés et listés (ni suspendus, ni supprimés). L'effectif peut donc être inférieur au compteur de la publication (`like_count`, `prayer_count`). |
| Visibilité | Même règle que la publication (`isVisibleTo`) : une publication privée ou masquée renvoie 404 à qui ne peut pas la voir. Ouvert aux visiteurs pour une publication publique. |
| Requêtes de prière | Le bouton « Je prie » dédié (`prayer_request_prayers`) est compté comme la réaction `pray`. |
| Nombre de fois | Une personne qui a prié plusieurs fois est comptée une fois ; « 3 fois » est affiché à côté d'elle (`prayerTimes` dans l'API ; [prier-plusieurs-fois.md](prier-plusieurs-fois.md)). |

## Site

- **Témoignage** (page de lecture) : bouton « Qui a réagi ? » à droite des réactions.
- **Verset du jour** : bouton « Qui a réagi ? » dans « Méditer le verset ».
- **Requête de prière** : « N personnes prient » devient un bouton ; le popup s'ouvre sur l'onglet « Je prie ».

Le popup (`#reaction-people-modal`, dans `layouts/partials/global-ui.blade.php`) montre :

1. des onglets « Toutes 12 », « J'aime 8 », « Je prie 5 »… (icône Font Awesome, libellé, effectif) ;
2. les personnes : avatar, nom (lien vers le profil), coche vérifiée, icônes de leurs réactions ;
3. « Afficher plus » ajoute la page suivante à la liste.

Le contenu est rendu par le serveur (`GET /reactions/{kind}/{id}?type=&page=`, route `reactions.people`) et chargé par `resources/js/app.js` ; tout bouton `[data-reaction-people="url"]` ouvre le popup. Le contenu est rechargé à chaque ouverture (à jour après une réaction).

## API (`/api/v1`)

| Méthode | Route |
|---|---|
| GET | `testimonies/{id}/reactions/people` |
| GET | `daily-verse/{id}/reactions/people` |
| GET | `prayer/requests/{id}/reactions/people` |
| GET | `events/{id}/reactions/people` |
| GET | `prayer/sessions/{id}/reactions/people` |

Paramètres : `type` (facultatif) = `all`, un groupe (`appreciation`, `prayer`) ou une réaction (`like`, `pray`, `fire`…) ; `page`. Type inconnu : 422. Publique ; la personne connectée (jeton `sanctum`) est prise en compte pour les publications réservées.

Réponse :

```json
{
  "success": true,
  "data": {
    "summary": {
      "total": 3, "likesCount": 2, "prayersCount": 2,
      "types": [
        { "type": "like", "group": "appreciation", "label": "J'aime", "emoji": "👍", "icon": "fa-thumbs-up", "count": 1 },
        { "type": "pray", "group": "prayer", "label": "Je prie", "emoji": "🙏", "icon": "fa-hands-praying", "count": 2 }
      ]
    },
    "people": [
      { "id": "…", "displayName": "Afi", "initials": "A", "avatarUrl": null, "isVerified": false, "reactions": ["pray"], "prayerTimes": 2, "reactedAt": "2026-10-26T10:00:00+00:00" }
    ]
  },
  "meta": { "currentPage": 1, "lastPage": 1, "perPage": 30, "total": 3 }
}
```

`total` de `meta` : nombre de personnes pour le filtre demandé ; `summary` : toujours pour toutes les réactions. Chaque personne porte aussi les champs du badge « Approuvé » (`User::approvalFieldsFor`).

**Application** : à faire dans le dépôt Flutter (appui sur les compteurs de `ContentReactionBar` et des pages de témoignage → feuille avec onglets par réaction, à partir de cette route).

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Services/ReactionPeople.php` | Publication visible (`find`), effectifs (`summary`), personnes (`people`), icônes (`ICONS`) |
| `app/Http/Controllers/Api/ReactionPeopleController.php` | Route de l'API |
| `app/Http/Controllers/Web/ReactionPeopleController.php` | Contenu du popup |
| `resources/views/reactions/people.blade.php`, `reactions/partials/people-rows.blade.php` | Onglets, personnes, « Afficher plus » |
| `resources/views/layouts/partials/global-ui.blade.php` | Popup |
| `resources/js/app.js` | Ouverture, onglets, pages suivantes |
| `lang/{fr,en}/reactions.php` (`people`), `lang/{fr,en}/js.php` (`app.reaction_people_error`) | Textes |
| `tests/Feature/ReactionPeopleTest.php` | Tests |

## Déploiement

Aucune migration. `php artisan optimize:clear` puis `php artisan optimize` (nouvelles routes), recompiler et **copier `public/build/`** sur le serveur.
