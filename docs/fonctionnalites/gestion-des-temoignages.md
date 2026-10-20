# Gestion des témoignages (modération, une, corbeille, statistiques)

## But

Donner à l'équipe (modérateurs et administrateurs) un back-office complet pour les témoignages :

- modérer, avec un motif obligatoire en cas de refus ;
- attribuer un label de confiance, « Témoignage approuvé » ;
- mettre des témoignages à la une, avec ordre et dates ;
- gérer une corbeille restaurable ;
- suivre des statistiques par jour.

Chaque action est consignée dans un journal d'audit.

## Configuration : `config/testimonies.php`

| Clé | Défaut | Rôle |
|---|---|---|
| `require_moderation` | `true` (`TESTIMONIES_REQUIRE_MODERATION`) | `true` : seuls les témoignages approuvés sont publics. `false` : approbation automatique à la création (`moderated_by = null`, `moderated_at` = date de création) |
| `featured_fill_recent` | `true` (`TESTIMONIES_FEATURED_FILL_RECENT`) | Complète « À la une » avec les plus récents ; `false` : mise à la une strictement manuelle |
| `featured_limit` / `featured_cache_minutes` | 10 / 10 | Taille et cache de la liste « À la une » |
| `view_dedup_minutes` | 30 | Une vue par personne et par témoignage sur cette durée |
| `trash_days` | 30 | Délai de restauration, puis suppression définitive |
| `stats_timezone` | Africa/Porto-Novo | Jour du calendrier des statistiques |
| `events_keep_days` | 120 | Conservation des événements bruts |

## Statuts et modération

Les statuts existants sont repris : `draft` (carnet privé), `pending`, `approved` et `rejected`, ainsi que la suppression douce. Nouvelles colonnes de `testimonies` :

| Colonne | Contenu |
|---|---|
| `moderated_by`, `moderated_at` | Dernière décision (approbation ou refus). `null` pour une approbation automatique ou un renvoi par l'auteur |
| `rejection_reason` | Motif lisible, **obligatoire** au refus. Il est envoyé à l'auteur et exposé en `rejectionReason` (à l'auteur et à la modération) |

`approved_by` et `approved_at` restent la date de la **première publication**, qui sert au tri et à « À la une ».

**Approuver** :
- le statut passe à `approved` ;
- notification `testimony_approved` (in-app et push) ;
- les abonnés sont prévenus à la première publication seulement ;
- les mentions sont notifiées à chaque approbation, sans doublon.

**Refuser** :
- l'API accepte `reason` (code `RejectionReason`), `rejection_reason` (texte libre), ou les deux ; sans aucun des deux → **422** ;
- le site exige un code, avec une précision facultative ;
- un témoignage déjà publié est dépublié ;
- notification `testimony_rejected` avec le motif dans le message et `payload.rejection_reason`.

Le compteur `users.testimony_count` suit les entrées et sorties du public. Le service commun est `App\Services\TestimonyModeration`, utilisé par l'API, le site et les actions groupées.

**Visibilité** : la liste publique et `GET /testimonies/{id}` ne montrent que les témoignages approuvés. Un témoignage en attente ou refusé renvoie 404, sauf à son auteur et à la modération. L'auteur voit tous les siens avec leur statut (`GET /testimonies/my`).

### « Modifier et renvoyer » (auteur)

`PUT /api/v1/testimonies/{id}`, sans endpoint séparé, modifié **par l'auteur** :

| Avant | Après |
|---|---|
| `rejected` | `pending` ; `rejection_reason`, `moderated_by` et `moderated_at` vidés ; modérateurs et administrateurs prévenus (`testimony_resubmitted`, in-app et push) |
| `approved` et `require_moderation = true` | `pending` (pas de contournement de la modération), modérateurs prévenus |
| `approved` et `require_moderation = false` | reste `approved` |
| label `is_verified` | toujours retiré, jusqu'à une nouvelle vérification par un administrateur |

Une correction faite par la modération garde le statut. Le carnet privé reste un brouillon.

## Label « Témoignage approuvé »

Ce label est distinct de la publication. Colonnes : `is_verified`, `verified_by`, `verified_at`.

- Il est **réservé aux administrateurs** et ne s'applique qu'à un témoignage approuvé.
- Les modérateurs reçoivent 403.

## À la une

Colonnes : `is_featured` (existante), `featured_order`, `featured_from`, `featured_until` (dates facultatives). Les modérateurs et les administrateurs peuvent mettre un témoignage à la une.

