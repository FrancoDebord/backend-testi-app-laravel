# Ouverture des liens de partage dans l'application (App Links / Universal Links)

## But

Quand quelqu'un touche un [lien de partage](lien-de-partage.md) (`{APP_URL}/testimonies/{id}`) sur un téléphone où l'application TestiApp est installée, le témoignage doit s'ouvrir **dans l'application** plutôt que dans le navigateur. Sans l'application, le lien ouvre simplement la page web.

Android (App Links) et iOS (Universal Links) n'autorisent cela que si **le site déclare lui-même l'application** dans deux fichiers publics. Le serveur les fournit.

## Fichiers servis

| Adresse | Lue par | Contenu |
|---|---|---|
| `/.well-known/assetlinks.json` | Android | Nom du paquet de l'app et empreintes SHA-256 des certificats de signature |
| `/.well-known/apple-app-site-association` | iOS | Identifiant de l'app (`TEAMID.bundle`) et chemins acceptés (`/testimonies/*`, `/lives/*`) |

Exemple Android :

```json
[{
  "relation": ["delegate_permission/common.handle_all_urls"],
  "target": {
    "namespace": "android_app",
    "package_name": "com.airid.testi_app",
    "sha256_cert_fingerprints": ["41:2F:…:7D"]
  }
}]
```

Tant que `IOS_APP_ID` n'est pas renseigné, le fichier iOS est servi **vide** (`"details": []`) : aucun lien n'est associé à une application iOS.

## Configuration

Fichier `config/applinks.php`, alimenté par le `.env` :

| Variable | Valeur par défaut | Rôle |
|---|---|---|
| `ANDROID_APP_PACKAGE` | `com.airid.testi_app` | Identifiant Android (`applicationId` de l'app Flutter) |
| `ANDROID_APP_SHA256_FINGERPRINTS` | empreinte de la **clé de debug** | Empreintes des certificats autorisés, séparées par des virgules |
| `IOS_APP_ID` | *(vide)* | `<TEAM_ID>.<bundle id>`, par ex. `ABCDE12345.com.airid.testiApp` |

Chemins concernés : clé `paths` de `config/applinks.php` (actuellement `/testimonies/*` et `/lives/*`, ce dernier ajouté pour ouvrir les directs dans l'app).

**Important pour la production** : la valeur par défaut ne contient que l'empreinte de la clé de **debug**. Pour l'application publiée, ajouter dans `ANDROID_APP_SHA256_FINGERPRINTS` l'empreinte de la clé **release**, ou celle de **Play App Signing** (Google Play Console → Intégrité de l'application → Certificat de clé de signature). Sinon Android refusera d'ouvrir les liens dans l'app publiée.

## Fonctionnement technique

- Les deux routes sont déclarées en tête de `routes/web.php` (noms `applinks.android` et `applinks.ios`) et renvoient du JSON, sans barres obliques échappées (`JSON_UNESCAPED_SLASHES`).
- Elles sont publiques (aucune connexion) : Android et iOS les téléchargent eux-mêmes, en **HTTPS**, sans redirection.
- Android vérifie le fichier à l'installation de l'app ; iOS le récupère via le CDN d'Apple, avec parfois un délai de plusieurs heures.

## Fichiers

| Fichier | Rôle |
|---|---|
| `config/applinks.php` | Paquet Android, empreintes, identifiant iOS, chemins |
| `routes/web.php` | Routes `/.well-known/assetlinks.json` et `/.well-known/apple-app-site-association` |
| `tests/Feature/AppLinksTest.php` | Tests |

## Déploiement

1. Renseigner dans le `.env` du serveur `ANDROID_APP_SHA256_FINGERPRINTS` (empreinte release / Play App Signing) et, pour iOS, `IOS_APP_ID`.
2. `php artisan config:clear` (ou `php artisan optimize:clear`).
3. Vérifier que les deux adresses répondent en HTTPS avec du JSON :
   ```bash
   curl -i https://<domaine>/.well-known/assetlinks.json
   curl -i https://<domaine>/.well-known/apple-app-site-association
   ```
   Certains hébergeurs bloquent les dossiers commençant par un point : si la réponse est 403 ou 404, autoriser `/.well-known/` dans la configuration Apache ou Nginx.
4. Test Android : [outil de Google](https://developers.google.com/digital-asset-links/tools/generator), ou `adb shell pm get-app-links com.airid.testi_app` sur un téléphone.

## Limites connues

- **Côté application mobile, rien n'est encore branché** (constaté le 2026-09-25) :
  - Android : pas de `<intent-filter android:autoVerify="true">` pour `https://<domaine>/testimonies` dans `AndroidManifest.xml` ;
  - iOS : pas d'entitlement `applinks:<domaine>` (Associated Domains) ;
  - Flutter : pas de gestion du lien entrant (par ex. paquet `app_links`) pour ouvrir l'écran du témoignage.

  Tant que ce n'est pas fait, les liens de partage ouvrent la page web.
- Le domaine déclaré côté mobile doit être exactement celui de `APP_URL`.
