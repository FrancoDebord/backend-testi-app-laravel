# Paramètres globaux (site et application)

## But

L'administrateur voit et modifie **exactement les mêmes réglages** sur la page web *Administration › Paramètres* (`/admin/settings`) et dans l'application : mêmes groupes, même ordre, mêmes libellés et descriptions, traduits en français ou en anglais.

## Règles

| Règle | Détail |
|---|---|
| Qui | Administrateurs seulement (`role:administrateur`) ; modérateurs et utilisateurs → **403**, sans connexion → 401 |
| Source unique | `App\Services\SettingsSchema` construit les groupes pour la page web **et** pour l'API : impossible qu'ils divergent |
| Réglages affichés | Ceux enregistrés dans `app_settings`, dans l'ordre de `SettingsSchema::GROUPS` ; un réglage enregistré mais non répertorié est ajouté à la fin de son groupe (libellé de la base) |
| Réglages masqués | `allow_audio`, `allow_video` (`SettingsSchema::HIDDEN`) : remplacés par les interrupteurs du groupe **Publication** (`testimony_type_*_enabled`, voir [types de témoignages](types-de-temoignages.md)). Masqués sur le site et dans l'API, refusés en modification |
| Libellés | `lang/fr/settings.php`, `lang/en/settings.php` (selon `Accept-Language` ou la langue du site) |
| Types | `boolean`, `integer`, `string`, `json` : la valeur est renvoyée **typée** |
| Modification | Chaque valeur est vérifiée contre le schéma : clé inconnue ou masquée, ou type incorrect → **422**, et **rien** n'est enregistré |

### Groupes (ordre d'affichage)

| Clé | Libellé | Réglages |
|---|---|---|
| `app` | Général | `app_name`, `app_tagline`, `app_version`, `maintenance_mode` |
| `publishing` | Publication | `testimony_type_text_enabled`, `testimony_type_audio_enabled`, `testimony_type_video_enabled`, `testimony_type_youtube_enabled` |
| `moderation` | Modération et limites | `auto_approve_testimonies`, `max_testimony_length`, `max_audio_duration_sec`, `max_video_duration_sec`, `max_file_size_mb` |
| `features` | Fonctionnalités | `allow_comments`, `allow_reactions`, `allow_sharing`, `require_consent_form` |
| `notifications` | Notifications | `push_notifications_enabled` (booléen, vrai par défaut ; faux = plus aucun push, voir [notifications push](notifications-push.md)). Ligne créée par la migration `2026_10_19_300003_add_push_notifications_setting` si elle manque (et par `AppSettingSeeder`) |
| `registration` | Inscriptions | `allow_registration`, `default_role`, `require_email_verification` |

> Seuls les interrupteurs du groupe **Publication** (`TestimonyTypePolicy`) et `push_notifications_enabled` (`PushNotifications::globallyEnabled()`) sont appliqués par le serveur aujourd'hui. Les autres réglages sont enregistrés et affichés, mais pas encore lus par le code.

## API

Préfixe `/api/v1`, Sanctum, administrateurs.

### `GET admin/settings/schema`

```json
{
  "success": true,
  "data": {
    "groups": [
      {
        "key": "app",
        "label": "Général",
        "settings": [
          { "key": "maintenance_mode", "label": "Mode maintenance", "description": "Signale une maintenance en cours.", "type": "boolean", "value": false },
          { "key": "app_name", "label": "Nom de l’application", "description": null, "type": "string", "value": "My Testimony" }
        ]
      },
      { "key": "publishing", "label": "Publication", "settings": [ … ] }
    ]
  },
  "message": ""
}
```

### `GET admin/settings` (inchangé)

`{ groupe: { clé: valeur } }`, valeurs typées (tous les réglages, masqués compris).

### `PUT admin/settings`

Corps `{ "clé": valeur, … }` (une ou plusieurs clés). Valeurs acceptées :

| Type | Accepté |
|---|---|
| `boolean` | `true` / `false`, `1` / `0`, `"1"` / `"0"`, `"true"` / `"false"` |
| `integer` | entier ou texte d'entier (`250`, `"250"`) |
| `string` | texte (`null` = vide), 5 000 caractères au plus |
| `json` | objet ou tableau, ou texte JSON valide |

Réponse 200 : même format que `GET admin/settings`. Erreur 422 :

```json
{ "success": false, "message": "…", "errors": { "max_file_size_mb": ["Le paramètre max_file_size_mb doit être un nombre entier."], "nope": ["Paramètre inconnu : nope."] } }
```

## Site

`resources/views/admin/settings.blade.php` affiche `SettingsSchema::groups()` : libellé, description, clé technique, interrupteur (booléen), champ nombre (entier), zone de texte (JSON) ou champ texte. L'enregistrement passe par la même validation (erreurs sous chaque champ).

## Fichiers

| Fichier | Rôle |
|---|---|
| `app/Services/SettingsSchema.php` | Groupes, ordre, réglages masqués, libellés, validation et enregistrement |
| `lang/{fr,en}/settings.php` | Libellés des groupes et des réglages, messages de validation |
| `app/Http/Controllers/Api/AdminController.php` | `settingsSchema()`, `updateSettings()` |
| `app/Http/Controllers/Web/AdminController.php` | `settings()`, `updateSettings()` |
| `tests/Feature/GlobalSettingsTest.php` | 8 tests : ordre et libellés, traduction, page web identique au schéma, enregistrement typé, refus (clé inconnue, masquée, mauvais type), interrupteur des push, droits, formulaire web |

## Ajouter un réglage

1. L'insérer dans `app_settings` (migration ou `AppSettingSeeder`) avec son `type` et son `group`.
2. L'ajouter à `SettingsSchema::GROUPS` (à la bonne place) et ses libellé et description dans `lang/fr/settings.php` **et** `lang/en/settings.php`.
3. Le site et l'application l'affichent aussitôt.
