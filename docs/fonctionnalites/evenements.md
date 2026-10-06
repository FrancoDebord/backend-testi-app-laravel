# Événements chrétiens

## But

La plateforme recueille tout ce qui peut être source de témoignage et de louange. Les **événements chrétiens** (croisades d'évangélisation, conférences, séminaires, camps, tournées, concerts de louange, retraites…) ont leur page :

- les fidèles la trouvent, répondent **« Je participe » / « Je ne participe pas »** et racontent ce qu'ils ont vécu en **commentaire** ;
- l'organisateur gère la page, **lance le direct** de l'événement et **enregistre les vrais témoignages** (commentaire promu, ou témoignage publié pour l'événement).

Disponible sur le site (`/evenements`) et dans l'application (menu latéral « Événements », bandeau « Événements à venir » de l'accueil, « Créer un événement » dans Publier).

## Règles

| Règle | Détail |
|---|---|
| **Qui crée** | **Organisations vérifiées** (`isVerified()`), leurs **gestionnaires** (au nom de l'organisation) et **administrateurs** (`Event::canBeCreatedBy`). Une organisation en attente de vérification ne peut pas. |
| **Gestionnaires d'une organisation** | **2 au plus** (`Event::MAX_ORGANIZATION_MANAGERS`, table `organization_managers`) : comptes personnels actifs désignés par l'organisation (ou un administrateur). Avec leur propre compte, ils créent des événements **au nom de l'organisation** (`organization_id`) et gèrent tous ses événements comme elle. Retirés par l'organisation, ou en se retirant eux-mêmes. |
| **Responsables d'un événement** | Organisateur, gestionnaires de l'organisation organisatrice, administrateurs (`Event::canBeAdministeredBy`) : tout ce que font les gestionnaires, plus **supprimer** l'événement et **désigner les co-gestionnaires**. |
| **Co-gestionnaires d'un événement** | **2 au plus** (`Event::MAX_MANAGERS`, table `event_managers`), en plus de l'organisateur : modifier, images, participants, promouvoir un commentaire, publier un témoignage rattaché, lancer le direct (`Event::canBeManagedBy`). Ne suppriment pas l'événement et ne désignent personne ; peuvent se retirer. |
| **Statuts** (`EventStatus`) | `draft` : visible des seuls gestionnaires (404 pour les autres) · `published` · `cancelled` : reste visible avec la mention « Annulé », plus de participation ni de direct. |
| **Moment** (`phase`) | Calculé : `upcoming` (à venir), `ongoing` (en cours), `past` (terminé, après `ends_at`). |
| **Types** (`EventType`) | `crusade` Croisade d'évangélisation · `conference` · `seminar` · `camp` · `tour` Tournée · `worship` Concert de louange · `retreat` Retraite spirituelle · `other`. |
| **Images** | Carrousel de **6 images** au plus (JPG/PNG/WebP, 8 Mo, 600 × 300 px minimum), disque `public`, dossier `event-images/`. La première sert de vignette (« Mettre en premier »). |
| **Invités principaux** | Facultatifs, **10** au plus : `{ name, role? }`. |
| **Participer** | Une réponse par personne (`going` / `not_going`), modifiable ou retirable, jusqu'à la fin de l'événement ; pas sur un événement annulé. Compteurs `going_count`, `not_going_count`. |
| **Commentaires** | Réservés aux comptes connectés et actifs ; 5 par minute ; 3 000 caractères. Fermés si `comments_enabled = false` (les gestionnaires peuvent toujours écrire). Suppression : l'auteur, les gestionnaires, la modération. |
| **Témoignage officiel** | (1) **Promouvoir un commentaire** : crée un témoignage texte **au nom de l'auteur du commentaire**, rattaché à l'événement (`testimonies.event_id`, `event_comments.testimony_id`) ; une seule fois par commentaire (409). (2) **Publier un témoignage** avec `event_id` (site : « Publier un témoignage » sur la page ; application : même bouton, qui rattache le témoignage en cours de publication). |
| **Modération** | Un témoignage officiel suit les règles habituelles : **publié directement** s'il est enregistré par un administrateur ou un modérateur, sinon **en attente** de relecture. En attente, il est visible des gestionnaires dans l'onglet Témoignages, pas du public. |
| **Direct** | L'organisateur (organisation vérifiée) peut lancer un direct **uniquement rattaché à son événement publié** (`live_sessions.event_id`), sans être modérateur. Sans événement, diffuser reste réservé aux modérateurs et administrateurs ([lives.md](lives.md)). La page de l'événement annonce le direct à l'antenne ; un direct en préparation n'est montré qu'aux gestionnaires. |

## Utilisation

### Organisateur

1. Site : **Événements → Créer un événement** ; application : **Publier → Créer un événement** (ou le bouton de la page Événements).
2. Titre, type, description, dates de début et de fin, lieu / adresse / ville / pays, **position sur la carte** (facultatif, voir [Lieu et itinéraire](#lieu-et-itinéraire)), invités, images, commentaires ouverts ou non, **Brouillon** ou **Publié**.
3. Sur la page de l'événement, bloc **Gestion** : Modifier · Lancer un direct · Publier un témoignage · Participants · Supprimer.
4. Onglet **Commentaires** : **Enregistrer comme témoignage** sur le commentaire d'un participant (titre et catégorie facultatifs).

### Fidèle

Page de l'événement : carrousel, dates, lieu (adresse, mini-carte, **Itinéraire**, **Copier l'adresse**), organisateur (coche « vérifiée »), invités, description, **Je participe / Je ne participe pas** (toucher de nouveau sa réponse la retire dans l'application), **Regarder le direct**, onglets **Témoignages** et **Commentaires**. Liste : À venir · J'y participe · Passés · Mes événements (organisateurs) ; filtre par type.

## Lieu et itinéraire

- **Saisie** (site et application) : lieu, adresse (rue, quartier, point de repère), ville, pays, puis bloc **Position sur la carte** : carte OpenStreetMap où l'on **touche pour placer le repère** (déplaçable sur le site), bouton **Ma position** (position de l'appareil ; en cas de refus, un message explique comment l'autoriser, ou de toucher la carte), **Chercher l'adresse** (géocodage Nominatim de « adresse, lieu, ville, pays », une requête par clic, jamais à la frappe) et **Effacer le repère**. Tout est facultatif ; latitude et longitude vont ensemble.
- **Consultation** : bloc « Lieu » (application) ou carte « Informations » (site) : adresse, mini-carte non interactive avec le repère (s'il existe), **Itinéraire** et **Copier l'adresse**. Sans repère, l'itinéraire part de l'adresse ; sans lieu ni adresse, le bloc est masqué.
- **Itinéraire** : site → lien `directionsUrl` (Google Maps, qui ouvre l'application sur mobile) ; application → Android `geo:lat,lng?q=lat,lng(Lieu)` (choix de l'application de navigation), iOS Plans (`maps.apple.com/?daddr=`), repli sur `directionsUrl` ; Flutter web → `directionsUrl`.
- **Cartes** : tuiles `https://tile.openstreetmap.org/{z}/{x}/{y}.png`, attribution « © contributeurs OpenStreetMap » toujours affichée ; application : `userAgentPackageName` = `com.airid.testi_app` ; site : Leaflet 1.9.4 depuis cdnjs (avec SRI), partiel `events/partials/leaflet.blade.php`. Nominatim : en-tête `User-Agent: TestiApp/1.0 (com.airid.testi_app)` dans l'application (le navigateur envoie le sien sur le web).
- **Permissions** : Android `ACCESS_FINE_LOCATION` / `ACCESS_COARSE_LOCATION` ; iOS `NSLocationWhenInUseUsageDescription` ; navigateur : autorisation de localisation du site (HTTPS requis).

## Données

| Table | Contenu |
|---|---|
| `events` | `organizer_id`, `title`, `description`, `type`, `status`, `starts_at`, `ends_at`, `location`, `address` (300), `city`, `country`, `latitude` / `longitude` (decimal 10,7, nullables), `guests` (JSON), `comments_enabled`, compteurs `going_count`, `not_going_count`, `comment_count` ; suppression douce |
| `event_images` | `event_id`, `url`, `position` |
| `event_participations` | `event_id`, `user_id` (unique), `status` |
| `event_comments` | `event_id`, `user_id`, `body`, `testimony_id` (promu) ; suppression douce |
| `testimonies.event_id` | Témoignage officiel de l'événement (`null` à la suppression de l'événement) |
| `live_sessions.event_id` | Direct de l'événement |

Supprimer un événement efface ses images, réponses et commentaires ; les témoignages déjà enregistrés restent publiés.

## API mobile (`/api/v1`)

| Méthode | Route | Accès | Rôle |
|---|---|---|---|
| GET | `events?scope=upcoming\|past\|going\|mine&type=&q=&page=` | public (`going`, `mine` : connecté) | Liste paginée ; `meta.canCreate`, `meta.types` |
| GET | `events/{id}` | public (brouillon : gestionnaires) | Détail (`EventResource`) |
| GET | `events/{id}/comments` · `events/{id}/testimonies` | public | Commentaires (récents d'abord) · témoignages officiels (`TestimonyResource`) |
| POST | `events` | organisation vérifiée, administrateur | Créer |
| PUT / DELETE | `events/{id}` | gestionnaires | Modifier (champs partiels) / supprimer |
| POST | `events/{id}/images` (multipart `image`) | gestionnaires | Ajouter une image |
| DELETE · POST | `events/{id}/images/{imageId}` · `…/{imageId}/cover` | gestionnaires | Retirer · mettre en premier |
| POST · DELETE | `events/{id}/participation` `{ status: going\|not_going }` | connecté | Répondre · retirer sa réponse |
| GET | `events/{id}/participants?status=not_going` | gestionnaires | Participants |
| POST · DELETE | `events/{id}/managers` `{ user_id }` · `…/managers/{userId}` | responsables (retrait : aussi soi-même) | Co-gestionnaires (2 au plus) |
| GET · POST · DELETE | `users/me/managers` `{ user_id }` · `users/me/managers/{userId}` | organisation | Gestionnaires de l'organisation (2 au plus) ; `{ max, managers[] }` |
| GET · DELETE | `users/me/managed-organizations` · `…/{organizationId}` | connecté | Organisations que je gère · ne plus gérer |
| POST · DELETE | `events/{id}/comments` `{ body }` · `…/comments/{commentId}` | connecté · auteur / gestionnaires | Commenter · supprimer |
| POST | `events/{id}/comments/{commentId}/promote` `{ title?, body_text?, category? }` | gestionnaires | Témoignage officiel (201, `TestimonyResource`) |
| POST | `lives` `{ …, event_id }` | gestionnaires de l'événement | Direct de l'événement |
| POST | `testimonies` `{ …, event_id }` | gestionnaires de l'événement | Témoignage rattaché (403 sinon) |

`GET events` renvoie aussi `meta.organizations` : au nom de qui la personne peut créer (elle-même, les organisations vérifiées qu'elle gère) ; `scope=mine` = événements gérés (organisateur, organisation gérée, co-gestionnaire). `POST events` accepte `organization_id`.

`POST` / `PUT events` acceptent `address` (≤ 300), `latitude` (-90 à 90) et `longitude` (-180 à 180), facultatifs mais **ensemble** (422 sinon : `errors.latitude` / `errors.longitude`) ; envoyer `latitude: null, longitude: null` efface le repère. Valeurs arrondies à 7 décimales.

`EventResource` : `id`, `title`, `description`, `type`, `typeLabel`, `status`, `statusLabel`, `phase`, `phaseLabel`, `startsAt`, `endsAt`, `location`, `city`, `country`, `address`, `latitude`, `longitude` (nombres ou `null`), `directionsUrl` (`https://www.google.com/maps/dir/?api=1&destination=lat,lng`, ou l'adresse encodée à défaut de repère, `null` sans lieu), `guests[]`, `commentsEnabled`, `images[{id,url}]`, `coverUrl`, `organizer{id,displayName,initials,avatarUrl,isOrganization,isVerified}`, `stats{going,notGoing,comments,testimonies}`, `myParticipation`, `acceptsParticipation`, `canManage`, `canAdministrate`, `managers[{id,displayName,initials,avatarUrl}]` (aux gestionnaires), `live{id,status,title}|null`, `webUrl`, `createdAt`. `TestimonyResource` gagne `eventId` ; `LiveSessionResource` gagne `event{id,title}`.

Erreurs : message lisible dans `message` ; 403 droits, 404 introuvable ou brouillon, 409 commentaire déjà promu, 422 validation / événement annulé ou terminé, 429 trop de commentaires.

## Site

| Nom | Méthode | Chemin |
|---|---|---|
| `events.index` | GET | `/evenements` (onglets, filtre par type, recherche) |
| `events.create` / `events.store` | GET / POST | `/evenements/creer`, `/evenements` |
| `events.show` | GET | `/evenements/{id}` |
| `events.edit` / `events.update` / `events.destroy` | GET / PUT / DELETE | `/evenements/{id}/modifier`, `/evenements/{id}` |
| `events.participants` | GET | `/evenements/{id}/participants` (`?statut=non` : ne participent pas) |
| `events.images.store` / `.destroy` / `.cover` | POST / DELETE / POST | `/evenements/{id}/images`, `…/images/{image}`, `…/images/{image}/premiere` |
| `events.participate` / `events.participation.cancel` | POST / DELETE | `/evenements/{id}/participation` |
| `events.comments.store` / `.destroy` / `.promote` | POST / DELETE / POST | `/evenements/{id}/commentaires`, `…/{comment}`, `…/{comment}/temoignage` |

- Menu latéral : **Événements** (après Directs), pour tous.
- Page d'un événement : bloc **Co-gestionnaires** (2 au plus ; « Retirer », « Me retirer », ajout par recherche de personne : `users.search` GET `/personnes/recherche`) ; `events.managers.store` POST et `events.managers.destroy` DELETE `/evenements/{id}/co-gestionnaires[/{user}]`. « Supprimer » réservé aux responsables.
- Formulaire : choix de l'**Organisateur** (soi-même / organisations gérées).
- Paramètres du profil : **Gestionnaires de l'organisation** (organisation) ou **Organisations que je gère** (« Ne plus gérer ») : `profile.managers.store` / `.destroy`, `profile.managed.leave`.
- `/lives/create?event={id}` : ouvert à l'organisateur de l'événement (sinon modérateurs et administrateurs) ; titre prérempli, champ caché `event_id`.
- `/publish?event={id}` : témoignage rattaché (gestionnaires).
- La page d'un direct et celle d'un témoignage rattachés renvoient à l'événement.
- Carrousel, invités et aperçu des images : `resources/js/app.js`. Carte du formulaire (repère, Ma position, recherche d'adresse) et de la page (mini-carte, copie de l'adresse) : scripts en ligne de `events/form.blade.php` et `events/show.blade.php`, Leaflet via `events/partials/leaflet.blade.php`.

## Fichiers

- Serveur : `app/Models/Event.php`, `EventImage.php`, `EventParticipation.php`, `EventComment.php` · `app/Enums/EventType.php`, `EventStatus.php` · `app/Services/EventService.php` (toutes les règles, partagé site / API), `EventActionException.php` · `app/Http/Controllers/Api/EventController.php`, `Web/EventController.php` · `app/Http/Resources/EventResource.php`, `EventCommentResource.php` · `resources/views/events/` · migrations `2026_10_05_100001_create_events_tables`, `2026_10_05_300001_add_geolocation_to_events_table` · tests `tests/Feature/EventTest.php`, `EventLocationTest.php`.
- Application : `lib/features/events/` (modèles, dépôt, fournisseurs, écrans liste / détail / formulaire, carrousel), routes `/events`, `/events/new`, `/events/:id`, `/events/:id/edit`, `/lives/new?event=` ; `publishEventProvider` (rattachement du témoignage publié) ; lieu et cartes : `widgets/event_location_widgets.dart` (`EventMapPicker`, `EventPlaceCard`, `EventDirections`, `EventLocationService`) ; paquets `flutter_map`, `latlong2`, `geolocator` ; tests `test/events_test.dart`, `test/event_location_test.dart`.

## Limites connues

- Création puis envoi des images : non atomique (un envoi qui échoue laisse l'événement sans cette image ; un message l'indique).
- Pas encore de notification (abonnés de l'organisateur à la publication, participants au démarrage du direct, auteur d'un commentaire promu).
- Pas de type « en ligne » : un événement sans lieu ni repère n'affiche simplement pas de bloc Lieu.
- Recherche d'adresse : premier résultat Nominatim seulement (à ajuster en déplaçant le repère) ; couverture inégale des adresses en Afrique de l'Ouest.
- Les gestionnaires et co-gestionnaires sont choisis parmi les comptes personnels (recherche par nom, ou adresse e-mail complète sur le site).
