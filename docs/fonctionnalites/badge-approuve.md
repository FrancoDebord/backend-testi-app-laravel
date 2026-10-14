# Badge « Approuvé » des comptes

## But

Signaler les comptes dignes de confiance, qu'il s'agisse de personnes (pasteurs, évangélistes, chantres…) ou d'organisations (églises, ministères, associations). Le badge s'affiche sur le profil et sur toutes les publications. Il **ne donne aucun droit** : les événements, les directs et les administrateurs de compte restent réservés aux organisations vérifiées et aux administrateurs.

## Données : on réutilise l'existant

Le badge **est** la vérification existante : `users.verification_status = verified`, avec `verified_at` et `verified_by`. Une seule colonne s'ajoute.

| Contrat | Source |
|---|---|
| `is_approved` | `verification_status = verified` (`User::isApproved()`) |
| `approved_at`, `approved_by` | `verified_at`, `verified_by`. Seul `approved_at` est exposé par l'API (ISO 8601, `null` sans badge) |
| `account_type` | `individual` ou `organization`, inchangé pour ne pas casser l'app |
| `approval_source` | **nouvelle colonne** : `verification_form`, `organization_review` ou `admin_manual` (`null` sans badge) |
| `approval_badge` | Calculé : `approved_personal`, `approved_organization` ou `null` |

Migration : `2026_10_14_200001_add_approval_source_to_users_table`. Les badges existants y sont repris : organisations → `organization_review`, personnes → `verification_form`.

## Trois chemins

| Chemin | Qui | Source | Notification |
|---|---|---|---|
| Demande de vérification d'une personne ([verification-des-comptes.md](verification-des-comptes.md)), approuvée par un administrateur | administrateur | `verification_form` | `account_verified` |
| Vérification d'une organisation ([comptes-organisation.md](comptes-organisation.md)) | administrateur | `organization_review` | `organization_verified` |
| **Attribution manuelle** depuis la fiche utilisateur, **motif obligatoire** | administrateur | `admin_manual` | `account_verified` ou `organization_verified` |

**Retrait manuel** (motif obligatoire) :
- `verification_status = rejected`, le motif est rangé dans `verification_note` et `approval_source` est vidé ;
- la demande approuvée de la personne passe à « retirée » ;
- notification **`account_approval_revoked`** (personne ou organisation), avec le motif.

Les retraits par les chemins existants (`revoke-verification` pour une personne, refus d'une organisation déjà vérifiée) vident aussi la source et envoient aussi `account_approval_revoked`.

**Notifications** : `account_approval_revoked` signale un **retrait** de badge, quel que soit le chemin. Les **refus de demande** (personne dont la demande est refusée, organisation jamais vérifiée) gardent `account_verification_rejected` et `organization_rejected`. Le type est « système » (in-app, push, filtre « Système » du site). Renommer une organisation vérifiée la repasse en attente : badge et source sont retirés.

Chaque attribution et chaque retrait, tous chemins confondus, est consigné dans `admin_audit_logs` (`user.badge_granted` ou `user.badge_revoked`, avant / après, motif). Voir [gestion-des-temoignages.md](gestion-des-temoignages.md#journal-daudit--admin_audit_logs).

## Droits

Réservé aux **administrateurs** (`App\Policies\UserPolicy::manageApproval`). Un modérateur reçoit 403.

## API

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/api/v1/admin/users/{id}/approval` `{ reason }` | Attribuer le badge (422 sans motif ou déjà approuvé). Renvoie l'utilisateur (`UserResource`) |
| DELETE | `/api/v1/admin/users/{id}/approval` `{ reason }` | Retirer le badge (422 sans motif ou sans badge) |
| GET | `/api/v1/admin/users?approved=1&approval_source=admin_manual` | Filtre « Comptes approuvés » |

**Champs ajoutés partout où un utilisateur ou un auteur est exposé** (`is_approved`, `account_type`, `approval_badge`, `approved_at`, en snake_case comme le demande le contrat de l'app ; source unique : `User::approvalFieldsFor()`) :

- `UserResource`, donc aussi l'auteur `user` d'un témoignage, d'un commentaire et d'une parole publique, et `repostedBy` ;
- les objets intégrés en camelCase, qui gardent leurs anciens champs (`isVerified`…) :
  - `author` d'une requête de prière, `organizer` d'un événement, `host` d'une session de prière et d'un direct ;
  - `user` des commentaires du verset et des événements ;
  - `author` de la file de modération ;
  - messages de prière, administrateurs de compte, comptes à suivre, mentions, partages internes, intervenants d'un direct, suggestions de recherche.

```json
{ "is_approved": true, "account_type": "organization", "approval_badge": "approved_organization", "approved_at": "2026-10-14T09:12:00+01:00" }
```

## Back-office

- **Fiche utilisateur** (`/admin/users/{id}`) : section « Badge Approuvé ».
  - Avec badge : état, date, auteur, origine, et formulaire « Retirer le badge » avec motif.
  - Sans badge : formulaire « Attribuer le badge Approuvé » avec motif.
  - Les 5 dernières attributions ou retraits du journal d'audit s'affichent sous le formulaire.
- **Liste des utilisateurs** : onglet « Comptes approuvés » (`/admin/users?tab=approved`).
- **File des demandes de vérification** : `/admin/verifications` (existante).

Routes web : `POST` et `DELETE /admin/users/{id}/badge` (`admin.users.approval.grant` et `admin.users.approval.revoke`).

## Tests

`php artisan test --filter=AccountApprovalTest` couvre :
- l'attribution manuelle (motif obligatoire, source, audit, notification, double attribution refusée) ;
- le retrait (motif obligatoire, notification avec le motif) ;
- la source du formulaire et des organisations, et le retrait par le chemin existant ;
- les champs d'API (`UserResource`, auteur d'un témoignage, auteur en camelCase d'une requête de prière) ;
- le back-office (bouton, onglet, filtre API) et les 403 pour un modérateur ou un membre.
