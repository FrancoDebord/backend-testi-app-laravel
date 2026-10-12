# Administrateurs de compte

## But

Chaque titulaire **administre son propre compte**. Il peut aussi nommer **deux personnes** qui gèrent le compte avec lui : elles publient des témoignages, des événements, des requêtes et des sessions de prière, commentent, réagissent et suivent des comptes **au nom du compte**.

Une personne voit tous les comptes qu'elle administre (le sien d'abord) et **bascule** de l'un à l'autre, sur le site comme dans l'application. Tant qu'elle agit au nom d'un compte, tout ce qu'elle fait apparaît sous le nom de ce compte.

Cette fonctionnalité remplace les « gestionnaires d'organisation », qui étaient réservés aux organisations : elle utilise la même table `organization_managers` et vaut désormais pour **tous les comptes**. Les gestionnaires déjà nommés deviennent administrateurs de l'organisation.

## Règles

| Règle | Détail |
|---|---|
| Qui nomme | Le **titulaire** du compte, ou un administrateur de la plateforme. Un administrateur du compte ne nomme personne |
| Combien | **2 personnes au plus** en plus du titulaire (`AccountDelegation::MAX_MANAGERS`) |
| Qui peut être nommé | Un **compte personnel actif**, autre que le titulaire. Pas un compte organisation |
| Comptes exclus | Les comptes de l'équipe (**modérateurs, administrateurs**) ne peuvent pas être confiés : leurs droits ne se délèguent jamais (refus à la nomination et à la bascule) |
| Retrait | Par le titulaire, ou par la personne elle-même (« Me retirer ») ; l'accès est coupé immédiatement |
| Notifications | `account_manager_added` à la personne nommée, `account_manager_removed` quand le titulaire la retire (application + push) |
| Ce que fait un administrateur du compte | Tout ce que fait le compte : publier (témoignages, événements si le compte est une organisation vérifiée, requêtes et sessions de prière, directs autorisés au compte), commenter, réagir, suivre, partager, modifier le profil public (nom, photo, bio…), lire les notifications du compte |
| Réservé au titulaire | Identifiants (e-mail, téléphone, mot de passe : champs ignorés), connexion et jetons, **suppression du compte**, administrateurs du compte, vérification, position, réglages, appareils (push), **carnet privé et paroles prophétiques**, modération et administration de la plateforme |
| Journal | Chaque écriture faite au nom du compte (requête non GET réussie) est inscrite dans `account_delegate_actions` : qui, quoi, quand. Le titulaire consulte les 50 dernières (application) ou les 20 dernières (site) |

## Fonctionnement technique

- **Application** : l'en-tête `X-Act-As: <id du compte>` accompagne les requêtes. **Site** : le compte choisi est gardé en session (`acting_as`), via `POST /comptes/{id}/agir` (`accounts.switch`) ; le même formulaire avec son propre identifiant ramène sur son compte.
- Le middleware `App\Http\Middleware\ActAsAccount` (groupes `api` et `web`) vérifie que la personne connectée administre le compte (`AccountDelegation::canActFor`), puis traite la requête **comme si le compte était connecté** (`$request->user()`, `auth()->user()`). La personne réelle reste disponible : `AccountDelegation::actor($request)` (attribut `acting_actor`).
- Chemins réservés au titulaire : `AccountDelegation::OWNER_ONLY`, plus `DELETE users/me`.
  - **API** : refus `403` avec `code: "owner_only"`.
  - **Site** : ces pages ne basculent pas et s'affichent pour la personne réelle (paramètres, carnet…).
- Accès retiré, compte inactif ou compte de l'équipe :
  - **API** : `403` avec `code: "acting_as_forbidden"` ; l'application revient alors sur le compte de la personne.
  - **Site** : le choix en session est oublié.
- Les réponses de l'API faites au nom d'un compte portent l'en-tête `X-Acting-As`.

## API

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/api/v1/users/me/accounts` | Comptes de la personne réelle : `{accounts: [{id, displayName, username, initials, avatarUrl, isOrganization, isVerified, role: owner\|manager, since}], actingAs}` (le sien d'abord) |
| GET | `/api/v1/users/me` | Comme `auth/me`, mais suit `X-Act-As` : sans e-mail ni téléphone, avec `acting_as` et `acting_actor` |
| GET / POST | `/api/v1/users/me/managers` | Administrateurs du compte `{max, managers}` / nommer `{user_id}` (titulaire) |
| DELETE | `/api/v1/users/me/managers/{userId}` | Retirer un administrateur (titulaire) |
| GET | `/api/v1/users/me/managers/activity` | Journal : `[{id, actorId, actorName, action, createdAt}]` |
| GET | `/api/v1/users/me/managed-organizations` | Comptes confiés à la personne (même forme que `accounts`) |
| DELETE | `/api/v1/users/me/managed-organizations/{id}` | Se retirer de l'administration d'un compte |

## Utilisation

- **Site** :
  - **Paramètres du compte → Administrateurs du compte** : nommer, retirer, consulter le journal ; section « Comptes que j'administre ».
  - **Menu du compte → Mes comptes** pour basculer, et un bandeau « Vous agissez au nom de … » avec « Revenir à mon compte ».
- **Application** :
  - **Paramètres → Administrateurs du compte** (même contenu).
  - **Mes comptes** depuis le menu latéral pour basculer, et le même bandeau.

## Développeurs

- `App\Services\AccountDelegation` : règles, liste des comptes, journal, nomination et retrait.
- `App\Http\Middleware\ActAsAccount`, `App\Http\Controllers\Api\OrganizationManagerController` (`accounts`, `activity`, libellés du journal), `App\Http\Controllers\Web\AccountSwitchController`, `App\Models\AccountDelegateAction`.
- `EventService::addOrganizationManager/removeOrganizationManager` délèguent à `AccountDelegation`.
- Site : `layouts/app.blade.php` (calcul des comptes, « Mes comptes » dans le menu du compte de l'en-tête et dans le menu latéral mobile, bandeau), `layouts/partials/account-switcher.blade.php` (un formulaire POST par compte), `profile/settings.blade.php` (ancres `#administrateurs`, `#journal-des-actions`, `#comptes-administres`) avec `Web\ProfileController::settings`.
- Tests : `tests/Feature/AccountManagersTest.php` (serveur) et `test/account_managers_test.dart` (application).

## Déploiement

- `php artisan migrate --force` (`2026_10_08_100001_create_account_delegate_actions_table`).
- `php artisan route:cache` et `view:cache` si utilisés.
- `npm run build` (menu du compte de l'en-tête : marqueur du `<summary>` masqué sous Safari).
- Nouvelle version de l'application.
