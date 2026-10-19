# Journal des modifications

Entrées les plus récentes en premier. Chaque entrée indique ce qui change pour les utilisateurs, les développeurs et le déploiement.

---

## 2026-10-19 — Anciens directs affichés comme dans l'application

- **Utilisateurs** :
  - page d'un **direct terminé** : le lecteur vide et les réactions désactivées laissent place au **bilan de l'application** (fond violet nuit) : avatar du diffuseur avec pastille « TERMINÉ », « Direct terminé » (« Votre direct est terminé » pour le diffuseur), titre, « Diffuseur · raison de fin », « En chiffres » (« Bilan de votre direct ») avec Durée, Spectateurs au plus fort, Commentaires, Réactions, barres des réactions (❤️ 🙏 🙌 🛐 🔥, de la plus fréquente à la moins fréquente), bouton jaune « Témoigner sur ce direct » (déplacé de la colonne de droite, plus de doublon), « Voir la rediffusion » / « Relire la vidéo », « Revoir la discussion » (descend à la discussion) [lives.md](fonctionnalites/lives.md#directs-passés--revoir-le-chat-témoigner-après-la-fin-2026-10-19) ;
  - « Revoir le chat » devient « Revoir la discussion » (moments `mm:ss` / `h:mm:ss`, texte vide de l'application) ; « N témoignages sur ce direct » ;
  - liste « Directs récents » de `/lives` : chaque ligne ouvre le direct terminé (avatar, titre, diffuseur, « durée · N spectateurs · N commentaires », chevron), la rediffusion reste un lien secondaire.
- **Développeurs** : `resources/views/lives/partials/ended-summary.blade.php` ; `LiveSession::durationSeconds()`, `totalReactions()`, `formatClock()` (= `formatLiveDuration` / `formatLiveOffset`), `compactNumber()` (« 1,2 k » / « 1.2k ») ; `lang/{fr,en}/lives_replay.php` : `summary.*`, `recent.*`, `testimonies.count`, `chat.title` / `chat.empty` / `testify.button` (en) alignés sur l'application ; pagination de la discussion avec l'ancre `#live-chat-replay`. Pour un direct terminé, la page ne rend plus `data-live-video`, `data-live-overlay`, `data-live-react` ni la fenêtre des spectateurs (`live.js` en mode `replay` n'y touche pas).
- **Tests** : `PastLivesTest` (bilan, formulation du diffuseur, barres de réactions, ancre de la discussion, lignes « Récents » liées à `lives.show`, formats de durée et nombres compacts).
- **Déploiement** : code seulement (aucune migration) : `php artisan view:cache`, recompilation des assets (`npm run build`) et copie de `public/build/`.

---

## 2026-10-19 — Types d'événements gérés par les administrateurs

- **Utilisateurs** : administrateurs : nouvelle page *Administration › Types d'événements* (`/admin/types-evenements`) : ajouter un type (nom français et anglais, icône choisie parmi 19), le renommer, changer son icône, le réordonner (glisser-déposer ou flèches), l'activer / le désactiver, le supprimer (si des événements l'utilisent : « Remplacer par » un autre type actif, les événements y passent). Un type désactivé n'est plus proposé à la création d'un événement ; les événements qui l'utilisent le gardent. Les filtres et badges des événements affichent le nom et l'icône de la table [types-evenements.md](fonctionnalites/types-evenements.md).
- **API** : `GET event-types` (public ; `?all=1` pour les administrateurs) → `{ success, data: [{ id, slug, name, nameFr, nameEn, icon, position, isActive, eventsCount }] }` ; administrateurs : `POST admin/event-types`, `PUT admin/event-types/{id}` (slug non modifiable), `PUT admin/event-types/reorder` `{ ids }`, `DELETE admin/event-types/{id}` (`replace_with` ; 409 `{ errors.events, eventsCount }` si utilisé sans remplaçant valide ; 422 pour le dernier type actif) ; modérateurs 403. `POST|PUT events` : `type` doit être un type actif (sauf le type déjà enregistré en modification). `EventResource` gagne `typeIcon` ; `meta.types` de `GET events` = types actifs `[{ value, label, icon }]`.
- **Développeurs** : énumération `App\Enums\EventType` supprimée, remplacée par le modèle `App\Models\EventType` (cache `event_types`, `ICONS`, `labelFor()`, `iconFor()`, `faClass()`, `options()`), `App\Services\EventTypes`, `EventTypeInUseException`, `Api\EventTypeController`, `Web\EventTypeController`, vues `admin/event-types/`, `lang/{fr,en}/event_types.php` ; `Event::typeLabel()`, `typeIcon()`, `typeFaIcon()` ; `EventService::rules(currentType:)` ; tests `EventTypesTest` (18).
- **Déploiement** : migration `2026_10_19_500001_create_event_types_table` (`php artisan migrate --force`), `php artisan optimize:clear` (nouvelles routes), `php artisan view:cache`, recompilation des assets (`npm run build`) et copie de `public/build/`.

---

## 2026-10-19 — Aperçu de partage façon YouTube (WhatsApp, Facebook, Telegram, X, LinkedIn)

- **Utilisateurs** : un lien de témoignage collé dans WhatsApp, Facebook, Telegram, X ou LinkedIn affiche une **grande image 1200 × 630**, le titre « Titre - Auteur » en gras, le début du texte et le domaine. Vidéo : miniature (Bunny ou YouTube) avec **bouton lecture** et durée ; couverture pour les témoignages texte / audio (pastille « Audio ») ; sinon une carte TestiApp (dégradé bleu, rayons de soleil, titre, auteur, catégorie). Même chose pour les **événements**, les **requêtes de prière publiques** (jamais l'auteur d'une requête anonyme) et les **directs**. Un contenu non public ne révèle rien dans l'aperçu [apercu-de-partage.md](fonctionnalites/apercu-de-partage.md).
- **Site** : balises `og:*`, `twitter:*` et `<link rel="canonical">` sur `testimonies.show`, `videos.show`, `events.show`, `prayer.requests.show`, `lives.show` (YouTube : `og:video` vers `https://www.youtube.com/embed/{id}`).
- **Nouvelles routes** (publiques, sans session ni cookies, 404 si non public) : `GET /partage/temoignages/{id}.jpg` (`share-images.testimony`), `/partage/evenements/{id}.jpg`, `/partage/requetes/{id}.jpg`, `/partage/directs/{id}.jpg`, `/partage/site.jpg` — JPEG 1200 × 630, `Cache-Control: public, max-age=2592000`, adresse versionnée `?v=` qui change avec le contenu.
- **Développeurs** : `App\Services\ShareImage` (dessin GD, téléchargement 5 s max avec `Referer: {APP_URL}/` pour Bunny, lecture directe des fichiers `/storage/…` du site, cache `storage/app/share-images/`, secours retenté après 1 h), `TestimonyShareImage`, `PageShareImages`, `App\Support\ShareMeta`, composant `<x-share-meta>`, `Web\ShareImageController`, `lang/{fr,en}/share.php` ; tests `SharePreviewTest` (20).
- **Déploiement** : `php artisan optimize:clear` (nouvelles routes), `php artisan view:cache` ; vérifier que `SHARE_URL` est en https et que `storage/app/` est accessible en écriture (dossier `share-images` créé automatiquement). Contrôler avec <https://developers.facebook.com/tools/debug/> (« Scrape Again ») ; WhatsApp garde les aperçus en cache : ajouter `?v=2` au lien pour forcer un nouvel aperçu.

---

## 2026-10-19 — Paramètres identiques site / application, directs passés, crédit des vidéos YouTube

- **Utilisateurs** :
  - administrateurs : la page *Administration › Paramètres* et l'application affichent exactement les mêmes réglages (groupes Général, Publication, Modération et limites, Fonctionnalités, Notifications — interrupteur « Notifications push » —, Inscriptions ; libellés et descriptions en français ou en anglais) ; les anciens interrupteurs « audio / vidéo » du groupe Fonctionnalités, remplacés par le groupe Publication, ne sont plus affichés ; une valeur incorrecte (ex. texte dans un nombre) est refusée avec un message [parametres-globaux.md](fonctionnalites/parametres-globaux.md) ;
  - page d'un **direct terminé** : « Revoir le chat » (tous les messages visibles avec leur moment dans le direct), bouton **« Témoigner sur ce direct »** (Publier prérempli) et liste des témoignages publiés sur ce direct [lives.md](fonctionnalites/lives.md) ;
  - témoignage par **lien YouTube** : champs « Auteur de la vidéo », « Chaîne YouTube », « Lien de la chaîne » ; sous le lecteur : « Vidéo de {auteur} · {chaîne} », lien ouvert dans un nouvel onglet [videos-youtube.md](fonctionnalites/videos-youtube.md).
- **API** :
  - `GET admin/settings/schema` (administrateurs) → `{ success, data: { groups: [{ key, label, settings: [{ key, label, description, type, value }] }] } }` ; `PUT admin/settings` vérifie chaque clé et chaque type → 422 `errors.{clé}` (clé inconnue ou masquée, mauvais type), rien n'est enregistré ; modérateurs 403 ;
  - `GET lives/{id}/comments` : pour un direct terminé (ou avec `?page` / `?limit`), tous les commentaires visibles du plus ancien au plus récent, paginés (`limit` ≤ 100, `meta { currentPage, lastPage, total, perPage }`) ; chaque commentaire gagne `offsetSeconds` (direct en cours : tableau inchangé) ;
  - `live_id` (uuid) accepté par `POST testimonies` et `POST videos/upload-init` : direct existant, visible et terminé, sinon 422 `errors.live_id` ; `TestimonyResource.live` `{ id, title, startedAt } | null` ; `GET lives/{id}/testimonies` (témoignages publiés liés, paginés) ; `GET lives/{id}` gagne `testimoniesCount` et `canTestify` ;
  - `youtube_credit_author`, `youtube_credit_channel` (150 car.), `youtube_credit_url` (http/https, 300 car.) sur `POST|PUT testimonies`, pris en compte pour un témoignage YouTube seulement ; `TestimonyResource.youtubeCredit` `{ author, channel, url } | null`.
- **Développeurs** : `App\Services\SettingsSchema` (source unique de la page web et de l'API : `GROUPS`, `HIDDEN`, `groups()`, `validate()`, `update()`), `lang/{fr,en}/settings.php` ; `App\Services\LiveTestimonies` (`rules()`, `messages()`, `resolve()`), `LiveSession::linkedTestimonies()`, `canBeTestifiedBy()`, `offsetSecondsAt()`, `Testimony::live()`, partiel `lives/partials/replay` (page en `mode: replay`, sans LiveKit), `lang/{fr,en}/lives_replay.php` ; `App\Support\YouTubeCredit`, `Testimony::youtubeCredit()`, `lang/{fr,en}/youtube_credit.php` ; tests `GlobalSettingsTest` (8), `PastLivesTest` (10), `YouTubeCreditTest` (4).
- **Déploiement** : migrations `2026_10_19_300001_add_live_id_to_testimonies_table`, `2026_10_19_300002_add_youtube_credit_to_testimonies_table` et `2026_10_19_300003_add_push_notifications_setting` (réglage `push_notifications_enabled` créé s'il manque) (`php artisan migrate --force`), `php artisan optimize:clear` (nouvelles routes), `php artisan view:cache`, recompilation des assets (`npm run build`) et copie de `public/build/`.

---

## 2026-10-19 — Verset du jour : menu et « Versets précédents » comme l'application

- **Utilisateurs** : menu « Découvrir » : « Verset du jour » et « Versets précédents » ; bouton « Versets précédents » sur le verset du jour ; page d'historique refaite comme l'écran de l'application (aujourd'hui mis en avant, J'aime / prières / commentaires / témoignages, réactions en couleur, pluriels, anglais). [verset-du-jour.md](fonctionnalites/verset-du-jour.md)
- **Corrections** : couleurs hors charte de l'historique (`text-red-600`, `secondary-*` inexistant) remplacées par les jetons de la charte ; message 404 d'un jour sans verset traduit.
- **Tests** : `DailyVerseHistoryTest` (page alignée sur l'application, menu et lien).
- **Déploiement** : code seulement (aucune migration ; aucun script modifié).

---

## 2026-10-19 — Temps de prière partout, notifications push sur consentement

- **Utilisateurs** :
  - le **temps de prière** (5 minutes avant de valider) s'applique aussi au « Je prie » du **verset du jour**, au « J'ai prié » des **paroles prophétiques** et aux réactions « Je prie » des **événements** et **sessions de prière** ; retirer sa prière reste immédiat, les réactions 🙏 des directs ne sont pas concernées [temps-de-priere.md](fonctionnalites/temps-de-priere.md) ;
  - chaque notification part aussi en push, **sauf** si sa catégorie est coupée : nouvelles catégories *Nouveaux abonnés*, *Événements et sessions de prière*, *Mon compte*, *Modération* (équipe) ; les partages relèvent désormais de *Réactions* ; sur le site, décocher un interrupteur de notification est enfin enregistré [notifications-push.md](fonctionnalites/notifications-push.md) ;
  - le réglage du back-office `push_notifications_enabled` à faux coupe réellement tous les push.
- **API** :
  - `POST daily-verse/{id}/pray/start`, `POST prophecies/{id}/pray/start`, `POST events/{id}/pray/start`, `POST prayer/sessions/{id}/pray/start` → `{ success, data: { startedAt, requiredSeconds, confirmableAt } }` ; ajouter « pray » (verset), « J'ai prié » (`prophecies/{id}/prayers`) ou une réaction du groupe « je prie » (`events|prayer/sessions/{id}/reactions`) sans temps écoulé → 422 `{ success: false, message, errors: { prayer_focus }, remaining_seconds }` ;
  - `GET|PUT users/me/settings` : nouvelles clés `push_follows`, `push_events`, `push_account`, `push_moderation` (booléens, `true` par défaut).
- **Développeurs** : `PrayerFocus::KINDS` (+ `daily_verse`, `prophecy`, `event`, `prayer_session`), `DailyVerses::startPrayer`, `Prophecies::startPrayer` / `pray(..., $by)`, `ContentInteractions::startPrayer` ; `PushNotifications::PREFERENCES` (public, tous les types), `CATEGORIES`, `GLOBAL_SETTING`, `globallyEnabled()` ; formulaires `data-prayer-focus-kind` (`resources/js/prayer-focus.js`), textes `prayer_focus.modal.for_*` / `confirm_*` ; tests `PrayerFocusOtherKindsTest`, `PushConsentTest`.
- **Déploiement** : migration `2026_10_19_400001_add_push_categories_to_user_settings_table` (`php artisan migrate`), `php artisan optimize:clear` (routes), `php artisan view:cache`, recompilation des assets (`npm run build`) et copie de `public/build/`. Pendant la transition de l'application, `PRAYER_FOCUS_SECONDS=0` désactive le temps de prière.

---

## 2026-10-18 — Connexion par téléphone, Google à l'inscription, nom en double dans les notifications

- **Utilisateurs** :
  - connexion avec le **numéro de téléphone** indiqué à l'inscription et le mot de passe, sur le site (choix « E-mail » / « Téléphone » sur `/login`) et dans l'application ; la connexion par SMS exige toujours un numéro vérifié [telephone.md](fonctionnalites/telephone.md) ;
  - bouton Google sur la page de création de compte du site (compte créé s'il est nouveau, connexion sinon) [connexion-google.md](fonctionnalites/connexion-google.md) ;
  - page des notifications du site : le nom de l'auteur n'est plus répété (« Marie Marie a aimé votre commentaire » → « Marie a aimé votre commentaire »).
- **API** : `POST /api/v1/auth/login` accepte `{ email, password }` **ou** `{ phone, phone_country, password }` (`phone` national tel que tapé ou « +229… », `phone_country` ISO facultatif) ; même réponse, même 401 pour un identifiant inconnu, un mauvais mot de passe ou un compte sans mot de passe, 403 si banni ; limite de 10 essais par minute (IP + identifiant, 429).
- **Développeurs** : `App\Services\PhoneLogin` (E.164 puis anciens numéros : valeur brute, chiffres seuls), `LoginRequest::baseRules()` / `baseMessages()` partagés avec `Web\AuthController::login`, limiteur `login` (`AppServiceProvider`) sur `POST /api/v1/auth/login` et `POST /login`, partiel `auth/partials/google-signin`, paramètres `phoneLabel` / `phoneHint` / `phoneErrors` de `components/phone-input`, clés `account.login.method*`, `phone_hint`, `email_required` ; tests `PhoneLoginTest`, `WebNotificationActorNameTest`, `GoogleAuthTest` complété.
- **Déploiement** : `php artisan optimize:clear` (routes) et `php artisan view:cache`. Aucune migration, aucune recompilation des assets (scripts propres aux pages).

---

## 2026-10-18 — « J'aime » sur les commentaires, types de témoignages autorisés

- **Utilisateurs** :
  - cœur « J'aime » sous les commentaires des témoignages (réponses comprises), des événements et du verset du jour ; l'auteur est prévenu la première fois (« Marie a aimé votre commentaire », push selon le réglage des « j'aime ») [jaime-commentaires.md](fonctionnalites/jaime-commentaires.md) ;
  - administrateurs : section **Publication** dans les paramètres (texte, audio, vidéo, liens YouTube) et, sur la fiche d'un membre, exception par type (Par défaut / Autorisé / Interdit) ; un lien YouTube peut être accordé à un profil non administrateur ; la page Publier ne propose que les types autorisés [types-de-temoignages.md](fonctionnalites/types-de-temoignages.md).
- **API** :
  - `POST|DELETE comments/{id}/like`, `events/{id}/comments/{commentId}/like`, `daily-verse/{id}/comments/{commentId}/like` → `{ success, data: { likesCount, isLikedByMe } }` (404 si invisible) ; `likesCount` et `isLikedByMe` dans `CommentResource`, `EventCommentResource`, `DailyVerseCommentResource` ; notification `comment_liked` (`commentId` ajouté à `NotificationResource`) ;
  - `allowed_testimony_types` dans la ressource utilisateur « owner » (`/me`, `/users/me`, `/auth/me`) ; `GET me/publishing` ; `GET|PUT admin/users/{id}/publishing` (administrateurs) ; réglages `testimony_type_*_enabled` dans `GET|PUT admin/settings` ; `POST testimonies` et `POST videos/upload-init` répondent 403 `{ errors: { type: [...] } }` pour un type désactivé (carnet privé compris ; modification jamais bloquée).
- **Développeurs** : `App\Services\CommentLikes` (instance par requête), `App\Models\CommentLike`, `Api\CommentLikeController` (partagé par le site), composant `components/comment-like`, `resources/js/comment-likes.js` ; `App\Services\TestimonyTypePolicy`, `Api\PublishingController`, `Web\AdminController::updatePublishing()` ; `lang/{fr,en}/comment_likes.php`, `lang/{fr,en}/publishing.php` ; tests `CommentLikesTest`, `TestimonyTypePolicyTest`.
- **Déploiement** : migrations `2026_10_18_100001_create_comment_likes_table` et `2026_10_18_200001_add_testimony_type_settings` (`php artisan migrate`), puis `php artisan optimize:clear` (nouvelles routes, réglages en cache) ; recompilation des assets (`npm run build`) et copie de `public/build/`.

---

## 2026-10-18 — Temps de prière avant « Je prie »

- **Utilisateurs** : « Je prie » sur une requête de prière ou un témoignage ouvre un écran plein « Temps de prière » (décompte de 5 min, en pause quand l'onglet est caché, verset Jacques 5:16) ; à la fin, « J'ai prié… » puis « Valider ma prière » enregistre la prière. Fermer l'écran n'enregistre rien ; retirer sa prière reste immédiat. [temps-de-priere.md](fonctionnalites/temps-de-priere.md)
- **API** : `POST prayer/requests/{id}/pray/start`, `POST testimonies/{id}/pray/start` → `{ startedAt, requiredSeconds, confirmableAt }`. `POST prayer/requests/{id}/pray` et `POST testimonies/{id}/reactions` (groupe prière) répondent **422** `{ errors: { prayer_focus: [...] }, remaining_seconds }` quand ils ajoutent une prière sans temps de prière écoulé. **Les anciennes versions de l'application reçoivent 422 jusqu'à leur mise à jour.**
- **Développeurs** : `config/prayer.php`, `App\Services\PrayerFocus`, `App\Exceptions\PrayerFocusRequired`, `lang/{fr,en}/prayer_focus.php`, composant `components/prayer-focus-modal`, `resources/js/prayer-focus.js` ; routes web `prayer.requests.pray.start`, `testimonies.prayer.start` ; `tests/TestCase.php` désactive la règle (`prayer.focus_seconds = 0`), `PrayerFocusTest` l'active.
- **Déploiement** : `PRAYER_FOCUS_SECONDS=300` dans `.env` (**0** pendant la transition, tant que l'application mise à jour n'est pas diffusée), `php artisan optimize:clear`, recompilation des assets et copie de `public/build/`. Aucune migration. Cache persistant requis (`CACHE_STORE=database` par défaut).

---

## 2026-10-19 — Verset du jour : menu et « Versets précédents » comme l'application

- **Utilisateurs** : menu « Découvrir » : « Verset du jour » et « Versets précédents » ; bouton « Versets précédents » sur le verset du jour ; page d'historique refaite comme l'écran de l'application (aujourd'hui mis en avant, J'aime / prières / commentaires / témoignages, réactions en couleur, pluriels, anglais). [verset-du-jour.md](fonctionnalites/verset-du-jour.md)
- **Corrections** : couleurs hors charte de l'historique (`text-red-600`, `secondary-*` inexistant) remplacées par les jetons de la charte ; message 404 d'un jour sans verset traduit.
- **Tests** : `DailyVerseHistoryTest` (page alignée sur l'application, menu et lien).
- **Déploiement** : code seulement (aucune migration ; aucun script modifié).

---

## 2026-10-19 — Temps de prière : « encore 4 min 54 s » après la fin du décompte

- **Cause** : le serveur remettait le décompte à zéro à chaque appel `…/pray/start` ; un appel en double ou reçu en retard (double appui, écran recréé, requête lente) juste avant la validation faisait refuser la prière alors que l'écran du site ou de l'application avait fini ses 5 minutes.
- **Correction** : un nouvel appel garde le premier début s'il date de moins d'une heure (`PrayerFocus::RESTART_AFTER_SECONDS`) ; le décompte de 5 min reste imposé par l'écran, et chaque prière enregistrée consomme son début. Refus « trop tôt » journalisés. [temps-de-priere.md](fonctionnalites/temps-de-priere.md)
- **Tests** : `PrayerFocusTest` (début en double ou tardif, début de plus d'une heure).
- **Déploiement** : code seulement (aucune migration, aucun asset).

---

## 2026-10-19 — Images en 403 en production (lien `public/storage` refusé par Apache)

- **Cause** : Apache refuse le lien symbolique `public/storage` (`AH00037: Symbolic link not allowed or link target not accessible`) ; comme le lien existait, la route de secours de Laravel n'était jamais appelée et chaque image répondait 403.
- **Correction** : le lien n'est plus utilisé par défaut. Le déploiement le retire (`App\Support\PublicStorageLink::sync()`, appelé par `_deploy`) et Laravel sert `/storage/…` (`PublicStorageController`, cache 7 jours, lecture partielle). Nouvelle commande `php artisan storage:public-link`. Variable `STORAGE_PUBLIC_LINK=true` pour revenir au lien sur un serveur qui l'accepte. [README §4](README.md)
- **Développeurs** : `config('filesystems.public_link')`, tests ajoutés dans `tests/Feature/PublicStorageTest.php`.
- **Déploiement** : aucune migration ; après envoi du code, l'appel `_deploy` du pipeline retire le lien. En urgence : supprimer `public/storage` par FTP.

---

## 2026-10-18 — « Je prie » du site aligné sur l'application

- **Utilisateurs** : page d'un témoignage : un seul bouton « Je prie » (au lieu de Prière / Amen / Adorer Dieu), qui pose ou retire la prière en un appui, avec mise à jour immédiate ; nombre de prières sur les cartes. [reactions.md](fonctionnalites/reactions.md)
- **Développeurs** : `TestimonyReactions::togglePrayer()`, réponse du site enrichie (`is_prayed`, `is_liked`), `resources/js/videos.js` ; tests `WebPrayToggleTest`, `WorshipReactionTest` adapté.
- **Déploiement** : recompilation des assets et copie de `public/build/`. Aucune migration.

---

## 2026-10-17 — Témoignage de conversion : module terminé, corrections après interruption

- **Utilisateurs** : bloc « Témoignages de conversion » sur l'accueil ; invitation à se connecter pour les visiteurs ; modérateurs : filtre « Témoignages de conversion », badge et détails (année, lieu, verset) dans la liste et la fiche. [temoignage-de-conversion.md](fonctionnalites/temoignage-de-conversion.md)
- **Corrections** : erreurs de syntaxe PHP (`lang/*/` dans un commentaire fermait le commentaire : `Continents`, `Countries`, `PhoneNumber`), textes manquants (`lang/*/prophecies.php`, `journal.php`, `support.php`, compléments de `admin.php` et `verification.php`, textes français d'origine), erreurs 500 (`EventLayout::LABELS` supprimée, `Locales::number(null)`), versets d'encouragement remis en français (la Bible n'est jamais traduite).
- **Déploiement** : migration `2026_10_15_200001_…` (erreur 500 sur `/temoignages-de-conversion` sans elle), recompilation des assets.

---

## 2026-10-15 — Anglais, témoignage de conversion, événements passés, design « toujours », push fiables

- **Utilisateurs** :
  - le site, l'API, les messages d'erreur, les notifications et les push existent en anglais ; sélecteur `FR | EN` sur le site, langue du compte enregistrée [langues.md](fonctionnalites/langues.md) ;
  - témoignage de conversion (un par compte personnel, année, lieu, verset), section « Témoignages de conversion » et mise en avant sur le profil [temoignage-de-conversion.md](fonctionnalites/temoignage-de-conversion.md) ;
  - page « Événements passés » avec le nombre de témoignages de chaque événement [evenements.md](fonctionnalites/evenements.md) ;
  - super-administrateurs : bouton « Envoyer une notification de test » sur la fiche d'un membre.
- **API** :
  - langue selon `Accept-Language` (français par défaut), `Content-Language` dans la réponse ;
  - `GET`/`PUT /me` (`locale`), `GET /me/conversion-testimony`, `GET /users/{id}/conversion-testimony`, `GET /testimonies?kind=conversion`, `POST /me/push-test` ;
  - `TestimonyResource` : `kind`, `isConversion`, `conversion` ; profil : `has_conversion_testimony`, `locale` ;
  - `GET /events?when=past` (`testimonies_count`, `thumbnail_url`) ;
  - `GET`/`PUT /me/verse-style` renvoient `design_of_the_day` et `effective_design_id` ; « toujours utiliser mon design » vérifié et testé.
- **Corrections (push)** : les jetons d'un appareil sont supprimés à la déconnexion (session rattachée au jeton) et à la suppression du compte ; chaque échec d'envoi est journalisé avec le compte.
- **Développeurs** : `App\Support\Locales`, `NotificationText`, middleware `SetLocale`, fichiers `lang/fr|en/*.php`, test de parité `tests/Unit/TranslationKeysTest.php` ; libellés des énumérations et des modèles traduits (constantes remplacées par des méthodes : `Event::reportReasons()`…) ; `ConversionTestimonies`, `TestimonyKind`, `Event::finished()`, `PushNotifier::sendTest()` ; `tests/TestCase.php` envoie les requêtes sans `Accept-Language` ; tests `LocaleTest`, `ConversionTestimonyTest`, `PastEventsTest`, `VerseDesignAlwaysTest`, `PushReliabilityTest`.
- **Déploiement** : migration `2026_10_15_200001_add_locale_conversion_and_push_device_fields`, dossier `lang/`, recompilation des assets et copie de `public/build/`. Application mobile : envoyer `Accept-Language` et `PUT /me { locale }`, et `device_token` à la déconnexion.

---

## 2026-10-15 — Vidéos Bunny Stream lisibles sur le site et dans l'application

- **Utilisateurs** :
  - une vidéo envoyée à Bunny est lisible dès la fin de l'encodage, sur le site (HLS) comme dans l'application ;
  - sur le site, une vidéo part maintenant directement à Bunny depuis le navigateur, avec une barre de progression, jusqu'à 2 Go ; le témoignage affiche « Vidéo en cours de traitement » ;
  - l'auteur est prévenu si l'encodage échoue. [videos-bunny-stream.md](fonctionnalites/videos-bunny-stream.md)
- **Cause du problème en production** :
  - le webhook répondait 200 vide dans tous les cas : bibliothèque différente de la configuration, GUID absent de `testimonies.bunny_video_id`, ou erreur de l'API Bunny avalée après la réponse ;
  - surtout, une vidéo prête gardait `media_url` vide, que le site et les anciennes versions de l'application lisent.
- **API** :
  - le webhook répond `{ ok, action: updated | ignored | not_found }` et journalise chaque appel ;
  - l'objet `video` expose `status` (`processing`, `ready` ou `failed`), `playback_url`, `mp4_url`, `thumbnail_url` et `duration`, sans aucune URL tant que la vidéo n'est pas lisible ; `hls_url` et `mp4_urls` sont gardés ;
  - `mediaUrl` vaut la playlist HLS d'une vidéo Bunny prête ;
  - push `video_failed` à l'auteur, notification `video_stuck` aux super-administrateurs.
- **Développeurs** :
  - service unique `BunnyVideoSync` ;
  - commande `videos:sync-bunny-status {--guid=}`, planifiée toutes les 10 minutes et lancée par `_deploy` ;
  - en lecture, `BUNNY_STREAM_CDN_HOSTNAME` est accepté, les valeurs du `.env` sont nettoyées, et `BUNNY_STREAM_MP4_FALLBACK` est ajouté ;
  - envoi direct depuis le site : `Web\TestimonyController::store` (`bunny_upload`), routes `testimonies.video.signature` et `testimonies.video.complete` ;
  - front : `resources/js/bunny-upload.js` (`tus-js-client`) et `resources/js/hls-player.js` (`hls.js`) ;
  - helpers `Testimony::isHlsMedia()`, `isVideoProcessing()`, `mp4PlaybackUrl()` ;
  - tests : `tests/Feature/BunnyVideoSyncTest.php`, `BunnyStreamTest` adapté.
- **Déploiement** :
  - migration `2026_10_15_100001_add_video_tracking_to_testimonies_table` ;
  - nouveaux paquets npm (la CI lance `npm ci`) et recompilation des assets ;
  - variables à vérifier : `BUNNY_STREAM_LIBRARY_ID`, `BUNNY_STREAM_API_KEY`, `BUNNY_STREAM_CDN_HOST`, `BUNNY_STREAM_MP4_FALLBACK` (facultative) ;
  - le déploiement lance `videos:sync-bunny-status` : la vidéo `c51171c5-…` est corrigée si un témoignage porte son GUID.

---

## 2026-10-14 — Badge « Approuvé » des comptes

- **Utilisateurs** : le badge de vérification devient le badge « Approuvé » (personnes et organisations). Un administrateur peut aussi l'attribuer ou le retirer directement, avec un motif ; la personne est prévenue. [badge-approuve.md](fonctionnalites/badge-approuve.md)
- **API** :
  - `POST` et `DELETE /admin/users/{id}/approval { reason }` (administrateurs) ;
  - `GET /admin/users?approved=1&approval_source=` ;
  - toutes les ressources utilisateur et auteur exposent `is_approved`, `account_type` (`individual` ou `organization`) et `approval_badge` (`approved_personal`, `approved_organization` ou `null`).
- **Développeurs** :
  - colonne `users.approval_source` (`verification_form`, `organization_review` ou `admin_manual`), renseignée par `PersonalVerification`, `OrganizationAccounts` et le nouveau service `AccountApproval` ;
  - `User::isApproved()`, `approvalBadge()` et `approvalFieldsFor()` ;
  - `App\Policies\UserPolicy` ;
  - audit `user.badge_granted` et `user.badge_revoked` ;
  - back-office : section « Badge Approuvé » sur la fiche utilisateur, onglet « Comptes approuvés » ;
  - tests : `tests/Feature/AccountApprovalTest.php`.
- **Déploiement** : `php artisan migrate --force` (`2026_10_14_200001_add_approval_source_to_users_table`, qui reprend la source des badges existants) ; recompiler les assets.

---

## 2026-10-14 — Gestion des témoignages (back-office, une, corbeille, statistiques)

- **Utilisateurs** :
  - un refus porte toujours un motif, envoyé à l'auteur ;
  - en modifiant un témoignage refusé, l'auteur le renvoie en modération (« Modifier et renvoyer ») ; un témoignage publié qu'il modifie repasse aussi en relecture (modération obligatoire) ;
  - nouveau label « Témoignage approuvé », attribué par un administrateur ;
  - « À la une » est désormais choisi par l'équipe (ordre, dates), complété par les plus récents. [gestion-des-temoignages.md](fonctionnalites/gestion-des-temoignages.md)
- **Équipe** : back-office `/moderation` (onglets En attente, Approuvés, Refusés, À la une et Corbeille, recherche, filtres, actions groupées), fiche avec historique et statistiques, tableau de bord `/moderation/tableau-de-bord`, journal d'audit. La corbeille se restaure pendant 30 jours ; la suppression définitive est réservée au super-administrateur.
- **API** :
  - `POST /testimonies/{id}/view` (vue dédoublonnée 30 min, `watched_seconds`) ;
  - `GET /testimonies?status=&verified=1&featured=1&sort=recent|popular|most_viewed` ;
  - `GET /testimonies/featured` : ordre manuel, champ `featured_source` ;
  - `POST /moderation/{id}/reject` exige `reason` ou `rejection_reason` (422 sinon) ;
  - ressource : `badges`, `featured_source`, `isVerified`, `verifiedAt`, `featuredOrder`, `featuredFrom`, `featuredUntil`, `moderatedAt` ;
  - notification `testimony_resubmitted` aux modérateurs ;
  - un témoignage en attente ou refusé renvoie 404 sauf à son auteur et à la modération ;
  - `GET /testimonies/{id}` ne compte plus une vue à chaque lecture (30 min par personne).
- **Développeurs** :
  - `config/testimonies.php` ;
  - services `TestimonyModeration` (commun à l'API, au site et aux actions groupées), `FeaturedTestimonies`, `TestimonyStats`, `AdminAudit` ;
  - `App\Policies\TestimonyPolicy` ;
  - commandes `testimonies:aggregate-stats`, `testimonies:purge-trash`, `users:super-admin` ;
  - `User::isSuperAdmin()`.
  - **La vidéo Bunny n'est plus supprimée à la mise à la corbeille, seulement à la suppression définitive.**
  - Tests : `tests/Feature/TestimonyManagementTest.php` ; `BunnyStreamTest` adapté.
- **Déploiement** :
  - `php artisan migrate --force` (3 migrations `2026_10_14_*`) ;
  - recompiler les assets ;
  - désigner le super-administrateur : `php artisan users:super-admin {email}` ;
  - le cron `schedule:run` existant lance les deux nouvelles tâches.

---

## 2026-10-13 — Designs du verset du jour

- **Utilisateurs** :
  - le verset du jour s'affiche dans l'un des **12 designs** de l'app (Aube, Nuit étoilée… Ardoise), le **design du jour** étant le même pour tous ;
  - sur le site, la carte de l'accueil et le **premier écran** de la page du verset prennent ce design. Le reste de la page apparaît au défilement ;
  - un sélecteur (vignette « Aa ») permet de choisir un autre design, pour la journée ou « toujours », ainsi que la police, la taille et l'alignement. Ces choix suivent le compte entre l'app et le site ; un visiteur les garde dans son navigateur ;
  - les liens partagés affichent une **image du verset** (1080 × 1350) dans le design du jour ;
  - le verset du jour change maintenant à **minuit, heure du Bénin** (auparavant à 1 h, minuit UTC). [designs-du-verset.md](fonctionnalites/designs-du-verset.md)
- **API** :
  - `GET /verse-designs` (public, cache 24 h) ;
  - `GET` et `PUT /me/verse-style` (422 sur toute valeur hors catalogue) ;
  - `GET /daily-verse` ajoute `design_of_the_day`, `user_style` (`null` pour un visiteur), `effective_design_id` et `share_image_url`. Les autres champs ne changent pas.
- **Développeurs** :
  - catalogue `config/verse_designs.php` (source unique ; ne jamais renommer un id) ;
  - `App\Services\VerseDesignOfTheDay` (algorithme de l'app, Africa/Porto-Novo), `VerseStyles`, `VerseShareImage` ; `App\Support\VerseColors` (portage de `verse_design.dart`) et `VerseDesigns` ;
  - script `scripts/generate-verse-colors.php`, qui génère `config/verse_design_colors.php` et `resources/css/verse-designs.css` ;
  - composant `<x-verse-of-the-day-card />`, `resources/css/verse.css`, `resources/js/verse-style.js`, polices dans `resources/fonts/` ; pile `@stack('meta')` dans le layout ;
  - `DailyVerse::today()` utilise Africa/Porto-Novo ;
  - `DailyVerse::forDate()` utilise `whereDate` : sous SQLite, le verset programmé n'était jamais trouvé, ce qui faisait toujours tomber sur le repli ;
  - les images de partage d'une date sont supprimées quand son verset est modifié ;
  - route web `PUT /verset-du-jour/style` ;
  - tests : `tests/Feature/VerseDesignTest.php`.
- **Déploiement** :
  - `composer install --no-dev --optimize-autoloader` (nouvelle dépendance `intervention/image`, GD requis) ;
  - `php artisan migrate --force` (`2026_10_13_100001_create_verse_style_preferences_table`) ;
  - **recompiler les assets** et copier `public/build/` ;
  - aucune variable d'environnement.

---

## 2026-10-12 — Vidéos des témoignages sur Bunny Stream

- **Utilisateurs** : un témoignage vidéo est envoyé par l'application **directement à Bunny Stream**, sans passer par le serveur LWS. Il n'apparaît dans les listes publiques et dans la modération qu'une fois prêt. L'auteur le voit pendant l'envoi et l'encodage, puis reçoit une notification « Vidéo prête ». Un envoi abandonné depuis plus de 24 h passe en échec. [videos-bunny-stream.md](fonctionnalites/videos-bunny-stream.md)
- **API** :
  - `POST /videos/upload-init` : mêmes champs que `POST /testimonies`. Renvoie `video_id`, `testimony_id`, `library_id`, `bunny_video_id`, `signature`, `expiration` et `tus_endpoint` ;
  - `POST /videos/{id}/upload-signature` : nouvelle autorisation pour la même vidéo. Propriétaire seulement, statut `uploading` seulement ;
  - `POST /videos/{id}/upload-complete` : passage en `processing` ;
  - webhook public `POST /webhooks/bunny` ;
  - nouvel objet `video` dans la ressource témoignage : `status`, `hls_url`, `thumbnail_url`, `mp4_urls` (objet indexé par hauteur), `duration`. Il vaut `null` sans vidéo ;
  - notification push `{ type: "video_ready", testimony_id }`.
- **Développeurs** :
  - nouveaux services `BunnyStream` (client HTTP) et `BunnyStreamVideos` (cycle de vie) ;
  - `TestimonyCreator`, extrait de `Api\TestimonyController::store`, est partagé avec `upload-init`. `ContentActionException` accepte maintenant des erreurs par champ ;
  - nouveau scope `Testimony::videoPlayable()`, appliqué dans `published()` et `pending()` ;
  - la vidéo Bunny est supprimée quand le témoignage est supprimé (événement `deleted`, échec journalisé) ;
  - nouvelles commandes `videos:expire-uploads` (planifiée chaque heure) et `videos:migrate-to-bunny {--dry-run} {--limit=10}` ;
  - tests : `tests/Feature/BunnyStreamTest.php`.
- **Déploiement** :
  - `php artisan migrate --force` (`2026_10_12_100001_add_bunny_stream_to_testimonies_table`) ;
  - `.env` : `BUNNY_STREAM_LIBRARY_ID`, `BUNNY_STREAM_API_KEY`, `BUNNY_STREAM_CDN_HOST`, puis `php artisan config:clear` ;
  - dans Bunny : webhook `https://temoignage.as-krea.com/api/v1/webhooks/bunny` et **MP4 Fallback** activé ;
  - aucune recompilation des assets.

---

## 2026-10-10 — Notifications push : hébergement LWS, envoi parallèle, `device-tokens`

- **Utilisateurs** : plus de push pour sa propre action (ex. modérateur qui approuve son propre témoignage). `push:test` envoie « Test ✅ », affiche le projet Firebase utilisé, l'erreur FCM de chaque appareil en échec (avec une explication pour `SenderId mismatch`) et, si FCM n'est pas configuré, la raison exacte (`FcmClient::configurationProblem()` : variable vide, fichier introuvable avec le chemin cherché, fichier invalide, `FCM_ENABLED=false`). [notifications-push.md](fonctionnalites/notifications-push.md)
- **API** : alias `POST` / `DELETE /api/v1/device-tokens` (mêmes règles que `devices/token`, conservé pour l'application) ; `platform` devient facultative ; un jeton déjà connu sous un autre compte est réattribué à l'utilisateur connecté. Les push portent une clé `id` (objet principal à ouvrir) en plus des clés détaillées.
- **Développeurs** : nouveau `App\Services\PushNotifier::sendToUser()` (envoi immédiat, résumé, aucune exception), utilisé par `SendPushNotificationJob` et `push:test` ; `FcmClient::sendMulticast()` envoie à plusieurs appareils en parallèle (`Http::pool`) ; colonne `device_tokens.last_used_at` mise à jour à l'enregistrement et à chaque envoi réussi. Tâche dans la file `push` (`PUSH_QUEUE`) ; `PUSH_DELIVERY=after_response` pour envoyer après la réponse HTTP sans file. Choix maintenu : client FCM HTTP v1 maison, sans `kreait/laravel-firebase` ; canal Android toujours réglable par `FCM_ANDROID_CHANNEL` (défaut `testi_notifications`).
- **Déploiement** :
  - `php artisan migrate --force` (`2026_10_10_000001_add_last_used_at_to_device_tokens_table`) ;
  - `.env` : `FIREBASE_CREDENTIALS`, `PUSH_DELIVERY=queue`, `PUSH_QUEUE=push`, puis `php artisan config:cache` ;
  - LWS : tâche cron chaque minute `php artisan queue:work --queue=push,default --stop-when-empty --max-time=50 --timeout=1900` (en plus de `schedule:run`), ou `PUSH_DELIVERY=after_response` ;
  - projet Firebase de référence : **`as-krea`** (serveur et application). Clé de compte de service : `storage/app/as-krea-firebase-adminsdk-fbsvc-13c56c0cb4.json`. Essai du 2026-10-10 : authentification réussie, mais FCM répond `SenderId mismatch` (403), car l'application mobile était encore configurée sur l'ancien projet `testi-app-flutter`. Application passée sur `as-krea` le même jour (`android/app/google-services.json`, `lib/firebase_options.dart` : expéditeur `566166419345`) ; il faut la recompiler, la réinstaller et se reconnecter pour obtenir un jeton `as-krea`. Deux anciens jetons `NotRegistered` ont été supprimés ;
  - `FCM_TIMEOUT=30` conseillé : il s'applique désormais aussi au délai de connexion (10 s fixes auparavant) ;
  - aucune recompilation des assets.

---

## 2026-10-10 — Connexion avec Google sur le site

- **Nouveau** : sur la page de connexion du site (`/login`), un bouton « Continuer avec Google » (bouton officiel Google Identity Services) s'ajoute sous le formulaire e-mail. Le compte est retrouvé, lié ou créé avec les mêmes règles que dans l'application, puis la session s'ouvre. [connexion-google.md](fonctionnalites/connexion-google.md)
- **Développeurs** : logique Google déplacée dans `App\Services\GoogleSignIn`, utilisée par `Api\GoogleAuthController` et `Web\AuthController::google` (`POST /login/google`).
- **Déploiement** :
  - console Google Cloud : ajouter `https://testi.airid-africa.com` aux **origines JavaScript autorisées** du client OAuth Web ;
  - `GOOGLE_WEB_CLIENT_ID` doit être dans le `.env` du serveur (bouton masqué sinon) ;
  - `php artisan view:cache`. Ni migration supplémentaire, ni recompilation des assets.

---

## 2026-10-10 — Heure des paroles prophétiques, « Adorer Dieu », déconnexion depuis le profil

- **Paroles prophétiques** : on note aussi l'**heure** à laquelle la parole a été reçue (facultative ; l'heure actuelle par défaut pour une nouvelle parole). Affichage « Reçue le 5 octobre 2026 à 14:30 » sur le site (carnet, page de la parole, témoignage d'accomplissement, Mon fil) et dans l'application. API : champ `received_time` (« HH:MM ») en écriture, `receivedTime` dans `ProphecyResource` et la version publique. [paroles-prophetiques.md](fonctionnalites/paroles-prophetiques.md)
- **Réaction « Adorer »** renommée **« Adorer Dieu »** (site, directs, application ; la valeur `worship` ne change pas).
- **Directs** : seuls le **diffuseur** et un **administrateur** peuvent couper un direct (`LiveSession::canBeEndedBy`, champ `canEnd` de l'API) ; un modérateur garde la modération du chat et des intervenants, mais ne coupe plus le direct de quelqu'un d'autre (403). Application : la page d'un événement ouvre la vue spectateur pour le public (même en préparation), le studio pour ses gestionnaires.
- **Application** : bouton **Se déconnecter** en bas de *Mon profil*, avec la même confirmation que dans les Paramètres.
- **Déploiement** : `php artisan migrate --force` (`2026_10_10_100001_add_received_time_to_prophecies_table`) ; `npm run build` ; `view:cache` si utilisé ; nouvelle version de l'application.

## 2026-10-09 — Connexion avec Google, « Je prie » notifié, push

- **Nouveau — Connexion avec Google** : `POST /api/v1/auth/google { id_token }` (contrôleur invokable `Api\GoogleAuthController`).
  - Le jeton est vérifié par `tokeninfo` : audience `GOOGLE_WEB_CLIENT_ID` (ou l'une de `GOOGLE_CLIENT_ID`), émetteur Google, e-mail vérifié. Sinon 401.
  - Compte retrouvé par `google_id`, puis par e-mail (liaison : `google_id`, photo si absente, e-mail vérifié) ; sinon créé sans mot de passe.
  - Même réponse que `POST /auth/login` (`user`, `access_token`, `token_type`) avec `is_new_user` ; jeton Sanctum `mobile-google`.
  - `POST /auth/social` passe par le même contrôleur et accepte `token` (nom envoyé par l'application actuelle, qui obtenait jusqu'ici une erreur 422). Les contrôles `iss` et `email_verified` y sont désormais appliqués.
  - Les comptes sans mot de passe étaient déjà gérés (connexion e-mail refusée proprement, changement de mot de passe, suppression du compte).
  - [connexion-google.md](fonctionnalites/connexion-google.md)
- **« Je prie » sur une requête de prière** : l'auteur est prévenu (notification et push `prayer_request_prayed`, préférence « prières »), une seule fois par personne et par requête. Auparavant, aucune notification. [requetes-de-priere.md](fonctionnalites/requetes-de-priere.md)
- **Notifications push** : relation `User::deviceTokens()` ; `storage/app/firebase-credentials.json` exclu de git ; `.env.example` propose ce chemin pour `FIREBASE_CREDENTIALS`. [notifications-push.md](fonctionnalites/notifications-push.md)
- **Développeurs** : `SocialAuthRequest` et la logique Google de `Api\AuthController` sont supprimés (remplacés par `GoogleAuthController`).
- **Déploiement** :
  - migration `2026_10_09_200001_add_google_id_to_users_table` (`users.google_id`, reprise des liens de `social_auth_providers`) ;
  - ajouter `GOOGLE_WEB_CLIENT_ID=566166419345-b78faa624rdlcqeigmublhhbseqvhofr.apps.googleusercontent.com` au `.env` du serveur ;
  - `php artisan migrate --force`, `php artisan config:clear`, `php artisan queue:restart`. Aucune dépendance Composer, aucun asset à recompiler.

---

## 2026-10-09 — Modération, réactions uniques, partages partout, Communauté complète

- **Suppression réservée à l'auteur et aux administrateurs** : un **modérateur ne supprime jamais** ce qu'un autre a soumis : témoignage, requête ou session de prière, événement, commentaire, message ou commentaire du verset du jour. Il garde ses outils de modération (refuser, masquer, retirer, signaler). Les administrateurs suppriment encore. API, site et application suivent cette règle, et `canDelete` est exposé par les ressources. Sur le site, le témoignage a un bouton Supprimer pour l'auteur et l'administrateur (`DELETE /testimonies/{id}`).
- **Réactions uniques** :
  - Au plus une réaction « j'aime » (like, love, fire, glory, strong, touched) et une « je prie » (pray, amen, worship) par personne. Changer de réaction la remplace ; réagir de nouveau ne compte rien.
  - Les doublons existants sont supprimés et les compteurs corrigés.
  - L'application lit l'état « déjà aimé » et les compteurs du serveur, et un second appui retire la réaction. Avant, le cœur restait vide après un rechargement, et chaque appui ajoutait un « j'aime ».
  - « Gloire ! » n'est plus comptée comme une prière.
  - [reactions.md](fonctionnalites/reactions.md)
- **Partages visibles partout** : l'en-tête « X a partagé » apparaît sur toutes les listes de témoignages de l'application (accueil, Mon fil, Explorer, catégories, recherche, profils, sauvegardés). `TestimonyResource` porte `repostedBy`, en montrant un compte suivi d'abord.
- **Nouveau — Partager et réagir aux événements, requêtes et sessions de prière** :
  - J'aime, je prie et partage sur TestiApp, avec un commentaire facultatif.
  - Notification à l'auteur.
  - Les publications partagées par les comptes suivis entrent dans Mon fil.
  - Application : barre de réactions sur leurs pages et sous les éléments insérés dans les fils. [partage-interne.md](fonctionnalites/partage-interne.md)
- **Événements** :
  - Nouvel onglet **En cours** dans l'application (`GET /events?scope=ongoing`).
  - Section **Événements à venir et en cours** et **Événements passés** sur le profil des organisateurs (application et site, `GET /events?organizer_id=`). On y retrouve les témoignages et commentaires des événements passés.
- **Mon fil** : il montre aussi **ses propres publications** : témoignages (`feedReason: mine`), partages, requêtes et sessions de prière, événements et paroles prophétiques. En plus des événements à venir et en cours, il contient les **événements passés** depuis moins de 90 jours, avec l'étiquette « Événement passé · à revivre » (`pastEvents` de `GET /feed/inserts`). On y trouve aussi les sessions et requêtes de prière, les paroles prophétiques et les directs en cours.
- **Communauté** : nouvel onglet **Tous**, ouvert par défaut dans l'application et sur le site, qui liste tous les comptes au défilement, sans attendre une recherche. L'onglet **Personnes** liste désormais tous les comptes personnels, et non plus seulement ceux qui ont publié. [abonnements.md](fonctionnalites/abonnements.md)
- **API** :
  - `DELETE testimonies/{id}/reactions?group=` (sans identifiant) ;
  - `POST|DELETE events|prayer/requests|prayer/sessions/{id}/reactions` et `…/repost` ;
  - `GET /community?tab=all` ;
  - nouveaux types de réaction `glory`, `strong`, `touched`.
- **Développeurs** :
  - `TestimonyReactions`, `ContentInteractions`, `ContentReaction`, `ContentRepost`, `Api\ContentInteractionController`, `Reposts::highlightFor`, `FeedInserts` (partages des comptes suivis) ;
  - application : `lib/features/repost/content_interactions.dart`, `lib/features/testimony/testimony_delete.dart` ;
  - tests `ReactionsAndSharesTest`, `test/reactions_shares_test.dart`.
- **Déploiement** (les réactions et partages renvoyaient 404 en production : la liste des routes mise en cache sur le serveur n'avait pas été régénérée) :
  - `php artisan optimize:clear`, puis `route:cache` ;
  - `php artisan migrate --force` (`2026_10_09_100001_dedupe_testimony_reactions`, `2026_10_09_100002_create_content_reactions_and_reposts_tables`) ;
  - `npm run build` ;
  - `route:cache` et `view:cache` si utilisés ;
  - nouvelle version de l'application.

## 2026-10-08 — Administrateurs de compte

- **Nouveau** : chaque titulaire administre son compte et peut nommer **2 personnes** (comptes personnels actifs) qui publient des témoignages, des événements, des requêtes et des sessions de prière, commentent, réagissent et suivent **au nom du compte**. Cela vaut pour tous les comptes, personnels et organisations ; les gestionnaires d'organisation existants deviennent administrateurs du compte. [administrateurs-de-compte.md](fonctionnalites/administrateurs-de-compte.md)
- **Basculer** :
  - **Site** : « Mes comptes » dans le menu du compte, et un bandeau « Vous agissez au nom de … » avec « Revenir à mon compte ».
  - **Application** : la même chose, avec le compte actif mémorisé.
- **Réservé au titulaire** : e-mail, téléphone, mot de passe, connexion, suppression du compte, choix des administrateurs, vérification, position, réglages, appareils, carnet privé et paroles prophétiques, modération et administration. Les comptes de l'équipe (modérateurs, administrateurs) ne peuvent pas être confiés.
- **Journal** : chaque action faite au nom du compte est enregistrée (`account_delegate_actions`) et visible du titulaire. Notifications `account_manager_added` et `account_manager_removed`.
- **API** :
  - En-tête `X-Act-As` (refus `403` avec `code` `acting_as_forbidden` ou `owner_only`).
  - Nouvelles routes : `GET /users/me/accounts`, `GET /users/me` (suit `X-Act-As`), `GET /users/me/managers/activity`.
  - `GET/POST/DELETE /users/me/managers` est désormais ouvert à tous les comptes, et non plus seulement aux organisations.
- **Développeurs** : `AccountDelegation`, middleware `ActAsAccount` (groupes `api` et `web`), `AccountSwitchController`, `AccountDelegateAction` ; tests `AccountManagersTest`.
- **Déploiement** : `php artisan migrate --force` (`2026_10_08_100001_create_account_delegate_actions_table`) ; `npm run build` ; `route:cache` et `view:cache` si utilisés ; nouvelle version de l'application.

## 2026-10-07 — Fils classés par zone puis par goûts

- **Nouveau — Le plus proche d'abord** : sur l'accueil « Pour vous » et dans **Mon fil** (application, site, API), les témoignages, requêtes et sessions de prière, événements et paroles insérés sont classés du plus proche (même village/quartier ou < 5 km, même ville ou < 30 km, même région, même pays, même continent) au plus lointain, puis selon les goûts (catégories, auteurs et types de contenus aimés, priés, enregistrés, commentés), la fraîcheur et la popularité. Rien n'est retiré : les fils sont réordonnés. Mon fil garde les comptes suivis en tête (du plus proche au plus lointain à l'intérieur). [localisation.md](fonctionnalites/localisation.md), [recommandations.md](fonctionnalites/recommandations.md)
- **Nouveau — Réglage « Utiliser ma position pour personnaliser mes fils »** (application : Paramètres › Localisation ; site : Paramètres du compte › Localisation) : consentement explicite, « Mettre à jour ma position », « Ma ville », « Supprimer ma position » (effacement immédiat). Sans position : ville et pays du profil, puis indicatif du téléphone, puis langue de l'appareil (aussi pour les visiteurs).
- **Interface** : mention « Près de chez vous · Cotonou » en tête des fils (lien vers le réglage), invitation refermable « Activez votre position pour des témoignages proches de vous », étiquette discrète « À Cotonou » / « Au Bénin » sur les cartes proches (application).
- **Vie privée** : coordonnées arrondies à 2 décimales (≈ 1 km), géocodées par le serveur (Nominatim, 1 requête/s, cache 30 jours) ; jamais dans les ressources publiques ; effacées à la suppression du compte.
- **API** : `GET/PUT/DELETE /api/v1/users/me/location` ; `meta.place` et `meta.nearby` sur `GET /testimonies?sort=for_you` et `GET /feed` ; `GET /prayer/requests?scope=feed&near=1` ; `GET /feed/inserts` classé par zone.
- **Développeurs** : `App\Services\Locations`, `App\Support\Place`, `App\Support\Continents`, `Countries::inPhrase()`, `UserLocation`, `ContentLocation`, `LocationServiceProvider` (copie du lieu à la création des contenus), `Recommendations::tastes/placeMeta/nearbyLabels`, `FeedInserts::rankByPlace`, poids dans `config/recommendations.php`. Application : `lib/features/location/`. Tests `LocationFeedTest` (serveur) et `test/location_feed_test.dart` (application).
- **Déploiement** : `php artisan migrate --force` (`2026_10_07_200001_create_user_and_content_locations_tables`), puis **`php artisan locations:backfill`** (lieu des contenus existants depuis le profil des auteurs ; idempotent) ; `php artisan config:cache`, `route:cache` et `view:cache` si utilisés ; variables facultatives `GEOCODER_ENABLED`, `GEOCODER_URL`, `GEOCODER_USER_AGENT`, `RECO_GUEST_COUNTRY` ; aucun asset à recompiler ; nouvelle version de l'application (texte de permission iOS mis à jour).

## 2026-10-07 — Vérification des comptes personnels

- **Nouveau — Badge « Compte vérifié »** : une personne (pasteur, évangéliste, chantre, responsable de ministère, personnalité publique…) peut demander la vérification de son compte : nom complet, fonction, église ou ministère, liens publics facultatifs, message, **1 à 3 pièces justificatives** (pièce d'identité, lettre de l'église ; JPG, PNG, WebP ou PDF, 8 Mo). Statuts : aucune → en attente → vérifié / refusé (motif) ; nouvelle demande possible 24 h après un refus ou un retrait. Site : `/profile/verification` (lien sur son profil) ; application : Profil → « Faire vérifier mon compte ». [verification-des-comptes.md](fonctionnalites/verification-des-comptes.md)
- **Pièces privées** : disque `local` (`storage/app/private/verification-requests/…`), jamais d'URL publique ; servies seulement aux administrateurs (`/admin/verifications/{id}/pieces/{n}`, `GET /api/v1/admin/verification-requests/{id}/documents/{n}`, `no-store`) ; effacées à la suppression du compte.
- **Administration** : menu « Vérification des comptes » (`/admin/verifications`) et icône dans le tableau de bord de l'application : approuver, refuser (motif obligatoire), retirer la vérification (aussi depuis la fiche du compte). Notifications `account_verified` / `account_verification_rejected` (application + push).
- **Badge** : le badge s'affiche pour les personnes vérifiées partout où il s'affichait pour les organisations (profils, cartes de témoignages, commentaires, requêtes et sessions de prière, suggestions, mentions) ; libellé « Compte vérifié » (personne) ou « Organisation vérifiée ». Raison des suggestions : « Compte vérifié ».
- **Droits inchangés** : création d'événements, diffusion d'un événement et gestionnaires restent réservés aux **organisations** vérifiées et aux administrateurs (`User::isVerifiedOrganization()` ; `User::isVerified()` vaut désormais pour tout compte vérifié).
- **API** : `GET/POST /api/v1/users/me/verification` ; admin : `GET /admin/verification-requests`, `GET …/{id}`, `GET …/{id}/documents/{index}`, `POST …/{id}/approve`, `POST …/{id}/reject`, `POST /admin/users/{id}/revoke-verification`. `UserResource.verification_status` d'une personne n'est public que si elle est vérifiée. Auteurs des requêtes / sessions / messages de prière : champ `isOrganization` ajouté.
- **Développeurs** : `App\Services\PersonalVerification`, `App\Models\VerificationRequest`, `App\Enums\PersonalVerificationRole`, `Api\AccountVerificationController`, `Web\AccountVerificationController` ; tests `AccountVerificationTest` (serveur) et `test/account_verification_test.dart` (application).
- **Déploiement** : `php artisan migrate --force` (`2026_10_07_100001_create_verification_requests_table`) ; `php artisan route:cache` et `view:cache` si utilisés ; aucun asset à recompiler ; nouvelle version de l'application.

## 2026-10-07 — Mentions « @ » dans les témoignages et les requêtes de prière

- **Nouveau — Témoignages** : taper « @ » dans le texte (ou la description / transcription d'un audio ou d'une vidéo) propose les comptes, comme dans les commentaires (application : formulaire Publier, étape 1 ; site : éditeur mis en forme de « Partager un témoignage »). Les personnes mentionnées reçoivent une notification `mention` (« Marie vous a mentionné dans son témoignage « Titre » », push selon la préférence « commentaires ») **quand le témoignage est approuvé**, une seule fois chacune (après une modification, seules les nouvelles mentions sont prévenues à la nouvelle approbation) ; jamais pour le carnet privé ; témoignage réservé aux abonnés : seuls les abonnés mentionnés. Les mentions sont des liens vers le profil (page du témoignage sur le site, lecture dans l'application).
- **Nouveau — Requêtes de prière** : mentions dans le texte de la requête (application : « Confier une requête » ; site : formulaire de création). Notification immédiate aux seules personnes qui peuvent voir la requête (publique : tous ; abonnés : les abonnés) ; jamais pour une requête « Moi seul » ; requête anonyme : « Quelqu'un vous a mentionné dans une requête de prière » (ni nom, ni photo, ni identifiant de l'auteur). Une modification qui ajoute une mention ou élargit la visibilité prévient les nouvelles personnes, jamais deux fois la même.
- **API** : `TestimonyResource.bodyText` et `PrayerRequestResource.body` sont rendus lisibles (« @Nom ») avec `mentions: [{id, displayName}]` ; `POST/PUT /testimonies` et `/prayer/requests` acceptent les jetons `@[Nom](user:id)` ou `mention_ids[]`. La longueur des requêtes de prière (2 000) se mesure sur le texte affiché. L'aperçu de modération (`contentPreview`) est lisible.
- **Développeurs** : `Mentions::notifyTestimony()` (appelé par `Api\ModerationController::approve` et `Web\ModerationController::approve`), `Mentions::notifyPrayerRequest()` (`PrayerRequests::create/update`), contexte `anonymous` de `Mentions::notify()`, `RichText::toHtml()` rend les jetons en liens. Application : `PublishDraft.mentions`, `Testimony.mentions`, `PrayerRequest.mentions`, `ProgressiveRichText(mentions:)`. Tests `ContentMentionsTest` (serveur) et `test/content_mentions_test.dart` (application). [mentions.md](fonctionnalites/mentions.md)
- **Déploiement** : aucune migration ; `php artisan view:cache` ; nouvelle version de l'application.

## 2026-10-07 — Autocomplétion des barres de recherche

- **Nouveau — Suggestions pendant la saisie** (site et application) : dès 2 caractères, complétion de texte (titres de témoignages publiés, catégories, comptes actifs, événements, livres de la Bible, sessions de prière publiques, recherches populaires), accents et casse ignorés, partie saisie en gras, flèche « Compléter », résultats directs qui ouvrent la page. Champ vide : recherches récentes de l'appareil ou du navigateur (effaçables). [recherche.md](fonctionnalites/recherche.md)
- **Nouveau — API** : `GET /api/v1/search/suggest?q=&scope=all|testimonies|users|events|prayer|bible&limit=` (public, cache 60 s par préfixe ; contenu privé, comptes suspendus ou privés, e-mail et téléphone jamais renvoyés) et `POST /api/v1/search/log` (recherche validée, anonyme). La page Explorer du site enregistre aussi ses recherches.
- **Site** : composant `components/search-autocomplete` (script en ligne, ARIA combobox, ↑ ↓ Entrée Échap) branché sur l'en-tête, Explorer, Vidéos, Communauté, Mes abonnés, Mes abonnements, Événements, carnet privé (récentes seulement), administration (utilisateurs, contenu) ; nouveau champ « Aller à un livre » dans la Bible.
- **Application** (`testi_app`) : widget `SearchAutocomplete` / `SearchAutocompleteField` branché sur la recherche de l'accueil et d'Explorer, Communauté, Mes abonnés, Mes abonnements, carnet privé (récentes seulement), administration des utilisateurs ; la loupe du lecteur de la Bible ouvre « Aller à un livre » (aussi hors ligne, « Jean 3 »).
- **Développeurs** : `App\Services\SearchSuggestions`, `Api\SearchController`, table `search_queries` ; tests `SearchSuggestTest` (serveur) et `test/search_autocomplete_test.dart` (application).
- **Déploiement** : `php artisan migrate --force` (`2026_10_07_300001_create_search_queries_table`) ; `php artisan route:cache` et `view:cache` si ces caches sont utilisés ; aucun asset à recompiler.

## 2026-10-06 — Alignement de l'API sur l'application mobile

L'application (`testi_app`) et l'API ont été comparées champ par champ ; les corrections ci-dessous sont faites côté serveur.

- **Corrigé — Sessions de prière** : modifier une session déjà commencée (titre, sujets…) renvoyait 422 « La session doit être programmée dans le futur ». Un horaire renvoyé inchangé est maintenant accepté ; un nouvel horaire doit rester dans le futur. [sessions-de-priere.md](fonctionnalites/sessions-de-priere.md)
- **Corrigé — Verset du jour** : `POST /daily-verse/react` ne renvoie plus 409 quand la réaction existe déjà (état local de l'application périmé). [verset-du-jour.md](fonctionnalites/verset-du-jour.md)
- **Corrigé — Notifications** :
  - « Je prie » sur un témoignage crée une notification `prayer` (« X prie pour votre témoignage », préférence `push_prayers`) au lieu de `like` ;
  - une mention sous le verset du jour porte `dailyVerseId` (push : `daily_verse_id`).
  [notifications-push.md](fonctionnalites/notifications-push.md)
- **Corrigé — Mentions** : la longueur maximale des commentaires (témoignages, verset du jour, événements, directs) se mesure sur le texte affiché, et non plus sur les jetons `@[Nom](user:id)`. [mentions.md](fonctionnalites/mentions.md)
- **Corrigé — Mes témoignages** :
  - renommer (`PUT testimonies/{id}` avec `{ title }` seul) ne renvoie plus 422 ;
  - `rejectionReason` (motif et note du refus) est renvoyé à l'auteur ;
  - un verset cité de plus de 255 caractères ne provoque plus d'erreur 500 (colonne `TEXT`).
  [reglages-et-compte.md](fonctionnalites/reglages-et-compte.md)
- **Nouveau — Réglages** : route `GET /users/me/settings`. Le réglage **« Qui peut commenter »** (`everyone` / `followers` / `nobody`) est maintenant appliqué (API et site, 403), et `canComment` est exposé sur les témoignages.
- **Corrigé — Supprimer son compte** : un compte sans mot de passe (téléphone, Google, Facebook) peut être supprimé avec `{ confirm: true }`.
- **Modifié — Partages** : `POST /testimonies/{id}/share` et `POST /daily-verse/share` acceptent les invités (30 par minute). Un témoignage non publié ou privé renvoie 404.
- **Développeurs** :
  - nouveaux éléments : règle `App\Rules\VisibleLength`, `Testimony::acceptsCommentsFrom()`, `NotificationType::Prayer` ;
  - tests : nouveau fichier `AppContractTest`, compléments dans `PrayerTest`, `DailyVerseTest` et `MentionsTest` (numéros de téléphone de test déterministes).
- **Nouveau — `has_password`** dans le profil de la personne connectée (`auth/me`) : faux pour un compte téléphone, Google ou Facebook.
- **Application corrigée le même jour** (`testi_app`) :
  - le profil reprend `bio` et `country` du serveur avant l'enregistrement : ils ne sont plus effacés ;
  - les réglages sont lus et enregistrés sur le serveur (`GET` / `PUT /users/me/settings`) ; « Qui peut commenter » propose aussi « Mes abonnés » ;
  - supprimer son compte appelle `DELETE /users/me` (mot de passe, ou confirmation pour un compte sans mot de passe) ;
  - nouvel écran « Changer le mot de passe » (`POST /auth/change-password`) ;
  - notifications `follow`, `share`, `mention`, `testimony_rejected` avec leur propre texte et leur navigation (profil, témoignage, direct, verset du jour), y compris depuis un push ; plus d'appel `GET /notifications` sans compte ;
  - `verse_ref` envoyé à la publication.
- **Fausse alerte — catégories** : la base de production (et la base locale) utilise bien `protection` et `provision`, comme la liste de secours de l'application. C'est `database/seeders/CategorySeeder.php` qui est ancien (`protection_divine`, `finances`…) : seule une installation neuve en dépend.
- Vérifié : `php artisan test` (seul `ExampleTest` échoue, comme avant) ; `php artisan view:cache` ; application : `dart analyze` sans remarque, `flutter test` (537 tests réussis).
- **Déploiement** : `php artisan migrate --force` (`2026_10_06_500001_widen_bible_verse_columns`) ; `php artisan route:cache` et `config:cache` si ces caches sont utilisés ; aucun asset à recompiler.

## 2026-10-06 — Événements : liste compacte, report, signalement, sondage « A-t-il vraiment eu lieu ? »

- **Nouveau — Liste compacte** (site `/evenements`, application écran Événements) : bascule **Cartes / Liste compacte** (une ligne : vignette date, titre, lieu / ville, organisateur, pastilles d'état), mémorisée (site : cookie `event_layout`, aussi sans compte ; application : stockage local `event_layout`). La grille de cartes est inchangée.
- **Nouveau — Reporter un événement** (gestionnaires ; site : bloc Gestion, application : panneau « Gestion de la page ») : **nouvelle date** (l'événement redevient « à venir ») ou **« Date à confirmer »** (statut `postponed`, badge « Reporté — date à confirmer », participations conservées) ; motif facultatif ; la **date initiale** reste affichée **barrée**. **Annuler l'événement** devient une action distincte avec motif. Dans les deux cas les personnes « Je participe » sont **notifiées** (notification + push : `event_postponed`, `event_cancelled`). Passer à « Annulé » depuis le formulaire prévient aussi.
- **Nouveau — Signaler un événement** (comptes connectés, pas les gestionnaires ; site : « Signaler cet événement », application : menu ⋮) : Pas un événement chrétien · Événement faux / n'existe pas · Contenu inapproprié · Arnaque / demande d'argent · Autre (+ commentaire). Une fois par personne. **Masqué automatiquement** au 3e signalement non traité. File **Modération → Événements signalés** (`/evenements/moderation` : Signalés, Non confirmés, Masqués) : Masquer, Rétablir, Rejeter les signalements.
- **Nouveau — Sondage d'après l'événement** : à la fin d'un événement publié, les participants (« Je participe » et auteurs de commentaires, sauf gestionnaires) reçoivent **une** invitation (`event_survey`) ; bloc **« Votre avis »** : a-t-il eu lieu (Oui / Non / Je n'y étais pas), note 1-5 (si Oui), avis facultatif ; une réponse par personne, modifiable 30 jours ; résultats publics (« 87 % confirment qu'il a eu lieu · ★ 4,6 · 23 avis ») et liste des avis. Majorité de « Non » après 3 réponses Oui/Non : badge **« Non confirmé »** et file de modération. **Fiabilité de l'organisateur** (confirmés / non confirmés / signalés) pour les modérateurs (page de l'événement, file, profil) ; badge public **« Organisateur fiable »** (≥ 3 confirmés, aucun non confirmé).
- **Nouveau — Mentions « @ » dans les commentaires d'événements** (site et application), comme les autres commentaires ([mentions.md](fonctionnalites/mentions.md)) ; notification `mention` avec `payload.event_id`.
- **API** : `POST events/{id}/postpone` (`{date_tbd:true}` ou `{starts_at, ends_at}`, `reason?`), `POST events/{id}/cancel` (`reason?`), `POST events/{id}/report` (`reason`, `comment?` ; 409 si déjà signalé), `POST events/{id}/review` (`attended`, `rating?`, `body?`), `GET events/{id}/reviews` ; modérateurs : `GET events/moderation?filter=reported|unconfirmed|hidden`, `POST events/{id}/hide|restore|dismiss`. `EventResource` : `isPostponed`, `dateTbd`, `originalStartsAt`, `originalEndsAt`, `statusReason`, `statusChangedAt`, `isHidden`, `hiddenReason`, `isUnconfirmed`, `canReport`, `hasReported`, `survey{…}`, `organizer.isReliable`, `moderation{…}` (rétrocompatible). `EventCommentResource` : `body` lisible + `mentions`. `NotificationResource` : `eventId` ; push : donnée `event_id`.
- **Développeurs** : `App\Services\EventModeration` (signalements, modération, sondage, `notify()`), `EventService::postpone()` / `cancel()`, modèles `EventReport`, `EventReview`, `EventStatus::Postponed`, `App\Support\EventLayout`, commande `events:send-surveys` (**planifiée toutes les heures**, `routes/console.php`). Vues `events/moderation`, `events/partials/{row,layout-toggle,status-badges,status-modals,survey,moderation-card,reliability}`. Application : `widgets/event_trust_widgets.dart`, `EventCompactTile`, `eventLayoutProvider`, `eventReviewsProvider`, extension `EventLifecycleApi`, type de notification `event`.
- Vérifié : `php artisan test tests/Feature/EventModerationTest.php` (15 tests) + `EventTest`, `EventLocationTest`, `PrayerTest`, `FeedInsertsTest`, `MentionsTest` ; `php artisan route:list --path=events` ; `php artisan view:cache` ; application : `flutter analyze` (fichiers touchés), `flutter test test/events_test.dart test/event_location_test.dart test/notification_parsing_test.dart`.
- **Limites** : voir [evenements.md](fonctionnalites/evenements.md#limites-connues).
- **Déploiement** : migration `2026_10_06_100001_add_postpone_reports_and_reviews_to_events` (`php artisan migrate`) ; **le planificateur (`schedule:run`) doit tourner** pour `events:send-surveys` ; `php artisan view:cache` ; `php artisan route:cache` si les routes sont en cache ; recompiler `public/build/` conseillé (quelques classes Tailwind). Application : nouvelle version.

## 2026-10-06 — Mon fil comme l'accueil, sessions de prière à l'accueil, partage interne « X a partagé »

- **Modifié — Mon fil** (site et application) : affiche désormais **tous les témoignages publiés**, les **comptes suivis d'abord** (leurs témoignages et partages des 30 derniers jours, du plus récent au plus ancien), puis le classement « Pour vous », puis le reste ; « Suggestion pour vous » sur les comptes non suivis ; sans doublon, pagination stable. Insérés entre les témoignages : **événements à venir**, **sessions de prière programmées**, **requêtes de prière** et **paroles prophétiques rendues publiques** — celles des comptes suivis d'abord, puis celles des autres comptes (sources en alternance). Bandeau **« Comptes à suivre »** après les 6 premiers éléments (en tête si l'on ne suit personne). [recommandations.md](fonctionnalites/recommandations.md#mon-fil-get-apiv1feedpagelimit-connecté--site-mon-fil)
- **Nouveau — Accueil** : bandeau **« Sessions de prière à venir »** (application, à côté des événements ; site, sous « En direct »), masqué s'il n'y en a pas ; **« Comptes à suivre »** (application après « À la une », site sous les sessions). Site : les directs « En direct · Maintenant » de l'accueil ne montrent plus que ceux visibles de la personne (comme Mon fil et l'application).
- **Corrigé — « À la une »** (application) : du plus récemment publié au plus ancien (liste de `GET /testimonies/featured`, et non plus filtrée du fil « Pour vous ») ; le site l'était déjà.
- **Nouveau — Partage interne** : « Partager sur TestiApp » (feuille de partage de la page d'un témoignage dans l'application, bouton sur la page du site) avec un commentaire facultatif ; un seul partage par personne et par témoignage, annulable ; jamais son propre témoignage ni un témoignage non public. Les abonnés voient **« X a partagé »** au-dessus de la carte d'origine (l'auteur reste affiché) dans Mon fil ; section **« Partages »** du profil ; compteur ; notification **« X a partagé votre témoignage »** à l'auteur. [partage-interne.md](fonctionnalites/partage-interne.md)
- **API** : `GET /feed` — `feedReason` peut valoir `repost` (+ `repostId`, `repostedBy`, `repostComment`, `repostedAt`) ; rétrocompatible. Nouveaux : `GET /feed/inserts` → `{ following, others }` × `{ events, prayerSessions, prayerRequests, prophecies }` ; `POST|DELETE /testimonies/{id}/repost { comment? }` ; `GET /users/{id}/reposts`. `TestimonyResource` : `stats.repostsCount`, `isRepostedByMe`. Nouvelle `PublicProphecyResource`.
- **Développeurs** : `App\Services\FeedInserts`, `App\Services\Reposts`, `RepostException`, `App\Models\TestimonyRepost`, `Api\FeedController`, `Api\RepostController`, `Web\RepostController` ; `Recommendations::personalFeed()` renvoie `[page, raisons, partages]` (clé de cache `reco:personal:v2:{id}`, `Recommendations::personalCacheKey()`) ; `Web\FeedController::followedInserts()` remplacé par `FeedInserts::mixed()`. Vues : `feed/partials/repost-header`, `prayer/partials/upcoming-strip`, `feed/partials/insert` (sessions, paroles), `videos/partials/cards` (`reposts`, `suggestedAt`). Libellés des insertions : couleur `text-accent-700` (la couleur `secondary` n'existe pas dans le thème du site). Application : `lib/features/feed/feed_inserts.dart`, `mixInserts()` (`feed_mix.dart`), `lib/features/repost/`, `PublicProphecy` / `PublicProphecyFeedCard`, `UpcomingPrayerSessionsStrip`, `featuredFromApiProvider` ; étiquette « Suggestion pour vous » sans débordement à 320 px.
- Vérifié : `php artisan test --filter="PersonalFeedAndRepostTest|FeedInsertsTest|ProphecyAndFeedTest|RecommendationsTest"` (26 tests ; `FeedInsertsTest` et `ProphecyAndFeedTest` mis à jour pour la nouvelle règle « suivis d'abord, puis les autres ») et la suite complète ; `php artisan route:list` ; `php artisan view:cache` ; application : `flutter analyze` (fichiers touchés), `flutter test test/personal_feed_test.dart test/feed_mix_test.dart test/home_layout_test.dart test/suggested_accounts_test.dart`.
- **Limites** : pas de menu « Partager sur TestiApp » sur les cartes du fil (page du témoignage seulement) ; l'application ne connaît l'état « déjà partagé » que pendant la session ; 5 éléments insérés au plus par source et par groupe. Voir [partage-interne.md](fonctionnalites/partage-interne.md#limites-connues).
- **Déploiement** : migration `2026_10_06_200001_create_testimony_reposts_table` (`php artisan migrate`) ; `php artisan view:cache` ; `php artisan route:cache` si les routes sont en cache ; **recompiler `public/build/`** (classes Tailwind nouvelles). Application : nouvelle version.

## 2026-10-06 — Verset du jour en plein écran

- **Nouveau — Verset du jour en plein écran** (site `/verset-du-jour`, application : tap sur la bannière de l'accueil) : grand texte sur fond bleu, référence en jaune, J'aime / Je prie, partage, écoute, mode lecture. **Versions de la Bible** installées (version préférée mémorisée, « Comparer » toutes les versions ; version sans ce passage non sélectionnable ; hors connexion : Bibles téléchargées de l'appareil). Onglets **Sujets de prière** (requête de prière rattachée au verset, mêmes règles que les requêtes), **Commentaires** (mentions « @ », suppression par l'auteur / la modération, signalement, retrait au 3e signalement), **Témoignages** qui citent le verset + « Témoigner sur ce verset » (référence pré-remplie). [verset-du-jour.md](fonctionnalites/verset-du-jour.md)
- **Modifié** : sans verset programmé, le verset du jour est choisi d'après la date (stable toute la journée) au lieu d'un tirage au hasard à chaque appel. Accueil du site : le bloc « Verset du jour » ouvre la page. Formulaire Publier du site : `?verset=` et `?reference=` pré-remplissent le verset. Application : les compteurs J'aime / Je prie de la bannière lisent enfin `counts` / `myReactions` de l'API.
- **API** : `GET /daily-verse?date=&translation=` (ajoute `parsed`, `translation`, `counts.comments`, `counts.prayerRequests`, `webUrl`), `GET /daily-verse/versions`, `GET /daily-verse/{id}`, `/{id}/versions`, `/{id}/comments` (+ POST, DELETE `/{commentId}`, POST `/{commentId}/report`), `/{id}/prayer-requests`, `/{id}/testimonies`, `POST /daily-verse/{id}/react`. Requêtes de prière : `daily_verse_id` à la création, `scope=verse&daily_verse_id=`, `dailyVerseId` dans `PrayerRequestResource`.
- **Développeurs** : `App\Support\BibleReference` (analyse des références), `App\Services\DailyVerses`, `Api\DailyVerseController` (étendu), `Web\DailyVerseController`, `DailyVerseComment`, `DailyVerseCommentResource`, vue `daily-verse/show`. Application : `lib/features/daily_verse/` (`openDailyVerse`, `DailyVerseScreen`, `VerseReference`, `DailyVerseRepository`, fournisseurs), `PrayerRequestDraft.dailyVerseId`.
- Vérifié : `php artisan test --filter=DailyVerseTest` (13 tests) et `PrayerTest|HomePageTest|EventTest` ; `php artisan route:list` ; `php artisan view:cache` ; application : `flutter analyze` (fichiers touchés), `flutter test test/daily_verse_test.dart` (14 tests), `test/home_layout_test.dart`, `test/prayer_test.dart`.
- **Limites** : témoignages liés retrouvés par analyse du texte (300 candidats au plus) ; pas d'autocomplétion « @ » sur le formulaire du site ; pas de file de modération dédiée aux commentaires signalés. Voir [verset-du-jour.md](fonctionnalites/verset-du-jour.md#limites-connues).
- **Déploiement** : migration `2026_10_06_400001_create_daily_verse_participation` (`php artisan migrate`) ; `php artisan view:cache` ; `php artisan route:cache` si les routes sont en cache ; pas de recompilation CSS nécessaire. Application : nouvelle version.

## 2026-10-06 — Mentions « @ » sur les vrais comptes ; comptes à suivre

- **Nouveau — Mentions** (application et site) : après « @ », les comptes actifs sont proposés (comptes suivis et participants du témoignage d'abord) ; la personne mentionnée reçoit une notification `mention` (push « commentaires ») et son nom devient un lien vers son profil. Commentaires de témoignages (création, réponse, modification), messages d'encouragement des requêtes de prière, commentaires des directs (application). La liste factice de l'application est supprimée. [mentions.md](fonctionnalites/mentions.md)
- **Nouveau — Comptes à suivre** : classement des comptes non suivis (organisations vérifiées, publications récentes, engagement, abonnés en commun, centres d'intérêt), avec une raison (« Suivi par X que vous suivez », « Organisation vérifiée »…). Site : « Suggestions pour vous » sur la Communauté, partiel `community.partials.suggested` ; application : onglet **Suggestions** de la Communauté et bandeau `SuggestedAccountsStrip`. [abonnements.md](fonctionnalites/abonnements.md#comptes-à-suivre-suggestions)
- **API** : `GET /users/mention-search?q=&limit=8&testimony_id=` (connecté), `GET /users/suggestions?limit=10&type=all|organizations|people` (invité accepté). Commentaires : `text` / `body` désormais **lisibles** (« @Nom ») + `mentions: [{id, displayName}]` (`CommentResource`, `LiveComment::toPayload`, `PrayerRequestMessage::toPayload`) ; `mention_ids` facultatif à l'envoi d'un commentaire de témoignage. Format enregistré : `@[Nom](user:uuid)`.
- **Développeurs** : `App\Services\Mentions`, `App\Services\AccountSuggestions`, `Web\MentionSearchController` (route `users.mentions`), composant `components/mention-input` (script en ligne). Application : `lib/shared/models/mention.dart`, `lib/shared/widgets/mention_text.dart` (`MentionText`), `lib/shared/widgets/mention_text_field.dart` (`MentionTextField`, `MentionEditingController`, réutilisable), `suggestedAccountsProvider`, `lib/features/community/widgets/suggested_accounts_strip.dart`.
- Vérifié : `php artisan test --filter="MentionsTest|AccountSuggestionsTest"` (15 tests) et tests des abonnements, commentaires, directs, prière, notifications ; `php artisan view:cache` ; `php artisan route:list` ; application : `flutter analyze` (fichiers touchés), `flutter test test/mentions_test.dart test/suggested_accounts_test.dart` (10 tests) et tests Communauté / témoignage / directs / prière.
- **Limites** : commentaires d'événements non branchés ; directs du site sans autocomplétion. Voir [mentions.md](fonctionnalites/mentions.md#limites-connues).
- **Déploiement** : aucune migration ; `php artisan view:cache` ; `php artisan route:cache` si les routes sont en cache ; recompiler `public/build/` conseillé (quelques classes Tailwind nouvelles). Application : nouvelle version.

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
