# Preuves d'un témoignage

## But

Permettre à l'auteur de joindre jusqu'à **deux preuves** (images ou PDF : certificat médical, attestation, photo…) pour aider l'équipe de modération à vérifier le témoignage. Par défaut, les preuves restent **privées** ; l'auteur peut accepter de les **publier** pour confirmer son témoignage au public.

## Utilisation

| Où | Comment |
|---|---|
| Site, *Publier un témoignage* | Section « Preuves du témoignage (facultatif) » : champs **Preuve 1** et **Preuve 2** |
| Application, publication | Section « Preuves du témoignage » : deux emplacements (galerie ou PDF), envoyés juste après le témoignage |
| Page du témoignage (auteur, équipe) | Encadré jaune « Preuves du témoignage » : aperçu des images, icône des PDF, ouverture dans un nouvel onglet |
| Relecture (modération) | Même encadré dans la fiche de relecture, ou « Aucune preuve jointe » ; si l'auteur a demandé leur publication : bouton **Ne pas publier les preuves** |
| Accord de publication | Case « Publier aussi mes preuves pour confirmer le témoignage » (site et application), sous les deux champs ; API `proofs_public` à la création ou à la modification |
| Page du témoignage (public) | Si l'auteur l'a accepté et que le témoignage est publié : encadré « Documents partagés par l'auteur pour confirmer ce témoignage » |

## Règles

| Règle | Détail |
|---|---|
| Nombre | 2 au plus (positions 1 et 2) ; une nouvelle preuve à une position occupée remplace l'ancienne |
| Formats | JPG, PNG, WebP, PDF ; 10 Mo chacune |
| Qui voit | L'auteur, les modérateurs et les administrateurs ; **tout le monde** si l'auteur a accepté (`proofs_public`) **et** que le témoignage est publié (validé, public, non supprimé) ; sinon 403 et champ `proofs` absent de l'API |
| Retrait par la modération | `POST /moderation/{id}/hide-proofs` : `proofs_public` repasse à faux ; seul l'auteur peut de nouveau accepter (en modifiant son témoignage, ce qui le renvoie en relecture) |
| Cache | Preuves publiques : `public, max-age=300` ; privées : `private, max-age=300` |
| Qui ajoute / retire | L'auteur seulement |
| Stockage | Disque privé `local` (`storage/app/private/proofs/{témoignage}/`), jamais dans `public/` ; fichier servi par Laravel après contrôle, `Content-Disposition: inline`, `X-Content-Type-Options: nosniff`, cache privé 5 min |
| Suppression d'un témoignage | Les lignes suivent le témoignage (suppression douce) ; les fichiers restent tant que le témoignage existe en base |

## API

| Méthode | Adresse | Accès |
|---|---|---|
| POST | `/api/v1/testimonies/{id}/proofs` | Auteur ; multipart `file`, `position` (1 ou 2) facultative ; 20 par minute ; 201 → `{ id, position, name, mimeType, size, isPdf, url }` ; 422 s'il y en a déjà deux |
| GET | `/api/v1/testimonies/{id}/proofs/{proofId}` | Route publique : auteur, équipe (jeton Bearer), ou tout le monde si les preuves sont publiées |
| DELETE | `/api/v1/testimonies/{id}/proofs/{proofId}` | Auteur |
| GET | `/testimonies/{id}/proofs/{proofId}` (`testimonies.proof`) | Site : même contrôle (sans connexion pour des preuves publiées) |

`TestimonyResource` : `proofsPublic` (accord de l'auteur) et `proofs` (liste ci-dessus) pour qui peut les voir. Migration de l'accord : `2026_09_30_200001_add_proofs_public_to_testimonies_table`.

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Services/TestimonyProofs.php` | Règles, messages, `canView()`, `add()`, `delete()` |
| `app/Models/TestimonyProof.php`, `Testimony::proofs()` | Modèle |
| `app/Http/Controllers/Api/TestimonyProofController.php` | API |
| `Web\TestimonyController::store()` (`proof_1`, `proof_2`), `::proof()` | Site |
| `resources/views/testimonies/partials/proofs.blade.php` | Encadré |
| Migration `2026_09_30_100002_create_testimony_proofs_table` | Table `testimony_proofs` |

## Tests

`tests/Feature/TestimonyProofsAndYouTubeTest.php` : formulaire avec deux preuves, accès (auteur, modérateur, autre, visiteur), fiche de relecture, format refusé, API (ajout, limite de deux, lecture, retrait, champ masqué aux autres).

## Limites connues

- Application : les preuves ne partent qu'avec une publication en ligne (pas avec la file d'envoi hors connexion).
- Pas d'analyse antivirus des fichiers.
