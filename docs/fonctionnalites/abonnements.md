# Suivre des comptes et page « Communauté »

## But

Permettre de **suivre** une organisation (église, ministère, association…) ou une personne, pour être prévenu de ses **nouveaux témoignages** et de ses **directs**, et de découvrir des comptes à suivre sur la page **Communauté**.

## Utilisation

### Site

| Endroit | Bouton |
|---|---|
| **Communauté** (`/communaute`, menu Découvrir) | Onglets **Organisations** (vérifiées d'abord, puis les plus suivies) et **Personnes** (ayant au moins un témoignage publié) ; recherche par nom, ville ou pays ; fiche : avatar, nom, coche vérifiée, type · ville · pays, abonnés, témoignages, présentation, **Suivre**, Voir le profil |
| Profil (`/profiles/{id}`) | **Suivre** (bouton principal) / **Abonné** ; le nombre d'abonnés se met à jour |
| Page de lecture d'un témoignage | **Suivre** à côté de l'auteur |
| **Mes abonnements** (`/profile/abonnements`, menu Mon espace, ou le nombre d'« abonnements » de son profil) | Comptes suivis, les plus récents d'abord, recherche ; mêmes fiches que la Communauté. Se désabonner laisse la fiche en place (bouton « Suivre ») pour revenir sur son choix ; elle disparaît au rechargement. Liste vide : lien « Découvrir la Communauté » |
| **Mes abonnés** (`/profile/abonnes`, le nombre d'« abonnés » de son profil, ou l'onglet **Abonnés** en haut de Mes abonnements) | Comptes qui me suivent, les plus récents d'abord, recherche ; mêmes fiches. Bouton **Suivre en retour** pour ceux que je ne suis pas (« Abonné » sinon). Liste vide : « Personne ne vous suit encore. » et lien vers la Communauté |

Le bouton bascule sans recharger la page (« Suivre » ↔ « Abonné ») ; sans JavaScript, c'est un formulaire classique. Personne non connectée : **Suivre** mène à la connexion. Sur son propre compte, aucun bouton.

### Application mobile

- **Menu latéral** (icône ☰ de l'accueil) : Communauté, Directs, Mes abonnements, Mon carnet privé, Mes témoignages, Sauvegardés, Paramètres, et Modération / Administration selon le rôle.
- **Communauté** : mêmes onglets, recherche, liste qui se charge au défilement.
- **Abonnés et abonnements** : un écran à deux onglets. **Mes abonnés** (`/followers`) : depuis la statistique « Abonnés » de *Mon profil*, le menu latéral ou le menu du profil ; « N comptes vous suivent », recherche, **Suivre en retour** / Abonné. **Mes abonnements** (`/following`) : depuis le menu latéral ou la ligne « abonnements » de *Mon profil* ; « Vous suivez N comptes », recherche, Suivre / Abonné, lien vers la Communauté si la liste est vide.
- **Messages d'erreur** : si le serveur répond 404 (mise à jour pas encore déployée), la liste affiche « Cette liste n'est pas encore disponible sur le serveur » au lieu d'un message de connexion.
- **Profil d'un auteur** (`/users/{id}`), ouvert depuis l'avatar ou le nom sur une carte, la page d'un témoignage, le lecteur vidéo ou « Qui regarde » d'un direct : présentation, compteurs, **Suivre**, témoignages publiés.
- Les boutons **Suivre** des cartes, du lecteur vidéo et de la page d'un témoignage fonctionnent désormais (ils ne changeaient qu'un affichage local).

## Règles

| Règle | Détail |
|---|---|
| **Soi-même** | Impossible de se suivre (422 « Vous ne pouvez pas vous suivre vous-même. »), contrôlé par le service et par une contrainte en base (MySQL 8.0.16+) |
| **Doublons** | Un seul abonnement par paire, même en cas de double clic (insertion « si absente » dans une transaction) |
| **Compteurs** | `follower_count` / `following_count` ne changent que si l'abonnement est réellement créé ou retiré ; jamais négatifs ; recalculés à la migration |
| **Compte suivi** | Doit être actif (sinon 404) |
| **Limite** | 30 actions par minute |
| **Notification** | La personne suivie reçoit « X vous suit maintenant » (notification + push) |
| **Abonnés prévenus** | Témoignage **publié** (validé par la modération), public ou réservé aux abonnés : notification `new_followed_testimony` à tous les abonnés ; **jamais** pour le carnet privé. Direct **à l'antenne** : notification `live_started` ([lives.md](lives.md)). Préférence push `push_new_followed` respectée |
| **Communauté** | Comptes actifs ; organisations refusées exclues ; personne connectée exclue ; coordonnées (e-mail, téléphone) jamais incluses |
| **Témoignages comptés** | Comptés en direct (témoignages publiés et publics, `User::withPublishedTestimonyCount()`) pour l'onglet Personnes, les fiches, les profils (site et API) et Mes abonnements. Le compteur enregistré `users.testimony_count` n'est augmenté qu'à la validation par la modération et jamais diminué : il était faux pour beaucoup de comptes, ce qui vidait l'onglet Personnes |
| **Mes abonnements** | Comptes actifs seulement (un compte suspendu ou banni disparaît de la liste, l'abonnement reste en base) ; les plus récents d'abord ; 24 par page |
| **Mes abonnés** | Mêmes règles (comptes actifs, plus récents d'abord, 24 par page) ; `is_following` indique si je suis l'abonné en retour. Seule la personne elle-même voit ses abonnés : **pas de liste publique** `GET /users/{id}/followers`, car le réglage « Compte privé » existe mais n'est encore appliqué nulle part — publier la liste des abonnés d'un compte irait contre ce choix. Les compteurs restent publics |

## Fonctionnement technique

| Élément | Fichier |
|---|---|
| Règles | `app/Services/FollowService.php` (`follow`, `unfollow`, `isFollowing`, `recountAll`), `FollowException` |
| Abonnés prévenus | `App\Services\FollowerNotifications::newTestimony()` (utilisé par les deux validations de modération), `notify()` |
| Liste Communauté | `app/Services/CommunityDirectory.php` |
| État « je suis ce compte » | portée `User::withFollowState($viewer)` → `is_following` dans `UserResource` (seulement si chargé) |
| Mes abonnements | `CommunityDirectory::following($viewer, $q)` |
| Mes abonnés | `CommunityDirectory::followers($viewer, $q)` ; `UserController::followers` ; site `CommunityController::followers`, vues `community/followers`, `community/partials/follow-tabs` (onglets Abonnés / Abonnements) ; `components/follow-button` accepte `idleLabel` (« Suivre en retour ») |
| Site | `Web\CommunityController` (`index`, `following`, `follow`, `unfollow`), vues `community/index`, `community/following`, `community/partials/card`, `components/follow-button` ; JavaScript `data-follow-form` dans `resources/js/app.js` |
| Migration | `2026_09_29_300002_add_follows_integrity` : suppression des abonnements à soi-même, recalcul des compteurs, contrainte `follows_not_self` |

### Routes

| Méthode | Adresse | Accès |
|---|---|---|
| GET | `/communaute?tab=organizations\|people&q=` | Public |
| POST / DELETE | `/profiles/{id}/follow` (`users.follow` / `users.unfollow`) | Connecté ; JSON `{ following, followerCount, message }` ou redirection |
| GET | `/api/v1/community?tab=&q=&page=` | Public ; `UserResource` + `is_following` si connecté ; `meta` : `current_page`, `last_page`, `total` |
| GET | `/api/v1/users/{id}` | Public ; `is_following` si connecté |
| POST | `/api/v1/users/{id}/follow` | Connecté → `{ following: true, followerCount }` |
| DELETE | `/api/v1/users/{id}/unfollow` | Connecté → `{ following: false, followerCount }` |
| GET | `/profile/abonnements?q=` (`profile.following`) | Connecté : Mes abonnements |
| GET | `/api/v1/users/me/following?q=&page=` | Connecté : Mes abonnements, `UserResource` + `is_following`, `meta` : `current_page`, `last_page`, `total` |
| GET | `/profile/abonnes?q=` (`profile.followers`) | Connecté : Mes abonnés |
| GET | `/api/v1/users/me/followers?q=&page=` | Connecté : Mes abonnés, même forme que `following` ; `is_following` = je le suis en retour ; `meta` : `current_page`, `last_page`, `total` |
| GET | `/api/v1/users/me/following-ids` | Connecté : identifiants des comptes suivis (l'application affiche ainsi le bon état de tous ses boutons) |

### Application (Flutter, `testi_app`)

| Élément | Fichier |
|---|---|
| Modèle, appels | `lib/features/community/models/community_account.dart`, `data/community_repository.dart` |
| État partagé (affiché tout de suite, confirmé par le serveur, rétabli en cas d'erreur) | `lib/features/community/providers/follow_provider.dart` |
| Bouton, fiche, écrans | `widgets/follow_button.dart`, `widgets/account_tile.dart`, `widgets/paged_account_list.dart` (liste paginée partagée, `accountListErrorMessage`), `screens/community_screen.dart`, `screens/following_screen.dart` (`FollowsScreen`, onglets `FollowsTab.followers` / `following` ; `FollowButton.idleLabel`), `screens/user_profile_screen.dart` |
| Menu latéral | `lib/shared/widgets/app_drawer.dart` (ouvert depuis l'accueil) |
| Routes | `/community`, `/following`, `/followers`, `/users/:id` dans `lib/core/router/app_router.dart` |
| Tests | `test/community_follow_test.dart`, `test/following_list_test.dart`, `test/followers_test.dart` |

## Déploiement

`php artisan migrate --force` ; recompiler et copier `public/build/` ; **`php artisan optimize:clear`** puis `php artisan view:cache`. Application : nouvelle version à publier.

**Vérifier après le déploiement** que l'API répond : `https://testi.airid-africa.com/api/v1/community?tab=organizations` doit renvoyer `{"success":true,…}`. Le 2026-09-29, le serveur de production répondait **404 « The route api/v1/community could not be found »** : le code n'y était pas encore déployé (ou le cache des routes n'avait pas été vidé), d'où une Communauté vide dans l'application. La même vérification vaut pour `/api/v1/users/me/following` (connecté).

## Tests

`tests/Feature/FollowingListTest.php` : onglet Personnes et compteurs justes malgré un `testimony_count` faux, API Mes abonnements (ordre, comptes bannis masqués, recherche, `meta`), connexion exigée, page du site (liste vide, fiches, lien depuis le profil, menu) ; API Mes abonnés (ordre, `is_following` en retour, comptes bannis masqués, recherche, connexion exigée) et page du site (« Suivre en retour », liens depuis le profil et Mes abonnements).

`tests/Feature/FollowTest.php` : pas d'abonnement à soi-même (API et site), double abonnement compté une fois et notifié une fois, compteurs jamais négatifs, compte inactif, recalcul, bouton du site (JSON, sans JavaScript, personne non connectée), bouton de la page de lecture, autres témoignages en format compact, Communauté (ordre, exclusions, recherche), API Communauté avec `is_following` et sans coordonnées, identifiants suivis, abonnés prévenus (jamais pour le carnet).

## Limites connues

- Pas de liste publique des abonnés ou abonnements d'un autre compte (voir « Mes abonnés » dans Règles).
- Site : après un désabonnement sans rechargement, le bouton d'un abonné affiche « Suivre » (et non « Suivre en retour ») jusqu'au rechargement.
- Le compteur enregistré `users.testimony_count` reste inexact ; il n'est plus utilisé pour l'affichage des listes et des profils.
- Pas de notion d'« amis » (abonnement réciproque) : les notifications vont à tous les abonnés.
