<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'all');

        $query = AppNotification::where('recipient_id', Auth::id())->orderByDesc('created_at');

        if ($filter === 'unread') {
            $query->where('is_read', false);
        } elseif ($filter === 'comments') {
            $query->whereIn('type', ['comment', 'reply', 'mention']);
        } elseif ($filter === 'reactions') {
            $query->whereIn('type', ['like', 'follow', 'share']);
        } elseif ($filter === 'system') {
            $query->whereIn('type', ['testimony_approved', 'testimony_rejected', 'pending_correction']);
        }

        $notifications = $query->paginate(20);

        return view('notifications.index', compact('notifications', 'filter'));
    }

    public function markRead(string $id): RedirectResponse
    {
        AppNotification::where('id', $id)->where('recipient_id', Auth::id())
                       ->update(['is_read' => true, 'read_at' => now()]);

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        AppNotification::where('recipient_id', Auth::id())
                       ->where('is_read', false)
                       ->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'Toutes les notifications marquées comme lues.');
    }
}
