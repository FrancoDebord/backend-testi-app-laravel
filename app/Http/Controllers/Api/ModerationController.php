<?php

namespace App\Http\Controllers\Api;

use App\Enums\RejectionReason;
use App\Enums\TestimonyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ModerationActionRequest;
use App\Http\Resources\ModerationItemResource;
use App\Models\AppNotification;
use App\Models\ModerationLog;
use App\Models\Testimony;
use App\Services\FollowerNotifications;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    use ApiResponse;

    public function stats(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        return $this->success([
            'pending'         => Testimony::pending()->count(),
            'approvedToday'   => ModerationLog::where('action', 'approved')
                                              ->whereDate('created_at', $today)
                                              ->count(),
            'rejectedToday'   => ModerationLog::where('action', 'rejected')
                                              ->whereDate('created_at', $today)
                                              ->count(),
            'totalThisMonth'  => ModerationLog::whereMonth('created_at', now()->month)->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');

        $query = Testimony::with(['user', 'moderationLogs'])
            ->withoutJournal() // carnet privé exclu
            ->whereIn('status', $status === 'all' ? ['pending', 'approved', 'rejected'] : [$status])
            ->latest();

        $items = $query->paginate(20);

        return $this->paginated(
            ModerationItemResource::collection($items->items()),
            ['total' => $items->total(), 'pending' => Testimony::pending()->count()]
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::with(['user', 'moderationLogs.moderator'])->withoutJournal()->find($id);
        if (!$testimony) return $this->notFound();

        // Log that a moderator opened this testimony for review
        if ($testimony->status->value === 'pending') {
            ModerationLog::create([
                'testimony_id'  => $id,
                'moderator_id'  => $request->user()->id,
                'action'        => 'in_review',
                'created_at'    => now(),
            ]);
        }

        return $this->success(new ModerationItemResource($testimony));
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::with('user')->withoutJournal()->find($id);
        if (!$testimony) return $this->notFound();

        $testimony->update([
            'status'      => TestimonyStatus::Approved->value,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        ModerationLog::create([
            'testimony_id' => $id,
            'moderator_id' => $request->user()->id,
            'action'       => 'approved',
            'created_at'   => now(),
        ]);

        $now = now();

        // Notify author
        AppNotification::create([
            'recipient_id'    => $testimony->user_id,
            'actor_id'        => $request->user()->id,
            'actor_name'      => $request->user()->display_name,
            'type'            => 'testimony_approved',
            'testimony_id'    => $id,
            'testimony_title' => $testimony->title,
            'message'         => 'Votre témoignage "' . $testimony->title . '" a été approuvé',
            'created_at'      => $now,
        ]);

        // Notify followers (insertion groupée + push : App\Services\FollowerNotifications)
        FollowerNotifications::newTestimony($testimony->fresh('user'));

        // Update user testimony count
        $testimony->user->increment('testimony_count');

        return $this->success(null, 'Témoignage approuvé');
    }

    public function reject(ModerationActionRequest $request, string $id): JsonResponse
    {
        $testimony = Testimony::withoutJournal()->find($id);
        if (!$testimony) return $this->notFound();

        $testimony->update(['status' => TestimonyStatus::Rejected->value]);

        ModerationLog::create([
            'testimony_id'     => $id,
            'moderator_id'     => $request->user()->id,
            'action'           => 'rejected',
            'rejection_reason' => $request->reason,
            'moderator_note'   => $request->moderator_note,
            'created_at'       => now(),
        ]);

        // Notify author
        $reasonLabel = RejectionReason::tryFrom((string) $request->reason)?->label()
            ?? ($request->reason ? ucfirst($request->reason) : 'Non conforme');
        AppNotification::create([
            'recipient_id'    => $testimony->user_id,
            'actor_id'        => $request->user()->id,
            'actor_name'      => $request->user()->display_name,
            'type'            => 'testimony_rejected',
            'testimony_id'    => $id,
            'testimony_title' => $testimony->title,
            'message'         => 'Votre témoignage "' . $testimony->title . '" a été rejeté : ' . $reasonLabel,
            'created_at'      => now(),
        ]);

        return $this->success(null, 'Témoignage rejeté');
    }
}
