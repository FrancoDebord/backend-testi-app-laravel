# Paroles prophétiques

## But

Les paroles prophétiques reçues (lors d'un culte, d'une prière, d'un rêve…) sont des sources de témoignage : quand elles s'accomplissent, on rend gloire à Dieu. Dans le **carnet privé** (application et site), chacun peut :

- **garder** la parole (texte ou audio) ;
- **prier** dessus : journal de prière, rappels, lecture en plein écran pour la proclamer ;
- **témoigner** quand elle s'accomplit, et **rendre la parole publique** avec ce témoignage.

Disponible dans l'**application** et sur le **site** (`/carnet/paroles`). Les rappels sont toujours envoyés par l'application, sur le téléphone. La page publique d'un témoignage affiche la parole publiée avec lui.

## Règles

| Règle | Détail |
|---|---|
| **Qui voit une parole** | **Uniquement son auteur** (404 pour tout autre, modération comprise), jusqu'à ce qu'il la rende publique avec son témoignage. |
| **Champs** | Date (aujourd'hui par défaut) · la parole : **texte ou audio** (au moins l'un des deux) · qui l'a donnée (facultatif) · échéance (facultative, pas avant la date) · titre (facultatif). |
| **Audio** | Enregistré dans l'application, envoyé par `POST /media/upload` (même stockage que les témoignages ; adresse non devinable, voir la limite du [carnet privé](carnet-prive.md)). |
| **États** | `waiting` (en attente) · `fulfilled` (accomplie, avec `fulfilled_on`). L'application signale une **échéance passée**. |
| **Journal de prière** | « J'ai prié » enregistre la date et une note facultative ; compteur `prayer_count` et `last_prayed_at`. Une prière peut être retirée du journal. |
| **Rappels** | Chaque jour ou chaque semaine (jour choisi), à l'heure choisie : réglage enregistré sur le serveur (`reminder_frequency`, `reminder_time` HH:MM heure du téléphone, `reminder_weekday` 1 = lundi … 7), **notification locale programmée sur le téléphone** (pas d'envoi par le serveur). Chaque appareil reprogramme les rappels à l'ouverture des paroles ; plus de rappel une fois la parole accomplie. |
| **Proclamer** | Lecture en plein écran (grand texte, audio) avec Habakuk 2:3. |
| **Accomplissement** | « Elle s'est accomplie » → **Témoigner** (le parcours Publier s'ouvre, rattaché à la parole) ou **marquer accomplie** sans témoigner (témoigner reste possible ensuite). Une parole marquée accomplie sans témoignage peut revenir « en attente ». |
| **Rendre publique** | Au moment de publier le témoignage : « Publier aussi la parole prophétique » (`prophecy_public`, coché par défaut). La parole est alors montrée **avec le témoignage**, une fois celui-ci publié par la modération. Jamais pour une entrée du carnet privé. |

## Données

| Table | Contenu |
|---|---|
| `prophecies` | `user_id`, `title`, `received_on`, `body_text`, `audio_url`, `audio_duration`, `given_by`, `due_on`, `status`, `fulfilled_on`, `testimony_id`, `is_public`, `reminder_frequency`, `reminder_time`, `reminder_weekday`, `prayer_count`, `last_prayed_at` ; suppression douce |
| `prophecy_prayers` | `prophecy_id`, `note`, `prayed_at` |

`Testimony::prophecy()` (hasOne) relie le témoignage à sa parole. Supprimer le témoignage laisse la parole accomplie (`testimony_id` vidé).

## API mobile (`/api/v1`, connecté)

