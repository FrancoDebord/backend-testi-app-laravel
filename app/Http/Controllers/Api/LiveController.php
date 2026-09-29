<?php

namespace App\Http\Controllers\Api;

use App\Enums\LiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LiveSessionResource;
use App\Models\LiveComment;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveActionException;
use App\Services\LiveService;
use App\Services\LiveStage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Actions des témoignages en direct, au format JSON { success, data, message }.
 * Utilisé par l'API mobile (routes/api.php, Sanctum) ET par les pages web (routes/web.php, session).
 * Voir docs/fonctionnalites/lives.md
 */
class LiveController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LiveService $lives,
        private readonly LiveStage $stage,
    ) {}

    /** Directs en cours (et en préparation pour les modérateurs), puis les 10 derniers terminés. */
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $active = LiveSession::with('host')->active()->latest()->get()->filter(fn ($l) => $l->isVisibleTo($user))->values();
        $recent = LiveSession::with(['host', 'testimony'])->where('status', LiveStatus::Ended)->whereNotNull('started_at')
            ->latest('ended_at')->limit(10)->get();

        return $this->success([
            'configured' => $this->lives->isConfigured(),
            'recordingConfigured' => $this->lives->isRecordingConfigured(),
            'canGoLive'  => (bool) $user?->canModerate(),
            'active'     => LiveSessionResource::collection($active),
            'recent'     => LiveSessionResource::collection($recent),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->success(array_merge((new LiveSessionResource($live))->toArray($request), [
            'liveStats' => $this->lives->stats($live),
        ]));
    }

    /** Démarre un direct (modérateurs et administrateurs) et renvoie les accès diffuseur. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'required|string|max:150',
            'description'      => 'nullable|string|max:1000',
            'category_slug'    => 'nullable|string|exists:categories,slug',
            'comments_enabled' => 'nullable|boolean',
            'record'           => 'nullable|boolean',
        ], [
            'title.required' => 'Merci de donner un titre au direct.',
        ]);

        return $this->attempt(function () use ($request, $data) {
            $user = $this->user($request);
            $live = $this->lives->start($user, $data);

            return $this->created([
                'live'  => new LiveSessionResource($live->load('host')),
                'video' => $this->lives->hostCredentials($live, $user),
            ], 'Direct créé : il sera visible dès que la caméra est en ligne.');
        });
    }

    public function hostToken(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(fn () => $this->success($this->lives->hostCredentials($live, $this->user($request))));
    }

    public function viewerToken(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(fn () => $this->success($this->lives->viewerCredentials($live, $this->user($request))));
    }

    public function goLive(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(fn () => $this->success(
            new LiveSessionResource($this->lives->goLive($live, $this->user($request))->load('host')),
            'Vous êtes en direct.'
        ));
    }

    public function end(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);
        $user = $this->user($request);

        return $this->attempt(fn () => $this->success(
            new LiveSessionResource($this->lives->end($live, $user, $live->isHost($user) ? 'host' : 'moderator')->load('host')),
            'Le direct est terminé.'
        ));
    }

    /** Qui regarde : comptes connectés et nombre de visiteurs (public). */
    public function viewers(Request $request, string $id): JsonResponse
    {
        return $this->success($this->lives->viewers($this->find($id, $request)));
    }

    public function stats(Request $request, string $id): JsonResponse
    {
        return $this->success($this->lives->stats($this->find($id, $request)));
    }

    // ─── Commentaires ────────────────────────────────────────────────────────

    /** 100 derniers commentaires visibles, du plus ancien au plus récent. */
    public function comments(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);
        $comments = $live->comments()->with('user')->where('is_hidden', false)
            ->latest()->limit(100)->get()->reverse()->values();

        return $this->success($comments->map->toPayload());
    }

    public function storeComment(Request $request, string $id): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:' . config('livekit.comment_max_length')], [
            'body.required' => 'Le commentaire est vide.',
            'body.max'      => 'Le commentaire est trop long.',
        ]);
        $live = $this->find($id, $request);

        return $this->attempt(fn () => $this->created(
            $this->lives->postComment($live, $this->user($request), $request->input('body'))->toPayload(),
            'Commentaire publié.'
        ));
    }

    public function hideComment(Request $request, string $id, string $commentId): JsonResponse
    {
        $live = $this->find($id, $request);
        $comment = LiveComment::where('live_session_id', $live->id)->findOrFail($commentId);

        return $this->attempt(function () use ($comment, $request) {
            $this->lives->hideComment($comment, $this->user($request));
            return $this->success(null, 'Commentaire masqué.');
        });
    }

    public function pinComment(Request $request, string $id, string $commentId): JsonResponse
    {
        $live = $this->find($id, $request);
        $comment = LiveComment::where('live_session_id', $live->id)->findOrFail($commentId);

        return $this->attempt(fn () => $this->success(
            $this->lives->pinComment($comment, $this->user($request))->toPayload(),
            'Commentaire épinglé.'
        ));
    }

    public function unpinComment(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $this->lives->unpinComment($live, $this->user($request));
            return $this->success(null, 'Commentaire désépinglé.');
        });
    }

    public function ban(Request $request, string $id): JsonResponse
    {
        $request->validate(['user_id' => 'required|string|exists:users,id']);
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $this->lives->ban($live, User::findOrFail($request->input('user_id')), $this->user($request));
            return $this->success(null, 'Cette personne ne peut plus commenter ni réagir pendant ce direct.');
        });
    }

    public function react(Request $request, string $id): JsonResponse
    {
        $request->validate(['type' => 'required|string']);
        $live = $this->find($id, $request);

        return $this->attempt(fn () => $this->success(['reactions' => $this->lives->react($live, $this->user($request), $request->input('type'))]));
    }

    // ─── Intervenants (docs/fonctionnalites/lives-intervenants.md) ──────────

    /** État de la scène : intervenant actuel, file (diffuseur et modérateurs), demande de la personne connectée. */
    public function stage(Request $request, string $id): JsonResponse
    {
        return $this->success($this->stage->state($this->find($id, $request), $this->user($request)));
    }

    public function requestStage(Request $request, string $id): JsonResponse
    {
        $request->validate(['message' => 'nullable|string|max:' . config('livekit.stage_message_length')], [
            'message.max' => 'Le sujet est trop long.',
        ]);
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $this->stage->request($live, $this->user($request), $request->input('message'));
            return $this->created($this->stage->state($live, $this->user($request)), 'Demande envoyée : le diffuseur vous invitera à votre tour.');
        });
    }

    /** Retirer sa demande, refuser l'invitation, ou quitter l'antenne. */
    public function withdrawStage(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $this->stage->withdraw($live, $this->user($request));
            return $this->success($this->stage->state($live, $this->user($request)));
        });
    }

    /** Accepter l'invitation. identity : participant LiveKit de la connexion spectateur (viewer-token). */
    public function acceptStage(Request $request, string $id): JsonResponse
    {
        $request->validate(['identity' => 'required|string|max:120', 'camera' => 'nullable|boolean']);
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $this->stage->accept($live, $this->user($request), $request->input('identity'), $request->boolean('camera'));
            return $this->success($this->stage->state($live, $this->user($request)), "Vous êtes à l'antenne.");
        });
    }

    public function inviteSpeaker(Request $request, string $id, string $speakerId): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request, $speakerId) {
            $this->stage->invite($live, $speakerId, $this->user($request));
            return $this->success($this->stage->state($live, $this->user($request)), 'Invitation envoyée.');
        });
    }

    public function declineSpeaker(Request $request, string $id, string $speakerId): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request, $speakerId) {
            $this->stage->decline($live, $speakerId, $this->user($request));
            return $this->success($this->stage->state($live, $this->user($request)), 'Demande retirée de la file.');
        });
    }

    public function removeSpeaker(Request $request, string $id, string $speakerId): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request, $speakerId) {
            $this->stage->remove($live, $speakerId, $this->user($request));
            return $this->success($this->stage->state($live, $this->user($request)), 'Intervention terminée.');
        });
    }

    public function stageSettings(Request $request, string $id): JsonResponse
    {
        $request->validate(['enabled' => 'required|boolean']);
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $enabled = $request->boolean('enabled');
            $this->stage->setEnabled($live, $this->user($request), $enabled);
            return $this->success($this->stage->state($live, $this->user($request)),
                $enabled ? "Demandes d'intervention ouvertes." : "Demandes d'intervention fermées.");
        });
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    private function user(Request $request): ?User
    {
        return $request->user() ?? $request->user('sanctum');
    }

    private function find(string $id, Request $request): LiveSession
    {
        $live = LiveSession::with('host')->find($id);
        abort_if(!$live || !$live->isVisibleTo($this->user($request)), 404, 'Direct introuvable.');

        return $live;
    }

    private function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (LiveActionException $e) {
            return $this->error($e->getMessage(), $e->status(), $e->live ? ['liveId' => $e->live->id] : null);
        }
    }
}
