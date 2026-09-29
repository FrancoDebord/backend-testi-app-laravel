# Carnet privé

## But

Permettre à chacun d'**enregistrer ses témoignages pour lui-même** (texte, audio, vidéo), sans les publier, comme une mémoire de « ce que Dieu a fait », et de **les partager plus tard** s'il le souhaite.

## Règles

| Règle | Détail |
|---|---|
| **Qui voit une entrée** | **Uniquement son auteur.** Ni les autres utilisateurs, ni les modérateurs, ni les administrateurs |
| Modération | **Jamais** : une entrée du carnet n'entre pas dans la file de modération |
| Formats | Texte, audio, vidéo (mêmes envois de médias que les témoignages publics) |
| Catégorie | Facultative (`autre` par défaut) |
| Partager | À tout moment : l'entrée devient **publique et passe en modération**, comme un témoignage classique |
| Reprendre | Un témoignage public peut être **retiré du public et rangé dans le carnet** |
| Modifier | Une entrée modifiée reste privée (pas de nouvelle relecture) |

## Sauvegarde en ligne et copie hors ligne (application mobile)

- **Toujours en ligne** : chaque entrée (texte et fichiers audio / vidéo) est enregistrée sur le serveur. En cas de perte, de casse ou de changement de téléphone, l'utilisateur retrouve tout son carnet en se connectant.
- **Copie sur le téléphone, en option** : à la première visite du carnet, l'application propose de garder une copie pour le relire **sans connexion** (réglage modifiable à tout moment, icône en haut du carnet).
  - Rangée dans l'espace privé de l'application (invisible de la galerie), un dossier par compte.
  - Mise à jour à chaque ouverture du carnet et après chaque ajout, partage ou suppression ; un fichier qui échoue est retenté à la mise à jour suivante.
  - Sans connexion : liste, recherche et lecture des audios / vidéos depuis la copie.
  - Désactiver l'option efface la copie du téléphone ; **rien n'est supprimé en ligne**.
  - Aucune route serveur supplémentaire : la copie utilise `GET /journal` et les adresses des fichiers.

## Modèle de données

Aucune table nouvelle : une entrée du carnet est un témoignage avec **`visibility = private`** et **`status = draft`**.

| | `visibility` | `status` |
|---|---|---|
| Entrée du carnet | `private` | `draft` |
| Partagée, en relecture | `public` | `pending` |
| Publiée | `public` | `approved` |

Portées du modèle `Testimony` : `journal()` (le carnet) et `withoutJournal()` (tout sauf le carnet). `pending()` exclut toujours le carnet.

Migration `2026_09_26_200001_move_private_testimonies_to_journal` : les témoignages privés déjà « en attente » ou « refusés » deviennent des entrées du carnet.

## Confidentialité : où le carnet est exclu

- Fil, explorer, vidéos, profil public : déjà filtrés par `published()` (approuvé **et** public).
- **Modération** (API et web) : liste, détail, approbation, refus → `withoutJournal()` (une entrée du carnet répond 404).
- **Administration** (API et web) : tableau de bord, fiche utilisateur, contenus → `withoutJournal()`.
- Détail `GET /testimonies/{id}` : l'auteur est reconnu via son jeton Sanctum ; pour toute autre personne, une entrée du carnet répond **404** (son existence n'est pas révélée). Les lectures par l'auteur ne comptent pas comme des vues.
- **Site web** : création en « Privé » → carnet (brouillon) ; `/testimonies/{id}` et `/videos/{id}` répondent 404 à toute autre personne que l'auteur (modération comprise) ; pas de vues comptées, pas de bouton Partager.
- `GET /testimonies/my` (mes témoignages publiés) n'inclut pas le carnet.

**Limite connue** : les fichiers audio et vidéo sont stockés sur le disque `public`, sous un nom aléatoire (non devinable, mais accessible à qui connaîtrait l'adresse exacte). Une étape ultérieure pourra les servir via des URL signées.

## API mobile

Préfixe `/api/v1`, authentification Sanctum.

| Méthode | Route | Rôle |
|---|---|---|
| POST | `testimonies` | Avec `visibility: private` → entrée du carnet (brouillon, `category` facultative) |
| GET | `journal` | Mes entrées, les plus récentes d'abord · `?type=text\|audio\|video` · `?q=recherche` · `?limit=` (50 max) |
| GET | `testimonies/{id}` | Détail (auteur uniquement pour une entrée du carnet) |
| PUT | `testimonies/{id}` | Modifier (reste dans le carnet) |
| DELETE | `testimonies/{id}` | Supprimer |
| POST | `testimonies/{id}/publish` | Partager : `{ category? }` → public + en attente de modération · **409** si déjà partagée |
| POST | `testimonies/{id}/make-private` | Retirer du public et ranger dans le carnet |

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Models/Testimony.php` | Portées `journal`, `withoutJournal`, `isInJournal()` |
| `app/Http/Controllers/Api/TestimonyController.php` | `store`/`update` (brouillon privé), `journal`, `publishFromJournal`, `moveToJournal`, `show` |
| `app/Http/Requests/Api/StoreTestimonyRequest.php` | Catégorie obligatoire seulement à la création d'un témoignage public |
| Contrôleurs de modération et d'administration (API et web) | Exclusion du carnet |
| `tests/Feature/JournalTest.php` | 8 tests : confidentialité, modération, recherche, partage, retour au carnet |
