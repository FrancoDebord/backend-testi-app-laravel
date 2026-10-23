# Page de profil (site)

## But

`/profiles/{id}` présente un compte (personne ou organisation) : identité, présentation, statistiques, puis ses témoignages, événements et partages. Sur **son propre profil**, la personne trouve aussi « Mon espace » : raccourcis vers ses pages personnelles et bouton **Se déconnecter**, utiles surtout sur mobile, où le menu latéral est replié.

## Présentation

| Zone | Contenu |
|---|---|
| **Carte d'en-tête** (`card`) | Bandeau : [photo de couverture](photo-de-couverture.md), sinon bleu de la charte (`primary-600` → `primary-700`) avec une étoile `sun-400`. Avatar `xl` cerclé de blanc, à cheval sur le bandeau. Nom (coche « vérifié » accrochée au dernier mot), puis type et ville de l'organisation, pays et « Membre depuis {mois année} ». Présentation (`bio`) ; sur son profil sans présentation, lien « Ajouter une présentation ». |
| **Actions** | Soi : « Modifier le profil » (`btn-secondary`) et « Publier » (`btn-cta`). Autre compte : bouton Suivre (`components/follow-button`). Sur mobile, les deux boutons se partagent la largeur. |
| **Statistiques** | Trois tuiles encadrées : témoignages publiés (lien vers la section), abonnés et abonnements (liens vers les listes pour soi seulement). Le nombre d'abonnés se met à jour quand on clique sur Suivre (`data-follower-count`). |
| **Fiabilité** | Modération seulement : bilan des événements organisés. |
| **Vérification d'une organisation** | Soi : message « en cours » ou « refusée » sous la carte. |
| **Mon espace** (soi) | Raccourcis en tuiles : Mes témoignages, Sauvegardes, Carnet privé, état de la vérification (compte personnel), Raconter ma conversion (si possible et pas encore fait), Paramètres. **Se déconnecter** en haut à droite du bloc (`POST /logout`, le même que le menu latéral). Absent du profil des autres. |
| **Sections** | Témoignage de conversion, Témoignages (`#temoignages`, bascule grandes cartes / liste compacte), événements à venir et passés, partages. Titres `card-title`. |

Responsive vérifié à 390, 768 et 1440 px, sans défilement horizontal : raccourcis sur 2 colonnes en mobile, 3 au-delà.

## Fichiers

`app/Http/Controllers/Web/ProfileController.php` (`show`) · `resources/views/profile/show.blade.php` · traductions `lang/{fr,en}/profile.php` › `show` (`member_since`, `add_bio`, `logout`, `shortcuts.*`) · test `tests/Feature/FollowTest.php` (`test_web_follow_button_toggles_and_guests_are_sent_to_login`).
