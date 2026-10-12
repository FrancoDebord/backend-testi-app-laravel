# Réglages, compte et « Mes témoignages » (API de l'application)

Ces points du contrat de l'API servent aux écrans Profil et Paramètres de l'application. Ils ont été ajoutés ou corrigés le 2026-10-06, après une comparaison avec l'application (`testi_app`).

## Réglages (`user_settings`)

| Méthode | Route | Rôle |
|---|---|---|
| GET | `users/me/settings` | Réglages enregistrés (la ligne est créée avec les valeurs par défaut si besoin) |
| PUT | `users/me/settings` | Modifie **seulement** les clés envoyées ; renvoie les réglages complets |

Clés :

- `push_comments`, `push_likes`, `push_prayers`, `push_approval`, `push_new_followed` : booléens (voir [notifications push](notifications-push.md)) ;
- `comment_permission` : `everyone` \| `followers` \| `nobody` ;
- `is_private_account`, `app_theme`, `language`, `last_bible_*`.

## « Qui peut commenter » (`comment_permission`)

Le réglage s'applique aux commentaires sur les témoignages de l'auteur :

- API : `POST testimonies/{id}/comments` ;
- site : page de lecture et ancienne route `testimonies/{id}/comments`.

Valeurs :

- `everyone` (par défaut) : tout compte connecté ;
- `followers` : les abonnés de l'auteur ;
- `nobody` : personne.

L'auteur et la modération peuvent toujours commenter. Un commentaire refusé renvoie **403** « L'auteur a limité les commentaires sur ses témoignages. ».

`TestimonyResource` expose `canComment` pour la personne connectée, afin que l'application masque la zone de saisie. Code : `Testimony::acceptsCommentsFrom()`.

## Supprimer le compte (`DELETE users/me`)

- Compte avec mot de passe (e-mail) : envoyer `{ password }` (403 si le mot de passe est incorrect).
- Compte **sans mot de passe** (téléphone, Google, Facebook) : envoyer `{ confirm: true }` (422 `errors.confirm` sinon).

Le compte est supprimé (suppression douce) et ses jetons sont révoqués.

`UserResource` (profil de la personne connectée : `auth/me`, connexion) expose `has_password`. L'application s'en sert pour demander le mot de passe ou une simple confirmation, et pour afficher ou non le formulaire « Changer le mot de passe » (`POST auth/change-password`).

## Mes témoignages

- **Renommer** : `PUT testimonies/{id}` avec `{ title }` seul. En modification, seuls les champs envoyés changent ; `title` et `type` ne sont obligatoires qu'à la création.
- **Motif du refus** : `rejectionReason` est un texte lisible (« Faux témoignage — note du modérateur »), renvoyé pour un témoignage refusé. Seuls l'auteur et la modération le voient. Il vient du dernier `moderation_logs` d'action `rejected`.
- **Verset cité** (`bible_verse`) : jusqu'à 500 caractères. La colonne était en VARCHAR(255), ce qui provoquait une erreur 500 au-delà de 255 caractères ; elle passe en `TEXT` (migration `2026_10_06_500001_widen_bible_verse_columns`, aussi pour `drafts.bible_verse`).

## Partages comptés sans compte

`POST testimonies/{id}/share` et `POST daily-verse/share` acceptent les invités (30 requêtes par minute). Seuls les témoignages publiés et non privés se partagent (404 sinon).

## Tests

`tests/Feature/AppContractTest.php`.
