# Ajouter une version de la Bible

Une version (traduction) de la Bible est disponible dans l'application et sur le site dès que ses livres et ses versets sont dans les tables `bible_books` et `bible_verses`. `GET /bible/translations` liste les traductions présentes en base ; `GET /bible/download/{code}` sert la traduction complète pour la lecture hors connexion.

Trois commandes artisan remplissent ces tables :

| Commande | Source |
|---|---|
| `bible:import-verses` | Fichier JSON « un objet par verset » (fichiers de `storage/bible/`) |
| `bible:import` | Fichier JSON « livres → chapitres » (fichiers de `storage/bible/old/`) |
| `bible:fetch` | API.Bible ou BibleBrain (clé d'API, traduction déclarée dans `config/bible.php`) |

## 1. Choisir le code et déclarer la version

Le **code** (`LSG`, `KJV`, `BDS`…) identifie la version partout : base, API, choix mémorisé de l'utilisateur, annotations. 10 caractères au plus, mis en majuscules par les commandes.

Déclarer la version dans `config/bible.php`, tableau `translations` :

```php
'BDS' => [
    'name'      => 'Bible du Semeur',
    'language'  => 'fr',
    'lang_name' => 'Français',
    'source'    => 'file',   // valeur libre pour un import par fichier ; 'apibible' ou 'biblebrain' pour bible:fetch
],
```

Sans cette entrée, la version reste utilisable mais l'API renvoie le code comme nom et `language: "unknown"`.

Après modification de `config/bible.php` sur le serveur : `php artisan config:clear` (ou `config:cache` si la configuration est mise en cache).

## 2. Importer un fichier « un objet par verset » (`bible:import-verses`)

C'est le format des fichiers à la racine de `storage/bible/` (`french_bds.json`, `english_niv.json`, `fon.json`, `yoruba_2014.json`…) :

```json
[
  { "language": "French", "version": "BDS", "testament": "Old", "book": 1, "chapter": 1, "verse": 1, "text": "Au commencement…" }
]
```

`book` (1 = Genèse … 66 = Apocalypse), `chapter`, `verse` et `text` sont obligatoires ; `language`, `version` et `testament` sont facultatifs.

```bash
# 1. Contrôler sans rien écrire
php artisan bible:import-verses BDS "storage/bible/french_bds.json" --dry-run

# 2. Importer
php artisan bible:import-verses BDS "storage/bible/french_bds.json"
```

Mettre le chemin entre guillemets quand le nom contient une espace (`"storage/bible/french_s 21.json"`).

| Option | Effet |
|---|---|
| `--dry-run` | Fait tous les contrôles et affiche le rapport, sans écrire en base |
| `--fresh` | Supprime d'abord les livres et versets de ce code (dans la même transaction que l'import) |
| `--lang=fr\|en` | Langue des noms de livres ; par défaut, déduite du champ `language` du fichier, sinon de `config/bible.php` |
| `--names-from=<CODE>` | Copie les noms et abréviations des livres d'une traduction déjà en base |

**Contrôles avant écriture.** La commande lit le schéma réel des tables, pas celui des migrations (le serveur peut avoir des colonnes élargies à la main), et refuse l'import en cas d'erreur, sans rien écrire :

- longueur du code : la plus petite des colonnes `bible_books.translation` et `bible_verses.translation` (10 caractères en local) ;
- livre entre 1 et 66, chapitre et verset entre 1 et le maximum de leur colonne (`smallint unsigned` : 65 535) ;
- taille du texte selon le type de la colonne `text` (`TEXT` : 65 535 octets) ;
- noms et abréviations des livres selon la longueur des colonnes `name` et `abbreviation`.

