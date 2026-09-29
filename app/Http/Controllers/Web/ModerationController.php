<?php

namespace App\Http\Controllers\Web;

use App\Enums\RejectionReason;
use App\Enums\TestimonyStatus;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\ModerationLog;
use App\Models\Testimony;
use App\Services\FollowerNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ModerationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $today  = now()->toDateString();

        $query = Testimony::with('user')
            ->withoutJournal() // carnet privé exclu
            ->whereIn('status', $status === 'all' ? ['pending', 'approved', 'rejected'] : [$status])
            ->latest();

        $items = $query->paginate(20);

        $stats = [
            'pending'        => Testimony::pending()->count(),
            'approvedToday'  => ModerationLog::where('action', 'approved')->whereDate('created_at', $today)->count(),
            'rejectedToday'  => ModerationLog::where('action', 'rejected')->whereDate('created_at', $today)->count(),
            'totalThisMonth' => ModerationLog::whereMonth('created_at', now()->month)->count(),
        ];

        $reasons = RejectionReason::cases();

        return view('moderation.index', compact('items', 'stats', 'status', 'reasons'));
    }

    public function show(string $id): View
    {
        $testimony = Testimony::with(['user', 'moderationLogs.moderator'])->withoutJournal()->findOrFail($id);

        if ($testimony->status->value === 'pending') {
            ModerationLog::create([
                'testimony_id' => $id,
                'moderator_id' => Auth::id(),
                'action'       => 'in_review',
                'created_at'   => now(),
            ]);
        }

        $reasons = RejectionReason::cases();
        return view('moderation.show', compact('testimony', 'reasons'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        // Même traitement que Api\ModerationController::approve (carnet privé exclu).
        $testimony = Testimony::with('user')->withoutJournal()->findOrFail($id);

        $testimony->update([
            'status'      => TestimonyStatus::Approved->value,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        ModerationLog::create([
            'testimony_id' => $id,
            'moderator_id' => Auth::id(),
            'action'       => 'approved',
            'created_at'   => now(),
        ]);

        AppNotification::create([
            'recipient_id'    => $testimony->user_id,
            'actor_id'        => Auth::id(),
            'actor_name'      => Auth::user()->display_name,
            'type'            => 'testimony_approved',
            'testimony_id'    => $id,
            'testimony_title' => $testimony->title,
            'message'         => 'Votre témoignage "' . $testimony->title . '" a été approuvé',
            'created_at'      => now(),
        ]);

        // Abonnés de l'auteur (insertion groupée + push : App\Services\FollowerNotifications)
        FollowerNotifications::newTestimony($testimony->fresh('user'));

        $testimony->user->increment('testimony_count');

        return redirect()->route('moderation.index')->with('success', 'Témoignage approuvé.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'reason'         => 'required|string',
            'moderator_note' => 'nullable|string|max:1000',
        ]);

        $testimony = Testimony::withoutJournal()->findOrFail($id);
        $testimony->update(['status' => TestimonyStatus::Rejected->value]);

        ModerationLog::create([
            'testimony_id'     => $id,
            'moderator_id'     => Auth::id(),
            'action'           => 'rejected',
            'rejection_reason' => $request->reason,
            'moderator_note'   => $request->moderator_note,
            'created_at'       => now(),
        ]);

        AppNotification::create([
            'recipient_id'    => $testimony->user_id,
            'actor_id'        => Auth::id(),
            'actor_name'      => Auth::user()->display_name,
            'type'            => 'testimony_rejected',
            'testimony_id'    => $id,
            'testimony_title' => $testimony->title,
            'message'         => 'Votre témoignage "' . $testimony->title . '" a été rejeté : '
                                 . (RejectionReason::tryFrom((string) $request->reason)?->label() ?? 'Non conforme'),
            'created_at'      => now(),
        ]);

        return redirect()->route('moderation.index')->with('success', 'Témoignage rejeté.');
    }
}