`GET /api/v1/testimonies/featured` (public) et la section « À la une » de l'accueil du site (5 éléments) utilisent la même liste (`App\Services\FeaturedTestimonies`). Depuis le 2026-10-20, avec une limite N (`featured_limit`, 10) :

1. le **témoignage publié le plus récent** y entre **toujours**, même s'il y a déjà N mises à la une manuelles ;
2. puis les **mises à la une manuelles** actives (approuvées, publiques, non supprimées, dans leurs dates), de la plus récente à la plus ancienne, jusqu'à N au total ;
3. si `featured_fill_recent` est vrai, complément avec les témoignages publiés **suivants les plus récents**, jusqu'à N ;
4. la liste est ensuite **triée par date de publication** (`COALESCE(approved_at, created_at)`, comme `latestPublished`), du plus récent au plus ancien, manuels et compléments mêlés.

`featured_order` n'influence plus l'ordre (il reste enregistré et renvoyé dans `featuredOrder`). Chaque élément porte `featured_source` : `manual` (mise à la une manuelle en cours, badge `featured`) ou `recent` (le plus récent quand il n'est pas mis à la une manuellement, et le complément ; pas de badge).

Cache : identifiants en cache `featured_cache_minutes` (10 min), **vidé** à chaque publication (approbation, publication directe sans modération), refus, modification par l'auteur, mise à la une, corbeille, restauration et suppression : `TestimonyModeration::forgetFeatured()` et, pour tous les autres chemins (suppression par l'auteur, vidéo devenue prête, changement de visibilité…), les événements `saved` / `deleted` / `restored` du modèle `Testimony`. Un témoignage qui vient d'être publié apparaît donc aussitôt en tête.

## Filtres de la liste

`GET /api/v1/testimonies` accepte les paramètres suivants, combinables :

- `?status=approved` (défaut) ; `pending` et `rejected` sont réservés à la modération (sinon 403) ; toute autre valeur → 422 ;
- `?verified=1` : avec le label seulement ;
- `?featured=1` : mises à la une manuelles actives ;
- `?sort=` :
  - `recent` (**défaut**, sans `sort`) : date de publication, du plus récent au plus ancien ;
  - `popular` : réactions, puis vues ;
  - `most_viewed` : vues (`recommended` reste accepté comme ancien nom) ;
  - `for_you` : classement personnalisé, seulement s'il est demandé explicitement.

Site : la liste « Témoignages récents » de l'accueil, Explorer et Vidéos sont triées du plus récemment publié au plus ancien par défaut (« Plus récents » sélectionné) ; les autres tris restent proposés.

Avec `?after=` (synchronisation par différence), le tri par `updated_at` reste prioritaire.

## Corbeille

- **Mise à la corbeille** (suppression douce) : par l'auteur ou un administrateur depuis l'API (inchangé), et par la modération depuis le back-office. Restauration possible pendant **30 jours**.
- **Suppression définitive** : réservée au **super-administrateur**, ou faite automatiquement par `testimonies:purge-trash` (chaque nuit) au-delà de 30 jours.
- **Vidéo Bunny** : depuis ce lot, elle est supprimée **à la suppression définitive seulement**. Avant, elle l'était dès la mise à la corbeille, ce qui la rendait impossible à restaurer.

### Super-administrateur

`users.is_super_admin` n'est valable que pour un administrateur. Il ne se modifie que par la ligne de commande, jamais par l'API ni le back-office :

```bash
php artisan users:super-admin admin@example.com           # attribuer
php artisan users:super-admin admin@example.com --revoke  # retirer
```

Chaque attribution ou retrait est consigné dans `admin_audit_logs`.

## Statistiques

| Table | Contenu |
|---|---|
| `testimony_events` | Événements bruts : `view` (vue dédoublonnée), `share`, `watch` (secondes regardées) ; empreinte du spectateur (SHA-256 du compte, de l'appareil, de la session, ou de l'IP et du navigateur). Gardés 120 jours |
| `testimony_daily_stats` | Une ligne par témoignage et par jour : `views`, `unique_viewers`, `reactions`, `comments`, `shares`, `video_watch_seconds` |

**Sources des données** :
- **Vues** : `App\Services\ViewCounter`, une vue par personne sur 30 min. Il est appelé par les pages du site, par `GET /testimonies/{id}` (qui ne compte plus chaque lecture) et par `POST /testimonies/{id}/view { device_id?, watched_seconds? }` (public, en-tête `X-Device-Id` accepté).
- **Partages** : `POST /testimonies/{id}/share`.
- **Réactions et commentaires** : repris de leurs tables, par date de création.

