# Documentation en ligne

## But

Lire cette documentation directement dans la plateforme, en pages HTML aux couleurs de la charte AIRID, sans ouvrir les fichiers Markdown.

## Utilisation

- Menu latéral : **Administration → Documentation**, ou adresse `/admin/documentation`.
- **Réservé aux administrateurs** : la documentation décrit le déploiement et le fonctionnement interne du serveur.
- Sommaire à gauche (bouton « Sommaire » sur mobile). Les liens entre pages et vers les sections (`#4-déploiement`…) fonctionnent comme dans les fichiers.
- La date de dernière mise à jour du fichier est affichée sous le titre.

## Fonctionnement technique

- Les pages sont **lues à chaque affichage** depuis le dossier `docs/` : modifier un fichier `.md` met la page à jour, sans compilation ni commande.
- Une nouvelle page `docs/…/nom.md` apparaît automatiquement dans le sommaire, avec son titre `# …`. Ordre : accueil (`README.md`), autres pages générales, `fonctionnalites/*`, puis le journal.
- Conversion : `Str::markdown()` de Laravel (CommonMark, variante GitHub : tableaux, listes, blocs de code). Le HTML écrit dans un fichier Markdown est **échappé** (`html_input: escape`), et les liens dangereux (`javascript:`…) sont neutralisés.
- Post-traitement dans `DocumentationController::enhance()` :
  - identifiants des titres au format GitHub, pour les ancres ;
  - liens `autre-page.md` réécrits vers la route de la documentation ;
  - tableaux placés dans un cadre défilant (jamais de défilement horizontal de la page).
- Sécurité : seule une page présente dans la liste des fichiers `.md` de `docs/` peut être affichée ; tout autre chemin renvoie 404, ce qui empêche de lire un autre fichier du serveur.
- Styles : classe `doc-content` dans `resources/css/app.css`.

## Accès réservé aux administrateurs

- Page `/admin/documentation` : groupe de routes `role:administrateur` ; entrée de menu visible seulement des administrateurs ; test `test_only_administrators_can_read_the_documentation` (visiteur → connexion, utilisateur et modérateur → 403).
- Aucun autre accès : pas de route publique, rien dans `public/`, rien dans l'API.
- **Garde-fou** : `docs/.htaccess` refuse toute lecture directe du dossier si un serveur Apache exposait par erreur la racine du projet (la racine publique doit être `public/`).

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Http/Controllers/Web/DocumentationController.php` | Liste des pages, conversion, liens, contrôle d'accès |
| `resources/views/admin/documentation.blade.php` | Page (sommaire + contenu) |
| `routes/web.php` | Route `admin.documentation` : `/admin/documentation/{page?}` |
| `resources/views/layouts/app.blade.php` | Entrée « Documentation » du menu Administration |
| `resources/css/app.css` | Styles `doc-content` |
| `tests/Feature/DocumentationPageTest.php` | Tests |

## Déploiement

- Le dossier `docs/` doit être présent sur le serveur (il fait partie du dépôt).
- CSS modifié : recompiler et copier `public/build/` ([README §4](../README.md#4-déploiement)).
- Puis `php artisan optimize:clear` (nouvelle route).

## Limites connues

- Pas de recherche plein texte.
- `CLAUDE.md` (règles pour l'assistant) n'est pas affiché : il n'est pas dans `docs/`.