Avertissements (l'import continue) : versets en double (le dernier est gardé), textes vides (ignorés), livres absents, plusieurs langues ou versions dans le fichier, traduction déjà en base, code absent de `config/bible.php`.

**Ce qui est écrit.**

- `bible_books` : un livre par numéro présent ; `chapters_count` = plus grand chapitre ; `testament` = `OT` / `NT` (à partir de `Old` / `New`, sinon d'après le rang du livre), valeurs attendues par le lecteur du site.
- `bible_verses` : par lots de 1 000, en mise à jour si le verset existe déjà (relancer la commande ne crée pas de doublon). Sans `--fresh`, les versets absents du fichier restent en base.
- Après l'import, la commande recompte les livres et versets en base et affiche l'entrée à ajouter dans `config/bible.php` si le code n'y est pas.

**Noms des livres.** Les fichiers ne les donnent pas. Noms et abréviations intégrés (`App\Support\BibleBookNames`) : français (`Genèse`, `Gn`) et anglais (`Genesis`, `Gen`). Pour une autre langue (yoruba, ewe, fon), la commande prend les noms français et le signale ; `--names-from=<CODE>` reprend ceux d'une traduction de la même langue déjà en base.

**Résultat des contrôles sur les fichiers actuels (2026-10-22).** Les 22 fichiers de `storage/bible/` passent le contrôle : 66 livres chacun, aucun doublon ni texte vide, chapitres ≤ 150, versets ≤ 176, texte le plus long 2 551 octets (`yoruba_2010.json`). Nombre de versets : de 28 176 (`french_bfc.json`) à 31 167 (`english_darby.json`). Import réel testé en local (31 067 versets de `french_bds.json`) ; 128 Mo de mémoire PHP suffisent.

Codes suggérés :

| Fichier | Code | Langue |
|---|---|---|
| `french.json` | `LSG` (Louis Segond 1910) | fr |
| `french_bds.json` · `french_bfc.json` · `french_darby.json` · `french_martin.json` · `french_pdv 2017.json` · `french_s 21.json` | `BDS` · `BFC` · `DBY` · `MAR` · `PDV` · `S21` | fr |
| `english_amplified.json` · `english_asv.json` · `english_csb.json` · `english_darby.json` · `english_erv.json` · `english_esv.json` · `english_kj.json` · `english_niv.json` · `english_nkj.json` · `english_nlt.json` | `AMP` · `ASV` · `CSB` · `DARBY` · `ERV` · `ESV` · `KJV` · `NIV` · `NKJV` · `NLT` | en |
| `yoruba.json` · `yoruba_2010.json` · `yoruba_2014.json` | `YOR` · `YOR10` · `YOR14` | yo |
| `ewe_2020.json` | `EWE20` | ee |
| `fon.json` | `FON` | fon |

`LSG`, `KJV` et `ASV` existent déjà (en base ou dans `config/bible.php`) : réimporter avec `--fresh` remplace la version actuelle.

> **LSG** : une ancienne base locale contenait sous `LSG` le texte de `old/fr_apee.json` avec des noms de livres anglais (« Genesis »). `php artisan bible:import-verses LSG storage/bible/french.json --fresh` la remplace par la Louis Segond 1910 avec les noms français (fait en local, vérifié le 2026-10-22 : 66 noms français). Vérifier la base du serveur (Jean 3:16 doit commencer par « Car Dieu a tant aimé le monde ») avant de le faire en production.
>
> **Noms des livres** : chaque traduction garde ses propres noms (`bible_books.name`), affichés dans le lecteur `/bible`, l'API et, depuis le 2026-10-22, la référence de chaque version du [verset du jour](verset-du-jour.md). Importer une traduction anglaise avec `--lang=en` (ou un fichier dont `language` vaut `English`) pour avoir « John », et `--names-from=<CODE>` pour une langue sans noms intégrés.

Les fichiers de `storage/bible/` (≈ 10 Mo chacun) ne sont pas dans git : copier sur le serveur ceux à importer.

## 3. Importer un fichier « livres → chapitres » (`bible:import`)

```bash
php artisan bible:import <CODE> <chemin/vers/fichier.json>
```

Exemples :

```bash
php artisan bible:import BBE storage/bible/old/en_bbe.json
php artisan bible:import KJV storage/bible/old/en_kjv.json --fresh
```

| Option | Effet |
|---|---|
| `--fresh` | Supprime d'abord tous les livres et versets de ce code. À utiliser pour réimporter une version déjà présente. |

Sans `--fresh`, la commande met à jour les versets existants (même livre, chapitre, verset) et ajoute les autres : la relancer ne crée pas de doublons. L'import se fait dans une transaction : en cas d'erreur, rien n'est enregistré.

### Format attendu

Un tableau de 66 livres dans l'ordre canonique (Genèse = 1 … Apocalypse = 66), chaque livre contenant ses chapitres, chaque chapitre la liste de ses versets (format [thiagobodruk/bible](https://github.com/thiagobodruk/bible)) :

```json
[
  {
    "abbrev": "gn",
    "name": "Genèse",
    "chapters": [
      ["Au commencement…", "La terre était…"],
      ["Ainsi furent achevés…"]
    ]
  }
]
```

- `name` (facultatif) : nom du livre. Absent, la commande prend le nom **anglais** si le code est `KJV`, `ESV`, `NIV` ou `NKJV`, sinon le nom **français**. Pour une autre version anglaise sans `name` (par exemple `BBE`), les livres porteront donc des noms français : ajouter `name` au fichier ou ajouter le code à la liste `$isEnglish` de `ImportBible`. Les noms viennent de `App\Support\BibleBookNames`.
- `abbreviation` ou `abbrev` (facultatif), `testament` (`OT` / `NT`, facultatif, déduit du rang du livre).
- Fichier en UTF-8 ; le BOM éventuel est ignoré.

Fichiers prêts à ce format : `storage/bible/old/` (`en_bbe.json`, `en_kjv.json`, `fr_apee.json`).

Les fichiers à la racine de `storage/bible/` (un objet par verset) ne sont pas lus par `bible:import` : utiliser `bible:import-verses` (§ 2). Chaque commande refuse le format de l'autre.

## 4. Importer depuis une API (`bible:fetch`)

Pour une version déclarée dans `config/bible.php` avec `source` = `apibible` ou `biblebrain` :

```bash
# Trouver l'identifiant de la version
php artisan bible:sources --source=apibible   --lang=fr
php artisan bible:sources --source=biblebrain --lang=fon

# Importer
php artisan bible:fetch LSG
php artisan bible:fetch YCB --bible-id=<id>      # API.Bible
php artisan bible:fetch FON --fileset=<id>       # BibleBrain
php artisan bible:fetch KJV --fresh
```

| Option | Effet |
|---|---|
| `--fresh` | Supprime les données existantes de ce code avant l'import |
| `--bible-id=` / `--fileset=` | Remplace l'identifiant de `config/bible.php` |
| `--delay=150` | Pause entre deux requêtes, en millisecondes |

Variables d'environnement : `BIBLE_API_KEY` (API.Bible), `BIBLEBRAIN_API_KEY` (BibleBrain), et pour chaque version `BIBLE_ID_<CODE>` ou `BIBLEBRAIN_FILESET_<CODE>`.

## 5. Vérifier

- `GET /api/v1/bible/translations` : la version apparaît avec `booksCount` (66 attendus) et `versesCount` (≈ 31 100 pour une Bible complète).
- Page Bible du site et page du verset du jour : la version est proposée (voir [verset du jour](verset-du-jour.md)).
- Dans l'application, la version se télécharge depuis la liste des traductions.

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Console/Commands/ImportBibleVerses.php` | `bible:import-verses` |
| `app/Console/Commands/ImportBible.php` | `bible:import` |
| `app/Support/BibleBookNames.php` | Noms et abréviations des 66 livres (français, anglais) |
| `tests/Feature/ImportBibleVersesTest.php` | Tests de `bible:import-verses` |
| `app/Console/Commands/FetchBible.php` | `bible:fetch` |
| `app/Console/Commands/ListBibleSources.php` | `bible:sources` |
| `config/bible.php` | Clés d'API et registre des versions (nom, langue, source) |
| `app/Http/Controllers/Api/BibleController.php` | `translations`, `download`, lecture et recherche |
| `storage/bible/` | Fichiers JSON sources |
