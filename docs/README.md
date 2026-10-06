# TestiApp — Documentation du serveur

Serveur Laravel de **TestiApp** (AIRID) : site web de partage de témoignages et API de l'application mobile Flutter.

| Document | Contenu |
|---|---|
| [Interface web (charte ARISE & SHINE Krea)](interface.md) | Composants CSS, layouts, comportements JavaScript, règles responsive, ajout d'une page |
| [Mise en forme des témoignages](fonctionnalites/mise-en-forme-temoignages.md) | Gras, italique, émojis : éditeur, format stocké, affichage |
| [Lien de partage](fonctionnalites/lien-de-partage.md) | Colonne `share_url`, API `shareUrl`, commande de recalcul |
| [Page Vidéos](fonctionnalites/videos.md) | Page publique `/videos` (vidéos, shorts, directs, audios, textes), lecture, vues uniques, J'aime, commentaires et réponses |
| [Témoignages en direct](fonctionnalites/lives.md) | Diffusion vidéo depuis un téléphone (modérateurs/admins), commentaires et réactions en temps réel, modération, API mobile |
| [Événements chrétiens](fonctionnalites/evenements.md) | Croisades, conférences, camps… créés par les organisations vérifiées et les administrateurs : carrousel d'images, invités, « Je participe », commentaires des participants, témoignages officiels, direct de l'événement (site et application) |
| [Requêtes de prière](fonctionnalites/requetes-de-priere.md) | Publication directe, visibilité publique / abonnés / privée, anonymat, « Je prie », encouragements, exaucée, signalement et retrait par la modération, rattachement à un événement (site et application) |
| [Sessions de prière](fonctionnalites/sessions-de-priere.md) | Sessions programmées par tout compte, inscriptions « Je serai là », rappel 15 min avant ; la salle est un direct LiveKit ouvert par l'hôte (commentaires, intervenants) |
| [Paroles prophétiques](fonctionnalites/paroles-prophetiques.md) | Carnet privé (application) : garder les paroles reçues (texte ou audio), journal de prière, rappels, proclamation, témoignage de l'accomplissement et parole rendue publique avec lui |
| [Messages pour inciter à témoigner](fonctionnalites/encouragements.md) | Versets (Psaume 78:4, Apocalypse 12:11…) et invitations sur les pages et dans les fils, bouton Témoigner |
| [Pourquoi témoigner ?](fonctionnalites/pourquoi-temoigner.md) | Bloc en accordéon : 7 raisons bibliques de témoigner et leurs versets, sur l'accueil, Publier, Mes témoignages, la lecture d'un témoignage et l'inscription |
| [Intervenants dans un direct](fonctionnalites/lives-intervenants.md) | File des demandes, invitation, passage à l'antenne (micro, caméra facultative) en médaillon, une personne à la fois |
| [Ouverture des liens dans l'app](fonctionnalites/app-links.md) | App Links (Android) / Universal Links (iOS) : fichiers `/.well-known/…` |
| [À la une](fonctionnalites/a-la-une.md) | Témoignages publiés depuis moins de 7 jours ou mis en avant manuellement |
| [Affichage et lecture](fonctionnalites/affichage-et-lecture.md) | Site : choix de la qualité, en boucle, lecture automatique du suivant ; grandes cartes ou liste compacte dépliable |
| [Lecture à voix haute](fonctionnalites/lecture-vocale.md) | Écouter un témoignage écrit (synthèse vocale de l'appareil) en français ou en anglais : site et application, vitesse, voix, lecture automatique (application) |
| [Qualités des médias](fonctionnalites/qualites-media.md) | Versions 240p–720p et 32k–128k produites par ffmpeg, champ `renditions` de l'API, commande `media:transcode` |
| [Recommandations et fil « Pour vous »](fonctionnalites/recommandations.md) | Récents et plus vus pour un nouveau venu ; recommandations selon le témoignage en cours, les centres d'intérêt et les comptes suivis ; **Mon fil** (comptes suivis + suggestions) |
| [Preuves d'un témoignage](fonctionnalites/preuves.md) | Deux images ou PDF par témoignage, privés (auteur et équipe de modération) |
| [Vidéos YouTube](fonctionnalites/videos-youtube.md) | Publication d'un témoignage par lien YouTube (administrateurs), lecture avec le lecteur YouTube |
| [Direct avec une caméra IP](fonctionnalites/lives-camera-ip.md) | Caméra IP, OBS ou encodeur (RTMP, ou adresse du flux) via LiveKit Ingress ; aperçu et lancement depuis le studio |
| [Page d'accueil](fonctionnalites/accueil.md) | Maquette « Témoignages de Gloire » : bandeau et chiffres, actions rapides, récents, catégories, statistiques, modération et gestion des contenus selon le rôle |
| [Photo de couverture](fonctionnalites/photo-de-couverture.md) | Bandeau du profil illustré par une photo (site et application) : ajout, remplacement, retrait |
| [Suivre et Communauté](fonctionnalites/abonnements.md) | Bouton Suivre (site et application), page Communauté (organisations, personnes), abonnés prévenus des témoignages et directs, menu latéral de l'application |
| [Téléphone de contact](fonctionnalites/telephone.md) | Numéro avec indicatif (drapeau, suit le pays) à l'inscription et dans le profil, format international, numéro vérifié ou non, confidentialité de l'API |
| [Comptes organisation](fonctionnalites/comptes-organisation.md) | Comptes église / ministère / association / ONG / média, vérification par un administrateur, coche « vérifiée » (API et site) |
| [Notifications push](fonctionnalites/notifications-push.md) | Envoi FCM (API HTTP v1) de chaque notification de l'application, préférences push, suppression des jetons périmés, commande `push:test` |
| [Documentation en ligne](fonctionnalites/documentation-en-ligne.md) | Lecture de cette documentation dans la plateforme (`/admin/documentation`) |
| [Journal des modifications](journal-des-modifications.md) | Historique daté de chaque ajout, modification et correction |

> **Lire en ligne** : connecté en administrateur, menu **Administration → Documentation** (`/admin/documentation`). Les pages sont générées à partir de ces fichiers et restent donc toujours à jour.

> Cette documentation couvre les fonctionnalités ajoutées ou reprises depuis septembre 2026. Les parties plus anciennes (API d’authentification, Bible…) ne sont pas encore décrites en détail.

---

## 1. Vue d'ensemble

| Élément | Choix |
|---|---|
| Framework | Laravel 12 (PHP ≥ 8.2) |
| Vues | Blade + Tailwind CSS 4 (compilé par Vite 7) |
| JavaScript | Natif uniquement (aucun framework), dans `resources/js/app.js` |
| Icônes / police | Font Awesome 6.5 (CDN cdnjs) · Inter (fonts.bunny.net) |
| Base de données | MySQL `utf8mb4` en production (émojis pris en charge) · SQLite en mémoire pour les tests |
| API mobile | `routes/api.php`, préfixe `/api/v1`, authentification Sanctum |

### Deux interfaces sur le même serveur

- **Site web** (`routes/web.php`, contrôleurs `app/Http/Controllers/Web`) : consultation publique, publication, profil, modération, administration.
- **API** (`routes/api.php`, contrôleurs `app/Http/Controllers/Api`, ressources `app/Http/Resources`) : utilisée par l'application Flutter `testi_app`.

Les deux partagent les mêmes modèles (`app/Models`) et les mêmes règles métier (enums dans `app/Enums`).

### Rôles

| Rôle (`UserRole`) | Accès web |
|---|---|
| `visiteur`, `utilisateur` | Pages publiques, puis « Mon espace » une fois connecté |
| `moderateur` | + File de modération (`/moderation`) |
| `administrateur` | + Administration (`/admin`) et modération |

Les contrôles sont faits par le middleware `role:` sur les groupes de routes ; le menu latéral n'affiche que les entrées autorisées.

---

## 2. Arborescence utile

```
app/
  Console/Commands/          Commandes artisan (bible:*, verse:schedule, testimonies:refresh-share-urls)
  Enums/                     Statuts, rôles, types (badgeClass() renvoie la classe de badge de la charte)
  Http/Controllers/Web/      Contrôleurs du site
  Http/Controllers/Api/      Contrôleurs de l'API mobile
  Http/Resources/            Format JSON renvoyé à l'application mobile
  Models/                    Modèles Eloquent
  Support/RichText.php       Conversion du texte mis en forme (gras, italique)
  Http/Controllers/Web/DocumentationController.php   Affichage de docs/ dans la plateforme
resources/
  css/theme.css              Charte ARISE & SHINE Krea : jetons (couleurs, police, arrondis, ombres)
  css/app.css                Composants de l'interface (boutons, cartes, champs…)
  js/app.js                  Comportements communs (modales, chargements, brouillons, éditeur…)
  views/layouts/             app (site connecté/public), guest (connexion), partials/global-ui
  views/components/          avatar, empty-state, switch, testimony-card, rich-editor
  views/vendor/pagination/   Pagination aux couleurs de la charte
public/icons/arise-shine-krea.png       Logo ARISE & SHINE Krea (fond transparent)
public/icons/arise-shine-krea-star.png  Étoile du logo : icône d'onglet
docs/                        Cette documentation
```

---

## 3. Installation locale

```bash
composer install
cp .env.example .env && php artisan key:generate
# Renseigner DB_*, puis APP_URL (utilisé pour les liens de partage)
php artisan migrate --seed          # comptes de démonstration : admin@testiapp.com / password
npm ci && npm run build             # Node 20.19 ou plus (voir §4)
php artisan serve
```

Recommandé : `APP_LOCALE=fr` dans `.env`, pour que les dates relatives s'affichent en français (« il y a 3 semaines »).

---

## 4. Déploiement

### Assets CSS/JS (important)

Les fichiers compilés vont dans `public/build/`, **exclu de git** (`.gitignore`). Après toute modification de `resources/css` ou `resources/js` :

- **si npm est disponible sur le serveur** : `npm ci && npm run build` (Node **20.19+** : Vite 7 et Tailwind 4 refusent Node 18) ;
- **sinon (cas actuel)** : compiler sur un poste de développement, puis copier le dossier `public/build/` (`manifest.json` + `assets/`) sur le serveur en remplaçant l'existant. Archive prête à envoyer : `build-a-deployer.zip` à la racine du projet, à décompresser dans `public/`.

Symptôme d'assets non à jour : la page s'affiche mais les nouveaux éléments sont mal stylés et les boutons ne réagissent pas (cas rencontré avec l'éditeur de texte). Vérification : les noms `app-XXXX.css` / `app-XXXX.js` du code source de la page doivent être ceux de `public/build/manifest.json`.

### Tâche planifiée (recommandée)

`routes/console.php` planifie `lives:cleanup` toutes les 5 minutes. Si l'hébergement le permet, ajouter la tâche cron Laravel :

```
* * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
```

### File d'attente et conversion des médias

Les versions allégées des médias ([qualites-media.md](fonctionnalites/qualites-media.md)) sont produites par ffmpeg en file d'attente (`QUEUE_CONNECTION=database`) :

- installer **ffmpeg** (fournit `ffprobe`) : `sudo apt install ffmpeg`, ou binaire statique + `FFMPEG_BINARY` / `FFPROBE_BINARY` ;
- lancer en permanence (Supervisor / systemd) : `php artisan queue:work --tries=2 --timeout=1900` ; sans processus permanent possible, cron chaque minute : `php artisan queue:work --stop-when-empty --timeout=1900` ;
- après chaque déploiement : `php artisan queue:restart` ;
- une fois (rattrapage des médias existants) : `php artisan media:transcode --missing`.

Carnet privé et paroles prophétiques sur le site (2026-10-05) : **aucune migration** ; **recompilation et copie de `public/build/`** et `php artisan view:cache`.

Migration du 2026-10-05 (suite) : `2026_10_05_200001_create_managers_and_prophecies_tables` (gestionnaires d'organisation et co-gestionnaires d'événement, [paroles prophétiques](fonctionnalites/paroles-prophetiques.md) et journal de prière), avec **recompilation et copie de `public/build/`** (recherche de personnes, Mon fil, messages d'encouragement) et `php artisan view:cache`.

Migration du 2026-10-05 : `2026_10_05_100001_create_events_tables` ([événements](fonctionnalites/evenements.md) : tables `events`, `event_images`, `event_participations`, `event_comments`, colonnes `testimonies.event_id` et `live_sessions.event_id`), avec **recompilation et copie de `public/build/`** (carrousel, invités, aperçu des images) et `php artisan view:cache`. Les images sont enregistrées sur le disque `public` (`storage/app/public/event-images`, lien `php artisan storage:link` déjà en place) ; PHP doit accepter des envois de 8 Mo.

Migrations du 2026-09-30 : `2026_09_30_100001_add_youtube_id_to_testimonies_table` ([YouTube](fonctionnalites/videos-youtube.md)), `2026_09_30_100002_create_testimony_proofs_table` ([preuves](fonctionnalites/preuves.md)), `2026_09_30_100003_create_testimony_views_table` ([recommandations](fonctionnalites/recommandations.md)), `2026_09_30_100004_add_camera_source_to_live_sessions_table` ([caméra IP](fonctionnalites/lives-camera-ip.md) ; LiveKit Ingress doit être disponible sur le projet), `2026_09_30_200001_add_proofs_public_to_testimonies_table` (accord de publication des preuves).

Migration de la photo de couverture : `2026_09_29_400001_add_cover_url_to_users_table` ([photo de couverture](fonctionnalites/photo-de-couverture.md) ; PHP doit accepter des envois de 8 Mo).

Migrations du 2026-09-29 (suite) : `2026_09_29_300001_refresh_testimony_share_urls` (liens de partage recalculés avec `SHARE_URL`) et `2026_09_29_300002_add_follows_integrity` ([abonnements](fonctionnalites/abonnements.md) : compteurs recalculés, contrainte « pas d'abonnement à soi-même »). Diagnostic des qualités : `php artisan media:check`.

Migrations du 2026-09-29 : `2026_09_29_100001_create_live_speakers_table` ([intervenants dans un direct](fonctionnalites/lives-intervenants.md)) et `2026_09_29_200001_add_phone_verification_to_users_table` ([téléphone de contact](fonctionnalites/telephone.md)), avec recompilation et copie de `public/build/`. Envoyer aussi le dossier `public/flags/` (drapeaux, suivi par git).

Migrations du 2026-09-28 : `2026_09_28_100001_add_renditions_to_media_files_and_testimonies` (qualités des médias) et `2026_09_28_200001_add_organization_fields_to_users_table` ([comptes organisation](fonctionnalites/comptes-organisation.md)) — aucune recompilation des assets.

Le menu **Qualité**, la lecture en boucle / automatique et la liste compacte du site ([affichage et lecture](fonctionnalites/affichage-et-lecture.md), 2026-09-28) demandent en revanche de **recompiler et copier `public/build/`** (nouveau JavaScript). Aucune migration ni variable d'environnement.

### Notifications push (FCM)

Les push ([notifications-push.md](fonctionnalites/notifications-push.md)) partent par la même file d'attente :

- console Firebase › Paramètres du projet › Comptes de service › « Générer une nouvelle clé privée » ; déposer le JSON **hors de `public/`** et de git (ex. `storage/app/private/firebase.json`) ;
- `.env` : `FIREBASE_CREDENTIALS=/chemin/absolu/firebase.json`, puis `php artisan config:cache` et `php artisan queue:restart` ;
- vérifier : `php artisan push:test adresse@exemple.com` (l'utilisateur doit s'être connecté une fois dans l'application sur son téléphone).

Sans `FIREBASE_CREDENTIALS`, aucun push n'est envoyé (notifications dans l'application inchangées). Aucune migration.

### Dépendances PHP

`vendor/` n'est pas versionné : après un changement de `composer.json` / `composer.lock` (2026-09-28 : `league/flysystem-aws-s3-v3` et mises à jour de sécurité), lancer `composer install --no-dev --optimize-autoloader` sur le serveur, ou copier `vendor/`. Pour les enregistrements de directs, la clé `LIVEKIT_RECORDING_S3_KEY` doit permettre lecture **et** écriture du bucket (dépôt des versions allégées).

### Après chaque déploiement

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan view:cache
php artisan queue:restart
```

Contrôle rapide de l'API utilisée par l'application : `https://testi.airid-africa.com/api/v1/community?tab=organizations` doit répondre `{"success":true,…}` (un 404 signifie que le code ou les routes ne sont pas à jour). Mettre aussi `APP_DEBUG=false` en production : un 404 affichait la trace de l'erreur et les chemins du serveur.

Puis recharger la page avec Ctrl+F5.

### Variables d'environnement à vérifier

| Variable | Pourquoi |
|---|---|
| `APP_URL` | Adresse du serveur (liens des e-mails, fichiers envoyés). |
| `SHARE_URL` | Adresse publique des **liens de partage** des témoignages (défaut : `https://testi.airid-africa.com`), indépendante de `APP_URL` ([détails](fonctionnalites/lien-de-partage.md)). |
| `APP_LOCALE` | `fr` pour les dates relatives en français. |
| `LIVEKIT_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET` | Service vidéo des [témoignages en direct](fonctionnalites/lives.md). Sans elles, les directs sont désactivés (message explicite). |
| `LIVEKIT_RECORDING_S3_*`, `LIVEKIT_RECORDING_PUBLIC_URL` | Stockage des enregistrements de directs (S3 : R2, AWS, B2…) et adresse publique de lecture. Sans elles, pas d'enregistrement ([détails](fonctionnalites/lives.md#enregistrement--témoignage-vidéo)). |
| `ANDROID_APP_SHA256_FINGERPRINTS` | Empreinte(s) du certificat release / Play App Signing, pour ouvrir les liens dans l'app Android ([détails](fonctionnalites/app-links.md)). |
| `IOS_APP_ID` | `TEAMID.bundle` de l'app iOS, pour les Universal Links. |
| `FFMPEG_BINARY`, `FFPROBE_BINARY` | Chemins de ffmpeg / ffprobe (défaut : dans le PATH) pour les [qualités des médias](fonctionnalites/qualites-media.md). |
| `MEDIA_TRANSCODING_ENABLED` | `false` si le serveur n'a pas ffmpeg : pas de conversion, lecture de l'original. |
| `MEDIA_TRANSCODE_TIMEOUT` | Durée maximale d'une conversion (défaut 1800 s). |
| `DB_QUEUE_RETRY_AFTER` | Doit dépasser `MEDIA_TRANSCODE_TIMEOUT` (ex. 1900), sinon une conversion longue est relancée en parallèle. |
| `FIREBASE_CREDENTIALS` | Chemin du JSON du compte de service Firebase pour les [notifications push](fonctionnalites/notifications-push.md). Vide = pas de push. Hors de `public/`, jamais versionné. |
| `FIREBASE_PROJECT_ID` | Facultatif : sinon `project_id` du fichier JSON. |
| `FCM_ENABLED` | Vide = push actifs dès que le fichier est lisible ; `false` pour les couper. |
| `MEDIA_TEMP_DIR` | Dossier de travail des conversions de fichiers S3 (défaut `storage/app/transcode`) : prévoir l'espace d'un original et d'une version. |
| `LIVEKIT_RECORDING_S3_VISIBILITY` | `public` pour un bucket AWS lu par ACL ; vide pour R2 / B2. |
| `DB_CHARSET` / `DB_COLLATION` | `utf8mb4` / `utf8mb4_unicode_ci` (valeurs par défaut) pour stocker les émojis. |

---

## 5. Tests

```bash
php artisan test
```

| Fichier | Couvre |
|---|---|
| `tests/Unit/RichTextTest.php` | Conversion gras/italique, échappement du HTML, texte brut des aperçus |
| `tests/Feature/TestimonyShareUrlTest.php` | Création du lien, exposition API, ouverture de la page, recalcul |
| `tests/Feature/LiveSessionTest.php` | Directs : droits, jetons, commentaires, limites, modération, webhooks, nettoyage |
| `tests/Feature/LiveRecordingTest.php` | Enregistrement des directs → témoignage vidéo |
| `tests/Feature/ProfileCoverTest.php` | Photo de couverture : envoi, remplacement, retrait, validation, API, bandeau du profil |
| `tests/Feature/TestimonyProofsAndYouTubeTest.php` | Preuves (formulaire, accès, API) et vidéos YouTube (liens, réservé aux administrateurs, lecteur) |
| `tests/Feature/RecommendationsTest.php` | Fil « Pour vous » (nouveau venu, intérêts, suivis), recommandations, historique de lecture |
| `tests/Feature/LiveCameraTest.php` | Direct avec caméra IP : RTMP, adresse du flux, pas de démarrage automatique, suppression à la fin, échec |
| `tests/Feature/HomePageTest.php` | Accueil : blocs selon le rôle (visiteur, membre, équipe), gestion des contenus par statut, filtres |
| `tests/Feature/AdminDashboardTest.php` | Tableau de bord de l'administration : blocs, dates en français, accès réservé |
| `tests/Feature/FollowingListTest.php` | Mes abonnements (site et API), onglet Personnes et nombre réel de témoignages |
| `tests/Feature/FollowTest.php` | Suivre (soi-même refusé, doublons, compteurs, notifications), Communauté, identifiants suivis, abonnés prévenus, format compact de la page de lecture |
| `tests/Feature/PhoneNumberTest.php` | Téléphone : formats, validation, profil, connexion par téléphone (numéro vérifié seulement), confidentialité de l'API |
| `tests/Feature/LiveStageTest.php` | Intervenants : file, une personne à la fois, droits LiveKit, expiration, fin, exclusion, coupure, pages |
| `tests/Feature/AppLinksTest.php` | Contenu des fichiers `assetlinks.json` et `apple-app-site-association` |
| `tests/Feature/FeaturedTestimoniesTest.php` | Règle « à la une » (7 jours, manuel, privé exclu), tri, API |
| `tests/Feature/MediaTranscodingTest.php` | Conversion ffmpeg simulée, `renditions` en base et dans l'API, commande `media:transcode` |
| `tests/Feature/OrganizationAccountTest.php` | Inscription organisation, mise à jour du profil, vérification / refus par l'admin, badge dans l'API |
| `tests/Feature/PushNotificationTest.php` | Push FCM : JWT OAuth2, corps du message, cache du jeton, suppression des jetons invalides, préférences, observateur, commande `push:test` |
| `tests/Feature/WebOrganizationRegistrationTest.php` | Inscription personne / organisation depuis le site, validation, ancienne saisie |
| `tests/Feature/WebOrganizationProfileEditTest.php` | Modification d'une organisation depuis « Modifier le profil », remise en attente |
| `tests/Feature/LiveStartNotificationTest.php` | Abonnés prévenus une seule fois au démarrage d'un direct (notification + push) |
| `tests/Feature/WebModerationNotificationTest.php` | Modération web et API : auteur et abonnés prévenus, carnet privé ignoré, motif en français |
| `tests/Feature/DocumentationPageTest.php` | Accès réservé, rendu HTML, liens internes, chemins refusés |
| `tests/Feature/DisplayAndPlaybackTest.php` | Grandes cartes / liste compacte (cookie, six listes, « Afficher plus »), menu Qualité, En boucle, suivants de la lecture auto |

`tests/Feature/ExampleTest.php` échoue actuellement : il interroge la base sans la migrer (absence de `RefreshDatabase`). Ce défaut est antérieur aux travaux documentés ici.

---

## 6. Maintenir cette documentation

À **chaque** ajout de fonctionnalité, modification de comportement ou correction :

1. ajouter une entrée datée dans [journal-des-modifications.md](journal-des-modifications.md) ;
2. mettre à jour la page concernée (ou créer `docs/fonctionnalites/<nom>.md` pour une nouvelle fonctionnalité, et l'ajouter au tableau en haut de ce fichier) ;
3. signaler tout changement de déploiement (migration, variable d'environnement, recompilation des assets) dans la section 4.

Modèle de page de fonctionnalité : *But · Utilisation · Fonctionnement technique · Fichiers · Déploiement · Limites connues*.
