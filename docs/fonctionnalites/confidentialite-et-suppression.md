# Politique de confidentialité et suppression de compte

Ajouté le 2026-10-20. Google Play exige une politique de confidentialité et une page de suppression de compte accessibles **sans compte**, ainsi qu'une suppression du compte depuis l'application.

## Source unique du texte

| Fichier | Contenu |
|---|---|
| `resources/legal/privacy.fr.md` | Politique en français (texte de référence) |
| `resources/legal/privacy.en.md` | Traduction anglaise fidèle (« My Testimony Privacy Policy ») |

Le texte n'est copié nulle part : le site et l'API lisent ces fichiers (`App\Services\PrivacyPolicy`) et les rendent en HTML avec `Str::markdown` (Markdown GitHub : tableaux, liens automatiques ; le HTML écrit dans le fichier est échappé).

- **Titre** : le premier titre `#` du fichier (retiré du corps HTML).
- **Date** : lue dans la ligne « Dernière mise à jour : 20 octobre 2026 » / « Last updated: October 20, 2026 » ; à défaut, date de modification du fichier.
- Pour modifier la politique : modifier **les deux** fichiers, sans changer la forme de la ligne de date. Les éléments entre crochets (`[adresse e-mail de contact]`, `[adresse postale]`, `[13]`…) sont à compléter avant la publication sur Google Play.

## Pages publiques (sans connexion)

| Adresse | Page | Langue par défaut |
|---|---|---|
| `/confidentialite` | Politique de confidentialité | français |
| `/privacy` | Politique de confidentialité | anglais |
| `/suppression-compte` | Suppression de compte | français |
| `/delete-account` | Suppression de compte | anglais |

Langue affichée : `?lang=fr|en`, sinon la langue choisie avec le sélecteur (session), sinon celle du compte connecté, sinon celle de l'adresse. Chaque page a un lien vers l'autre langue (« Read in English » / « Lire en français ») ; la politique a aussi des balises `hreflang`.

Mise en page : `layouts.app` (menu, pied de page), colonne de lecture `max-w-3xl`, styles `.doc-content` de la documentation ; les tableaux larges défilent dans leur cadre (`.doc-table`), jamais la page.

Liens « Politique de confidentialité » : pied de page de toutes les pages (`layouts.app` et `layouts.guest`), page de connexion, case de consentement de l'inscription, mention sous le bouton Google, *Paramètres* (avec « Supprimer mon compte »).

## Suppression de compte

### Ce qui est supprimé (`App\Services\AccountDeletion`)

Suppression **définitive** (plus de suppression douce) :

- profil (ligne `users`), avatar et photo de couverture, pièces de vérification (`PersonalVerification::purgeDocuments`) ;
- témoignages, y compris ceux de la corbeille : fichiers (`media_url`, `cover_url`, qualités `renditions`), preuves (`proofs/{id}` du disque `local`) et **vidéos Bunny** (`Testimony::forceDeleted`, après la réponse ; un échec Bunny est journalisé sans bloquer) ;
- sujets de prière, sessions de prière animées, paroles prophétiques (et leurs audios) ;
- événements de l'organisation et leurs images ;
- fichiers envoyés (`media_files` : audios, vidéos, images, enregistrements de directs) et leurs qualités ;
- préférences (`user_settings`, `verse_style_preferences`), brouillons, jetons FCM (`device_tokens`), jetons Sanctum, sessions web ;
- tout le reste (réactions, commentaires, abonnements, notifications…) part avec la ligne `users` (clés étrangères en cascade). Les compteurs d'abonnés / d'abonnements des comptes liés sont décrémentés.

Les fichiers sont effacés une fois la base à jour ; un échec est journalisé (`Suppression de compte : fichier non supprimé.`). Le journal d'audit reçoit une ligne `user.deleted` sans donnée personnelle (identifiant, type de compte, nombre de témoignages).

Après la suppression, l'e-mail et l'identifiant Google sont libérés : la personne peut recréer un compte.

