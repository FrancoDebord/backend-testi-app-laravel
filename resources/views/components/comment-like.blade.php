{{--
    « J'aime » d'un commentaire (cœur + nombre) — docs/fonctionnalites/jaime-commentaires.md
    @include('components.comment-like', ['type' => 'testimony_comment', 'comment' => $comment, 'url' => route('comments.like', $comment->id)])
    type : testimony_comment | event_comment | verse_comment. Bouton géré par resources/js/comment-likes.js
    (POST pour aimer, DELETE pour retirer, même adresse). Visiteur : lien vers la connexion.
--}}
@php
    $likeCount = (int) ($comment->likes_count ?? 0);
    $likeViewer = auth()->user();
    $liked = $likeViewer ? app(\App\Services\CommentLikes::class)->isLikedBy($type, (string) $comment->id, $likeViewer) : false;
@endphp
@if($likeViewer)
<button type="button" class="btn-ghost btn-sm {{ $class ?? '' }} aria-pressed:text-error-500" data-comment-like="{{ $url }}"
        aria-pressed="{{ $liked ? 'true' : 'false' }}" title="{{ __('comment_likes.like_label') }}" data-like-error="{{ __('comment_likes.error') }}">
    <i class="{{ $liked ? 'fa-solid' : 'fa-regular' }} fa-heart" aria-hidden="true" data-like-icon></i>
    <span class="sr-only">{{ __('comment_likes.like') }}</span>
    <span class="tabular-nums" data-like-count>{{ $likeCount }}</span>
</button>
@else
<a href="{{ route('login') }}" class="btn-ghost btn-sm {{ $class ?? '' }}" title="{{ __('comment_likes.login') }}">
    <i class="fa-regular fa-heart" aria-hidden="true"></i>
    <span class="sr-only">{{ __('comment_likes.login') }}</span>
    <span class="tabular-nums">{{ $likeCount }}</span>
</a>
@endif
