# Français et anglais

## But

Tous les textes visibles existent en français (langue par défaut) et en anglais : site web, réponses de l'API, messages de validation, notifications de l'application et push, libellés des listes (types, statuts, motifs). Chaque membre choisit sa langue ; ses push et e-mails partent dans cette langue.

## Règles

| Canal | Langue utilisée |
|---|---|
| API (`/api/v1`) | En-tête `Accept-Language` de la requête (poids `q` respectés) ; **français** sans en-tête ou pour une langue non prise en charge. La réponse porte `Content-Language: fr` ou `en`. |
| Site web | Choix du sélecteur de langue (session) ; sinon `users.locale` du membre connecté ; sinon (visiteur) **langue du navigateur** (`Accept-Language`, 2026-10-27) ; sinon français. |
| Nouveau compte | `users.locale` = langue de la requête d'inscription (2026-10-27) : langue choisie dans l'application ou, par défaut, celle du système ; langue du site (navigateur) pour une inscription sur le site. Auparavant toujours `fr`. (`User::creating`) |
| Verset du jour | Texte dans la langue de la requête : voir [verset-du-jour.md](verset-du-jour.md#langue-du-verset-2026-10-27). |
| Push | `users.locale` du destinataire, quelle que soit la langue de la personne qui déclenche l'action. |
| E-mails, notifications Laravel | `users.locale` (`User` implémente `HasLocalePreference`). Le serveur n'envoie pas encore d'e-mail. |
| Liste des notifications | Langue de la requête (API) ou du site. |

Langues prises en charge : `fr`, `en` (`App\Support\Locales::SUPPORTED`). Le français est aussi la langue de secours : une clé absente en anglais s'afficherait en français (le test de parité l'empêche).

L'application mobile doit envoyer `Accept-Language: en` (ou `fr`) sur chaque requête, selon la langue choisie dans l'app, et enregistrer ce choix avec `PUT /me { "locale": "en" }` pour recevoir ses push dans la bonne langue.

## Utilisation

