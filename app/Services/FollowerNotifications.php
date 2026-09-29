<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Notifie tous les abonnés (table follows) d'un auteur : une AppNotification par abonné,
 * puis l'envoi push de chacune (docs/fonctionnalites/notifications-push.md).
 *
 * Insertion par lots de 50 via insert() : l'UUID est généré ici (insert() ne passe pas par
 * HasUuids) et l'observateur n'est pas déclenché, d'où l'appel explicite à
 * PushNotifications::dispatchFor() pour chaque ligne. L'auteur n'est jamais notifié.
 */
class FollowerNotifications
{
    /** Clés de $payload recopiées dans les colonnes de app_notifications (le reste va dans `payload`). */
    private const COLUMNS = ['testimony_id', 'testimony_title', 'comment_id', 'actor_avatar'];

    private const CHUNK = 50;

    /**
     * @param  string  $type     valeur de App\Enums\NotificationType (ex. 'new_followed_testimony', 'live_started')
     * @param  array   $payload  colonnes (testimony_id, testimony_title, comment_id, actor_avatar) et
     *                           données libres (ex. live_id), stockées dans la colonne JSON `payload`
     * @param  string|null  $message  texte de la notification (et corps du push)
     * @return int nombre d'abonnés notifiés
     */
    /**
     * Témoignage publié : ses abonnés sont prévenus. Public ou réservé aux abonnés : tous les abonnés
     * peuvent le lire. Jamais pour le carnet privé ni pour un témoignage non approuvé.
     */
    public static function newTestimony(\App\Models\Testimony $testimony): int
    {
        if ($testimony->isInJournal() || $testimony->status !== \App\Enums\TestimonyStatus::Approved || !$testimony->user) {
            return 0;
        }

        return self::notify($testimony->user, 'new_followed_testimony', [
            'testimony_id'    => $testimony->id,
            'testimony_title' => $testimony->title,
        ], $testimony->user->display_name . ' a partagé un nouveau témoignage : "' . $testimony->title . '"');
    }

    public static function notify(User $author, string $type, array $payload, ?string $message): int
    {
        $columns = array_intersect_key($payload, array_flip(self::COLUMNS));
        $extra   = array_diff_key($payload, array_flip(self::COLUMNS));
        $now     = now();
        $count   = 0;

        $author->followers()
            ->select('users.id')
            ->where('users.id', '!=', $author->id)
            ->chunk(self::CHUNK, function ($followers) use ($author, $type, $columns, $extra, $message, $now, &$count) {
                $rows = $followers->map(fn ($follower) => array_merge([
                    'id'              => (string) Str::uuid(),
                    'recipient_id'    => $follower->id,
                    'actor_id'        => $author->id,
                    'actor_name'      => $author->display_name,
                    'actor_avatar'    => $author->avatar_url,
                    'type'            => $type,
                    'testimony_id'    => null,
                    'testimony_title' => null,
                    'comment_id'      => null,
                    'message'         => $message ?? '',
                    'payload'         => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                    'created_at'      => $now,
                ], $columns))->all();

                AppNotification::insert($rows);
                $count += count($rows);

                foreach ($rows as $row) {
                    PushNotifications::dispatchFor((new AppNotification())->setRawAttributes($row));
                }
            });

        return $count;
    }
}
