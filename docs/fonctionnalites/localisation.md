# Localisation et fils classés par zone

## But

Sur l'accueil (« Pour vous ») et dans **Mon fil**, montrer **d'abord ce qui est proche** de la personne (village ou quartier, ville, région, pays, continent), puis ce qui correspond à ses **goûts** — sans jamais rien retirer : les fils sont **réordonnés**, pas filtrés. Voir aussi [recommandations.md](recommandations.md).

## Lieu de la personne

Par ordre de préférence :

| Source (`source`) | Quand | Précision |
|---|---|---|
| `gps` | Réglage **« Utiliser ma position pour personnaliser mes fils »** activé (consentement explicite) | Coordonnées arrondies à 2 décimales (≈ 1 km), village/quartier, ville, région, pays, continent |
| `profile` | « Ma ville » (réglage de localisation) ou ville de l'organisation, et pays du profil (`users.country`) | Ville et pays |
| `phone` | Indicatif du téléphone de contact (`users.phone_country`) | Pays |
| `language` | Langue de l'appareil ou du navigateur (`Accept-Language: fr-BJ`), aussi pour les visiteurs ; à défaut `RECO_GUEST_COUNTRY` | Pays |
| aucune | — | Classement d'avant (goûts, fraîcheur, popularité) |

Le continent est déduit du pays (`App\Support\Continents`, table pays → continent dans le code).

### Géocodage inverse (décision)

Fait **côté serveur** (`Locations::reverseGeocode`), pour que le site (navigator.geolocation ne donne que des coordonnées) et l'application aient exactement le même découpage : Nominatim (OpenStreetMap), **sur les coordonnées déjà arrondies**, `User-Agent` identifiable (`GEOCODER_USER_AGENT`), **une requête par seconde au plus** pour tout le serveur, résultat **mis en cache 30 jours** par point arrondi, `accept-language=fr`. Si le géocodeur est indisponible (ou `GEOCODER_ENABLED=false`), les noms envoyés par l'appareil (`city`, `region`, `country_code`) sont utilisés, sinon le pays du profil complète les coordonnées : la distance reste utilisable. Aucune autre donnée n'est envoyée au géocodeur.

### Réglage

| Où | Ce qui est proposé |
|---|---|
| Application | Paramètres › **Localisation** (`/settings/location`) : interrupteur (l'activer demande la permission puis envoie la position), **« Mettre à jour ma position »**, « Ma ville (sans position) », **« Supprimer ma position »**. Position consentie de plus de 3 jours : rafraîchie en silence à l'ouverture du fil, seulement si la permission est déjà accordée (jamais de demande) |
| Site | Paramètres du compte › **Localisation** (`/profile/settings#localisation`) : même réglage ; la position n'est demandée au navigateur (HTTPS) qu'après un clic |

API (connecté, jamais publique) :

```
GET    /api/v1/users/me/location
PUT    /api/v1/users/me/location   { use_gps?, latitude?, longitude?, locality?, city?, region?, country_code?, home_city? }
DELETE /api/v1/users/me/location
→ { useGps, source, label, precision, locality, city, region, countryCode, country, continent, latitude, longitude, homeCity, updatedAt }
```

Envoyer `latitude`/`longitude` vaut consentement (réglage activé dans l'application ou sur le site). `use_gps: false` efface tout de suite les coordonnées et les noms obtenus par GPS. Site : `PUT/DELETE /profile/localisation`.

## Données stockées

| Table | Contenu |
|---|---|
| `user_locations` (une ligne par personne) | `use_gps`, `latitude`/`longitude` (DECIMAL 5,2), `locality`, `city`, `region`, `country_code`, `continent`, `source`, `home_city`, `location_updated_at` |
| `content_locations` | copie grossière du lieu d'un contenu : `subject_type` (`testimony`, `prayer_request`, `prayer_session`), `subject_id`, `user_id` (auteur), mêmes colonnes ; index `(subject_type, country_code, city)`, `(subject_type, country_code, region)`, `(subject_type, continent)` |

- **Témoignage, requête de prière, session de prière** : à la création, copie du lieu actuel de l'auteur (`LocationServiceProvider`) ; session ou requête **liée à un événement** : lieu de l'événement. Sans copie, le lieu actuel de l'auteur est utilisé au classement.
- **Événement** : ses propres colonnes (`latitude`, `longitude`, `city`, `country`).
- **Contenus existants** : `php artisan locations:backfill` (idempotent ; `--refresh` recalcule ce qui n'est pas issu du GPS).

## Vie privée

- **Jamais exposé publiquement** : ni `UserResource`, ni `TestimonyResource`, ni les pages publiques ne contiennent coordonnées, quartier ou ville saisie. Seule la personne lit son réglage.
- Dans les fils, `meta.place` (zone de la personne elle-même) et `meta.nearby` : étiquette **« À Cotonou »** (même ville ou plus près) ou **« Au Bénin »** (même région ou même pays), seulement pour la **zone commune** avec la personne qui regarde — jamais plus précis que la ville.
- **Effacement immédiat** : « Supprimer ma position » supprime la ligne `user_locations` et les copies **GPS** sur ses contenus ; désactiver le réglage efface les coordonnées ; la suppression du compte (même douce) efface tout.
- Pas d'adresse IP utilisée.

## Classement

Voir [recommandations.md](recommandations.md#zones-puis-goûts) : niveaux de zone, formule et poids (`config/recommendations.php`).

## Fichiers

Serveur : `App\Services\Locations`, `App\Support\Place`, `App\Support\Continents`, `Countries::inPhrase()`, `App\Models\UserLocation`, `App\Models\ContentLocation`, `App\Providers\LocationServiceProvider`, `Api\LocationController`, `Web\LocationController`, `profile/partials/location.blade.php`, `feed/partials/place.blade.php`, commande `locations:backfill`, migration `2026_10_07_200001_create_user_and_content_locations_tables`. Application : `lib/features/location/` (`user_location.dart`, `location_providers.dart`, `nearby_widgets.dart`, `location_settings_screen.dart`). Tests : `tests/Feature/LocationFeedTest.php`, application `test/location_feed_test.dart`.

## Limites connues

- Sans GPS, la précision s'arrête à la ville saisie et au pays du profil (pas de région).
- Le géocodage dépend de Nominatim (service gratuit à usage modéré) ; pour un fort trafic, héberger son propre Nominatim (`GEOCODER_URL`).
- Les étiquettes « À Cotonou » des cartes ne sont affichées que dans l'application ; le site affiche la mention en tête de Mon fil.
- L'accueil du site garde « Témoignages récents » dans l'ordre chronologique ; seules les requêtes de prière qui y sont insérées sont classées par zone.
