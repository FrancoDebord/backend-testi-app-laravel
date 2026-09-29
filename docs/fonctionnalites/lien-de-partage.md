# Lien de partage des témoignages

## But

Chaque témoignage dispose d'une URL publique enregistrée en base, que l'application mobile peut partager (WhatsApp, SMS, réseaux sociaux…).

## Format

```
{SHARE_URL}/testimonies/{id}
```

`SHARE_URL` (config `app.share_url`) vaut par défaut **`https://testi.airid-africa.com`**, l'adresse publique, **indépendante de `APP_URL`**. Avant le 2026-09-29, le lien reprenait `APP_URL` : un serveur configuré avec `APP_URL=http://localhost` (valeur par défaut) produisait des liens « http://localhost/testimonies/… » inutilisables.

Exemple : `https://testiapp.exemple.org/testimonies/b9ed2765-dd57-4c17-abb5-7bbc3ce52ead`

Le lien ouvre la page publique du témoignage (route web `testimonies.show`). Un témoignage privé renvoie 403 aux personnes autres que son auteur.

## Fonctionnement technique

### Base de données

Colonne `testimonies.share_url` (`string`, nullable), ajoutée par la migration `2026_09_25_000001_add_share_url_to_testimonies_table.php`, qui **remplit aussi l'URL de tous les témoignages existants**, y compris ceux supprimés logiquement.

### Création

Dans `App\Models\Testimony::booted()` (événement `creating`), l'identifiant UUID est fixé avant l'insertion, puis `share_url` est calculé : l'enregistrement se fait donc en une seule requête. Tous les chemins de création sont couverts : site web, API mobile, seeders.

`share_url` n'est pas dans `$fillable` : un client ne peut pas imposer sa propre URL.

### Construction de l'URL

`Testimony::shareUrlFor(string $id)` construit l'URL à partir de **`SHARE_URL`** (jamais du domaine de la requête ni de `APP_URL`), pour obtenir un lien public identique depuis le web, l'API ou une commande artisan.

**Toujours recalculé à la lecture** : l'accesseur `Testimony::getShareUrlAttribute()` renvoie `shareUrlFor(id)`, si bien qu'une ancienne valeur enregistrée (localhost, adresse IP locale) n'est jamais renvoyée, ni par l'API (`shareUrl`), ni par le site (bouton Partager). La colonne reste renseignée. La migration `2026_09_29_300001_refresh_testimony_share_urls` a recalculé les valeurs enregistrées.

### API mobile

| Appel | Réponse |
|---|---|
| Toute ressource témoignage (`TestimonyResource`) : liste, détail, création… | champ `shareUrl` |
| `POST /api/v1/testimonies/{id}/share` (authentifié) | incrémente `share_count` et renvoie `{"data": {"shareUrl": "…"}}` ; **404** si le témoignage n'existe pas |

Utilisation prévue côté Flutter (`share_plus` est déjà une dépendance ; `TODO` présent dans `testimony_detail_screen.dart`) :

```dart
await Share.share(testimony.shareUrl);   // puis appel POST …/share pour le compteur
```

### Changement de domaine

```bash
php artisan testimonies:refresh-share-urls
```

Recalcule `share_url` de tous les témoignages à partir de la valeur actuelle de `SHARE_URL` (utile seulement pour les exports : la lecture recalcule déjà le lien).

## Fichiers

| Fichier | Rôle |
|---|---|
| `database/migrations/2026_09_25_000001_add_share_url_to_testimonies_table.php` | Colonne + remplissage de l'existant |
| `app/Models/Testimony.php` | `booted()`, `shareUrlFor()` |
| `app/Console/Commands/RefreshTestimonyShareUrls.php` | Commande de recalcul |
| `app/Http/Resources/TestimonyResource.php` | Champ `shareUrl` |
| `app/Http/Controllers/Api/TestimonyController.php` | `recordShare()` |
| `tests/Feature/TestimonyShareUrlTest.php` | Tests |

## Déploiement

1. Vérifier **`APP_URL`** dans le `.env` du serveur (adresse publique réelle, en `https`).
2. `php artisan migrate --force`, puis `php artisan optimize:clear`.

Si la migration a été lancée avec une mauvaise `APP_URL`, corriger le `.env` puis lancer `php artisan testimonies:refresh-share-urls`.

## Limites connues

- Ouverture directe dans l'application : voir [App Links / Universal Links](app-links.md).
- Côté application mobile, le modèle `testimony_model.dart` ne lit pas encore `shareUrl` et le bouton de partage n'est pas branché.
- L'URL contient l'identifiant technique (UUID), pas de titre lisible.