**Super-administrateurs** : jamais supprimés par cette voie (403 dans l'API, message sur la page). Retirer d'abord le statut (`php artisan users:super-admin {email} --revoke`).

Au nom d'un compte administré (`X-Act-As`, compte choisi sur le site) : refusé dans l'API (`owner_only`) ; sur le site, la page s'ouvre sur le compte de la personne réellement connectée.

### API : `DELETE /api/v1/me` (et `DELETE /api/v1/users/me`)

Authentifié (Sanctum). Confirmation obligatoire :

- compte avec mot de passe : `{ "password": "…" }` (422 s'il manque, 403 s'il est faux) ;
- compte sans mot de passe (téléphone, Google) : `{ "confirm": true }` (422 `errors.confirm` sinon).

Réponse 200 `{ success: true, data: null, message: "Compte et données supprimés." }`. `UserResource` expose `has_password` pour choisir la confirmation. `DELETE me` : 10 requêtes par minute.

### Site : `/suppression-compte`

La page explique ce qui est supprimé et les délais (immédiat en étant connecté ; 30 jours pour une demande ; 90 jours pour les sauvegardes ; 12 mois pour les journaux techniques).

- **Connecté** : bouton « Supprimer définitivement mon compte », fenêtre de confirmation (`openConfirmModal`), mot de passe demandé pour un compte qui en a un (`DELETE /suppression-compte`, route `account-deletion.destroy`). La personne est déconnectée puis revient sur la page avec un message.
- **Supprimer certaines données sans supprimer votre compte** (section ajoutée le 2026-10-20) : dans l'application, chaque témoignage, sujet de prière ou parole prophétique enregistrée se supprime depuis son menu « ⋮ » ; les notifications se désactivent dans Paramètres ; pour toute autre donnée (photo de profil, historique de prière, données de vérification), écrire à l'adresse de contact (`privacy.deletion.contact_email`, à compléter comme dans la politique) ou utiliser le formulaire en choisissant « Supprimer certaines données ». Le lien de la section ouvre le formulaire avec ce choix coché (`?type=partial`).
- **Formulaire de demande** (`POST /suppression-compte`, route `account-deletion.request`), affiché à tous : choix `type` = `account` (« Supprimer mon compte », coché par défaut sans connexion) ou `partial` (« Supprimer certaines données », coché par défaut en étant connecté), e-mail ou téléphone, champ libre « Précisions » (`message`, **obligatoire** pour `partial`). Protection : 5 demandes par heure et par adresse IP (`throttle:5,60`) et champ piège invisible `website` (rempli : réponse identique, rien d'enregistré). La réponse est la même qu'un compte corresponde ou non.

La demande est enregistrée dans `account_deletion_requests` (`type` account | partial, contact normalisé : e-mail en minuscules, téléphone au format `+229…` ; compte reconnu dans `user_id`) et chaque super-administrateur actif (à défaut, chaque administrateur) reçoit une notification `account_deletion_requested` (push, catégorie `push_moderation` ; texte « Demande de suppression partielle… » pour `partial`, `payload.request_type`).

### Administration : *Administration › Demandes de suppression*

`/admin/demandes-suppression` (administrateurs) : demandes en attente d'abord, contact, compte reconnu (lien vers sa fiche), message. Pastille « Compte entier » ou « Suppression partielle ». **Super-administrateurs** seulement : pour une demande de compte, « Supprimer le compte » (même suppression complète, demande passée « Traitée ») ; pour une suppression partielle, les données sont retirées à la main puis « Marquer comme traitée » (`POST …/{id}/traitee` ; « Supprimer le compte » y répond 404) ; dans les deux cas, « Classer sans suite ».

### Administration : *Administration › Utilisateurs*

Les **super-administrateurs** peuvent supprimer un compte sans demande préalable :

- liste des utilisateurs : bouton corbeille à côté de « Gérer » (fiche mobile et tableau) ;
- fiche du compte : carte « Supprimer le compte » en bas de page. Les autres administrateurs voient la carte avec la mention « Réservé aux super-administrateurs. » et sans bouton.

Confirmation par `openConfirmModal` (nom du compte et nombre de témoignages), puis `DELETE /admin/users/{id}` (`admin.users.destroy`, `AdminController::deleteUser`) : même suppression complète que par le titulaire (`AccountDeletion`), retour à la liste avec « Compte supprimé définitivement. ». Journal d'audit `user.deleted`, auteur = le super-administrateur, motif « Suppression depuis l'administration ».

Refusés : un administrateur non super-administrateur (403), son propre compte, un autre super-administrateur (message d'erreur ; le bouton n'est pas affiché).

## Consentement à l'inscription

Colonne `users.accepted_privacy_at` (date et heure du serveur, jamais envoyée par le client).

| Parcours | Règle |
|---|---|
| Site, `/register` | Case « J'accepte la politique de confidentialité » (lien) **obligatoire** (`accepted_privacy`) |
| API, `POST /auth/register` | `accepted_privacy: true` **obligatoire** (422 `errors.accepted_privacy` sinon) |
| Site, bouton Google | Mention « En continuant avec Google, vous acceptez la politique de confidentialité » ; date enregistrée à la création du compte |
| API, `POST /auth/google` / `auth/social` et `POST /auth/phone` | `accepted_privacy: true` facultatif ; enregistré à la création du compte s'il est envoyé |

`UserResource` (profil de la personne connectée) expose `accepted_privacy_at` (ISO 8601 ou `null` pour les comptes antérieurs).

**À faire dans l'application** : envoyer `accepted_privacy: true` à l'inscription par e-mail (sinon 422) et, de préférence, avec la connexion Google et téléphone ; afficher la politique depuis `GET /api/v1/legal/privacy`.

## API : `GET /api/v1/legal/privacy`

Sans authentification, 60 requêtes par minute. `?lang=fr|en`, sinon `Accept-Language`, sinon le français.

```json
{
  "success": true,
  "data": {
    "title": "My Testimony Privacy Policy",
    "updated_at": "2026-10-20",
    "html": "<p><strong>Last updated: …</strong></p>\n<h2>1. Who are we?</h2>…",
    "url": "https://temoignage.as-krea.com/privacy"
  },
  "message": ""
}
```

`html` ne contient pas le titre principal. En-tête `Content-Language` = langue renvoyée.

## Anti-robot de l'hébergeur (Anubis)

La « Protection DDoS » de LWS (Anubis v1.26, en amont de Laravel) répond, le 2026-10-20 :

- `curl -A "Mozilla/5.0" …` : **406** (filtre de l'hébergeur sur ce User-Agent minimal, même sur `/`) ;
- User-Agent de navigateur complet : **403** avec une page « Vérification en cours » (défi JavaScript) sur toutes les pages, y compris `/` et l'API ;
- `curl/8.0`, `Googlebot/2.1` : 200.

Un navigateur réel passe le défi, mais un robot de vérification (Google Play) ou un client sans JavaScript peut être bloqué. Ce réglage ne se fait **pas** dans le code : dans le panneau LWS, exclure de la protection les chemins `/confidentialite`, `/privacy`, `/suppression-compte`, `/delete-account` et `/api/` (ou désactiver la protection), puis vérifier :

```bash
curl -s -o /dev/null -w "%{http_code}\n" -A "Mozilla/5.0" https://temoignage.as-krea.com/confidentialite   # 200 attendu
curl -s -A "Mozilla/5.0" https://temoignage.as-krea.com/confidentialite | grep -c "Politique de confidentialité"
```

## Fichiers

- `app/Services/PrivacyPolicy.php`, `AccountDeletion.php`, `AccountDeletionRequests.php`
- `app/Http/Controllers/Web/LegalController.php`, `Web/AccountDeletionRequestController.php`, `Api/LegalController.php`
- `app/Models/AccountDeletionRequest.php`, `NotificationType::AccountDeletionRequested`
- `resources/views/legal/privacy.blade.php`, `legal/account-deletion.blade.php`, `admin/deletion-requests.blade.php`
- Suppression depuis l'administration : `Web/AdminController::deleteUser`, `resources/views/admin/users/show.blade.php`, `admin/users/index.blade.php`, `admin/users/partials/delete-button.blade.php`
- `lang/{fr,en}/privacy.php`
- migrations `2026_10_20_200001_add_privacy_consent_and_account_deletion_requests`, `2026_10_20_300001_add_type_to_account_deletion_requests`

## Tests

`tests/Feature/PrivacyAndAccountDeletionTest.php` : pages publiques FR/EN et liens, API, suppression complète (données, fichiers, vidéo Bunny, jetons), confirmation, super-administrateur, suppression depuis la page, demande sans connexion (notification, piège, limite), suppression partielle (section, précisions obligatoires, « Marquer comme traitée »), traitement par un super-administrateur, consentement à l'inscription.

`tests/Feature/AdminUserDeletionAndReapprovalTest.php` : suppression depuis *Administration › Utilisateurs* (bouton, données supprimées, audit) et refus (administrateur simple, son propre compte, autre super-administrateur).
