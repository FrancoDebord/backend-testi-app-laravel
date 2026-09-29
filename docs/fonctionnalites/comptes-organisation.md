# Comptes organisation

## But

Permettre aux **églises, ministères, associations, ONG et médias chrétiens** d'avoir un compte à leur nom sur TestiApp, et à un administrateur de **confirmer leur identité**. Une organisation confirmée porte une **coche « Organisation vérifiée »** à côté de son nom (profil, témoignages), dans l'application mobile comme sur le site.

## Règles

| Règle | Détail |
|---|---|
| Type de compte | `individual` (personne, par défaut) ou `organization`, choisi **à l'inscription** (application mobile ou site) |
| Changer de type | **Impossible** depuis le profil : une personne ne peut pas se déclarer organisation, ni se dire vérifiée |
| Nouvelle organisation | Créée **en attente de vérification** (`verification_status = pending`) |
| Qui vérifie | Les **administrateurs** uniquement (site : fiche du compte ; API : `admin/users/{id}/verify`) |
| Refus | Motif facultatif, communiqué à l'organisation |
| Renommer une organisation vérifiée | Elle **repasse en attente** : la coche disparaît jusqu'à une nouvelle vérification |
| Modifier une organisation refusée | La demande est **renvoyée en vérification** (statut `pending`) |
| Ville, site, type | Modifiables sans perdre la vérification |
| Prévenir l'organisation | Notification dans l'application (`organization_verified` / `organization_rejected`) |

La notification apparaît dans la liste des notifications de l'application et part aussi en **notification push** (« Organisation vérifiée » / « Vérification refusée ») sur les appareils de l'organisation, si FCM est configuré ([notifications-push.md](notifications-push.md)).

## Types d'organisation

| Valeur | Libellé |
|---|---|
| `church` | Église |
| `ministry` | Ministère |
| `association` | Association |
| `ngo` | ONG |
| `media` | Média chrétien |
| `other` | Autre |

Enum : `App\Enums\OrganizationType` (`label()`). Statuts : `App\Enums\VerificationStatus` (`pending` « En attente de vérification », `verified` « Vérifiée », `rejected` « Vérification refusée », avec `badgeClass()`). Type de compte : `App\Enums\AccountType`.

## Modèle de données

Migration `2026_09_28_200001_add_organization_fields_to_users_table`, colonnes de `users` :

| Colonne | Type | Rôle |
|---|---|---|
| `account_type` | string(20), défaut `individual`, index | Personne ou organisation |
| `organization_name` | string, nullable | Nom officiel |
| `organization_type` | string(20), nullable | Voir tableau ci-dessus |
| `organization_city` | string, nullable | Ville |
| `organization_website` | string, nullable | Site internet (http/https) |
| `verification_status` | string(20), nullable, index | `null` pour une personne |
| `verified_at` | timestamp, nullable | Date de la vérification |
| `verified_by` | uuid, nullable | Administrateur qui a vérifié ou refusé |
| `verification_note` | string, nullable | Motif du refus |

Modèle `User` : `isOrganization()`, `isVerified()` (organisation **et** statut `verified`), portées `organizations()` et `pendingVerification()`, relation `verifier()`.

Toute la logique (règles de validation, mise à jour du profil, vérification, refus, notification) est dans `App\Services\OrganizationAccounts`, partagé par l'API et l'interface web.

## API

### Inscription — `POST /api/v1/auth/register`

Champs ajoutés (en plus de `display_name`, `email`, `password`, `password_confirmation`, `country`) :

| Champ | Règle |
|---|---|
| `account_type` | facultatif, `individual` ou `organization` |
| `organization_name` | **obligatoire si** `account_type = organization`, 150 caractères max. |
| `organization_type` | facultatif, une des valeurs ci-dessus |
| `organization_city` | facultatif, 100 caractères max. |
| `organization_website` | facultatif, adresse `http(s)://` |

`first_name`, `last_name` et `name` envoyés par l'application sont acceptés et ignorés : seul `display_name` est enregistré (`last_name` est vide pour une organisation).

### Profil — `PUT /api/v1/users/me`

