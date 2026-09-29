<?php

namespace App\Observers;

use App\Models\AppNotification;
use App\Services\PushNotifications;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Chaque notification créée dans l'application part aussi en push FCM
 * (docs/fonctionnalites/notifications-push.md), après validation de la transaction :
 * pas de push pour une notification annulée.
 *
 * Attention : AppNotification::insert() ne déclenche pas d'événement Eloquent ; appeler alors
 * PushNotifications::dispatchFor() pour chaque ligne (voir Api\ModerationController::approve).
 */
class AppNotificationObserver implements ShouldHandleEventsAfterCommit
{
    public function created(AppNotification $notification): void
    {
        PushNotifications::dispatchFor($notification);
    }
}