**Agrégation** : `php artisan testimonies:aggregate-stats` tourne chaque nuit à 0 h 20 (heure du Bénin) pour la veille et l'avant-veille. On peut la relancer sans doublon. `--date=AAAA-MM-JJ` calcule un jour précis, `--days=N` les N derniers jours. Le jour en cours est calculé à la volée dans le back-office.

## Back-office (`/moderation`, modérateurs et administrateurs)

| Page | Contenu |
|---|---|
| `/moderation` | Onglets En attente, Approuvés, Refusés, À la une et Corbeille ; recherche (titre, texte, auteur) ; filtres type, catégorie et label. Actions groupées selon l'onglet : approuver, refuser avec motif, mettre à la une, retirer de la une, corbeille, restaurer |
| `/moderation/{id}` | Fiche : aperçu (vidéo Bunny en MP4 de repli, ou état de l'encodage), preuves, statistiques sur 30 jours, historique (audit et relectures) ; boutons approuver, refuser ou dépublier, une (ordre, dates), label (administrateurs), corbeille, restaurer, suppression définitive (super-administrateur) |
| `/moderation/{id}/statistiques?jours=7\|30\|90` | Courbes d'audience et de participation, évolution par rapport à la période précédente, détail par jour |
| `/moderation/tableau-de-bord?jours=7\|30\|90` | Tendances globales, témoignages les plus vus, file d'attente, compteurs (publiés, label, une, corbeille) |

## Droits : `App\Policies\TestimonyPolicy`

| Action | Modérateur | Administrateur | Super-admin |
|---|:-:|:-:|:-:|
| Approuver, refuser, mettre à la une, corbeille, restaurer, statistiques | oui | oui | oui |
| Label « Témoignage approuvé » | non | oui | oui |
| Suppression définitive | non | non | oui |

## Journal d'audit : `admin_audit_logs`

Colonnes : `actor_id` et `actor_name` (`null` ou « Système » pour la ligne de commande et la purge), `action`, `subject_type` et `subject_id`, `before` et `after` (JSON), `reason`, `ip`, `created_at`.

Actions consignées :
- `testimony.approved|rejected|resubmitted|verified|unverified|featured|unfeatured|deleted|restored|force_deleted` ;
- `user.super_admin_granted|revoked`.

Le service est `App\Services\AdminAudit::record()`. `moderation_logs` reste tenu à jour, car l'app lit l'historique des refus.

## API : nouveaux champs de `TestimonyResource`

| Champ | Valeur |
|---|---|
| `badges` | `["verified", "featured"]` selon le cas (`verified` = label, `featured` = à la une manuelle en cours) |
| `featured_source` | `manual`, `recent` (complément de `/featured`) ou `null` |
| `isVerified`, `verifiedAt` | Label « Témoignage approuvé » |
| `featuredOrder`, `featuredFrom`, `featuredUntil` | Réglages de la une |
| `moderatedAt` | Date de la dernière décision |
| `isPinned` | Mise à la une manuelle **en cours** (les dates sont désormais prises en compte) |
| `rejectionReason` | Lu d'abord dans `testimonies.rejection_reason` |

## Tests

`php artisan test --filter=TestimonyManagementTest` couvre :
- la visibilité selon le statut et l'approbation automatique ;
- approuver et refuser (motif obligatoire, audit, notifications) ;
- « Modifier et renvoyer » (refusé, approuvé avec et sans modération obligatoire, label retiré) ;
- le label, réservé aux administrateurs ;
- « À la une » (ordre, dates, complément, `featured_source`, cache) ;
- les filtres combinés ;
- la corbeille (restauration avant et après 30 jours, purge, droits de suppression définitive) ;
- les vues dédoublonnées (compte, appareil, 30 min, secondes regardées) ;
- l'agrégation quotidienne ;
- le back-office (onglets, actions groupées, 403) ;
- le super-administrateur (commande uniquement, audit).

## Limites connues

- `unique_viewers` est compté par jour : sur une période, le back-office additionne les jours (une même personne revenue deux jours compte deux fois).
- Une réaction retirée ou un commentaire supprimé reste compté le jour de sa création.
- La mise à la corbeille par l'auteur (API) ne diminue pas `users.testimony_count` (comportement existant).
