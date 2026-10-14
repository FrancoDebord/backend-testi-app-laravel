<?php

namespace App\Http\Controllers\Api;

use App\Enums\RejectionReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ModerationActionRequest;
use App\Http\Resources\ModerationItemResource;
use App\Models\ModerationLog;
use App\Models\Testimony;
use App\Services\ContentActionException;
use App\Services\TestimonyModeration;
use Illuminate\Support\Facades\Gate;
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
        Gate::authorize('moderate', $testimony);

        // Statut, journal, audit, notification de l'auteur, abonnés et mentions à la première publication.
        app(TestimonyModeration::class)->approve($testimony, $request->user());

        return $this->success(null, 'Témoignage approuvé');
    }

    /**
     * Refus : motif obligatoire, code (reason) et/ou texte libre (rejection_reason). Voir
     * docs/fonctionnalites/gestion-des-temoignages.md
     */
    public function reject(ModerationActionRequest $request, string $id): JsonResponse
    {
        $testimony = Testimony::withoutJournal()->find($id);
        if (!$testimony) return $this->notFound();
        Gate::authorize('moderate', $testimony);

        try {
            app(TestimonyModeration::class)->reject($testimony, $request->user(), $request->input('rejection_reason'),
                RejectionReason::tryFrom((string) $request->reason), $request->moderator_note);
        } catch (ContentActionException $e) {
            return $this->error($e->getMessage(), $e->status(), $e->errors());
        }

        return $this->success(null, 'Témoignage rejeté');
    }
}
