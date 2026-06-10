<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = AppNotification::forUser($request->user()->id)
            ->orderByDesc('created_at');

        if ($after = $request->query('after')) {
            $query->after($after);
        }

        $notifications = $query->paginate(30);

        return $this->paginated(
            NotificationResource::collection($notifications->items()),
            [
                'total'       => $notifications->total(),
                'unreadCount' => AppNotification::forUser($request->user()->id)->unread()->count(),
            ]
        );
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = AppNotification::where('id', $id)
                                       ->where('recipient_id', $request->user()->id)
                                       ->first();

        if (!$notification) return $this->notFound();

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return $this->success(null, 'Notification marquée comme lue');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('recipient_id', $request->user()->id)
                       ->where('is_read', false)
                       ->update(['is_read' => true, 'read_at' => now()]);

        return $this->success(null, 'Toutes les notifications marquées comme lues');
    }
}