Accepte `organization_name`, `organization_type`, `organization_city`, `organization_website`, **pour une organisation seulement** (ignorés pour une personne). Le nom ne peut pas être vidé ; `organization_type: null` conserve le type enregistré. `account_type`, `verification_status`, `is_verified` et `verified_*` sont toujours ignorés.

### Réponse — `UserResource`

Champs ajoutés : `account_type`, `organization_name`, `organization_type`, `organization_city`, `organization_website`, `is_verified` (booléen), `verification_status` (`pending` / `verified` / `rejected` / `null`).

`TestimonyResource` inclut l'auteur sous `user` (même `UserResource`) : `user.account_type` et `user.is_verified` servent au badge de l'application.

### Administration (rôle `administrateur`)

| Route | Effet |
|---|---|
| `GET admin/users?account_type=organization&verification_status=pending` | Filtres ajoutés ; `meta.pending_organizations` = nombre d'organisations en attente |
| `POST admin/users/{id}/verify` | Vérifie : statut `verified`, `verified_at`, `verified_by`, notification |
| `POST admin/users/{id}/reject-verification` | Corps : `reason` (facultatif, 255 car.). Statut `rejected`, motif enregistré, notification |

Réponse : `UserResource` du compte. **422** si le compte n'est pas une organisation, **403** pour un non-administrateur.

## Interface web (administration)

- **Utilisateurs** (`/admin/users`) : onglets « Tous les comptes », « Organisations », « Organisations en attente » (avec le nombre). Paramètre `tab=organizations|pending`, conservé par les filtres. Sous le nom : type, ville et pastille de vérification.
- **Fiche d'un compte** (`/admin/users/{id}`) : bloc « Organisation » (nom, type, ville, site, statut, date et auteur de la vérification, motif de refus) et actions **Vérifier** (bouton principal) / **Refuser** (motif facultatif), confirmées par `openConfirmModal`. Une organisation vérifiée peut se voir **retirer** la vérification. Routes `admin.users.verify` et `admin.users.reject-verification` (POST).
- **Tableau de bord** : bandeau « N organisation(s) en attente de vérification » avec lien vers l'onglet.

## Pages publiques

Coche `components/verified-badge.blade.php` (icône grise, « Organisation vérifiée ») à côté du nom :

- profil public (`/profiles/{id}`), qui affiche aussi le type et la ville de l'organisation ;
- cartes de témoignage (`videos/partials/card`, utilisée par `components/testimony-card`) ;
- page de lecture (`videos/partials/watch`).

```blade
@include('components.verified-badge', ['user' => $user])
```

## Inscription depuis le site

Le formulaire `/register` (`resources/views/auth/register.blade.php`, `Web\AuthController@register`) crée une personne **ou** une organisation, avec les règles de l'API (`OrganizationAccounts::registerRules()` et `registrationAttributes()`).

| Élément | Comportement |
|---|---|
| Choix du type | En haut du formulaire, deux cartes radio `account_type` : « Je suis une personne » (`individual`, par défaut) / « Je représente une organisation » (`organization`) |
| Mode personne | Champ **Nom complet** (`display_name`, obligatoire : `required_unless:account_type,organization`) |
| Mode organisation | **Nom de l'organisation** (obligatoire : `required_if`), **Type** (liste avec les libellés français de `OrganizationType`, facultatif), **Ville**, **Site internet** (facultatifs), puis la note « Votre organisation sera vérifiée par notre équipe… ». Le champ Nom complet est masqué : le nom affiché (`display_name`) est le nom de l'organisation |
| Téléphone | **Obligatoire pour une organisation** (facultatif pour une personne) : indicatif qui suit le pays, puis le numéro ; l'équipe s'en sert pour la vérification. Voir [telephone.md](telephone.md) |
| Champs communs | E-mail, pays, mot de passe et confirmation |
| Compte créé | `status = active`, `role = utilisateur`, `verification_status = pending` pour une organisation (`null` pour une personne), comme l'API |
| Après l'inscription | Personne : accueil. Organisation : son profil, avec le message « Compte créé. Votre organisation sera vérifiée par notre équipe. » |
| Erreurs de validation | `old('account_type')` rétablit le mode choisi et les champs saisis |

