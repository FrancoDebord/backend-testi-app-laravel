<?php

namespace App\Http\Controllers\Api;

use App\Enums\LiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LiveSessionResource;
use App\Http\Resources\TestimonyResource;
use App\Models\LiveComment;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveActionException;
use App\Services\LiveService;
use App\Services\LiveStage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Rules\VisibleLength;

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
            // Direct par lien YouTube : toujours possible, même sans LiveKit (docs/fonctionnalites/lives.md)
            'youtubeEnabled' => true,
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
            // Directs passés (docs/fonctionnalites/lives.md) : témoignages publiés sur ce direct,
            // et droit d'en publier un (personne connectée, direct terminé).
            'testimoniesCount' => $live->linkedTestimonies()->published()->count(),
            'canTestify'       => $live->canBeTestifiedBy($this->user($request)),
        ]));
    }

    /** Témoignages publiés sur ce direct, du plus récent au plus ancien (?page, ?limit ≤ 50). */
    public function testimonies(Request $request, string $id): JsonResponse
    {
        $live  = $this->find($id, $request);
        $items = $live->linkedTestimonies()->published()->with('user')->latest()
            ->paginate(max(1, min((int) $request->query('limit', 20), 50)));

        return $this->paginated(TestimonyResource::collection($items->items()), [
            'currentPage' => $items->currentPage(),
            'lastPage'    => $items->lastPage(),
            'total'       => $items->total(),
            'perPage'     => $items->perPage(),
        ]);
    }

    /** Démarre un direct (modérateurs et administrateurs) et renvoie les accès diffuseur. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'required|string|max:150',
            'description'      => 'nullable|string|max:1000',
            'category_slug'    => 'nullable|string|exists:categories,slug',
            'event_id'         => 'nullable|uuid', // direct d'un événement (docs/fonctionnalites/evenements.md)
            'comments_enabled' => 'nullable|boolean',
            'record'           => 'nullable|boolean',
            // Caméra IP / encodeur (docs/fonctionnalites/lives-camera-ip.md)
            'source'           => 'nullable|in:' . implode(',', LiveSession::SOURCES),
            'camera_url'       => ['nullable', 'required_if:source,url', 'string', 'max:500', 'regex:#^(rtsps?|rtmps?|https?|srt)://[^\s]+$#i'],
            // Direct YouTube : lien watch, youtu.be, /live/, /shorts/, /embed/ (ou l'identifiant seul)
            'youtube_url'      => self::youtubeUrlRules(),
        ], [
            'title.required' => __('lives.messages.title_required'),
            'camera_url.required_if' => __('lives.messages.camera_url_required'),
            'camera_url.regex'       => __('lives.messages.camera_url_regex'),
            ...self::youtubeUrlMessages(),
        ]);

        return $this->attempt(function () use ($request, $data) {
            $user = $this->user($request);
            $live = $this->lives->start($user, $data);

            // Direct YouTube : déjà à l'antenne, aucun accès LiveKit (video: null).
            return $this->created([
                'live'  => new LiveSessionResource($live->load('host')),
                'video' => $live->isYoutube() ? null : $this->lives->hostCredentials($live, $user),
            ], $live->isYoutube() ? __('lives.messages.on_air') : __('lives.messages.created'));
        });
    }

    /** Règles du lien YouTube d'un direct (API et formulaire web). */
    public static function youtubeUrlRules(): array
    {
        return ['nullable', 'required_if:source,youtube', 'string', 'max:300', function (string $attribute, mixed $value, \Closure $fail) {
            if (is_string($value) && trim($value) !== '' && \App\Support\YouTube::parseId($value) === null) {
                $fail(__('lives.messages.youtube_url_invalid'));
            }
        }];
    }

    /** @return array<string, string> */
    public static function youtubeUrlMessages(): array
    {
        return [
            'youtube_url.required_if' => __('lives.messages.youtube_url_required'),
            'youtube_url.string'      => __('lives.messages.youtube_url_invalid'),
            'youtube_url.max'         => __('lives.messages.youtube_url_invalid'),
        ];
    }

    /**
     * Présence d'un spectateur (public, toutes les 30 s) : compte les spectateurs d'un direct YouTube
     * (sans LiveKit). viewer_id : identifiant généré par l'appareil (invités). Renvoie les mêmes données que stats.
     */
    public function heartbeat(Request $request, string $id): JsonResponse
    {
        $request->validate(['viewer_id' => 'nullable|string|max:64']);
        $live = $this->find($id, $request);

        return $this->success($this->lives->heartbeat($live, $this->user($request), $request->input('viewer_id'), $request->ip()));
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
            __('lives.messages.on_air')
        ));
    }

    public function end(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);
        $user = $this->user($request);

        return $this->attempt(fn () => $this->success(
            new LiveSessionResource($this->lives->end($live, $user, $live->isHost($user) ? 'host' : 'moderator')->load('host')),
            __('lives.messages.live_ended')
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

    /**
     * Direct en cours : 100 derniers commentaires visibles, du plus ancien au plus récent (tableau).
     * Direct terminé (ou ?page / ?limit envoyés) : revoir le chat, TOUS les commentaires visibles du plus ancien
     * au plus récent, paginés (limit ≤ 100, 50 par défaut) avec meta { currentPage, lastPage, total, perPage }.
     * Chaque commentaire porte offsetSeconds : secondes écoulées depuis le début du direct (null si inconnu).
     */
    public function comments(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);
        $payload = fn (LiveComment $c) => [...$c->toPayload(), 'offsetSeconds' => $live->offsetSecondsAt($c->created_at)];

        if ($live->status !== LiveStatus::Ended && !$request->has('page') && !$request->has('limit')) {
            $comments = $live->comments()->with('user')->where('is_hidden', false)
                ->latest()->limit(100)->get()->reverse()->values();

            return $this->success($comments->map($payload));
        }

        $page = $live->comments()->with('user')->where('is_hidden', false)
            ->oldest()->orderBy('id')
            ->paginate(max(1, min((int) $request->query('limit', 50), 100)));

        return $this->paginated(collect($page->items())->map($payload)->values(), [
            'currentPage' => $page->currentPage(),
            'lastPage'    => $page->lastPage(),
            'total'       => $page->total(),
            'perPage'     => $page->perPage(),
        ]);
    }

    public function storeComment(Request $request, string $id): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', new VisibleLength((int) config('livekit.comment_max_length'), __('lives.messages.comment_too_long'))]], [
            'body.required' => __('lives.messages.comment_empty'),
        ]);
        $live = $this->find($id, $request);

        return $this->attempt(fn () => $this->created(
            $this->lives->postComment($live, $this->user($request), $request->input('body'))->toPayload(),
            __('lives.messages.comment_posted')
        ));
    }

    public function hideComment(Request $request, string $id, string $commentId): JsonResponse
    {
        $live = $this->find($id, $request);
        $comment = LiveComment::where('live_session_id', $live->id)->findOrFail($commentId);

        return $this->attempt(function () use ($comment, $request) {
            $this->lives->hideComment($comment, $this->user($request));
            return $this->success(null, __('lives.messages.comment_hidden'));
        });
    }

    public function pinComment(Request $request, string $id, string $commentId): JsonResponse
    {
        $live = $this->find($id, $request);
        $comment = LiveComment::where('live_session_id', $live->id)->findOrFail($commentId);

        return $this->attempt(fn () => $this->success(
            $this->lives->pinComment($comment, $this->user($request))->toPayload(),
            __('lives.messages.comment_pinned')
        ));
    }

    public function unpinComment(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $this->lives->unpinComment($live, $this->user($request));
            return $this->success(null, __('lives.messages.comment_unpinned'));
        });
    }

    public function ban(Request $request, string $id): JsonResponse
    {
        $request->validate(['user_id' => 'required|string|exists:users,id']);
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $this->lives->ban($live, User::findOrFail($request->input('user_id')), $this->user($request));
            return $this->success(null, __('lives.messages.banned'));
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
            'message.max' => __('lives.messages.subject_too_long'),
        ]);
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $this->lives->ensureLiveKitLive($live); // direct YouTube : 409
            $this->stage->request($live, $this->user($request), $request->input('message'));
            return $this->created($this->stage->state($live, $this->user($request)), __('lives.messages.stage_requested'));
        });
    }

    /** Retirer sa demande, refuser l'invitation, ou quitter l'antenne. */
    public function withdrawStage(Request $request, string $id): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $this->lives->ensureLiveKitLive($live); // direct YouTube : 409
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
            $this->lives->ensureLiveKitLive($live); // direct YouTube : 409
            $this->stage->accept($live, $this->user($request), $request->input('identity'), $request->boolean('camera'));
            return $this->success($this->stage->state($live, $this->user($request)), __('lives.messages.stage_accepted'));
        });
    }

    public function inviteSpeaker(Request $request, string $id, string $speakerId): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request, $speakerId) {
            $this->lives->ensureLiveKitLive($live); // direct YouTube : 409
            $this->stage->invite($live, $speakerId, $this->user($request));
            return $this->success($this->stage->state($live, $this->user($request)), __('lives.messages.stage_invited'));
        });
    }

    public function declineSpeaker(Request $request, string $id, string $speakerId): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request, $speakerId) {
            $this->lives->ensureLiveKitLive($live); // direct YouTube : 409
            $this->stage->decline($live, $speakerId, $this->user($request));
            return $this->success($this->stage->state($live, $this->user($request)), __('lives.messages.stage_declined'));
        });
    }

    public function removeSpeaker(Request $request, string $id, string $speakerId): JsonResponse
    {
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request, $speakerId) {
            $this->lives->ensureLiveKitLive($live); // direct YouTube : 409
            $this->stage->remove($live, $speakerId, $this->user($request));
            return $this->success($this->stage->state($live, $this->user($request)), __('lives.messages.stage_removed'));
        });
    }

    public function stageSettings(Request $request, string $id): JsonResponse
    {
        $request->validate(['enabled' => 'required|boolean']);
        $live = $this->find($id, $request);

        return $this->attempt(function () use ($live, $request) {
            $enabled = $request->boolean('enabled');
            $this->lives->ensureLiveKitLive($live); // direct YouTube : 409
            $this->stage->setEnabled($live, $this->user($request), $enabled);
            return $this->success($this->stage->state($live, $this->user($request)),
                $enabled ? __('lives.messages.stage_opened') : __('lives.messages.stage_closed'));
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
        abort_if(!$live || !$live->isVisibleTo($this->user($request)), 404, __('lives.messages.not_found'));

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
