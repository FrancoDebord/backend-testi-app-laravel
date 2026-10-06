# Journal des modifications

Entrées les plus récentes en premier. Chaque entrée indique ce qui change pour les utilisateurs, les développeurs et le déploiement.

---

## 2026-10-05 — Directs, événements et requêtes de prière dans les fils ; thème retiré

- **Nouveau — Fils enrichis** : directs à l'antenne en tête de l'accueil (application) et de « Mon fil » (site et application) ; requêtes de prière publiques insérées dans « Pour vous » / « Témoignages récents » ; dans « Mon fil », événements à venir et requêtes de prière des comptes suivis, en alternance. [recommandations.md](fonctionnalites/recommandations.md#directs-événements-et-requêtes-de-prière-dans-les-fils)
- **API** : `GET /events?scope=following` (à venir, organisés par les comptes suivis ; vide pour un invité) ; `Event::scopeFollowedBy()`.
- **Retiré — Thème Clair / Sombre / Système** (paramètres du site et de l'application) : l'application n'a pas de palette sombre, le réglage n'avait aucun effet. La colonne `user_settings.app_theme` et sa validation API restent, inutilisées.
- **Application** : message clair (« pas encore disponible sur le serveur ») au lieu de « The route … could not be found » quand le serveur n'a pas encore une route de l'API.
- Vérifié : `php artisan test tests/Feature/FeedInsertsTest.php` (5 tests) ; `php artisan view:cache` ; application : `flutter test test/feed_mix_test.dart test/home_layout_test.dart` (36 tests).
- **Déploiement** : aucune migration propre à cette entrée ; `php artisan view:cache` ; `php artisan route:cache` si les routes sont en cache. Application : nouvelle version.

## 2026-10-05 — Requêtes et sessions de prière

- **Nouveau — Requêtes de prière** (site `/priere/requetes`, application, menus latéraux) : publication **directe** (sans relecture), visibilité tout le monde / mes abonnés / moi seul, option **anonyme** (nom masqué pour les autres, visible de la modération), **« Je prie »**, messages d'**encouragement** (verset facultatif, l'auteur est prévenu), **exaucée** puis « Témoigner », rattachement à un **événement** (bloc « Prière » de sa page). **Signalement** ; retrait automatique au 3e signalement en attendant la modération ; page **Modération → Requêtes de prière signalées** (`/priere/moderation`). [requetes-de-priere.md](fonctionnalites/requetes-de-priere.md)
- **Nouveau — Sessions de prière** (site `/priere/sessions`, application) : tout compte connecté programme une session (sujets de prière, date, durée, visibilité, événement facultatif pour ses gestionnaires) ; « **Je serai là** » ; rappel 15 minutes avant et notification à l'ouverture. À l'heure, l'hôte **ouvre la salle** : un **direct** (studio, commentaires, intervenants à tour de rôle), sans enregistrement. [sessions-de-priere.md](fonctionnalites/sessions-de-priere.md)
- **Développeurs** : `LiveService::start()` accepte `prayer_session_id` (envoyé seulement par `App\Services\PrayerSessions`, l'hôte de la session peut ouvrir sa salle sans être modérateur ; directs classiques inchangés) ; `LiveSession::isVisibleTo()` suit la visibilité de la session ; `LiveSessionResource.prayerSession`. Notifications `prayer_encouragement`, `prayer_session_started`, `prayer_session_reminder` (`NotificationResource.prayerRequestId` / `prayerSessionId`, données push `prayer_request_id` / `prayer_session_id`). API pour les fils : `GET /prayer/requests?scope=feed|following|event`, `PrayerRequestResource` autonome ; partiel Blade `prayer.partials.feed-card` ; application `PrayerRequestFeedCard`, `prayerRequestsPageProvider`.
- Vérifié : `php artisan test --filter=PrayerTest` (16 tests) et tests des directs, événements et notifications ; `php artisan view:cache` ; application : `flutter analyze` (fichiers touchés), `flutter test test/prayer_test.dart` (38 tests) et tests invité / notifications / événements.
- **Déploiement** : `php artisan migrate --force` (`2026_10_05_400001_create_prayer_tables`) ; planificateur actif (`prayer-sessions:remind` toutes les 5 minutes) ; recompiler et copier `public/build/` (nouvelles classes Tailwind des pages de prière) ; `php artisan view:cache` ; `php artisan route:cache` si les routes sont en cache. Application : nouvelle version.

## 2026-10-05 — Formulaire de connexion à une caméra IP (directs)

- **Nouveau — Formulaire de la caméra IP** (mode « Caméra IP (adresse du flux) » de *Lancer un direct*, site et application) : **modèle de caméra** (Hikvision, Dahua, Amcrest, Reolink, Axis, TP-Link Tapo, Foscam, ONVIF générique, Autre) qui pré-remplit port et chemin, flux principal / secondaire, protocole (RTSP, RTSPS, RTMP, RTMPS, HTTP/HTTPS (HLS), SRT) avec port par défaut, adresse IP ou nom d'hôte, port, chemin, identifiant, mot de passe masqué (bouton œil), Stream ID et phrase secrète pour SRT. Aperçu de l'adresse composée avec le mot de passe masqué ; avertissement si l'adresse est locale (192.168.x.x, 10.x…) ; mode **Adresse complète** pour coller une adresse (revenir au formulaire le remplit). Le dernier réglage est mémorisé sur l'appareil / dans le navigateur, **sans mot de passe**. [lives-camera-ip.md](fonctionnalites/lives-camera-ip.md#formulaire-de-la-caméra-ip)
- **API inchangée** : l'adresse est composée par l'application ou le navigateur (identifiants encodés : `@` → `%40`…) et envoyée dans `camera_url`. **Modifié** : `camera.sourceUrl` (diffuseur seulement) est renvoyé avec le mot de passe et la phrase secrète SRT masqués (`••••`).
- **Sécurité** : `camera_url` n'est plus remise en session après une erreur (site) ; mot de passe et phrase secrète du formulaire n'ont pas d'attribut `name`.
- **Développeurs** : application `lib/features/live/models/ip_camera_config.dart` (`IpCameraConfig`, `IpCameraPreset`, `IpCameraProtocol`), `lib/features/live/widgets/ip_camera_form.dart` ; site : `lives/create.blade.php` (script en ligne, même logique) ; `LiveSessionResource::maskCameraUrl()` ; `bootstrap/app.php` (`dontFlash`).
- Vérifié : `php artisan test tests/Feature/LiveCameraTest.php` (1 nouveau test) ; `php artisan view:cache` ; application : `flutter analyze` (fichiers touchés), `flutter test test/ip_camera_test.dart` (18 tests).
- **Déploiement** : aucune migration ; `php artisan view:cache` ; recompiler `public/build/` seulement pour les nouvelles classes Tailwind de la page (le script est en ligne). Application : nouvelle version.

## 2026-10-05 — Lieu des événements : carte et itinéraire

- **Nouveau — Position sur la carte** (création / modification d'un événement, site et application) : champ **Adresse**, carte OpenStreetMap où l'on touche pour placer le repère, boutons **Ma position**, **Chercher l'adresse** (Nominatim, sur clic) et **Effacer le repère**. Facultatif. [evenements.md](fonctionnalites/evenements.md#lieu-et-itinéraire)
- **Nouveau — Itinéraire** : sur la page d'un événement, adresse, mini-carte avec repère, boutons **Itinéraire** (application de navigation du téléphone : `geo:` sur Android, Plans sur iOS, Google Maps sinon) et **Copier l'adresse**.
- **API** : `POST`/`PUT events` acceptent `address`, `latitude`, `longitude` (ensemble, bornes vérifiées) ; `EventResource` gagne `address`, `latitude`, `longitude`, `directionsUrl`.
- **Développeurs** : `Event::hasCoordinates()`, `fullAddress()`, `directionsUrl()` ; partiel `events/partials/leaflet.blade.php` (Leaflet 1.9.4, cdnjs + SRI). Application : `widgets/event_location_widgets.dart` ; paquets `flutter_map` 8.3, `latlong2` 0.9, `geolocator` 14.1 ; permissions Android `ACCESS_FINE_LOCATION` / `ACCESS_COARSE_LOCATION`, iOS `NSLocationWhenInUseUsageDescription`, requête `geo:` déclarée dans `<queries>`.
- Vérifié : `php artisan test tests/Feature/EventLocationTest.php tests/Feature/EventTest.php` (3 nouveaux tests) ; `php artisan view:cache` ; application : `flutter analyze` (fichiers touchés), `flutter test test/event_location_test.dart` (10 tests).
- **Déploiement** : `php artisan migrate --force` (`2026_10_05_300001_add_geolocation_to_events_table`) ; `php artisan view:cache`. Pas de recompilation des assets (Leaflet par CDN). Application : nouvelle version (`flutter pub get`, nouvelles permissions de localisation).

## 2026-10-05 — Mes abonnés

- **Nouveau — Mes abonnés** (comptes qui me suivent) : site `/profile/abonnes` (nombre d'« abonnés » de son profil, onglets Abonnés / Abonnements en haut de Mes abonnements) ; application `/followers` (statistique « Abonnés » de Mon profil, menu latéral, menu du profil), écran commun à deux onglets avec Mes abonnements. Recherche, pagination, liste vide, bouton **Suivre en retour** pour les abonnés que l'on ne suit pas. [abonnements.md](fonctionnalites/abonnements.md)
- **API** : `GET /api/v1/users/me/followers?q=&page=` (connecté) — même forme que `users/me/following` ; `is_following` = je le suis en retour. Pas de liste publique des abonnés d'un autre compte (réglage « Compte privé »).
- **Développeurs** : `CommunityDirectory::followers()`, `UserController::followers`, `Web\CommunityController::followers`, vues `community/followers`, `community/partials/follow-tabs` ; `components/follow-button` accepte `idleLabel`. Application : `FollowsScreen` / `FollowsTab` (`following_screen.dart`), `CommunityRepository.followers()`, `FollowButton.idleLabel`.
- Vérifié : `php artisan test --filter="FollowingListTest|FollowTest"` (2 nouveaux tests) ; `php artisan view:cache` ; application : `flutter analyze` (fichiers touchés), `flutter test test/followers_test.dart`.
- **Déploiement** : aucune migration ; `php artisan route:clear` / `optimize:clear` puis `php artisan view:cache`. Application : nouvelle version.

## 2026-10-05 — « Pourquoi témoigner ? » et pied de page ARISE & SHINE Krea

- **Nouveau — Pourquoi témoigner ?** : bloc en accordéon (on ouvre une raison pour lire ses versets) avec 7 raisons bibliques et 17 versets (Louis Segond 1910), sur l'accueil (colonne de droite), Publier, Mes témoignages, la lecture d'un témoignage (avant les commentaires) et l'inscription ; replié sur une ligne sur les pages chargées. Adapté au mobile (390 px) et aux colonnes étroites. [pourquoi-temoigner.md](fonctionnalites/pourquoi-temoigner.md)
- **Modifié — Pied de page** (site connecté et pages sans connexion) : « © … TestiApp — ARISE & SHINE Krea » remplace la mention AIRID.
- **Développeurs** : composant `<x-why-testify />` ; contenu `config/encouragements.php` (`why_testify`) ; classes `accordion-*` et `why-testify-*` dans `resources/css/app.css`.
- Vérifié : `php artisan view:cache` ; `php artisan test` (seul `ExampleTest` échoue comme avant) ; assets recompilés.
- **Déploiement** : aucune migration ; copier `public/build/` (archive `build-a-deployer.zip`) ; `php artisan view:cache` ; `php artisan config:cache` si la configuration est mise en cache.

## 2026-10-05 — Carnet privé et paroles prophétiques sur le site

- **Nouveau — Carnet privé sur le site** (`/carnet`, menu latéral « Carnet privé ») : liste de mes témoignages privés avec recherche et filtre Texte / Audio / Vidéo ; « Nouvelle entrée » ouvre Publier avec « Privé » coché. Sur la page d'une entrée : **Partager** (catégorie, puis relecture par la modération) et **Supprimer**. Sur un de mes témoignages publics : **Ranger dans mon carnet**. [carnet-prive.md](fonctionnalites/carnet-prive.md#site-web)
- **Nouveau — Paroles prophétiques sur le site** (`/carnet/paroles`, onglet du carnet et menu latéral) : en attente / accomplies, garder une parole (texte ou fichier audio, date, qui l'a donnée, échéance, rappel), journal « J'ai prié », **Proclamer** en plein écran (Habakuk 2:3), marquer accomplie ou remettre en attente, **Témoigner** (Publier avec « Publier aussi la parole prophétique »). Les rappels réglés sur le site sont envoyés par l'application sur le téléphone. [paroles-prophetiques.md](fonctionnalites/paroles-prophetiques.md#site)
- **Développeurs** : règles des paroles regroupées dans `App\Services\Prophecies` (API et site) ; le site utilise `App\Services\Journal`. Changement d'API : `PUT /prophecies/{id}` refuse aussi (422, `errors.due_on`) une échéance antérieure à la date de la parole.
- Vérifié : `php artisan test` (8 nouveaux tests `WebJournalTest` ; seul `ExampleTest` échoue comme avant) ; `php artisan view:cache` ; assets recompilés.
- **Déploiement** : aucune migration ; copier `public/build/` (archive `build-a-deployer.zip`) ; `php artisan view:cache` ; `php artisan config:cache` si la configuration est mise en cache (`config/encouragements.php` : `prophecy_verses`).

## 2026-10-05 — Gestionnaires, paroles prophétiques, Mon fil, messages pour témoigner

- **Gestionnaires** : une organisation désigne **2 gestionnaires au plus** qui créent et gèrent ses événements en son nom ; chaque événement accepte **2 co-gestionnaires au plus** (modifier, témoignages, direct ; seuls l'organisateur, ses gestionnaires et les administrateurs suppriment et désignent). Site : bloc Co-gestionnaires, choix de l'organisateur, réglages du profil ; application : feuille Co-gestionnaires, écran « Gestionnaires de l'organisation » / « Organisations que je gère ». [evenements.md](fonctionnalites/evenements.md)
- **Nouveau — Paroles prophétiques** (application, carnet privé) : garder une parole reçue (date du jour par défaut, texte ou audio, qui l'a donnée, échéance, titre), journal « J'ai prié », rappels quotidiens ou hebdomadaires sur le téléphone, lecture en plein écran pour la proclamer ; à l'accomplissement, témoigner (parcours Publier rattaché) et rendre la parole publique avec le témoignage. API `prophecies`. [paroles-prophetiques.md](fonctionnalites/paroles-prophetiques.md)
- **Nouveau — Mon fil** (`GET /feed`, site `/mon-fil`, menu latéral) : témoignages des comptes suivis, avec une suggestion de compte inconnu tous les trois. [recommandations.md](fonctionnalites/recommandations.md#mon-fil-get-apiv1feedpagelimit-connecté--site-mon-fil)
- **Nouveau — Messages pour inciter à témoigner** : versets (Psaume 78:4, Apocalypse 12:11, Marc 5:19…) et invitations avec bouton Témoigner, un tous les 8 témoignages dans les fils et en bandeau sur quelques pages. [encouragements.md](fonctionnalites/encouragements.md)
- **Application** : l'onglet **Événements** remplace Téléchargements dans la barre du bas (Téléchargements : menu latéral, profil, paramètres).
- Vérifié : `php artisan test` (12 nouveaux tests : gestionnaires, paroles, Mon fil ; seul `ExampleTest` échoue comme avant) ; `php artisan view:cache` ; application : `flutter analyze`, `flutter test`.
- **Déploiement** : `php artisan migrate --force` (`2026_10_05_200001_create_managers_and_prophecies_tables`) ; copier `public/build/` ; `php artisan view:cache`. Application : nouvelle version (rappels programmés : nouvelle permission Android `RECEIVE_BOOT_COMPLETED`).

## 2026-10-05 — Événements chrétiens

- **Nouveau** : pages **Événements** (croisades d'évangélisation, conférences, séminaires, camps, tournées, concerts de louange, retraites) sur le site (`/evenements`, menu latéral) et dans l'application (menu latéral, bandeau « Événements à venir » de l'accueil, « Créer un événement » dans Publier). [evenements.md](fonctionnalites/evenements.md)
- Création réservée aux **organisations vérifiées** et aux **administrateurs** : titre, type, description, dates, lieu, invités principaux, jusqu'à 6 images en **carrousel**, brouillon / publié / annulé, commentaires ouverts ou non.
- Les fidèles répondent **« Je participe » / « Je ne participe pas »** et racontent ce qu'ils ont vécu en **commentaire**. L'organisateur voit la liste des participants.
- **Témoignages officiels** : l'organisateur **enregistre un commentaire comme témoignage** (au nom de son auteur) ou **publie un témoignage** rattaché à l'événement. Publication directe par un administrateur ou un modérateur, sinon relecture par la modération.
- **Direct de l'événement** : l'organisateur peut diffuser son événement publié sans être modérateur (`POST /lives` avec `event_id` ; `/lives/create?event=` sur le site). Les autres directs restent réservés à la modération. La page annonce le direct à l'antenne.
- API : `events` (liste, détail, création, modification, images, participation, participants, commentaires, promotion, témoignages) ; `TestimonyResource.eventId`, `LiveSessionResource.event`, `event_id` accepté par `POST /testimonies` et `POST /lives`.
- Vérifié : `php artisan test` (15 nouveaux tests `EventTest`, seul `ExampleTest` échoue comme avant) ; `php artisan view:cache` ; application : `flutter analyze`, `flutter test` (23 nouveaux tests : modèle, dates, mise en page à 320 et 390 px, texte ×1 et ×1,3).
- **Déploiement** : `php artisan migrate --force` (`2026_10_05_100001_create_events_tables`) ; copier `public/build/` ; `php artisan view:cache`. Application : nouvelle version.

## 2026-09-30 — Lecture à voix haute en anglais

- Les témoignages écrits en anglais sont lus avec une voix anglaise. La langue est déduite du texte (mots les plus fréquents) ; quelques mots anglais dans un texte français ne changent pas la voix. [lecture-vocale.md](fonctionnalites/lecture-vocale.md#langue--français-ou-anglais)
- **Site** : menu « Français / English » dans le bandeau d'écoute pour corriger la langue ; voix proposées selon la langue et retenues pour chacune.
- **Application** : la voix suit la langue du témoignage (`detectTextLanguage`), et non plus seulement celle de l'application (reprise quand le texte est trop court pour décider).
- Vérifié : `php artisan test` ; test dans Chrome avec un moteur simulé (détection, passage en anglais, voix retenue) ; application : `flutter analyze` et `flutter test` (4 nouveaux tests).
- **Déploiement** : copier `public/build/` ; `php artisan view:cache`. Application : nouvelle version.

## 2026-09-30 — Lecture à voix haute des témoignages écrits (site)

- **Nouveau sur le site** : bandeau « Écouter le témoignage » sur la page d'un témoignage texte. La synthèse vocale du navigateur lit le titre, le texte puis le verset, phrase par phrase : pause et reprise, arrêt, vitesse de 0,75× à 2×, choix de la voix française, phrase en cours et progression. Vitesse et voix sont retenues ; emojis et marques `**` ne sont pas lus. Aucun traitement ni coût côté serveur. L'application mobile avait déjà cette lecture (avec « Lecture automatique ») : elle est maintenant documentée. [lecture-vocale.md](fonctionnalites/lecture-vocale.md)
- Vérifié : `php artisan test` ; test dans Chrome avec un moteur de synthèse simulé (ordre, pause, reprise à une autre vitesse, fin) ; rendu à 390 et 1440 px.
- **Déploiement** : copier `public/build/` ; `php artisan view:cache`. Aucune migration.

## 2026-09-30 — Connexion, inscription et mot de passe oublié selon la charte (site)

- Pages sans connexion (`layouts/guest`) en deux colonnes dès 1024 px : panneau de marque Blue Light (pastille jaune ARISE & SHINE Krea, « Dieu agit encore. », trois points forts) terminé par le paysage vagues et soleil ; formulaire à droite. En dessous de 1024 px, un bandeau illustré remplace le panneau au-dessus du formulaire. Paysage réutilisable : `layouts/partials/krea-landscape` (`$class`, `$viewBox`). [interface.md](interface.md#4-créer-une-page)
- Connexion : titre `text-h3`, liens en Krea Blue, bouton « Se connecter » avec flèche, bouton pour afficher le mot de passe. Inscription et mot de passe oublié : mêmes titres et liens.
- `data-reveal` accepte `data-reveal-label` (« le mot de passe ») pour le libellé du bouton ; « la clé » par défaut.
- Vérifié : `php artisan test` ; contrôle dans Chrome à 390, 768 et 1440 px, sans défilement horizontal.
- **Déploiement** : copier `public/build/` ; `php artisan view:cache`. Aucune migration.

## 2026-09-30 — Bas du menu latéral selon la maquette (site)

- Bas du menu latéral : citation « Gloire à Dieu pour chaque vie transformée ! » en écriture manuscrite avec un soleil, et paysage de vagues jaune et bleu avec un soleil (SVG aux couleurs de la charte). Masqué sur les écrans de moins de 860 px de haut. Nouvelle classe `font-script` (police Caveat, chargée sur toutes les pages ; aussi utilisée par le bandeau de l'accueil). [interface.md](interface.md#logo)
- Vérifié : contrôle dans Chrome (1440 × 1000 : visible ; 1440 × 800 : masqué, menu défilable), sans erreur JavaScript.
- **Déploiement** : copier `public/build/` ; `php artisan view:cache`.

## 2026-09-30 — Cartes de témoignages selon la maquette (site)

- **Toutes les listes du site** (accueil, Explorer, Vidéos, profil, sauvegardes, mes témoignages, « Afficher plus ») affichent les témoignages avec la carte de la maquette : carte encadrée, miniature (type en haut à droite, durée en bas à droite, statut pour ses propres témoignages), **catégorie en pastille de couleur posée sur le bord de l'image**, titre bleu gras, extrait, auteur · vues · date, puis J'aime / commentaires / partages. La liste compacte reste disponible. [affichage-et-lecture.md](fonctionnalites/affichage-et-lecture.md)
- Catégorie retrouvée par son nom court pour les témoignages sans `category_id` (`Category::forSlug()`).
- Vérifié : tests du serveur ; contrôle dans Chrome à 390 et 1440 px (accueil, Explorer, Vidéos, mes témoignages), sans défilement horizontal ni erreur JavaScript.
- **Déploiement** : copier `public/build/` ; `php artisan view:cache`. Aucune migration.

## 2026-09-30 — Preuves publiées avec l'accord de l'auteur

- **Nouveau** : à la publication, case « **Publier aussi mes preuves pour confirmer le témoignage** » (site et application). Avec cet accord, une fois le témoignage publié, les preuves sont visibles et téléchargeables par tout le monde (page du témoignage, API) ; sans accord, elles restent réservées à l'auteur et à l'équipe de modération. L'équipe peut retirer l'affichage public (« Ne pas publier les preuves ») ; seul l'auteur peut de nouveau accepter. [preuves.md](fonctionnalites/preuves.md)
- Vérifié : tests du serveur (2 nouveaux, 1 mis à jour) et de l'application (1 nouveau).
- **Déploiement** : `php artisan migrate --force` (`2026_09_30_200001_add_proofs_public_to_testimonies_table`) ; copier `public/build/` ; `php artisan view:cache`. Application : nouvelle version.

## 2026-09-30 — Lien YouTube visible dès le choix du type

- **Correction** : le champ YouTube n'apparaissait qu'après avoir choisi « Vidéo », sous le fichier vidéo, et dans l'application seulement dans l'étape vidéo : on ne le trouvait pas. Site : 4ᵉ type **Lien YouTube** (administrateurs) à côté de Texte, Audio, Vidéo, avec champ obligatoire et fichier vidéo masqué. Application : carte **Lien YouTube** sur l'écran de choix du format (administrateurs). [videos-youtube.md](fonctionnalites/videos-youtube.md)
- Vérifié : tests du serveur (1 nouveau) et de l'application ; contrôle dans Chrome à 390 et 1440 px.
- **Déploiement** : copier `public/build/` ; `php artisan view:cache`. Application : nouvelle version.

## 2026-09-30 — Caméra IP pour les directs, fil « Pour vous », recommandations, YouTube, preuves

- **Nouveau — Direct avec une caméra IP ou un encodeur** : à la création d'un direct, choix « Caméra de cet appareil », « Caméra IP ou encodeur (RTMP) » ou « Adresse du flux de la caméra ». LiveKit Ingress crée un point d'entrée ; le studio affiche l'adresse et la clé (copier, afficher), l'aperçu du flux, puis « Passer à l'antenne » (jamais de démarrage automatique). Clé et adresse chiffrées, visibles du seul diffuseur ; point d'entrée supprimé à la fin. Mode d'emploi pour les caméras RTSP (OBS, ffmpeg). [lives-camera-ip.md](fonctionnalites/lives-camera-ip.md)
- **Nouveau — Fil « Pour vous »** (`GET /api/v1/testimonies?sort=for_you`) : un nouveau venu ne reçoit plus toute la liste mais **les plus récents et les plus vus** en alternance ; une personne connue reçoit un classement selon ses centres d'intérêt et les comptes qu'elle suit. L'application le charge 20 par 20. [recommandations.md](fonctionnalites/recommandations.md)
- **Nouveau — Recommandations automatiques** : « À regarder également » (site) et « Témoignages similaires » (application, `GET /api/v1/testimonies/{id}/recommendations`) selon le témoignage en cours (catégorie, auteur, type, mots-clés), les centres d'intérêt (sauvegardes, réactions, commentaires, lectures, publications), les comptes suivis ; déjà vus repoussés. Nouvel historique de lecture `testimony_views`.
- **Nouveau — Témoignage par lien YouTube** (administrateurs) : champ « Ou lien d'une vidéo YouTube » (site et application), lecture avec le lecteur YouTube (`youtube-nocookie` sur le site), miniature YouTube par défaut, champs `youtubeId` / `youtubeUrl` de l'API. [videos-youtube.md](fonctionnalites/videos-youtube.md)
- **Nouveau — Preuves du témoignage** : deux images ou PDF (10 Mo chacun), jamais publiés, visibles de l'auteur et de l'équipe de modération (page du témoignage, fiche de relecture) ; stockage privé ; API d'ajout, de lecture et de retrait. [preuves.md](fonctionnalites/preuves.md)
- **Application mobile** : fil « Pour vous » chargé page par page (20), recommandations dans « Témoignages similaires » et pour la vidéo suivante, lecteur YouTube (`youtube_player_iframe` 6.0.2) et lien YouTube à la publication (administrateurs), deux preuves à la publication et carte « Preuves du témoignage » (détail, modération), choix de la caméra au lancement d'un direct et panneau « Caméra IP » dans le studio (aperçu du flux, adresse et clé).
- Lecture d'un témoignage par l'API : l'équipe de modération lit aussi les témoignages réservés aux abonnés (jamais le carnet privé) ; seules les lectures d'un témoignage publié comptent comme des vues (la relecture n'en ajoute plus).
- Limites connues de l'application : preuves envoyées seulement avec une publication en ligne ; pas d'enchaînement automatique après une vidéo YouTube ; pas de bouton de retrait d'une preuve.
- Vérifié : tests du serveur (18 nouveaux) ; application : analyse sans erreur, 155 tests (38 nouveaux) ; contrôle dans Chrome à 390 et 1440 px (publication, lancement d'un direct, page d'un témoignage YouTube avec preuves), sans défilement horizontal ni erreur JavaScript. Caméra IP contrôlée avec un LiveKit simulé : **pas encore essayée avec une vraie caméra**.
- **Déploiement** : `php artisan migrate --force` (4 migrations) ; LiveKit Ingress disponible sur le projet ; recompiler et copier `public/build/` ; `php artisan view:cache`. Application : nouvelle version à publier.

## 2026-09-29 — Page d'accueil selon la maquette « Témoignages de Gloire »

- **Accueil refait** selon la maquette et la charte : bandeau « Témoignages de Gloire » (illustration lever de soleil en SVG, « Dieu agit encore ! », chiffres clés), **Actions rapides** selon le rôle, **Témoignages récents** en cartes encadrées (filtres par type, liste compacte toujours disponible), **Catégories populaires**, **Statistiques globales** (courbes des 7 jours, évolution par rapport à la semaine précédente), **À la une**, **Shorts**, **Les plus populaires**, **Verset du jour** ; pour l'équipe : **Modération rapide** et **Gestion des contenus** (onglets par statut) ; pour un membre : **Mes témoignages**. [accueil.md](fonctionnalites/accueil.md)
- Nouveaux composants : `btn-soft`, carte `videos/partials/tile`, `Category::presentation()` (icône et teinte de marque d'une catégorie), `App\Support\WeeklyActivity` (partagé avec le tableau de bord de l'administration).
- Différences assumées avec la maquette : 4ᵉ chiffre « Prières reçues » (pas de note des témoignages), pas d'onglets Images / Documents (types non gérés), illustration dessinée (pas de photo).
- Vérifié : tests du serveur (`HomePageTest`, 4 nouveaux ; tests existants de l'accueil inchangés) ; contrôle dans Chrome, visiteur et administrateur, à 390, 768 et 1440 px, sans défilement horizontal ni erreur JavaScript.
- **Déploiement** : recompiler et copier `public/build/` ; `php artisan view:cache`. Aucune migration.

## 2026-09-29 — Tableau de bord de l'administration aux couleurs de la charte

- **`/admin` refait selon la charte ARISE & SHINE Krea** (et la maquette) : bandeau bleu avec les chiffres clés sur fond blanc (pastilles bleue, bleue, orange, jaune : témoignages, utilisateurs, vues totales, taux d'approbation) ; **Actions rapides** (ajouter un témoignage, utilisateurs, modération, catégories, paramètres) ; **Activité des 7 derniers jours** (témoignages en bleu, inscriptions en orange) ; **Modération rapide** (4 plus anciens témoignages en attente, organisations à vérifier) ; **Derniers témoignages** (catégorie, statut) ; **Les plus regardés** ; **Derniers utilisateurs** (« Inscrit il y a… », organisation / rôle) ; **Catégories populaires** (témoignages publiés comptés en direct, barre de proportion). Liens « Voir tout » en bleu.
- **Correction — dates en anglais** (« 1 day ago », « Wed 23 ») : les dates relatives et les jours sont désormais toujours en français (`Carbon::setLocale('fr')` dans `AppServiceProvider`), y compris sur un poste où `APP_LOCALE=en`.
- Vérifié : tests du serveur (`tests/Feature/AdminDashboardTest.php`, 2 nouveaux) ; contrôle dans Chrome à 390, 768 et 1440 px, sans défilement horizontal ni erreur JavaScript.
- **Déploiement** : recompiler et copier `public/build/` ; `php artisan view:cache`. Aucune migration.

## 2026-09-29 — Charte ARISE & SHINE Krea : logo, détails, application mobile

- **Logo** ARISE & SHINE Krea sur le site (menu latéral, en-tête mobile, pages sans connexion ; fond transparent) ; icône d'onglet : l'étoile du logo ; couleur de barre du navigateur `#184797`. [interface.md](interface.md#logo)
- **Détails de la charte appliqués** : boutons de 48 px (15 px / 600, 20 px de marge), champs de 48 px (focus bleu avec halo de 3 px, bordure verte `data-valid="true"`), badges de marque 6 × 10 px, carte orange `card-orange`, hiérarchie de titres `text-h1` à `text-h4` et textes `text-body` / `text-secondary` / `text-small`, variables CSS du guide (`--text`, `--success-bg`, `--krea-radius-*`, dégradés autorisés…), largeur de contenu limitée à 1280 px, bouton orange aussi pour « Nouveau témoignage ».
- **Application mobile** : palette Krea (bleu, orange, jaune, neutres et couleurs de message de la charte) à la place du violet, dans la palette centrale et les ~730 couleurs écrites en dur ; police **Plus Jakarta Sans embarquée** (les noms « Inter » et « Poppins » renvoyaient à des polices absentes de l'application) ; thème Material (boutons et champs 48 px arrondis 10 px, cartes 16 px) ; barre de navigation selon la charte (actif bleu sur fond bleu clair) ; logo sur l'écran de démarrage (fond blanc) et l'accueil.
- Vérifié : tests du serveur et de l'application, analyse du code de l'application ; contrôle dans Chrome à 390 et 1440 px (10 pages), sans défilement horizontal ni erreur JavaScript. Application **pas encore essayée sur un téléphone**.
- **Déploiement** : recompiler et **copier `public/build/`** et les nouveaux fichiers `public/icons/arise-shine-krea*.png` ; `php artisan view:cache`. Application : `flutter pub get` (police et logo), nouvelle version à publier.

## 2026-09-29 — Nouvelle charte graphique ARISE & SHINE Krea (site web)

- **Nouveau fichier de style** `resources/css/theme.css` : source unique des jetons de la charte (Krea Blue `#184797`, Krea Orange `#F18717`, Krea Sun `#FCC11D`, neutres, couleurs de message, arrondis 10/16/20 px, ombres légères teintées de bleu, police **Plus Jakarta Sans**). Il remplace la charte AIRID (rouge `#C10202`, Inter). [interface.md](interface.md)
- **Toute l'interface suit la charte** sans réécriture des pages : `primary-*` passe au bleu, `slate-*` aux gris de la charte. Composants mis à jour : boutons (bleu, contour bleu), champs (44 px, focus bleu avec halo), cartes (16 px, ombre légère), filtres et onglets actifs en bleu, messages et pastilles aux couleurs de la charte, titres en bleu, menu latéral (entrée active sur fond bleu plein).
- **Nouveaux composants** : `btn-cta` (orange : « Publier un témoignage », « Créer mon compte », « S'inscrire »), `btn-accent` (jaune), `card-title`, `card-brand`, `card-insight`, `badge-blue`, `badge-orange`, `badge-yellow`. Les titres de cartes passent de petites capitales grises (`section-title`) à `card-title`.
- Le logo et le nom (« TestiApp », logo AIRID) sont **inchangés** : fournir le logo ARISE & SHINE Krea pour les remplacer.
- Vérifié : tests du serveur ; contrôle dans Chrome à 390 et 1440 px (connexion, inscription, accueil, Explorer, Communauté, Mes abonnements, profil, modération, administration, directs), sans défilement horizontal ni erreur JavaScript. L'application mobile garde sa palette actuelle (violet).
- **Déploiement** : recompiler et **copier `public/build/`** sur le serveur ; `php artisan view:cache`. Aucune migration.

## 2026-09-29 — Mes abonnements ; Communauté vide dans l'application

- **Diagnostic — comptes non chargés dans l'application** : le serveur de production répond **404** à `GET /api/v1/community` (« route could not be found ») : le code de la Communauté n'y est pas encore déployé, ou le cache des routes n'a pas été vidé. **À faire sur le serveur** : déployer, puis `php artisan optimize:clear`. Le serveur affiche aussi la trace des erreurs : passer `APP_DEBUG=false`. [abonnements.md](fonctionnalites/abonnements.md#déploiement)
- **Correction — onglet Personnes vide ou incomplet** : il reposait sur le compteur `users.testimony_count`, jamais diminué et seulement augmenté à la validation par la modération. Les témoignages publiés sont désormais comptés en direct (onglet Personnes, fiches, profils du site et de l'API).
- **Application** : message clair si le serveur n'a pas encore la mise à jour (au lieu de « Vérifiez votre connexion ») ; recherche plus sûre (une réponse ancienne ne remplace plus une recherche récente).
- **Nouveau — Mes abonnements** : site `/profile/abonnements` (menu Mon espace, lien depuis le nombre d'abonnements du profil) ; application : écran **Mes abonnements** (menu latéral, statistique « Abonnements » de *Mon profil*) ; API `GET /api/v1/users/me/following?q=&page=`. Plus récents d'abord, recherche, Suivre / Abonné, comptes suspendus ou bannis masqués.
- Vérifié : tests du serveur (4 nouveaux, 1 mis à jour) et de l'application (3 nouveaux) ; contrôle dans Chrome à 390 et 1440 px. Application **pas encore essayée sur un téléphone**.
- **Déploiement** : aucune migration ; `php artisan optimize:clear` puis `php artisan view:cache` ; vérifier `…/api/v1/community?tab=organizations`. Pas de changement CSS/JS. Application : nouvelle version à publier.

## 2026-09-29 — Photo de couverture du profil

- **Nouveau** : chaque compte peut mettre une **photo de couverture** dans le bandeau de son profil. Site : champ « Photo de couverture » de *Modifier le profil* (aperçu immédiat, case « Retirer »), bandeau de la page du profil. Application : bouton de l'en-tête de *Mon profil* et bandeau de *Modifier le profil* (galerie, appareil photo, retrait, envoi immédiat) ; affichée aussi sur le profil public d'un auteur. [photo-de-couverture.md](fonctionnalites/photo-de-couverture.md)
- API : `POST /api/v1/users/me/cover` (multipart `cover`), `DELETE /api/v1/users/me/cover` ; `cover_url` dans les réponses utilisateur (profil, Communauté). JPG, PNG, WebP, 8 Mo, 600 × 150 px au moins ; l'ancien fichier est supprimé.
- Vérifié : tests du serveur (6 nouveaux) et de l'application ; contrôle dans Chrome à 390 et 1440 px. Application **pas encore essayée sur un téléphone**.
- **Déploiement** : `php artisan migrate --force` ; recompiler et copier `public/build/` ; `php artisan view:cache`. Application : nouvelle version à publier.

## 2026-09-29 — Suivre, Communauté, liens de partage, qualités visibles, commentaires des directs

- **Correction — liens de partage** : `share_url` reprenait `APP_URL` (« http://localhost » sur un serveur non configuré). Le lien utilise désormais `SHARE_URL` (défaut `https://testi.airid-africa.com`) et il est **recalculé à chaque lecture** : les anciens liens erronés ne sont plus jamais renvoyés. Migration de recalcul. [lien-de-partage.md](fonctionnalites/lien-de-partage.md)
- **Qualités** : cause trouvée sur la base de développement — toutes les conversions en échec faute de ffmpeg. Le menu « Qualité » reste désormais **visible** (désactivé, avec la raison : « en préparation » ou « seule la qualité d'origine »), l'API expose `renditionsStatus`, l'application l'affiche. Nouvelle commande **`php artisan media:check`** (ffmpeg, file d'attente, états, quoi corriger). Le fil de l'application ne télécharge plus la vidéo d'origine pour connaître sa durée. [qualites-media.md](fonctionnalites/qualites-media.md)
- **Nouveau — Suivre** (site et application) : service unique (`FollowService`) — pas d'abonnement à soi-même (et contrainte en base), pas de doublon, compteurs exacts, notification à la personne suivie. Sur le site, le bouton « S'abonner » du profil ne s'affichait jamais (routes absentes) : bouton **Suivre** sur les profils, la page de lecture (à côté de l'auteur) et la Communauté. Dans l'application, les boutons Suivre étaient factices : ils sont reliés au serveur (état partagé, `GET /users/me/following-ids`). Abonnés prévenus de chaque témoignage publié (jamais du carnet privé) et de chaque direct. [abonnements.md](fonctionnalites/abonnements.md)
- **Nouveau — Communauté** : `/communaute` (menu Découvrir) et écran de l'application : organisations (vérifiées d'abord) et personnes qui témoignent, recherche, Suivre. API `GET /api/v1/community`. Application : **menu latéral** (Communauté, Directs, Carnet, Mes témoignages, Sauvegardés, Paramètres, Modération / Administration) et **profil public d'un auteur** (`/users/:id`, ouvert depuis les cartes ; il ouvrait jusqu'ici son propre profil).
- **Directs** : **qui regarde** visible de tous (liste des comptes connectés + visiteurs, `GET /lives/{id}/viewers`) ; le **message épinglé du diffuseur** ne peut être retiré, remplacé ni masqué que par lui (403 sinon, boutons masqués) ; zone des commentaires **plus haute** et restylée (bulles, pastille « Diffuseur », champ qui grandit, bouton d'envoi rond, compteur), sur le site et dans l'application. [lives.md](fonctionnalites/lives.md)
- **Page d'un témoignage** : les autres témoignages sont proposés en **format compact** (site : « À regarder également » ; application : « Témoignages similaires »). [affichage-et-lecture.md](fonctionnalites/affichage-et-lecture.md)
- **Documentation** : déjà réservée aux administrateurs (vérifié, test existant) ; ajout du garde-fou `docs/.htaccess`. [documentation-en-ligne.md](fonctionnalites/documentation-en-ligne.md)
- Vérifié : tests du serveur et de l'application ; contrôle dans Chrome (Communauté, bouton Suivre, page de lecture, direct réel avec LiveKit Cloud : message épinglé, commentaires, qui regarde) à 390 et 1440 px, sans défilement horizontal ni erreur JavaScript. Application **pas encore essayée sur un téléphone**.
- **Déploiement** : `php artisan migrate --force` (2 migrations) ; variable `SHARE_URL` (facultative, défaut correct) ; recompiler et copier `public/build/` ; `php artisan view:cache` ; `php artisan media:check` pour vérifier les qualités. Application : nouvelle version à publier.

## 2026-09-29 — Application mobile : intervenants des directs, pays et téléphone

Détails : [lives-intervenants.md](fonctionnalites/lives-intervenants.md#application-mobile-flutter-testi_app--faite-le-2026-09-29), [telephone.md](fonctionnalites/telephone.md#application-mobile-flutter-testi_app)

- **Application (Flutter)** : intervenants des directs (bouton « Témoigner », file, invitation « C'est votre tour » avec caméra facultative, barre « À l'antenne », médaillon ; côté diffuseur et modération, bouton « Intervenants » avec le nombre en attente, Inviter / Retirer / Terminer, demandes ouvertes ou fermées).
- **Correction dans l'application** : le lecteur des spectateurs affichait la dernière vidéo reçue ; il n'affiche plus en grand que celle du diffuseur (sinon l'intervenant passait en plein écran, puis écran noir à son départ).
- **Application** : liste complète des 204 pays avec recherche et drapeaux (identique au site), téléphone avec indicatif qui suit le pays, à l'inscription et dans le profil ; numéro vérifié par SMS en lecture seule.
- **Serveur** : `POST /api/v1/auth/register` et `PUT /api/v1/users/me` acceptent `phone_country` + `phone` (mêmes contrôles que le site ; facultatif par l'API pour ne pas bloquer les versions déjà installées). Nouvelle commande `php artisan countries:export-dart {fichier}` pour régénérer la liste de l'application. 1 test ajouté (`PhoneNumberTest`).
- Vérifié : analyse du code de l'application sans remarque, 94 tests de l’application et 193 tests du serveur passent (`ExampleTest` échoue, comme avant). **Pas encore essayé sur un téléphone.**
- **Déploiement** : serveur, aucune migration de plus (`php artisan view:cache`) ; application, nouvelle version à publier.

## 2026-09-29 — Téléphone de contact avec indicatif ; corrections de sécurité

Détails : [telephone.md](fonctionnalites/telephone.md)

- **Nouveau** : champ **Téléphone** juste après **Pays**, à l'inscription et dans « Modifier le profil » : indicatif avec drapeau et recherche (par nom ou par indicatif), qui **suit le pays choisi** (Côte d'Ivoire → +225), puis le numéro. **Obligatoire pour une organisation**, facultatif pour une personne. Enregistré au format international (`+2290197123456`), unique, jamais affiché publiquement. Fiche d'administration : numéro (lien d'appel) et « Vérifié par SMS » / « Non vérifié ».
- **Sécurité** : la connexion par téléphone (`POST /api/v1/auth/phone`) retrouvait un compte par le numéro **envoyé dans la requête**, sans que ce numéro soit vérifié. Elle ne retient désormais que les numéros **vérifiés par SMS** (`users.phone_verified_at`). Un numéro saisi sur le site ou modifié dans le profil n'est pas vérifié et ne donne jamais accès au compte.
- **Confidentialité** : l'API renvoyait l'e-mail et le téléphone de **tout auteur de témoignage ou de commentaire** (lecture publique). Ils ne sont plus fournis qu'à la personne elle-même et aux administrateurs (`null` ailleurs, champs conservés pour l'application).
- `PUT /api/v1/users/me` : numéro unique (422 au lieu d'une erreur 500) ; un numéro modifié perd sa vérification.
- **Reste à corriger** (antérieur) : la signature des jetons Firebase n'est pas vérifiée (`verifyFirebaseToken()`), voir [telephone.md, Limites](fonctionnalites/telephone.md#limites-connues).
- Code : `App\Support\PhoneNumber`, indicatifs dans `App\Support\Countries`, `components/phone-input`, `User::setContactPhone()` / `hasVerifiedPhone()`, `UserResource::owner()`, `data-follow-country` dans `resources/js/app.js`. 9 tests (`PhoneNumberTest`) ; les tests d'organisation envoient désormais un numéro. Vérifié dans Chrome à 390, 768 et 1440 px.
- **Déploiement** : `php artisan migrate --force` (migration `2026_09_29_200001_add_phone_verification_to_users_table`) ; recompiler et copier `public/build/` ; `php artisan view:cache`.

## 2026-09-29 — Drapeaux dans le choix du pays

- Chaque pays de la liste (inscription, « Modifier le profil ») est précédé de son **drapeau** ; le drapeau du pays choisi s'affiche dans le champ (masqué pendant la saisie d'un autre nom).
- Images SVG (et non émojis : la charte les exclut et Windows ne les affiche pas) du jeu libre **flag-icons 7.5.0** (licence MIT, `public/flags/LICENSE.txt`), 204 fichiers dans `public/flags/{code}.svg`, versionnés avec le code.
- Économie de données : aucun drapeau chargé avant l'ouverture de la liste, puis seulement ceux qui deviennent visibles (`IntersectionObserver` sur la liste) : 10 sur 204 à l'ouverture. Sans JavaScript, liste classique sans drapeaux.
- Code : `Countries::code()` / `flagUrl()` (code ISO de chaque pays), attribut `data-flag` des options. Test : chaque pays a un drapeau existant.
- **Déploiement** : envoyer le dossier `public/flags/` avec le code ; recompiler et copier `public/build/` ; `php artisan view:cache`.

## 2026-09-29 — Choix du pays dans une liste avec recherche

- **Inscription** (`/register`) et **Modifier le profil** (`/profile/edit`) : le champ texte « Pays » devient une liste de tous les pays (≈200, en français, plus les territoires d'outre-mer) avec **recherche** : on tape « cote » et « Côte d'Ivoire » apparaît (accents ignorés, pays qui commencent par la saisie en premier), flèches et Entrée au clavier. Sans JavaScript : liste déroulante classique.
- Orthographe identique à l'application mobile pour les pays communs (« Congo (RDC) », « Côte d'Ivoire »…), pour des données homogènes.
- **Changement de comportement** : le site n'accepte plus qu'un pays de la liste (« Choisissez un pays dans la liste. »). Une valeur déjà enregistrée hors liste (ex. « CIV ») reste affichée et peut être conservée dans le profil. L'API mobile est inchangée (texte libre, 100 caractères).
- Code : `App\Support\Countries`, `components/country-select`, comportement `data-country-select` dans `resources/js/app.js`. 3 tests ajoutés (`CountryNameTest`) ; `WebOrganizationProfileEditTest` envoie désormais « Bénin » au lieu du code « BJ ». Vérifié dans Chrome à 390, 768 et 1440 px (liste non coupée, aucun défilement horizontal, aucune erreur).
- **Déploiement** : aucune migration ; recompiler et copier `public/build/` ; `php artisan view:cache`.

## 2026-09-29 — Directs : intervenants à l'antenne, un à la fois

Détails : [lives-intervenants.md](fonctionnalites/lives-intervenants.md)

- **Nouveau** : pendant un direct, une personne connectée **demande à intervenir** (sujet facultatif) et entre dans une **file**. Le diffuseur ou un modérateur l'**invite** ; elle a 60 s pour accepter, avec micro et, si elle le veut, caméra. Elle apparaît alors **en médaillon dans un coin de la vidéo**, pour tous. **Une seule personne invitée ou à l'antenne à la fois**, garanti par le serveur.
- Diffuseur et modérateurs : bloc « Intervenants » (studio et page du direct) avec la file, les sujets annoncés, Inviter, Retirer de la file, Annuler l'invitation, Terminer l'intervention (confirmation) et l'interrupteur « Accepter les demandes ». Le diffuseur entend l'intervenant.
- Intervenant : barre « Vous êtes à l'antenne » (micro, caméra, Terminer mon intervention).
- Sécurité : aucun jeton de diffusion n'est remis ; Laravel ouvre puis retire les droits de publication sur la connexion de spectateur (`UpdateParticipant`), ce qui coupe micro et caméra même si l'appareil ne coopère pas. Exclusion, fin du direct et coupure réseau (webhook `participant_left`, secours `lives:cleanup`) libèrent la place.
- API mobile : `GET lives/{id}/stage` et 7 actions `lives/{id}/stage/…` ; message temps réel `stage` ; `stats.stage` et `speakersEnabled`. Écrans de l'application à réaliser.
- Code : `App\Services\LiveStage`, modèle `LiveSpeaker`, `LiveKitClient::updateParticipant()`, vues `lives/partials/stage` et `stage-pip`, `initStage()` dans `resources/js/live.js`. 14 tests (`LiveStageTest`). Vérifié de bout en bout avec LiveKit Cloud et deux navigateurs (caméra et micro simulés) ; aucune erreur JavaScript, aucun défilement horizontal à 390, 768 et 1440 px.
- **Déploiement** : `php artisan migrate --force` (migration `2026_09_29_100001_create_live_speakers_table`) ; recompiler et copier `public/build/` ; `php artisan view:cache`.

## 2026-09-29 — Pays en toutes lettres

- **Correction** : le pays était limité à 3 caractères à l'inscription sur le site, à la modification du profil sur le site et à la connexion par téléphone de l'application (`POST /api/v1/auth/phone`). Or l'application envoie le nom complet : une connexion par téléphone avec « Côte d'Ivoire » était refusée (erreur 422). Le pays s'enregistre désormais en toutes lettres, 100 caractères au plus, partout (y compris `POST auth/register` et `PUT users/me`, jusque-là sans limite).
- Les pays déjà enregistrés sous forme de code (« CIV ») ne sont pas convertis ; la personne peut les corriger dans son profil.
- 4 tests (`CountryNameTest`).
- **Déploiement** : aucune migration (la colonne `users.country` accepte déjà 255 caractères) ; `php artisan view:cache`.

## 2026-09-28 — Site : qualité de lecture, en boucle, lecture auto, liste compacte

Détails : [affichage-et-lecture.md](fonctionnalites/affichage-et-lecture.md)

- **Qualité** (vidéo et audio) sur la page de lecture : Auto (selon le réseau annoncé par le navigateur, mêmes plafonds que l'application), chaque version allégée (720p… 240p, 128… 32 kbps) ou qualité d'origine. Changement sans perte de position ; choix mémorisé dans le navigateur ; retour automatique à l'original si une version est illisible. Affiché seulement si des versions existent ([qualités des médias](fonctionnalites/qualites-media.md)).
- **En boucle** et **Lecture auto** : à la fin, bandeau « À suivre dans 5 s » (Lire maintenant / Annuler), puis ouverture du témoignage suivant du même type, qui démarre seul. Les témoignages déjà enchaînés dans l'onglet sont évités. La vitesse rejoint cette nouvelle barre sous le lecteur.
- **Grandes cartes / Liste compacte** sur l'accueil (fil), Explorer, Vidéos (« Afficher plus » compris), profil, Sauvegardes et Mes témoignages. Ligne compacte : miniature sans chargement vidéo, titre, auteur, type ou statut, vues, date ; le chevron déplie description, verset, catégorie, compteurs et bouton Regarder / Écouter / Lire. Choix mémorisé par cookie (`POST /affichage`), fonctionne sans compte et sans JavaScript.
- **Correction** : les libellés masqués sur mobile par `sr-only sm:not-sr-only` restaient invisibles partout (la classe `.sr-only` de Font Awesome l'emporte), par exemple « Signaler » sur la page de lecture. Remplacé par `hidden sm:inline` + `aria-label` ; règle ajoutée à [interface.md](interface.md).
- Code : `App\Support\FeedLayout`, `Web\DisplayPreferenceController`, `Testimony::typePill()` / `durationLabel()` (partagés par carte et ligne) / `playableRenditions()`, vues `components/testimony-list`, `components/layout-toggle`, `videos/partials/row` ; `resources/js/videos.js`. 11 tests (`DisplayAndPlaybackTest`) ; contrôle dans Chrome à 390, 768 et 1440 px sans défilement horizontal ni erreur JavaScript.
- **Déploiement** : aucune migration ; recompiler et copier `public/build/` ; `php artisan view:cache`.

## 2026-09-28 — Abonnés prévenus (directs, modération web) et affichage des organisations vérifiées

Détails : [lives.md](fonctionnalites/lives.md), [notifications-push.md](fonctionnalites/notifications-push.md)

- **Direct** : au passage « en préparation » → « en direct » (`LiveService::goLive`, via `go-live` ou le webhook LiveKit), chaque abonné du diffuseur reçoit une notification `live_started` (« {Nom} est en direct : {titre} », `payload.live_id`) et un push (titre « En direct », `data.live_id`, préférence `push_new_followed`). Une seule fois par direct : changement de statut conditionnel, sans effet d'un webhook rejoué ou d'appels simultanés. L'API des notifications expose `liveId`.
- **Modération web** : `Web\ModerationController::approve` prévient désormais les abonnés de l'auteur (`new_followed_testimony` + push), comme l'API. Les deux passent par le nouveau service `App\Services\FollowerNotifications` (lots de 50, push compris). Le site ignore aussi les entrées du carnet privé à l'approbation et au rejet (404), comme l'API.
- **Correction** : le motif de rejet envoyé à l'auteur est en français dans l'API comme sur le site (libellé `RejectionReason`, ex. « Contenu inapproprié » au lieu de « InappropriateContent »).
- **Correction** : durées d'une heure ou plus affichées « 1:02:05 » au lieu de « 62:05 » (`Testimony::formattedDuration()`).
- **Affichage** (contrôlé dans Chrome à 390, 768 et 1440 px avec de vraies vidéos et audios d'une organisation vérifiée) : sur les cartes, le nom et la coche restent groupés et la catégorie garde jusqu'à 40 % de la ligne (`videos/partials/card.blade.php`) ; sur le profil, la coche reste sur la ligne du dernier mot (`profile/show.blade.php`) ; coche ajoutée dans « Nouveaux utilisateurs » du tableau de bord.
- Tests : `LiveStartNotificationTest` (4), `WebModerationNotificationTest` (5).
- **Déploiement** : recompiler et copier `public/build/`, `php artisan view:cache`. Aucune migration ni variable.

## 2026-09-28 — Notifications push, organisations sur le site, conversion des directs, contrôle visuel

### Notifications push (FCM)

Détails : [notifications-push.md](fonctionnalites/notifications-push.md)

- **Nouveau** : chaque notification de l'application (commentaire, réponse, réaction, abonnement, témoignage approuvé ou refusé, nouveau témoignage d'un compte suivi, organisation vérifiée ou refusée) part aussi en **notification push** sur tous les appareils du destinataire, via Firebase Cloud Messaging (API HTTP v1), sans dépendance Composer.
- Préférences respectées côté serveur (`user_settings.push_comments`, `push_likes`, `push_prayers`, `push_approval`, `push_new_followed`) ; titres en français ; données `type`, `testimony_id`, `comment_id`, `actor_id`, `notification_id` pour la navigation dans l'application ; canal Android `testi_notifications`.
- Jetons d'appareil périmés (`UNREGISTERED`) ou mal formés supprimés automatiquement. Envoi en file d'attente (`SendPushNotificationJob`, 3 essais), jeton OAuth2 en cache ; sans configuration, aucun envoi et aucun changement.
- `POST /devices/token` accepte aussi `platform: web` (version web de l'application).
- **Correction** : l'approbation d'un témoignage par l'API (`POST moderation/{id}/approve`) échouait sur MySQL quand l'auteur avait des abonnés (insertion des notifications sans identifiant et avec une colonne `updated_at` inexistante).
- Code : `App\Services\FcmClient`, `App\Services\PushNotifications`, `App\Jobs\SendPushNotificationJob`, `App\Observers\AppNotificationObserver` ; commande `php artisan push:test {user}` ; 11 tests (`PushNotificationTest`).

### Organisations depuis le site

Détails : [comptes-organisation.md](fonctionnalites/comptes-organisation.md#inscription-depuis-le-site)

- Le formulaire d'inscription du site (`/register`) propose « Je suis une personne » / « Je représente une organisation ». En mode organisation : nom de l'organisation (obligatoire), type, ville, site internet et note sur la vérification ; le nom affiché est celui de l'organisation. Mêmes règles que l'API (`OrganizationAccounts`), fonctionne sans JavaScript, mode rétabli après une erreur (`old()`). Nouveau comportement JavaScript `data-account-type-form`.
- « Modifier le profil » (`/profile/edit`) : section « Organisation » (nom, type, ville, site, statut et motif d'un refus). Renommer une organisation vérifiée la remet en attente (mention sous le nom) ; modifier une organisation refusée renvoie la demande.
- Profil de l'organisation (vu par elle-même) : avis « Vérification en cours » ou « Vérification refusée » avec le motif et un lien vers « Modifier le profil ».
- Tests : `WebOrganizationRegistrationTest` (10), `WebOrganizationProfileEditTest` (12).

### Qualités des médias : enregistrements de directs (S3)

Détails : [qualites-media.md](fonctionnalites/qualites-media.md)

- Les versions allégées sont aussi produites pour les fichiers d'un disque non local, en particulier les enregistrements de directs stockés sur S3 : téléchargement par flux dans un dossier de travail (`MEDIA_TEMP_DIR`, défaut `storage/app/transcode`), conversion, renvoi de chaque version dans le bucket à côté de l'original ; le dossier de travail est toujours supprimé.
- Nouveau disque `recordings` (`config/filesystems.php`, variables `LIVEKIT_RECORDING_S3_*`). URL des versions : `LIVEKIT_RECORDING_PUBLIC_URL` + chemin (`MediaFile::urlFor`, réutilisé par `LiveService::recordingUrl`).
- À la fin d'un enregistrement, le témoignage vidéo reçoit sa ligne `media_files` et sa conversion est mise en file (`LiveService::handleEgress`). `media:transcode --missing` rattrape aussi les directs existants.
- Nouveau paquet `league/flysystem-aws-s3-v3` (accès S3 depuis Laravel).

### Contrôle visuel et corrections

- Pages contrôlées dans Chrome à 390, 768 et 1440 px (utilisateurs, fiche, tableau de bord, profil public, cartes et page de lecture) : aucun défilement horizontal. Corrigé : colonnes du tableau des utilisateurs (e-mail et date à partir de `xl`, noms longs sur plusieurs lignes), onglets courts sur mobile, barre de filtres, carte de résumé de la fiche, bandeaux du tableau de bord, titre du profil qui chevauchait la bannière. Vérifier / Refuser / Retirer testés de bout en bout (`openConfirmModal`).
- **Correction** : initiales des avatars accentuées (« Église » affichait « �� ») : `User::getInitialsAttribute()` utilise `mb_substr` / `mb_strtoupper`.
- Coche « Organisation vérifiée » ajoutée dans « À regarder également » (`videos/partials/recommendation.blade.php`).
- Langue par défaut `fr` (`config/app.php`, `.env.example`) : dates relatives en français.
- Sécurité : mise à jour de `guzzlehttp/guzzle` 7.15.5, `guzzlehttp/psr7` 2.13.1, `league/commonmark` 2.10.3 (21 alertes `composer audit` corrigées, aucune restante).
- **Correction** : la création de compte par téléphone (`POST /api/v1/auth/phone`) n'enregistrait pas le statut du compte (erreur 500 dans la réponse).

**Déploiement** : `composer install --no-dev` (nouveaux paquets) ; recompiler et copier `public/build/` ; `APP_LOCALE=fr` et `APP_FALLBACK_LOCALE=fr` dans `.env` ; configurer `FIREBASE_CREDENTIALS` ; `php artisan config:cache`, `php artisan view:cache`, `php artisan queue:restart` ; puis `php artisan media:transcode --missing`. Aucune migration supplémentaire.

## 2026-09-28 — Comptes organisation et vérification

Détails : [comptes-organisation.md](fonctionnalites/comptes-organisation.md)

- **Nouveau** : les églises, ministères, associations, ONG et médias chrétiens peuvent créer un **compte organisation** depuis l'application mobile (nom, type, ville, site internet). Le compte est créé **en attente de vérification**.
- **Administration** : onglets « Organisations » et « Organisations en attente » (avec le nombre) dans Utilisateurs ; bloc « Organisation » sur la fiche d'un compte avec les actions **Vérifier** et **Refuser** (motif facultatif, confirmation) ; bandeau sur le tableau de bord. API : `POST admin/users/{id}/verify`, `POST admin/users/{id}/reject-verification`, filtres `account_type` et `verification_status` sur `GET admin/users`.
- L'organisation est prévenue par une notification dans l'application (`organization_verified` / `organization_rejected`). Notification push ajoutée le même jour ([notifications-push.md](fonctionnalites/notifications-push.md)).
- Une organisation vérifiée porte une coche « Organisation vérifiée » sur son profil, ses cartes et sa page de lecture (site) ; l'API expose `account_type`, `organization_*`, `is_verified`, `verification_status` dans `UserResource` (donc dans `user` d'un témoignage).
- Règles : une personne ne peut pas se convertir en organisation ni se dire vérifiée ; renommer une organisation vérifiée la remet en attente ; modifier une organisation refusée renvoie la demande.
- **Correction** : `POST /api/v1/auth/register` et la création de compte par téléphone (`POST /api/v1/auth/phone`) renvoyaient une erreur 500 (statut du compte non renseigné) ; le statut `active` est désormais enregistré explicitement.
- Code : `App\Services\OrganizationAccounts`, enums `AccountType`, `OrganizationType`, `VerificationStatus` ; 9 tests (`OrganizationAccountTest`).
- **Déploiement** : `php artisan migrate --force` (migration `2026_09_28_200001_add_organization_fields_to_users_table`) ; `php artisan view:cache`. Pas de recompilation des assets nécessaire.

## 2026-09-28 — Qualités des médias (versions allégées audio et vidéo)

Détails : [qualites-media.md](fonctionnalites/qualites-media.md)

- Après chaque envoi audio ou vidéo (API et site), une tâche en file d'attente (`App\Jobs\TranscodeMediaJob`) produit avec ffmpeg des versions allégées : vidéo 240p / 360p / 480p / 720p (jamais au-dessus de la source, 240p toujours), audio 32k / 64k / 128k (seulement sous le débit de la source). L'original reste intact et lisible (`mediaUrl`).
- API : chaque témoignage expose `renditions` (`[{quality, height, bitrate, url}]` pour la vidéo, `[{quality, bitrate, url}]` pour l'audio, débit croissant, `[]` si aucune). La durée est complétée automatiquement quand elle vaut 0.
- `media_files` : `renditions`, `processing_status` (none / pending / processing / done / failed), `width`, `height` ; `testimonies.renditions`. Les envois du site créent désormais aussi une ligne `media_files`.
- Échec (ffmpeg absent, fichier illisible) : statut `failed`, journalisé, fichiers partiels supprimés, lecture de l'original inchangée.
- Nouvelle commande `php artisan media:transcode {--missing} {--id=} {--sync}` pour convertir les médias existants. 12 tests ajoutés (`MediaTranscodingTest`).
- **Déploiement** : installer ffmpeg (et ffprobe) ; variables `FFMPEG_BINARY`, `FFPROBE_BINARY`, `MEDIA_TRANSCODING_ENABLED`, `MEDIA_TRANSCODE_TIMEOUT`, et `DB_QUEUE_RETRY_AFTER` supérieur au délai (ex. 1900) ; `php artisan migrate --force` ; lancer en permanence `php artisan queue:work --tries=2 --timeout=1900` (Supervisor, ou cron `--stop-when-empty`) ; puis `php artisan media:transcode --missing`.

## 2026-09-28 — Contrôle des nouveautés : carnet privé côté web

Détails : [carnet-prive.md](fonctionnalites/carnet-prive.md), [videos.md](fonctionnalites/videos.md)

- **Correction** : un témoignage créé en « Privé » depuis le site partait en modération (où le carnet est exclu : il restait bloqué « en attente »). Il est désormais enregistré dans le **carnet privé** (brouillon), comme depuis l'application mobile.
- **Correction** : `/testimonies/{id}` répond **404** (et non plus 403) à toute autre personne que l'auteur pour une entrée du carnet : son existence n'est pas révélée.
- Les lectures d'une entrée du carnet ne comptent plus comme des vues ; sa page de lecture affiche « carnet privé » et masque « Partager » ; sa carte (Mes témoignages) porte la pastille « Carnet privé ».
- Vérifié : interactions de la page de lecture contrôlées dans Chrome (J'aime, sauvegarde, commentaire, réponse, mention, modification, suppression avec confirmation, afficher plus) sans erreur ; 2 tests ajoutés.
- **Déploiement** : aucune migration supplémentaire ; recompiler et copier `public/build/` ; `php artisan view:cache`.

## 2026-09-26 — Présentation « plateforme vidéo » sur tout le site

Détails : [videos.md, section « Présentation commune »](fonctionnalites/videos.md#présentation-commune-aux-autres-pages)

- **Aucune URL modifiée.** Les pages existantes adoptent la présentation de la page Vidéos :
  - **cartes** (accueil, Explorer, profil, sauvegardes, mes témoignages) : grande miniature, durée, pastille Short / Audio / Texte / statut, avatar, vues, date ;
  - **accueil** : pastilles type + catégories sur une ligne, verset du jour, **En direct**, **À la une** (une grande carte + 4 suivantes), étagère **Shorts** défilante, puis le fil ;
  - **Explorer** : onglets de type et pastilles de tri et de catégorie à la place des listes déroulantes (mêmes paramètres `q`, `type`, `sort`, `category`) ;
  - **page d'un témoignage** (`/testimonies/{id}`) : même page de lecture que `/videos/{id}` (lecteur, réactions, commentaires avec réponses, modification, suppression, « À regarder également », signalement) ;
  - **profil** : présentation « chaîne » (bandeau, avatar, statistiques, bouton S'abonner) ;
  - **Directs** : cartes de direct ; **barre du haut** : champ de recherche (vers Explorer), loupe sur mobile.
- **Correction de confidentialité** : sur `/videos/{id}`, une entrée du [carnet privé](fonctionnalites/carnet-prive.md) était visible (et commentable) par les modérateurs ; elle est désormais réservée à son auteur (`Testimony::isVisibleTo()`).
- Correction au passage : requêtes répétées (N+1) sur « Mes témoignages » (auteur non chargé d'avance) ; catégories chargées d'avance partout.
- Code : données de la page de lecture regroupées dans `App\Services\WatchPage` ; `components/testimony-card` s'appuie sur `videos/partials/card`. 3 tests ajoutés ; `WorshipReactionTest` suit le nouvel attribut `data-reaction`.
- **Déploiement** : aucune migration ; recompiler et copier `public/build/` ; `php artisan optimize:clear` puis `php artisan view:cache`.

## 2026-09-26 — Page Vidéos

Détails : [videos.md](fonctionnalites/videos.md)

- Nouvelle page publique **Vidéos** (`/videos`, menu Découvrir) : recherche (titre, texte, catégorie, auteur), onglets **Tout · Vidéos · Shorts · Directs · Audios · Textes**, tri (récentes, vues, aimées), catégories, grille de cartes cliquables avec prévisualisation au survol, « Afficher plus ».
- Nouvelle page de **lecture** `/videos/{id}` : lecteur HTML5 avec vitesse, format vertical pour les shorts, audio et texte, partage, sauvegarde, réactions (J'aime…), description repliable, **commentaires** (publier, modifier, supprimer, **répondre** sur un niveau) mis à jour sans rechargement, **À regarder également**.
- Shorts = vidéos de 60 s au plus ; Directs = directs en cours et rediffusions. Aucune nouvelle table.
- **Compteur de vues** : une vue par personne et par témoignage toutes les 6 heures ; vidéo et audio comptés au lancement de la lecture. **Changement de comportement** : `/testimonies/{id}` applique la même règle (chaque affichage était compté).
- Nouveaux fichiers : `VideoController`, `Web\CommentController`, `CommentRequest`, `CommentPolicy`, `ViewCounter`, vues `videos/`, `resources/js/videos.js`. 14 tests (`VideoPageTest`).
- **Déploiement** : aucune migration ; recompiler et copier `public/build/` ; `php artisan optimize:clear` puis `php artisan view:cache`.

## 2026-09-26 — Directs : commentaires du diffuseur et commentaire épinglé

Détails : [lives.md, section « Règles »](fonctionnalites/lives.md#règles)

- Le diffuseur et les modérateurs peuvent **toujours commenter**, même si les commentaires sont désactivés pour le public.
- **Commentaire épinglé** : le diffuseur ou un modérateur épingle un commentaire visible (le sien ou celui d'un spectateur) en haut du direct, et le désépingle. Un seul à la fois ; masquer ce commentaire ou exclure son auteur le désépingle.
- API : `POST /api/v1/lives/{id}/comments/{commentId}/pin`, `DELETE /api/v1/lives/{id}/pin` ; champ `pinnedComment` dans le direct et ses statistiques ; messages temps réel `comment_pinned` et `comment_unpinned`.
- **Déploiement** : nouvelle migration (`pinned_comment_id` dans `live_sessions`), donc `php artisan migrate --force` ; copier `public/build/`.
- Vérifié : test automatique `test_host_can_pin_and_unpin_a_comment`.

## 2026-09-25 — Enregistrement des directs → témoignage vidéo

Détails : [lives.md, section « Enregistrement »](fonctionnalites/lives.md#enregistrement--témoignage-vidéo)

- Option « Enregistrer le direct », cochée par défaut au lancement. Enregistrement MP4 par LiveKit (Egress) vers un stockage compatible S3, indicateur **REC** dans le studio et chez les spectateurs.
- À la fin, création automatique d'un **témoignage vidéo en attente de relecture** (auteur = diffuseur, titre et présentation du direct), publié après validation habituelle. Bouton **Rediffusion** ensuite.
- Robustesse : un échec d'enregistrement n'interrompt jamais le direct ; webhook `egress_ended` et vérification de secours par `lives:cleanup` ; pas de doublon ; enregistrements de moins de 20 s ignorés.
- API : option `record` au lancement ; bloc `recording` (état, durée, `replayTestimonyId`) dans chaque direct ; message temps réel `recording`.
- **Déploiement** : nouvelle migration (`php artisan migrate --force`) ; copier `public/build/` ; pour activer l'enregistrement, créer un stockage S3 et renseigner les variables `LIVEKIT_RECORDING_*` (sinon, l'option est affichée « Indisponible »).
- Vérifié : 6 tests automatiques ; requête d'enregistrement acceptée par LiveKit Cloud. Pas encore testé avec un vrai stockage.

## 2026-09-25 — Réaction « Adorer »

- Nouvelle réaction **Adorer** (clé `worship`, 🙌 deux mains levées), alignée sur l'application mobile. Elle s'ajoute à J'aime, Amour, Prière, Amen et Feu.
- Disponible sur les témoignages (web et API `POST /api/v1/testimonies/{id}/reactions`) et pendant les directs.
- Compteur : comme Prière et Amen, « Adorer » incrémente le compteur de prières (`prayer_count`) du témoignage. Règle centralisée dans `ReactionType::counterField()`, qui remplace quatre copies de cette logique.
- Sur le web, l'icône Amen devient « mains qui applaudissent », pour laisser les deux mains levées à « Adorer ».
- **Déploiement** : nouvelle migration (`worship_count` dans `live_sessions`), donc `php artisan migrate --force` ; copier `public/build/`.
- Compilation CSS : `storage/framework/views` n'est plus analysé par Tailwind (le résultat dépendait du cache). L'archive `build-a-deployer.zip` est désormais créée avec des chemins compatibles Linux.

## 2026-09-25 — Témoignages en direct

Détails : [lives.md](fonctionnalites/lives.md)

- Diffusion vidéo en direct **depuis le navigateur d'un téléphone**, réservée aux **modérateurs et administrateurs** : vérifications préalables (HTTPS, navigateur, réseau, caméra, micro, batterie), studio (micro, caméra, changement de caméra, qualité réseau, audience, fin).
- Spectateurs sur le web (sans compte) ; **commentaires et réactions en temps réel** pour les personnes connectées, avec limites anti-abus.
- **Modération en direct** : masquer un commentaire, exclure une personne, couper le direct.
- API mobile `/api/v1/lives…` et messages temps réel documentés pour l'application Flutter.
- Service vidéo : **LiveKit** (Cloud ou auto-hébergé). Nouvelle dépendance npm `livekit-client`, nouveau fichier `resources/js/live.js`, nouveau badge `badge-live`.
- **Déploiement** : créer le projet LiveKit, renseigner `LIVEKIT_URL`, `LIVEKIT_API_KEY` et `LIVEKIT_API_SECRET`, déclarer le webhook `/api/v1/livekit/webhook`, `php artisan migrate --force`, copier `public/build/`, tâche cron recommandée.
- Hors périmètre pour l'instant : rediffusion (enregistrement), diffusion depuis une salle (RTMP/OBS), notification push, écrans de l'application mobile.

## 2026-09-25 — App Links / Universal Links (documentation et tests)

Détails : [app-links.md](fonctionnalites/app-links.md)

- Documentation des routes `/.well-known/assetlinks.json` et `/.well-known/apple-app-site-association`, ajoutées lors d'une autre intervention, et de leur configuration (`config/applinks.php`).
- Ajout de tests (`tests/Feature/AppLinksTest.php`).
- **À faire avant la production** : renseigner `ANDROID_APP_SHA256_FINGERPRINTS` avec l'empreinte release ou Play App Signing (la valeur par défaut est la clé de debug), et `IOS_APP_ID`.
- **Côté mobile** : filtre d'intention Android, Associated Domains iOS et gestion du lien dans Flutter restent à faire.

## 2026-09-25 — « À la une » automatique pour les témoignages de la semaine

Détails : [a-la-une.md](fonctionnalites/a-la-une.md)

- Un témoignage publié depuis moins de 7 jours (date d'approbation) est automatiquement à la une, en plus de la mise en avant manuelle (`is_featured`).
- **Changement de tri** : « À la une » (accueil et `GET /api/v1/testimonies/featured`) est trié par date de publication, et non plus par nombre de vues.
- **Correction** : les témoignages privés ou réservés aux abonnés ne peuvent plus apparaître à la une (seuls les témoignages publics sont retenus).
- API : `isFeatured` suit la nouvelle règle ; nouveau champ `isPinned` pour la mise en avant manuelle.
- **Déploiement** : aucune migration ni recompilation ; `php artisan optimize:clear`.

## 2026-09-25 — Documentation lisible dans la plateforme

Détails : [documentation-en-ligne.md](fonctionnalites/documentation-en-ligne.md)

- Nouvelle page **Administration → Documentation** (`/admin/documentation`), réservée aux administrateurs : les fichiers de `docs/` affichés en HTML avec sommaire, ancres et liens internes.
- Nouvelle page ou modification d'un fichier `docs/*.md` : visible immédiatement, sans compilation.
- **Déploiement** : CSS modifié, donc recompiler et copier `public/build/` ; déployer le dossier `docs/` ; `php artisan optimize:clear`.

## 2026-09-25 — Documentation du serveur

- Création du dossier `docs/` : vue d'ensemble et déploiement ([README](README.md)), [interface](interface.md), pages de fonctionnalités et ce journal.
- Ajout de `CLAUDE.md` à la racine : règles du projet (charte, documentation à tenir à jour).

## 2026-09-25 — Lien de partage des témoignages

Détails : [lien-de-partage.md](fonctionnalites/lien-de-partage.md)

- Nouvelle colonne `testimonies.share_url`, remplie à la création et pour tous les témoignages existants.
- API : champ `shareUrl` dans chaque témoignage ; `POST /api/v1/testimonies/{id}/share` renvoie `shareUrl`.
- **Changement de comportement** : `POST …/share` répond 404 pour un témoignage inexistant (auparavant « Partage enregistré »).
- Nouvelle commande `php artisan testimonies:refresh-share-urls`.
- **Déploiement** : vérifier `APP_URL`, puis `php artisan migrate --force`.

## 2026-09-24 — Mise en forme des témoignages texte

Détails : [mise-en-forme-temoignages.md](fonctionnalites/mise-en-forme-temoignages.md)

- Éditeur visuel (gras, italique, émojis) sur la page de publication.
- Affichage mis en forme sur la page détail et en modération ; aperçus sans astérisques.
- Stockage en Markdown léger dans `body_text` (aucun HTML stocké).
- **Déploiement** : recompiler et copier `public/build/`. Incident rencontré : boutons inactifs en ligne tant que les assets n'avaient pas été remplacés.

## 2026-09-24 — Application de la charte graphique AIRID

Détails : [interface.md](interface.md)

- Toutes les vues web passent de Bootstrap 5 (CDN) à Tailwind CSS 4 selon la charte AIRID : couleurs, police Inter, Font Awesome 6.5, logo AIRID.
- Nouveau layout avec menu latéral selon le rôle, fil d'Ariane, titre et sous-titre de page ; layout public `guest` pour la connexion et l'inscription.
- L'ancien `layouts/admin.blade.php` est supprimé : les pages d'administration utilisent le layout commun.
- JavaScript natif commun : modale de confirmation (remplace `confirm()`), indicateurs de chargement, brouillon automatique, blocage hors connexion, engagement obligatoire.
- Responsive vérifié à 390, 768 et 1440 px : fiches compactes à la place des tableaux sur mobile, filtres repliables.
- Suppression des émojis et des dégradés décoratifs de l'interface.

Corrections faites à cette occasion :

- **Publication web impossible** : noms de champs du formulaire différents de ceux du contrôleur (`category`, `cover`, `media_file`). Ajout de la validation serveur du consentement, du texte obligatoire pour un témoignage texte, de la catégorie existante et du type de fichier, avec messages en français.
- **Plantage** du contrôleur de publication si le champ `tags` est absent.
- **Explorer** : l'option « Tous les types » ne renvoyait aucun résultat.
- **Paramètres admin** : un réglage booléen décoché n'était jamais enregistré comme désactivé.
- **Fiche utilisateur admin** : le message « Vous ne pouvez pas modifier votre propre rôle » n'était plus affiché.
- **Profil** : plantage pour un visiteur connecté (route `users.follow` inexistante) ; le bouton « S'abonner » est masqué tant que la route n'existe pas.
- **Déploiement** : Node 20.19+ nécessaire pour compiler (Node 18 refusé par Vite 7).
