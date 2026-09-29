# Téléphone de contact (vérification des comptes)

## But

Recueillir le numéro de téléphone des personnes et des organisations, pour que l'équipe puisse **vérifier un compte** (appeler une organisation avant de la confirmer, par exemple). Le numéro n'est **jamais affiché publiquement**.

## Utilisation

### Inscription (`/register`) et « Modifier le profil » (`/profile/edit`)

Champ **Téléphone**, juste après **Pays** :

- **indicatif** : liste avec recherche et drapeaux (par nom : « ben » → Bénin ; ou par indicatif : « 33 » → France). Une fois choisi, le champ affiche le drapeau et « +229 » ;
- l'indicatif **suit le pays choisi** : choisir « Côte d'Ivoire » sélectionne +225. Il reste modifiable (numéro d'un autre pays) ;
- **numéro** : tel qu'on l'écrit dans le pays (« 01 97 12 34 56 », « 06 12 34 56 78 »), espaces, points et tirets acceptés, ou au format international (« +33 6… », « 0033 6… »).

| Compte | Téléphone |
|---|---|
| Personne | Facultatif |
| Organisation | **Obligatoire** (« obligatoire pour une organisation ») |

Dans le profil, un numéro **vérifié par SMS** (connexion par téléphone depuis l'application) est affiché avec la pastille « Vérifié par SMS » et n'est pas modifiable depuis le site : il sert à se connecter.

### Administration

Fiche d'un compte (`/admin/users/{id}`) : ligne **Téléphone** (lien d'appel) avec « Vérifié par SMS » ou « Non vérifié ».

## Règles

| Règle | Détail |
|---|---|
| **Format enregistré** | International E.164 : `+2290197123456` (colonne `users.phone`), avec le pays de l'indicatif (`users.phone_country`, code ISO) pour le réafficher exactement |
| **0 initial** | Retiré comme préfixe national (« 06… » en France → `+336…`), **sauf** au Bénin et en Côte d'Ivoire (numéros à 10 chiffres commençant par 0), en Italie, à Saint-Marin et au Vatican |
| **Validité** | 4 chiffres au moins après l'indicatif, 15 chiffres au plus au total ; saisi au format international, il doit correspondre à l'indicatif choisi. Sinon : « Numéro de téléphone invalide pour l'indicatif choisi. » |
| **Unicité** | Un numéro par compte : « Ce numéro est déjà associé à un autre compte. » |
| **API mobile** | `POST /api/v1/auth/register` et `PUT /api/v1/users/me` acceptent `phone_country` (code ISO) + `phone` (numéro national), avec les mêmes contrôles que le site. **Facultatif à l'inscription par l'API, même pour une organisation** (les versions déjà installées de l'application ne l'envoient pas) ; la nouvelle application l'exige pour une organisation. Sans `phone_country`, `PUT users/me` accepte encore un numéro libre (anciennes versions). Un numéro vérifié n'est pas modifiable avec `phone_country` |
| **Vérification** | Un numéro saisi sur le site, ou modifié dans le profil (site ou `PUT /api/v1/users/me`), **n'est pas vérifié** (`phone_verified_at` vide). Seule la connexion par SMS (`POST /api/v1/auth/phone`) le vérifie |
| **Connexion par téléphone** | Ne retrouve un compte par son numéro que si ce numéro est **vérifié**. Un numéro seulement déclaré ne donne jamais accès au compte qui l'a déclaré. Si la personne qui confirme ce numéro par SMS n'a pas de compte, le numéro lui revient : il est retiré du compte qui l'avait seulement déclaré |
| **Confidentialité** | Dans l'API, `email`, `phone`, `phone_country` et `is_phone_verified` ne sont remplis que pour la personne elle-même (connexion, inscription, `auth/me`, `users/me`) et pour les administrateurs ; `null` partout ailleurs (auteur d'un témoignage ou d'un commentaire, profil public) |

## Fonctionnement technique

| Élément | Fichier |
|---|---|
| Indicatifs (204, par code ISO) | `App\Support\Countries::dialCode()`, `nameOf()` |
| Format, validation, affichage | `App\Support\PhoneNumber` : `toE164()`, `national()`, `display()`, `rules()` (unicité comprise), `MESSAGES` |
| Modèle | `User::setContactPhone()` (un numéro différent perd sa vérification), `hasVerifiedPhone()` |
| Formulaires | `resources/views/components/phone-input.blade.php` (indicatif `phone_country` + numéro `phone`) ; `Web\AuthController::register`, `Web\ProfileController::update` |
| Connexion par téléphone | `Api\AuthController::phoneAuth` |
| API | `Api\UserController::updateMe` (numéro unique, vérification retirée s'il change) ; `UserResource` (coordonnées privées, `UserResource::owner()` pour les réponses destinées à la personne) |
| JavaScript | `resources/js/app.js` : liste avec recherche (`data-country-select`, libellé court `data-label`, textes `data-placeholder` / `data-empty-text`) et `data-follow-country` (l'indicatif suit le pays, via `data-code` des options de pays) |

Migration `2026_09_29_200001_add_phone_verification_to_users_table` : colonnes `phone_country` et `phone_verified_at`. Les numéros existants des comptes créés par la connexion par téléphone (`firebase_uid` renseigné) sont marqués vérifiés.

## Application mobile (Flutter, `testi_app`)

| Élément | Fichier de l'application |
|---|---|
| Liste des 204 pays (nom, code ISO, indicatif), **générée depuis `App\Support\Countries`** : mêmes noms que le site ; recherche sans accents par nom ou indicatif | `lib/core/data/countries.dart` |
| Liste avec recherche (drapeaux émojis, natifs sur Android et iOS), champ « Pays » et champ « Téléphone » (indicatif + numéro) | `lib/shared/widgets/country_picker.dart` |
| Inscription : pays, puis téléphone dont l'indicatif **suit le pays** ; obligatoire pour une organisation, facultatif pour une personne | `lib/features/auth/screens/register_screen.dart`, `auth_notifier.dart` (`phone_country` + `phone`) |
| Profil : mêmes champs ; numéro vérifié par SMS affiché en lecture seule | `lib/features/profile/screens/edit_profile_screen.dart`, `profile_provider.dart` |
| Modèle : `phoneCountry`, `isPhoneVerified` | `lib/shared/models/user_model.dart` |
| Tests | `test/countries_phone_test.dart` |

Après un ajout ou une correction de pays côté serveur, régénérer la liste de l'application : `php artisan countries:export-dart ../testi_app/lib/core/data/countries.dart` (commande `App\Console\Commands\ExportCountriesDart`).

## Déploiement

`php artisan migrate --force` ; recompiler et copier `public/build/` ; `php artisan view:cache`.

## Tests

`tests/Feature/PhoneNumberTest.php` (9 tests) : formats (Bénin, Côte d'Ivoire, France, Nigeria, Cameroun, international, invalides), champ après le pays, inscription avec ou sans numéro, organisation (obligatoire, invalide, indicatif manquant, déjà utilisé), profil (numéro non vérifié modifiable, vérifié en lecture seule), API (unicité, vérification retirée), connexion par téléphone (numéro déclaré sans accès au compte, numéro vérifié toujours reconnu), confidentialité de l'API et fiche d'administration.

Vérifié dans Chrome à 390, 768 et 1440 px : l'indicatif suit le pays (Côte d'Ivoire → +225), recherche par indicatif, inscription complète enregistrée en `+2290197123456` ; aucune erreur, aucun défilement horizontal.

## Limites connues

- **Aucune vérification par SMS depuis le site** : le numéro saisi est « non vérifié » ; l'équipe le vérifie en appelant.
- **Sécurité à corriger (antérieur)** : `AuthController::verifyFirebaseToken()` **ne vérifie pas la signature** du jeton Firebase (il le décode seulement). Un jeton fabriqué permet de se connecter par `POST /api/v1/auth/phone` au compte d'un **numéro vérifié** ou d'un identifiant Firebase connu. Correction à faire : vérifier la signature RS256 (clés publiques Google), l'émetteur `https://securetoken.google.com/{projet}`, l'audience (identifiant du projet Firebase) et l'expiration, et prendre le numéro dans le jeton (`phone_number`) plutôt que dans la requête.
- Anciennes versions de l'application : elles n'envoient pas de numéro à l'inscription (accepté) et peuvent envoyer `phone` en texte libre dans `PUT users/me` (accepté, non vérifié).
