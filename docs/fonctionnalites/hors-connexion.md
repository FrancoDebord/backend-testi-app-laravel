# Hors connexion : file d'envoi de l'application

## But

Dans l'application mobile, une personne connectée peut créer et agir **sans réseau**. Ce qu'elle fait est gardé sur le téléphone (SQLite) puis envoyé automatiquement, dans l'ordre, dès que la connexion revient. Elle retrouve tout dans l'écran **« À envoyer »**.

## Ce qui fonctionne hors connexion

| Action | Détail |
|---|---|
| **Témoignage** texte ou audio | Avec photo de couverture, audio et preuves (copies des fichiers gardées par la file). Apparaît dans le fil « en attente » avec un identifiant provisoire. |
| **Témoignage vidéo** | L'envoi Bunny Stream attend le réseau sans limite (compression faite hors ligne, reprise après redémarrage). Si une autre vidéo est déjà en cours, celle-ci attend son tour dans la file. |
| **Requête de prière** | Écran « Confier une requête » et requête depuis le verset du jour. |
| **Session de prière** | Création seulement (la modification demande une connexion). |
| **Événement** | Création avec ses images (bouton « Créer » et choix de l'organisateur gardés depuis la dernière connexion). **Modification** d'un événement que je gère : « Mes événements » et la fiche sont gardés sur le téléphone à chaque consultation ; l'enregistrement (`PUT`, la dernière version l'emporte), les images ajoutées, retirées ou mises en premier partent au retour du réseau. Bandeau dans l'écran Événements tant que c'est en attente. |
| **Commentaires** | Témoignage (fiche, lecteur vidéo, Shorts, liste des commentaires), événement, verset du jour, encouragement d'une requête de prière. Affichés « En attente d'envoi » jusqu'à l'envoi. |
| **Réactions « j'aime »** | Témoignage, événement, requête, session (groupe « appréciation » : j'aime, gloire…) et « J'aime » du verset. Seule la **dernière** réaction posée sur un contenu part. |
| **Enregistrer un témoignage** | Enregistrer / retirer. |

**Restent en ligne** : « Je prie » (temps de prière chronométré par le serveur, voir [temps-de-priere.md](temps-de-priere.md)), les directs, la modération, la modification d'une session de prière, le report et l'annulation d'un événement.

## Fonctionnement côté application

- Table SQLite `outbox` (base locale v6) : une ligne par action, avec le compte de la personne, le compte administré au moment de l'action (`X-Act-As`, voir [administrateurs-de-compte.md](administrateurs-de-compte.md)), les étapes restantes en JSON (fichiers à envoyer avant, requête principale, fichiers à rattacher après) et l'état. Table `outbox_ids` : identifiant provisoire `local_…` → identifiant du serveur.
- Envoi : au changement de réseau, au retour au premier plan, toutes les 30 s, à la connexion au compte. Une action sur un contenu créé hors ligne (commentaire sur un témoignage pas encore envoyé) part après lui, avec l'identifiant du serveur.
- Refus du serveur (4xx) : l'action passe dans « Non envoyés » avec le message du serveur (Réessayer, Supprimer). Serveur indisponible (5xx, 408, 409, 429) : nouvelles tentatives espacées (30 s, 1 min, 2 min… 1 h), abandon après 8. Coupure : on attend le réseau, sans compter de tentative.
- La file appartient à la personne connectée : une autre personne qui se connecte sur le téléphone ne voit ni n'envoie ses actions. Supprimer son compte efface la file et les copies de fichiers.
- Code : `lib/features/offline/` (`outbox_controller.dart`, `outbox_models.dart`, `outbox_screen.dart`), `lib/core/local_db/daos/outbox_dao.dart`, tests `test/outbox_test.dart`.

## Serveur : écritures rejouées une seule fois

Une réponse peut se perdre pendant une coupure alors que le serveur a bien enregistré l'action ; la file la renvoie alors. Pour éviter les doublons, chaque écriture de la file porte l'en-tête **`Idempotency-Key`** (identifiant de l'action, suffixé par étape : `…-b1` fichier avant, `…-a2` fichier après). Upload-init d'une vidéo porte aussi sa clé.

Middleware `App\Http\Middleware\IdempotentRequests` (groupe `api`, après `ActAsAccount`) :

| Règle | Détail |
|---|---|
| Concerne | Requêtes non sûres (`POST`, `PUT`, `PATCH`, `DELETE`) d'une personne connectée (Sanctum) avec un en-tête `Idempotency-Key` valide (8 à 100 caractères `A-Z a-z 0-9 _ . : -`). Sans en-tête : comportement inchangé. |
| Clé de cache | `idempotency:{compte}:{sha1(méthode chemin clé)}` : propre à la personne (au compte administré quand `X-Act-As` est utilisé) et à la route. |
| Première fois | La requête est exécutée ; une réponse **2xx** est gardée **7 jours** (statut, corps, `Content-Type`). Un refus (4xx, 5xx) n'est pas gardé : l'action corrigée peut être renvoyée avec la même clé. |
| Fois suivantes | La réponse gardée est renvoyée telle quelle, avec l'en-tête `Idempotent-Replayed: true`, sans exécuter la requête. |
| Compte administré | Une réponse rejouée (`Idempotent-Replayed`) n'est pas inscrite une seconde fois au journal du compte (`ActAsAccount`). |
| Envois simultanés | Verrou de cache (`Cache::lock`, 120 s, attente 30 s) ; au-delà, **409** `api.request_in_progress` (« Cette action est déjà en cours d'envoi… ») — l'application réessaie plus tard. |

Tests : `tests/Feature/IdempotentRequestsTest.php`.

## Déploiement

Aucune migration : le cache utilise le magasin configuré (`CACHE_STORE=database` : tables `cache` et `cache_locks` déjà présentes). Vider le cache de configuration et de routes (`php artisan optimize:clear` puis `php artisan optimize`) pour charger le middleware.
