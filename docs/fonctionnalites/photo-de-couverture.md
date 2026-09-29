# Photo de couverture du profil

## But

Chaque compte (personne ou organisation) peut illustrer le bandeau de son profil par une photo de couverture, comme sur les réseaux sociaux. Sans photo, le bandeau garde son fond neutre.

## Utilisation

### Site web

- **Ajouter ou changer** : *Modifier le profil* → champ **Photo de couverture** (au-dessus de la photo de profil). L'aperçu se met à jour dès le choix du fichier ; la photo est enregistrée avec le bouton d'enregistrement du formulaire.
- **Retirer** : case **Retirer la photo de couverture** (affichée seulement s'il y en a une), puis enregistrer. Choisir un nouveau fichier décoche la case.
- **Affichage** : bandeau en haut de la page du profil (`/profiles/{id}`), 7 rem de haut sur mobile, 11 rem à partir de 640 px, 13 rem à partir de 1024 px ; l'image est recadrée (`object-cover`).

### Application mobile

- **Mon profil** : bouton (icône image) en haut à gauche de l'en-tête → feuille **Photo de couverture** : *Choisir dans la galerie*, *Prendre une photo*, *Retirer la couverture*. La photo apparaît derrière l'en-tête, sous un voile sombre qui garde le texte blanc lisible.
- **Modifier le profil** : bandeau cliquable en haut de l'écran (même feuille).
- L'envoi est **immédiat** (il ne dépend pas du bouton Enregistrer) ; un message confirme ou explique l'échec (message du serveur, par exemple image trop petite).
- **Profil d'un auteur** (Communauté, cartes) : bandeau de 150 px avec la photo, avatar posé à cheval.

## Règles

| Règle | Détail |
|---|---|
| Formats | JPG, PNG ou WebP |
| Poids | 8 Mo au plus (l'application réduit l'image à 1920 px de côté, qualité 85) |
| Dimensions | Au moins 600 × 150 pixels ; format large conseillé (1500 × 500) |
| Remplacement | L'ancien fichier est supprimé du disque |
| Suppression de fichier | Seulement si l'ancienne adresse pointe vers `storage/profile-covers/` (jamais un autre fichier) |
| Visibilité | Publique, comme la photo de profil (`cover_url` dans toutes les réponses `UserResource`, y compris Communauté) |

Messages d'erreur (français) : `ProfileCover::MESSAGES`.

## Fonctionnement technique

| Élément | Rôle |
|---|---|
| Colonne `users.cover_url` (500, nullable) | Adresse publique de l'image |
| `App\Services\ProfileCover` | `rules()`, `MESSAGES`, `store($user, $file)`, `remove($user)` ; disque `public`, dossier `profile-covers` |
| `Web\ProfileController::update` | Champs `cover` (fichier) et `remove_cover` (booléen) |
| `POST /api/v1/users/me/cover` | Multipart, champ `cover` ; 20 envois par minute ; réponse `{ cover_url }` |
| `DELETE /api/v1/users/me/cover` | Retire la couverture ; réponse `{ cover_url: null }` |
| `UserResource` | Champ `cover_url` |
| `profile/edit.blade.php`, `profile/show.blade.php` | Champ et bandeau |
| `resources/js/app.js` : `initCoverPreview` | Aperçu local (`[data-cover-input]`, `[data-cover-preview]`, `[data-cover-remove]`) |

Application (`testi_app`) :

| Fichier | Rôle |
|---|---|
| `lib/shared/widgets/profile_cover.dart` | `ProfileCoverImage` (image ou rien) et `showProfileCoverSheet()` |
| `lib/features/profile/providers/profile_provider.dart` | `uploadCover()`, `removeCover()` (mettent à jour l'utilisateur connecté et son cache) |
| `UserModel.coverUrl` (`copyWith(clearCoverUrl: true)` pour l'effacer), `CommunityAccount.coverUrl`, `UserProfile.coverUrl` | Modèles |
| `profile_screen.dart`, `edit_profile_screen.dart` (`_CoverPicker`), `user_profile_screen.dart` | Affichage |
| `AppConstants.profileCover` | `/users/me/cover` |

## Déploiement

- `php artisan migrate --force` (migration `2026_09_29_400001_add_cover_url_to_users_table`).
- Le lien `public/storage` doit exister (`php artisan storage:link`), comme pour les photos de profil.
- Recompiler les assets et copier `public/build/` (aperçu du formulaire), puis `php artisan view:cache`.
- Vérifier que `upload_max_filesize` et `post_max_size` de PHP acceptent 8 Mo.
- Application : nouvelle version à publier.

## Tests

- Serveur : `tests/Feature/ProfileCoverTest.php` (6 tests) : envoi, remplacement (ancien fichier supprimé), conservation sans fichier, retrait ; image trop petite ou non-image refusée ; API envoi, retrait, exposition ; visiteur refusé ; bandeau de la page du profil ; fichier hors du dossier des couvertures jamais supprimé.
- Application : `test/profile_cover_test.dart` (lecture de `cover_url`, cache, effacement, `CommunityAccount`, widget vide).

## Limites connues

- Pas de recadrage, ni dans l'application ni sur le site : l'image est centrée et recadrée à l'affichage. Les proportions diffèrent (bandeau 3:1 dans l'application, plus large sur ordinateur) : le haut et le bas d'une photo peuvent être coupés.
- Les cartes de la page Communauté n'affichent pas la couverture (seulement le profil).
