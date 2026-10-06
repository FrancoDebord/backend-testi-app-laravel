{{-- Configuration transmise à resources/js/live.js --}}
@php
    $liveUser = Auth::user();
    $liveConfig = [
        'mode'            => $mode,
        'configured'      => app(\App\Services\LiveService::class)->isConfigured(),
        'liveId'          => $live->id,
        'title'           => $live->title,
        'status'          => $live->status->value,
        'source'          => $live->source ?? 'browser',  // browser, rtmp ou url (docs/fonctionnalites/lives-camera-ip.md)
        'startedAt'       => $live->started_at?->toIso8601String(),
        'commentsEnabled' => $live->comments_enabled,
        'hostId'          => $live->host_id,
        'currentUserId'   => $liveUser?->id,
        'currentUserName' => $liveUser?->display_name,
        'canModerate'     => $live->canBeModeratedBy($liveUser),
        'isHost'          => $live->isHost($liveUser),
        'reactions'       => $live->reactionCounts(),
        'pinnedComment'   => $live->pinnedCommentPayload(),
        'urls'            => [
            'viewerToken' => route('lives.viewer-token', $live->id),
            'hostToken'   => route('lives.host-token', $live->id),
            'goLive'      => route('lives.go-live', $live->id),
            'end'         => route('lives.end', $live->id),
            'comments'    => route('lives.comments', $live->id),
            'commentHide' => route('lives.comments.hide', [$live->id, '__COMMENT__']),
            'commentPin'  => route('lives.comments.pin', [$live->id, '__COMMENT__']),
            'unpin'       => route('lives.comments.unpin', $live->id),
            'bans'        => route('lives.bans', $live->id),
            'reactions'   => route('lives.reactions', $live->id),
            'stats'       => route('lives.stats', $live->id),
            'viewers'     => route('lives.viewers', $live->id),
            'stage'         => route('lives.stage', $live->id),
            'stageRequest'  => route('lives.stage.request', $live->id),
            'stageWithdraw' => route('lives.stage.withdraw', $live->id),
            'stageAccept'   => route('lives.stage.accept', $live->id),
            'stageSettings' => route('lives.stage.settings', $live->id),
            'stageInvite'   => route('lives.stage.invite', [$live->id, '__SPEAKER__']),
            'stageDecline'  => route('lives.stage.decline', [$live->id, '__SPEAKER__']),
            'stageRemove'   => route('lives.stage.remove', [$live->id, '__SPEAKER__']),
            'show'        => route('lives.show', $live->id),
            'login'       => route('login'),
        ],
    ];
@endphp
<script type="application/json" id="live-config">@json($liveConfig)</script>