**Sans JavaScript**, les deux blocs restent affichés (avec la mention « Pour une personne uniquement » / « Pour une organisation uniquement ») et le serveur ne tient compte que du bloc du type choisi : les champs organisation envoyés pour une personne sont ignorés. **Avec JavaScript** (`initAccountTypeForm` dans `resources/js/app.js`, attributs `data-account-type-form`, `data-account-section="individual|organization"`, `data-section-required`, `data-account-nojs-hint`), seul le bloc du type choisi est visible et ses champs obligatoires reçoivent l'attribut `required`.

**Profil de l'organisation** (`/profiles/{id}`, vue par l'organisation elle-même uniquement) : avis « Vérification en cours » (`alert-info`) tant que le statut est `pending`, ou « Vérification refusée » (`alert-warning`) avec le motif si le statut est `rejected`. L'avis de refus renvoie vers « Modifier le profil » (ou l'application mobile) pour corriger les informations.

## Modifier son organisation depuis le site

La page « Modifier le profil » (`/profile/edit`, `resources/views/profile/edit.blade.php`, `Web\ProfileController@edit` / `@update`, route `profile.update` en PUT) permet à une organisation de modifier son **nom**, son **type**, sa **ville** et son **site internet**, avec les règles de l'application mobile : validation `OrganizationAccounts::updateRules()` et enregistrement `OrganizationAccounts::updateProfile()`, partagés avec `PUT /api/v1/users/me`.

| Élément | Comportement |
|---|---|
| Section « Organisation » | Pour une organisation seulement : badge du statut de vérification (`badgeClass()` / `label()` de `VerificationStatus`), nom (obligatoire, 150 car.), type (libellés `OrganizationType`, facultatif), ville (100 car.), site internet (`http(s)://`) |
| Vérification refusée | Avis `alert-warning` « Vérification refusée » avec le motif, au-dessus des champs |
| Organisation vérifiée | Mention sous le nom : « Modifier le nom remettra votre organisation en attente de vérification. » |
| Nom affiché | Pas de champ « Nom complet » pour une organisation : `display_name` reçoit le nom de l'organisation (comme l'application mobile) ; un `display_name` envoyé est ignoré |
| Champs communs | Logo (libellé « Logo de l'organisation »), pays et présentation |
| Renommer une organisation vérifiée | Statut `pending`, `verified_at` / `verified_by` effacés ; message « Profil mis à jour. Votre organisation sera de nouveau vérifiée par notre équipe. » |
| Modifier une organisation refusée | Demande renvoyée (`pending`, motif effacé), même message |
| Ville, type ou site d'une organisation vérifiée | Enregistrés sans perdre la vérification |
| Type laissé sur « Choisir… » | Le type enregistré est conservé |
| Personne | Aucun changement : « Nom complet » obligatoire ; champs organisation, `account_type` et `verification_status` envoyés ignorés |

Aucun JavaScript ni style ajouté : composants existants (`section-title`, `form-*`, `badge-*`, `alert-warning`), un seul `btn-primary` (« Enregistrer ») ; type et ville côte à côte à partir de `sm`, empilés sur mobile.

## Tests

`tests/Feature/OrganizationAccountTest.php` : inscription personne / organisation, erreurs de validation, modification du profil (renommage qui remet en attente, personne qui ne peut pas se convertir), vérification et refus par l'API, accès refusé aux non-administrateurs, pages web d'administration, `user.is_verified` dans le JSON d'un témoignage et coche sur le profil public.

`tests/Feature/WebOrganizationRegistrationTest.php` : inscription depuis le site (personne toujours possible, organisation créée en attente et active, champs obligatoires selon le mode, type ou site invalide refusé, mode et saisie rétablis après une erreur, avis « Vérification en cours » réservé au propriétaire du profil).

`tests/Feature/WebOrganizationProfileEditTest.php` : « Modifier le profil » du site (section Organisation et badge pour une organisation seulement, mention de renommage pour une organisation vérifiée, motif de refus, ville / type / site modifiés sans perte de vérification, renommage qui remet en attente et met à jour le nom affiché, organisation refusée renvoyée en vérification, validation du nom, du type et du site, type vide conservé, personne inchangée qui ne peut ni devenir organisation ni se dire vérifiée).
