# Vérification des comptes personnels

## But

Permettre aux **personnes** (pasteurs, évangélistes, chantres, responsables de ministère, personnalités publiques…) d'obtenir le badge **« Compte vérifié »**, comme les organisations obtiennent « Organisation vérifiée » ([comptes-organisation.md](comptes-organisation.md)). La personne envoie une demande avec des **pièces justificatives privées** ; un administrateur l'approuve, la refuse (motif) ou retire plus tard la vérification.

Le badge **ne donne aucun droit** : créer des événements, diffuser un événement en direct et les gestionnaires d'organisation restent réservés aux **organisations vérifiées** et aux administrateurs ([evenements.md](evenements.md)).

## Règles

| Règle | Détail |
|---|---|
| Qui peut demander | Tout compte **personnel** connecté. Une organisation se vérifie à partir de son profil (parcours existant) |
| Contenu de la demande | Nom complet, fonction (pasteur, évangéliste, chantre, responsable de ministère, personnalité publique, autre + précision), église ou ministère de rattachement, 0 à 3 liens publics (http/https), message facultatif (1 000 caractères), **1 à 3 pièces** (JPG, PNG, WebP ou PDF, 8 Mo chacune) |
| Une demande à la fois | Impossible d'envoyer une nouvelle demande tant que la précédente est en attente, ou quand le compte est déjà vérifié |
| Après un refus ou un retrait | Nouvelle demande possible **24 heures** après la décision (`PersonalVerification::RETRY_DELAY_HOURS`) |
| Qui examine | Les **administrateurs** uniquement (comme les organisations) : site et API admin. Les modérateurs n'ont pas accès aux pièces |
| Refus | **Motif obligatoire**, communiqué à la personne |
| Retrait | Motif obligatoire ; le badge disparaît, la demande approuvée passe à « Vérification retirée » |
| Prévenir la personne | Notification dans l'application + push : `account_verified` (« Compte vérifié ») ou `account_verification_rejected` (refus ou retrait, motif dans le message) |
| Confidentialité du statut | `verification_status` d'une **personne** n'est renvoyé au public que si elle est vérifiée ; une demande en cours ou refusée n'est visible que par elle-même et les administrateurs |

## Pièces justificatives (données sensibles)

- Enregistrées sur le disque **privé** `local` (`storage/app/private/verification-requests/{user_id}/{uuid}.{ext}`), jamais sur le disque `public` : **aucune URL publique**, `/storage/…` ne les sert pas.
- Servies uniquement par des routes réservées aux administrateurs, avec `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff` et une CSP restrictive :
  - site : `GET /admin/verifications/{id}/pieces/{index}` (session administrateur) ;
  - API : `GET /api/v1/admin/verification-requests/{id}/documents/{index}` (jeton administrateur).
- L'API du demandeur ne renvoie que leur **nombre** (`documentCount`), jamais un chemin ni une URL.
- **Suppression du compte** (suppression douce) : le dossier des pièces est effacé et la liste `documents` vidée (`User::booted()` → `PersonalVerification::purgeDocuments()`).

## Modèle de données

Le badge reste porté par les colonnes de `users` déjà utilisées par les organisations (`verification_status`, `verified_at`, `verified_by`, `verification_note`) : `User::isVerified()` vaut maintenant pour **tout compte** vérifié, et `User::isVerifiedOrganization()` (organisation **et** vérifiée) sert aux droits.

Table `verification_requests` (migration `2026_10_07_100001_create_verification_requests_table`) : le dossier de chaque demande.

| Colonne | Rôle |
|---|---|
| `id` (uuid), `user_id` | Demandeur (suppression en cascade) |
| `full_name`, `role`, `role_other`, `church` | Informations déclarées (`App\Enums\PersonalVerificationRole`) |
| `links` (json), `message` | Liens publics, message |
| `documents` (json) | `[{path, name, mime, size}]` sur le disque privé |
| `status` | `pending` · `approved` · `rejected` · `revoked` |
| `review_note`, `reviewed_by`, `reviewed_at` | Décision (motif, administrateur, date) |

Correspondance avec `users.verification_status` d'une personne : demande envoyée → `pending` ; approuvée → `verified` ; refusée ou retirée → `rejected` (motif dans `verification_note`).

## API

Demandeur (connecté) :

```
GET  /api/v1/users/me/verification
POST /api/v1/users/me/verification   (multipart, 10 envois / heure)
     full_name, role, role_other?, church, links[]?, message?, documents[] (fichiers)
```

Réponse (GET, et POST en 201) :