- **Site** : sélecteur `FR | EN` dans l'en-tête (pages connectées et pages de connexion ; en bas de page sur mobile). Pour un membre connecté, le choix est aussi enregistré dans son compte. En agissant au nom d'un compte administré, c'est la langue de la personne réellement connectée qui change.
- **API** :

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/me` (comme `/users/me`) | Profil, dont `locale` (`fr` ou `en`, visible du seul titulaire et des administrateurs) |
| PUT | `/me` (comme `/users/me`) `{ locale: "fr" \| "en" }` | Langue des push et e-mails ; autre valeur → 422. Champ réservé au titulaire (ignoré au nom d'un compte administré). |

Exemple :

```http
PUT /api/v1/me
Accept-Language: en
{ "locale": "en" }
```
```json
{ "success": true, "data": { "id": "…", "display_name": "Marie", "locale": "en", … }, "message": "Profile updated" }
```

## Fonctionnement technique

- **Fichiers de langue** : `lang/fr/*.php` et `lang/en/*.php` (clés PHP, un fichier par domaine : `layout`, `api`, `enums`, `models`, `notifications`, `conversion`, `events_past`, `validation`…). Les deux dossiers ont exactement les mêmes fichiers, clés et paramètres `:nom` : `tests/Unit/TranslationKeysTest.php` échoue sinon, en nommant la clé manquante.
- **Choix de la langue** : `App\Http\Middleware\SetLocale` (début du groupe `api`, fin du groupe `web`), règles dans `App\Support\Locales` (`fromAcceptLanguage`, `fromRequest`, `forUser`, `apply`, `using`, `number`). `AppServiceProvider` force le français par défaut quelle que soit `APP_LOCALE`. Les dates (`diffForHumans`, `translatedFormat`) suivent la langue (Carbon).
- **Énumérations et modèles** : `label()` des enums lit `lang/*/enums.php` ; les anciennes constantes de libellés sont devenues des méthodes (`Event::reportReasons()`, `PrayerRequest::visibilityLabels()`, `VerificationRequest::statusLabels()`, `LiveSession::sources()`…).
- **Notifications** : `App\Support\NotificationText::fields($cle, $parametres, $payload)` enregistre le texte français dans `message` et la clé et ses paramètres dans `payload.i18n`. L'attribut `AppNotification::message` est rendu dans la langue courante ; `PushNotifications::dispatchFor()` calcule titre et texte avec `Locales::using(langue du destinataire)`. Les notifications créées avant cette version gardent leur texte français.
- **Site** : `<html lang>` suit la langue ; les scripts lisent leurs textes dans `window.I18N` (fichier `lang/*/js.php`), exposé par les gabarits.
- **Nombres** : `Locales::number()` (« 1 245 » / « 1,245 »).
- **Ponctuation** : guillemets `bible.quote_open` / `quote_close` (« … » / “…”), deux-points d'étiquette `layout.colon` (« Lieu : » / « Place: »), pourcentage `admin.formats.percent` (« 85 % » / « 85% »). Formats de date propres à une page dans sa clé (`prophecies.date_format`, `admin.formats.*`, `profile.date_format`…), toujours avec `translatedFormat()`.
- **Versets bibliques** : Louis Segond en français ; sur le site en anglais, King James Version (`config/encouragements.php` › `english`, voir [encouragements.md](encouragements.md)). Le verset du jour a sa propre règle ([verset-du-jour.md](verset-du-jour.md#langue-du-verset-2026-10-27)).

### Site entièrement traduit (2026-11-05)

Toutes les pages du site (accueil, explorer, fil, carnet, paroles prophétiques, verset du jour, Bible, requêtes et sessions de prière, communauté, profil, réglages, vérification, notifications, administration, composants partagés) passent par `__()` : plus aucun texte français écrit en dur dans les vues. Nouveaux fichiers `lang/*/prayer.php` et `lang/*/bible.php` ; compléments dans `admin`, `daily_verse`, `discover`, `journal`, `prophecies`, `profile`, `community`, `verification`, `notifications`, `models`, `layout`. Les messages de validation des prières et du téléphone viennent de `PrayerRequests::messages()` et `PhoneNumber::messages()` (anciennes constantes françaises supprimées). Les notifications de prière (encouragement reçu, salle ouverte, rappel de session) suivent la langue du destinataire (`NotificationText`).

Règle : un nouveau texte visible va dans `lang/fr` **et** `lang/en` ; jamais de phrase française dans une vue ou un message de contrôleur. `tests/Feature/EnglishSiteTest.php` ouvre les pages principales en anglais (membre, contenu, administration, visiteur) et échoue s'il y trouve un mot français courant.

Ajouter un texte : créer la clé dans `lang/fr/<fichier>.php` **et** `lang/en/<fichier>.php`, puis `__('fichier.cle', ['nom' => …])` (ou `trans_choice` pour un pluriel).

## Données

Migration `2026_10_15_200001_add_locale_conversion_and_push_device_fields` : `users.locale` (`varchar(5)`, défaut `fr`).

## Tests

`tests/Feature/DailyVerseLanguageTest.php` : verset du jour en français / anglais (API, historique, site), langue du navigateur pour un visiteur, choix du sélecteur et langue du compte prioritaires, langue d'un nouveau compte.

`tests/Feature/EnglishSiteTest.php` : pages du site en anglais sans texte français (texte visible et attributs `aria-label`, `title`, `placeholder`, `alt`).

`tests/Unit/TranslationKeysTest.php` (parité des clés et des paramètres) ; `tests/Feature/LocaleTest.php` : `Accept-Language` (défaut, poids, langue inconnue), erreurs 401/422 traduites, `PUT /me`, push et liste des notifications dans la langue du destinataire, sélecteur du site et langue du compte. `tests/TestCase.php` envoie les requêtes de test sans `Accept-Language` (Symfony ajouterait `en-us` par défaut), comme l'application.

## Limites connues

- Les sorties des commandes artisan et les journaux restent en français.
- Les valeurs saisies par les membres (titres, catégories créées par les administrateurs, motifs écrits à la main) ne sont pas traduites.
- Textes enregistrés en base au moment de l'action, donc en français : motif de masquage automatique (« Retirée après plusieurs signalements… », « Masqué par la modération. »), motif de suppression du journal d'administration, nom « (caméra) » du participant caméra d'un direct.
- L'image de partage du verset du jour (générée une fois, gardée en cache) garde les guillemets français.

## Fichiers

`app/Support/Locales.php`, `app/Support/NotificationText.php`, `app/Http/Middleware/SetLocale.php`, `app/Http/Controllers/Web/LocaleController.php`, `resources/views/components/locale-switcher.blade.php`, `lang/`, `tests/Unit/TranslationKeysTest.php`, `tests/Feature/LocaleTest.php`.
