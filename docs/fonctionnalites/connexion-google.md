# Connexion avec Google

## But

Se connecter, ou créer son compte, avec son compte Google, sans mot de passe : dans l'application mobile et sur les pages de connexion (`/login`) et de création de compte (`/register`) du site. L'application reçoit exactement la même réponse qu'avec la connexion e-mail / mot de passe et la traite de la même façon.

## Parcours

1. L'application obtient un `id_token` Google avec le paquet `google_sign_in`, en passant **`serverClientId` = identifiant client Web** (`GOOGLE_WEB_CLIENT_ID`). Sans ce paramètre, l'audience du jeton est le client Android et le serveur le refuse.
2. Elle l'envoie à `POST /api/v1/auth/google { "id_token": "…" }`.
3. Le serveur vérifie le jeton auprès de Google (`GET https://oauth2.googleapis.com/tokeninfo?id_token=…`, délai 10 s).
4. Il retrouve ou crée le compte, puis renvoie un jeton Sanctum.

## Règles

Le jeton est refusé (**401**, `{"success": false, "message": "…"}`) si :

- Google ne le valide pas (expiré, falsifié) ou ne répond pas ;
- `aud` n'est pas `GOOGLE_WEB_CLIENT_ID`, ni l'un des identifiants de `GOOGLE_CLIENT_ID` (audiences héritées, séparées par des virgules) ; si les deux sont vides, tout jeton est refusé ;
- `iss` n'est ni `accounts.google.com` ni `https://accounts.google.com` ;
- `email_verified` n'est pas `"true"`, ou l'e-mail manque.

Recherche du compte, dans l'ordre :

| Cas | Effet |
|---|---|
| Compte avec ce `google_id` (`sub` du jeton) | Connexion. |
| Compte avec cet e-mail, sans `google_id` | **Liaison** : `google_id` enregistré ; `avatar_url` rempli avec la photo Google s'il est vide ; e-mail marqué vérifié s'il ne l'était pas. Le mot de passe éventuel reste valable. |
| Compte avec cet e-mail, lié à un **autre** compte Google | **409** « Cette adresse e-mail est déjà liée à un autre compte Google. » |
| Compte supprimé (corbeille) avec ce `google_id` ou cet e-mail | **403** « Ce compte a été supprimé. » |
| Aucun compte | **Création** : `display_name` = nom Google (sinon début de l'e-mail), `email`, `google_id`, `avatar_url`, `email_verified_at` = maintenant, sans mot de passe, rôle `utilisateur`, réglages par défaut. |

Compte banni : **403**, comme la connexion classique.

## Réponse

Identique à `POST /auth/login`, avec en plus `is_new_user` (comme `/auth/phone` et `/auth/register`) :

```json
{
  "success": true,
  "message": "Connexion réussie",
  "data": {
    "user": { "…": "UserResource::owner" },
    "access_token": "12|…",
    "token_type": "Bearer",
    "is_new_user": true
  }
}
```

Le jeton Sanctum s'appelle `mobile-google`. `user.has_password` vaut `false` pour un compte créé par Google.

## Site web : bouton sur `/login` et `/register`

- Sous le formulaire e-mail / mot de passe, séparateur « ou » puis le **bouton officiel de Google** (Google Identity Services, script `https://accounts.google.com/gsi/client`, libellé « Continuer avec Google », en français). Son apparence est imposée par Google : ce n'est pas un bouton de la charte.
- Affiché seulement si `GOOGLE_WEB_CLIENT_ID` est renseigné. Si le script Google ne se charge pas (bloqueur, hors ligne), le bloc disparaît et la connexion e-mail reste seule.
- Google renvoie un jeton d'identité (`credential`) au navigateur ; un formulaire caché (avec jeton CSRF) l'envoie à `POST /login/google` (`login.google`, invités seulement, 20 par minute). « Se souvenir de moi » est repris s'il est coché.
- Le serveur applique **exactement les mêmes règles** que l'API (`App\Services\GoogleSignIn`) : compte retrouvé, lié ou créé, puis session ouverte et retour à la page demandée (sinon l'accueil). En cas de refus ou de compte banni : retour sur `/login` avec le message d'erreur.
- **Page de création de compte** (`/register`) : le même bouton, sous le formulaire, libellé « S'inscrire avec Google » (`signup_with`). Même parcours : compte créé s'il est nouveau, connexion s'il existe déjà (même `POST /login/google`). En cas de refus, retour sur `/login` avec le message.
- Bloc et script communs aux deux pages : `resources/views/auth/partials/google-signin.blade.php` (`@include('auth.partials.google-signin', ['googleText' => 'continue_with' | 'signup_with'])`, `@push('scripts')`) : aucune recompilation des assets.

**Console Google Cloud (obligatoire)** : identifiants › client OAuth **Web** (`566166419345-b78f…`) › **Origines JavaScript autorisées** : ajouter `https://testi.airid-africa.com` (et `http://localhost:8000` en développement). Sans cela, Google affiche « origin_mismatch » et le bouton ne fonctionne pas. Aucune URI de redirection n'est nécessaire.

## Ancienne route `/auth/social`

`POST /api/v1/auth/social { provider: "google", token | id_token }` passe par le **même contrôleur** et suit les mêmes règles. Les versions actuelles de l'application envoient `token` : il est accepté comme synonyme de `id_token`. Les liens Google enregistrés auparavant dans `social_auth_providers` ont été recopiés dans `users.google_id` par la migration.

## Comptes sans mot de passe

Les comptes Google (comme les comptes téléphone) ont `password = null`. Le code existant le gère déjà :

- connexion e-mail (API et site) : refusée proprement (« Email ou mot de passe incorrect ») ;
- `POST /auth/change-password` : 403, faute d'ancien mot de passe. Un compte Google ne peut donc pas encore **définir** un premier mot de passe ;
- suppression du compte : confirmation explicite (`confirm`) à la place du mot de passe ;
- `forgot-password` : pas encore implémenté (répond sans rien envoyer).

## Fichiers

- `app/Services/GoogleSignIn.php` : vérification du jeton et compte retrouvé, lié ou créé (erreurs : `GoogleSignInException`, avec le code HTTP).
- `app/Http/Controllers/Api/GoogleAuthController.php` (invokable), routes `auth/google` et `auth/social` (limite : 20 par minute).
- Site : `Web\AuthController::google`, route `POST /login/google`, vues `resources/views/auth/login.blade.php` et `register.blade.php` (`googleClientId` transmis par `showLogin` et `showRegister`), partiel `auth/partials/google-signin.blade.php`.
- Migration `2026_10_09_200001_add_google_id_to_users_table` : `users.google_id` (unique, nullable) et reprise de `social_auth_providers`.
- `config/services.php` › `google.web_client_id` (`GOOGLE_WEB_CLIENT_ID`) et `google.client_id` (`GOOGLE_CLIENT_ID`).
- Tests : `tests/Feature/GoogleAuthTest.php` (tokeninfo simulé avec `Http::fake()`).

## Côté application (à faire)

- `GoogleSignIn(serverClientId: '<GOOGLE_WEB_CLIENT_ID>')` dans `lib/features/auth/services/social_auth_service.dart`.
- Appeler `/auth/google` avec `id_token` (ou garder `/auth/social`, compatible).