```json
{
  "success": true,
  "data": {
    "status": "none | pending | verified | rejected | revoked",
    "isVerified": false,
    "canRequest": true,
    "blockReason": null,
    "retryAt": null,
    "reason": null,
    "verifiedAt": null,
    "request": {
      "id": "…", "status": "pending", "fullName": "…", "role": "pastor", "roleLabel": "Pasteur",
      "roleOther": null, "church": "…", "links": ["https://…"], "message": "…",
      "documentCount": 2, "createdAt": "…", "reviewedAt": null
    },
    "roles": [{ "value": "pastor", "label": "Pasteur" }, "…"],
    "limits": { "maxDocuments": 3, "maxDocumentKb": 8192, "documentTypes": ["jpg", "jpeg", "png", "webp", "pdf"], "maxLinks": 3 }
  }
}
```

Erreurs : 422 avec `errors` par champ (`full_name`, `role`, `role_other`, `church`, `links.0`, `documents`, `documents.0`…) ; 422 avec un message seul quand la demande est impossible (organisation, déjà vérifié, demande en cours, délai après refus).

Administration (`role:administrateur`) :

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/v1/admin/verification-requests?status=pending\|approved\|rejected\|revoked\|all&per_page=` | `{items, pendingCount, currentPage, lastPage, total}` |
| GET | `/api/v1/admin/verification-requests/{id}` | Détail : `links`, `message`, `reviewer`, `documents[{index, name, mime, size, isImage, url}]` |
| GET | `/api/v1/admin/verification-requests/{id}/documents/{index}` | Fichier (privé) |
| POST | `/api/v1/admin/verification-requests/{id}/approve` | Approuver |
| POST | `/api/v1/admin/verification-requests/{id}/reject` | `{reason}` obligatoire |
| POST | `/api/v1/admin/users/{id}/revoke-verification` | `{reason}` obligatoire : retirer la vérification d'une personne |

Le badge est déjà présent dans toutes les ressources : `UserResource.is_verified`, auteurs des témoignages (`user.is_verified`), commentaires d'événement et du verset du jour, requêtes et sessions de prière (`isVerified`, et maintenant `isOrganization`), suggestions de comptes, mentions.

## Site

- **Demande** : `/profile/verification` (lien « Faire vérifier mon compte » sur son propre profil personnel) : formulaire, puis état (en cours, vérifié, refusé/retiré avec motif et date de la prochaine demande possible).
- **Administration** : menu « Vérification des comptes » (`/admin/verifications`, onglets En attente / Approuvées / Refusées / Retirées / Toutes), fiche de la demande avec aperçu des pièces (images en ligne, PDF dans un nouvel onglet), Approuver, Refuser (motif) ; « Retirer le badge » sur la demande approuvée et sur la fiche du compte (`/admin/users/{id}`). Le tableau de bord signale les demandes en attente.
- **Badge** : `components/verified-badge` affiche « Compte vérifié » pour une personne, « Organisation vérifiée » pour une organisation (même coche).

## Application mobile (`testi_app`)

- Profil → « Mon espace » → **Faire vérifier mon compte** (`/profile/verification`, `AccountVerificationScreen`) : état, formulaire (fonction en puces, liens, « Prendre une photo » / « Choisir un fichier », 3 pièces au plus, rappel que les pièces restent privées), validation locale identique à celle du serveur (`validateVerificationForm`).
- Notifications `account_verified` / `account_verification_rejected` : ouvrent l'écran de vérification.
- Badge `OrganizationBadge` : info-bulle « Compte vérifié » pour une personne. Les droits « événements » de l'application testent toujours `isOrganization && isVerified`.
- Administration → icône « Vérification des comptes » de la barre du tableau de bord (`AdminVerificationsScreen`) : liste par statut, détail, aperçu des images (téléchargées avec le jeton), PDF à ouvrir sur le site, Approuver / Refuser / Retirer.

## Fichiers

- Serveur : `App\Services\PersonalVerification`, `App\Models\VerificationRequest`, `App\Enums\PersonalVerificationRole`, `Api\AccountVerificationController`, `Web\AccountVerificationController`, vues `profile/verification`, `admin/verifications/*`.
- Application : `lib/features/profile/models/account_verification.dart`, `providers/account_verification_provider.dart`, `screens/account_verification_screen.dart`, `lib/features/admin/screens/admin_verifications_screen.dart`.
- Tests : `tests/Feature/AccountVerificationTest.php` ; `test/account_verification_test.dart`.

## Limites

- Les pièces sont conservées après la décision (jusqu'à la suppression du compte) ; pas encore de purge automatique.
- Pas d'aperçu des PDF dans l'application : l'administrateur les ouvre sur le site.
- Les modérateurs ne peuvent pas examiner les demandes (choix : mêmes droits que la vérification des organisations).
