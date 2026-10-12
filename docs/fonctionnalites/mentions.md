# Mentions « @ » dans les commentaires, les témoignages et les requêtes de prière

## But

Citer une personne dans un commentaire, le texte d'un témoignage ou d'une requête de prière (« Merci @Paul Mbeki ! ») : elle est **prévenue** et son nom devient un **lien vers son profil**. Les suggestions viennent de la vraie base des comptes (plus de liste factice dans l'application).

## Utilisation

### Application mobile

- Dans les commentaires d'un témoignage, les messages d'encouragement d'une requête de prière et les commentaires d'un direct : taper **@** puis quelques lettres. Une liste s'ouvre au-dessus du champ (« Recherche… », puis les comptes, ou « Aucun compte trouvé ») ; toucher un nom l'insère (« @Paul Mbeki », surligné).
- « Répondre » à un commentaire de témoignage préremplit la mention de son auteur.
- **Témoignages** : formulaire Publier, étape 1, champ « Description » (texte du témoignage, transcription d'un audio, description d'une vidéo) : même saisie. Le titre n'accepte pas de mention.
- **Requêtes de prière** : « Confier une requête », champ « Votre requête ».
- Dans les textes publiés, « @Nom » est en couleur : un appui ouvre le profil (`/users/{id}`) — commentaires, lecture d'un témoignage (texte mis en forme compris), requête de prière (page et cartes).

### Site

- Commentaires et réponses de la page de lecture (`/videos/{id}`), modification d'un commentaire, messages d'encouragement d'une requête de prière : même autocomplétion après **@** (flèches, Entrée, Échap ; souris).
- **Partager un témoignage** (`/testimonies/create`) : autocomplétion dans l'éditeur mis en forme (gras, italique). Le texte garde « @Nom » ; les comptes choisis partent en champs cachés `mention_ids[]`.
- **Nouvelle requête de prière** : autocomplétion dans le champ de la requête.
- Les mentions publiées sont des liens vers le profil (`/profiles/{id}`) : commentaires, page du témoignage (texte et description), page de la requête de prière ; texte lisible dans les cartes.

## Règles

| Règle | Détail |
|---|---|
| Comptes proposés | Comptes **actifs** (ni suspendus ni bannis), par nom affiché ou nom d'organisation ; jamais soi-même. Ordre : comptes **que je suis**, puis personnes ayant **participé au témoignage** (auteur, commentaires), puis noms qui **commencent** par la saisie, puis organisations vérifiées. « @ » seul : comptes suivis et participants uniquement |
| Confidentialité | Ni e-mail ni téléphone dans les résultats (`id`, `displayName`, `avatarUrl`, `initials`, `isVerified`, `isOrganization`, `isFollowing`) |
| Blocage | Il n'existe pas encore de blocage entre comptes : seuls les comptes inactifs sont exclus |
| Mentions retenues | 10 par commentaire au plus ; un compte inactif ou inconnu redevient du texte simple ; le nom enregistré est toujours le **nom réel** du compte (impossible d'afficher « @Admin » en visant quelqu'un d'autre) |
| Notification | Type `mention` (« X vous a mentionné dans un commentaire / un direct / un message de prière »), push selon la préférence « commentaires » (`push_comments`). Jamais pour soi-même ; l'auteur du témoignage ou du commentaire parent, déjà prévenu du commentaire, ne reçoit pas de seconde notification. Requêtes de prière : seulement les personnes qui peuvent voir la requête |
| Modification | Les mentions déjà présentes restent reconnues ; seules les **nouvelles** sont notifiées |
| Témoignage | Notification **à l'approbation** par la modération (API et site), pas à l'envoi : « X vous a mentionné dans son témoignage « Titre » » (`testimony_id`, `testimony_title`, `payload.mention_in = testimony`). **Une seule fois** par personne et par témoignage : après une modification puis une nouvelle approbation, seules les nouvelles personnes sont prévenues. Jamais pour le **carnet privé** ni un témoignage non approuvé. Public : toutes les personnes mentionnées ; réservé aux abonnés : seulement celles qui suivent l'auteur (`Testimony::isVisibleTo`). Il n'existe pas de publication directe : tout témoignage public passe par la modération |
| Requête de prière | Notification **immédiate** à la création ou à la modification, seulement aux personnes qui **peuvent voir la requête** (`PrayerRequest::isVisibleTo` : publique → tous, abonnés → abonnés de l'auteur) ; jamais pour une requête **privée** ou retirée par la modération ; une fois par personne et par requête (`payload.mention_in = prayer_request`). Une modification qui rend la requête visible de personnes déjà mentionnées les prévient. **Anonyme** : « Quelqu'un vous a mentionné dans une requête de prière », sans `actor_id` ni photo |

## Format retenu

Le modèle `User` n'a pas d'identifiant public (`username`) : plutôt que d'en créer un (migration, unicité, choix par les personnes), le texte enregistré contient un **jeton stable** :

```
Merci @[Paul Mbeki](user:0199aaaa-bbbb-7ccc-8ddd-eeeeffff0001) !
```

- **Enregistré** en base tel quel (colonnes `body`), après validation par le serveur.
- **Jamais exposé brut** : l'API renvoie le texte lisible (`Merci @Paul Mbeki !`) et `mentions: [{id, displayName}]` ; les clients rendent cliquables les « @displayName » de la liste. Les anciennes versions de l'application affichent donc un texte propre.
- **Envoi** : l'application envoie les jetons ; elle peut aussi envoyer le texte lisible avec `mention_ids: [uuid…]` (commentaires de témoignages), le serveur reconnaît alors « @Nom » pour ces comptes. Le site convertit les mentions choisies en jetons juste avant l'envoi.
- Un changement de nom ne casse pas le lien (l'identifiant fait foi) ; le texte garde le nom au moment de l'écriture.

## Fonctionnement technique

| Élément | Fichier |
|---|---|
| Service | `app/Services/Mentions.php` : `search()`, `prepare()` (normalise avant enregistrement), `notify()`, `plain()`, `of()`, `ids()`, `html()` (rendu Blade échappé avec liens) |
| Commentaires de témoignages | `Api\CommentController` (`store`, `update`), `Web\CommentController` (`store`, `update`), `Web\TestimonyController::storeComment` ; `CommentResource` (`text` lisible + `mentions`) |
| Directs | `LiveService::postComment()` (longueur mesurée sur le texte lisible), `LiveComment::toPayload()` (`body` lisible + `mentions`) |
| Requêtes de prière | `PrayerRequests::addMessage()`, `PrayerRequestMessage::toPayload()` ; texte de la requête : `PrayerRequests::create()` / `update()` → `Mentions::notifyPrayerRequest()` ; `PrayerRequestResource` (`body` lisible + `mentions`) |
| Texte des témoignages | `Api\TestimonyController` (`store`, `update` : `Mentions::prepare()`, `mention_ids` accepté), `Web\TestimonyController::store` ; notification : `Mentions::notifyTestimony()` appelé par `Api\ModerationController::approve` et `Web\ModerationController::approve` ; `TestimonyResource` (`bodyText` lisible + `mentions`) ; site : `RichText::toHtml()` (donc `body_html`) rend les jetons en liens, `body_plain` donne « @Nom » |
| Recherche | `Api\CommunityController::mentionSearch`, `Web\MentionSearchController` |
| Site | `components/mention-input.blade.php` (script en ligne, aucune recompilation), `data-mention-input` sur les zones de texte, `data-mention-testimony` (participants proposés d'abord) ; aussi la zone `contenteditable` de `components/rich-editor` (`'mentions' => true`) : « @Nom » reste dans le texte, `mention_ids[]` ajoutés à l'envoi ; vues `videos/partials/comment`, `videos/partials/watch`, `prayer/requests/show`, `prayer/requests/create`, `testimonies/create` |

### Routes

| Méthode | Adresse | Accès |
|---|---|---|
| GET | `/api/v1/users/mention-search?q=&limit=8&testimony_id=` | Connecté → `{ data: [{ id, displayName, avatarUrl, initials, isVerified, isOrganization, isFollowing }] }` |
| GET | `/personnes/mentions?q=&limit=&testimony_id=` (`users.mentions`) | Connecté (session du site), même réponse |
| POST | `/api/v1/testimonies/{id}/comments` `{ text, parent_id?, mention_ids? }` | `data.text` lisible, `data.mentions` |
| PUT | `/api/v1/comments/{id}` `{ text, mention_ids? }` | Idem |
| POST | `/api/v1/lives/{id}/comments` `{ body }`, `/api/v1/prayer/requests/{id}/messages` `{ message }` | `body` lisible + `mentions` |
| POST / PUT | `/api/v1/testimonies` `{ body_text, mention_ids? }`, `/api/v1/testimonies/{id}` | `data.bodyText` lisible, `data.mentions` |
| POST / PUT | `/api/v1/prayer/requests` `{ body, mention_ids? }`, `/api/v1/prayer/requests/{id}` | `data.body` lisible, `data.mentions` |

### Application (Flutter, `testi_app`)

| Élément | Fichier |
|---|---|
| Format (Dart pur) | `lib/shared/models/mention.dart` : `MentionRef`, `MentionCodec` (`decode`, `encode`, `ranges`) |
| Affichage cliquable | `lib/shared/widgets/mention_text.dart` : `MentionText(text, mentions:, style:, mentionStyle:, onMentionTap:)` — accepte aussi un texte à jetons (envoi en attente, cache local) |
| Saisie réutilisable | `lib/shared/widgets/mention_text_field.dart` : `MentionEditingController` (`encodedText`, `mentions`, `startWithMention`), `MentionTextField(controller:, testimonyId:, search:)`, `mentionSearchProvider` ; anti-rebond 300 ms, réponses périmées ignorées, états chargement / vide / erreur |
| Branché dans | `testimony_detail_screen.dart` (feuille des commentaires, « Répondre » ; texte du témoignage : `_ContentBody` → `ProgressiveRichText(mentions:)`), `live_widgets.dart` (`LiveCommentInput`, liste des commentaires), `prayer_request_detail_screen.dart` (messages, texte de la requête), `submit_prayer_request_screen.dart` (`encodedText` envoyé), `publish_preview_screen.dart` (description de l'étape 1 ; rappel de l'étape 2 avec `MentionText`), `prayer_widgets.dart` (cartes) |
| Modèles | `CommentModel.mentions`, `LiveComment.mentions`, `PrayerMessage.mentions`, `PrayerRequest.mentions`, `Testimony.mentions` (lu par `testimonyFromApiJson`), `PublishDraft.mentions` (+ `mentionIdsIn()` : `mention_ids` envoyés avec `body_text`) |
| Notification | `main.dart` : une mention sans témoignage ouvre la requête de prière ou le direct ; une mention de témoignage (`testimony_id`) ouvre le témoignage. Liste des notifications : idem (`prayerRequestId`, puis `testimonyId`) — aucun changement nécessaire |

Pour un autre écran (par ex. commentaires du verset du jour) : remplacer le `TextEditingController` par `MentionEditingController`, le `TextField` par `MentionTextField`, envoyer `controller.encodedText`, puis afficher avec `MentionText(body, mentions: …)` ; côté serveur, `Mentions::prepare()` avant d'enregistrer, `Mentions::notify()` après, et `Mentions::plain()` / `Mentions::of()` dans la réponse.

## Tests

- `tests/Feature/MentionsTest.php` : recherche (connexion exigée, sans e-mail ni téléphone, comptes inactifs et soi-même exclus, comptes suivis d'abord, nom d'organisation, participants du témoignage), route du site, enregistrement (nom réel, compte suspendu ignoré), notification sans doublon, `mention_ids` et modification, mention de soi, rendu HTML échappé, message de prière, `plain()` / `of()`.
- `tests/Feature/ContentMentionsTest.php` : témoignage (rien tant qu'il est en attente, notification à l'approbation, une seule fois après modification et nouvelle approbation, carnet privé jamais, réservé aux abonnés → abonnés seulement, approbation du site, `mention_ids` du site et rendu HTML), requête de prière (visibilité abonnés puis publique, privée jamais, anonyme sans nom ni identifiant).
- Application : `test/mentions_test.dart` (format, `MentionText` cliquable, champ : anti-rebond, suggestions, insertion, jeton envoyé, état vide et erreur) ; `test/content_mentions_test.dart` (Publier : mention choisie retenue dans le brouillon et `mention_ids`, `mentions` d'un témoignage et d'une requête, mention cliquable dans le texte mis en forme).

## Déploiement

Aucune migration. `php artisan view:cache` ; `php artisan route:cache` si les routes sont en cache. Le script du site est en ligne (pas de recompilation nécessaire) ; recompiler `public/build/` reste conseillé pour quelques classes Tailwind (survol des suggestions). Application : nouvelle version.

## Limites connues

- **Événements** : les commentaires d'événements (`EventService::comment`) ne résolvent pas encore les mentions (texte enregistré tel quel) ; à brancher comme ci-dessus.
- Site : les commentaires des **directs** (`resources/js/live.js`) affichent le texte lisible sans autocomplétion ni lien ; après **modification** d'un commentaire sans rechargement, les liens réapparaissent au rechargement.
- Application : la liste des notifications range `mention` avec les commentaires ; un appui sur une mention de message de prière ou de direct depuis la liste ouvre l'écran des notifications (le push, lui, ouvre la bonne page).
- **Témoignages** : le site n'a pas de page de modification d'un témoignage (l'application, oui) ; seul le texte (`body_text`) est concerné, il n'existe pas de champ de transcription séparé ; le titre n'accepte pas de mention. La règle « une seule fois » s'appuie sur les notifications enregistrées : une notification supprimée pourrait être renvoyée à une nouvelle approbation. Une personne mentionnée qui suit aussi l'auteur reçoit « nouveau témoignage » et la mention.
- **Site** : après une erreur de validation, le formulaire de requête de prière garde le texte mais les mentions redeviennent du texte simple. Pas de page de modification d'une requête (site ni application : l'API seule le permet).
- Pas de blocage entre comptes (aucune table) : seuls les comptes suspendus ou bannis sont exclus des suggestions et des notifications.
- La longueur maximale se mesure sur le **texte affiché** : un jeton compte comme `@Nom` (règle `App\Rules\VisibleLength`). C'est le cas pour les commentaires de témoignages (2 000), du verset du jour (2 000), des événements (3 000) et des directs. Le texte brut reste borné, avec une marge de 10 jetons. Les messages de prière (1 000) sont encore mesurés jetons compris.