| Méthode | Route | Rôle |
|---|---|---|
| GET | `prophecies?status=waiting\|fulfilled` | Mes paroles (en attente d'abord) ; `meta.waiting`, `meta.fulfilled` |
| POST | `prophecies` | Créer : `received_on?`, `title?`, `body_text` ou `audio_url` (+ `audio_duration`), `given_by?`, `due_on?`, `reminder_frequency?` (`daily`/`weekly`), `reminder_time`, `reminder_weekday` |
| GET / PUT / DELETE | `prophecies/{id}` | Détail · modifier (champs partiels, `status` = `waiting`/`fulfilled`, `fulfilled_on?`) · supprimer |
| GET · POST | `prophecies/{id}/prayers` `{ note?, prayed_at? }` | Journal de prière · « J'ai prié » (renvoie `{ prayer, prophecy }`) |
| DELETE | `prophecies/{id}/prayers/{prayerId}` | Retirer une prière |
| POST | `testimonies` `{ …, prophecy_id, prophecy_public }` | Témoignage de l'accomplissement : la parole passe `fulfilled`, `testimony_id` renseigné, `is_public` selon le choix |

`ProphecyResource` : `id`, `title`, `receivedOn`, `bodyText`, `audioUrl`, `audioDuration`, `givenBy`, `dueOn`, `status`, `fulfilledOn`, `testimonyId`, `isPublic`, `reminder{frequency,time,weekday}|null`, `prayerCount`, `lastPrayedAt`.

`TestimonyResource.prophecy` : `{ title, receivedOn, givenBy, bodyText, audioUrl, audioDuration, dueOn, fulfilledOn, isPublic }` si la parole est publique (ou pour l'auteur), sinon `null`.

## Application

- Menu latéral **Paroles prophétiques** et accès depuis le **carnet privé** ; route `/prophecies` (onglets En attente / Accomplies), `/prophecies/new`, `/prophecies/:id`, `/prophecies/:id/edit`.
- Rappels : `flutter_local_notifications` (`zonedSchedule`, répétition quotidienne ou hebdomadaire) et `timezone` ; Android : récepteurs `ScheduledNotificationReceiver` / `ScheduledNotificationBootReceiver` et permission `RECEIVE_BOOT_COMPLETED`. Toucher le rappel ouvre la parole.
- Publier : bandeau « Accomplissement de la parole … » avec l'interrupteur « Publier aussi la parole prophétique » (`publishProphecyProvider`).
- Page d'un témoignage : carte « Parole prophétique accomplie ».

## Site

Menu latéral **Paroles prophétiques** et onglet du **Carnet privé**. Mêmes règles que l'API (`App\Services\Prophecies`) ; 404 pour toute autre personne que l'auteur.

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/carnet/paroles` `?statut=fulfilled` | En attente (par défaut, Habakuk 2:3 en tête) / Accomplies, avec leur nombre |
| GET · POST | `/carnet/paroles/nouvelle` · `/carnet/paroles` | Garder une parole : date (aujourd'hui), qui l'a donnée, titre, texte et/ou **fichier audio** (20 Mo, durée lue par le navigateur), échéance, rappel |
| GET | `/carnet/paroles/{id}` | La parole, accomplissement, journal de prière, rappel ; bouton **Proclamer** (plein écran : grand texte, audio, Habakuk 2:3) |
| GET · PUT | `/carnet/paroles/{id}/modifier` · `/carnet/paroles/{id}` | Modifier (remplacer ou retirer l'audio ; « Aucun rappel » efface l'heure et le jour) |
| DELETE | `/carnet/paroles/{id}` | Supprimer (le témoignage publié reste en ligne) |
| POST | `/carnet/paroles/{id}/accomplie` `{ fulfilled_on? }` | Marquer accomplie sans témoigner (date du jour par défaut, pas dans le futur) |
| POST | `/carnet/paroles/{id}/en-attente` | Remettre en attente (refusé si un témoignage est rattaché) |
| POST · DELETE | `/carnet/paroles/{id}/prieres` `{ note? }` · `/carnet/paroles/{id}/prieres/{prayer}` | « J'ai prié » (tant que la parole est en attente) · retirer une prière |
| GET | `/publish?prophecy={id}` | **Témoigner** : bandeau « Accomplissement de la parole… » et case « Publier aussi la parole prophétique » (cochée) ; `POST /testimonies` reçoit `prophecy_id`, `prophecy_public` |

**Rappels** : le site enregistre seulement le réglage (fréquence, heure du téléphone, jour) ; l'application programme la notification à la prochaine ouverture des paroles.

Page d'un témoignage (`videos/partials/watch`) : carte « Parole prophétique accomplie » (date, auteur de la parole, titre, texte ou audio, échéance, date d'accomplissement) si la parole est publique ou pour l'auteur, qui a aussi le lien « Voir dans mon carnet ».

## Fichiers

`app/Models/Prophecy.php`, `ProphecyPrayer.php` · `app/Services/Prophecies.php` (règles communes) · `app/Http/Controllers/Api/ProphecyController.php` · `app/Http/Controllers/Web/ProphecyController.php` · vues `resources/views/journal/prophecies/` · versets `config/encouragements.php` (`prophecy_verses`) · `app/Http/Resources/ProphecyResource.php` · migration `2026_10_05_200001_create_managers_and_prophecies_tables` · tests `tests/Feature/ProphecyAndFeedTest.php`, `WebJournalTest.php` · application `lib/features/prophecies/`.

## Limites connues

- Rappels : heure locale du téléphone convertie en UTC à la programmation ; un changement d'heure d'été décale le rappel d'une heure jusqu'à la prochaine ouverture des paroles.
- Audio stocké comme les médias du carnet privé (adresse non devinable mais publique).
