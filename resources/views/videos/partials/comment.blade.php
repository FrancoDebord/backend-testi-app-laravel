{{--
    Commentaire (ou réponse) : @include('videos.partials.comment', ['comment' => $c, 'testimony' => $t])
    Rendu côté serveur, y compris après une publication dynamique : le texte est toujours échappé.
    Sans JavaScript, les formulaires fonctionnent par envoi classique.
--}}
@php
    $isReply    = $comment->parent_id !== null;
    $rootId     = $comment->parent_id ?? $comment->id;
    $isAuthor   = $comment->user_id === $testimony->user_id;
    $viewer     = Auth::user();
    $canUpdate  = $viewer?->can('update', $comment) ?? false;
    $canDelete  = $viewer?->can('delete', $comment) ?? false;
    $deleteForm = 'comment-delete-' . $comment->id;
@endphp
<article id="comment-{{ $comment->id }}" class="flex gap-3" data-comment="{{ $comment->id }}" @unless($isReply) data-comment-root @endunless>
    <a href="{{ route('profiles.show', $comment->user_id) }}" class="shrink-0 self-start rounded-full" tabindex="-1" aria-hidden="true">
        @include('components.avatar', ['user' => $comment->user, 'size' => $isReply ? 'sm' : 'md'])
    </a>
    <div class="min-w-0 flex-1">
        <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm">
            <a href="{{ route('profiles.show', $comment->user_id) }}" class="font-semibold text-slate-900 hover:underline">{{ $comment->user->display_name }}</a>
            @if($isAuthor)<span class="badge-neutral">{{ __('comments.item.author') }}</span>@endif
            <time class="text-xs text-slate-500" datetime="{{ $comment->created_at->toIso8601String() }}" title="{{ $comment->created_at->translatedFormat(__('comments.item.date_format')) }}">{{ $comment->created_at->diffForHumans() }}</time>
        </p>

        <p class="mt-0.5 text-sm break-words whitespace-pre-line text-slate-700" data-comment-body>{{ \App\Services\Mentions::html($comment->body) }}</p>

        @if($canUpdate)
        <form method="POST" action="{{ route('comments.update', $comment->id) }}" class="mt-2" data-comment-edit data-no-loading hidden>
            @csrf
            @method('PUT')
            <label for="comment-edit-{{ $comment->id }}" class="sr-only">{{ __('comments.item.edit_label') }}</label>
            <textarea id="comment-edit-{{ $comment->id }}" name="body" rows="2" required maxlength="{{ \App\Http\Requests\CommentRequest::MAX_LENGTH }}" class="form-input" data-mention-input>{{ \App\Services\Mentions::plain($comment->body) }}</textarea>
            <div class="mt-2 flex justify-end gap-2">
                <button type="button" class="btn-ghost btn-sm" data-edit-cancel>{{ __('comments.item.cancel') }}</button>
                <button type="submit" class="btn-primary btn-sm">{{ __('comments.item.save') }}</button>
            </div>
        </form>
        @endif

        <div class="mt-1 flex flex-wrap items-center gap-1" data-comment-actions>
            @include('components.comment-like', ['type' => 'testimony_comment', 'comment' => $comment, 'url' => route('comments.like', $comment->id), 'class' => '-ml-3'])
            @auth
            <button type="button" class="btn-ghost btn-sm" data-reply-toggle="{{ $rootId }}" @if($isReply) data-mention="{{ $comment->user->display_name }}" data-mention-id="{{ $comment->user_id }}" @endif>
                <i class="fa-regular fa-comment" aria-hidden="true"></i>{{ __('comments.item.reply') }}
            </button>
            @else
            <a href="{{ route('login') }}" class="btn-ghost btn-sm">{{ __('comments.item.reply') }}</a>
            @endauth
            @if($canUpdate)
            <button type="button" class="btn-ghost btn-sm" data-edit-toggle><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>{{ __('comments.item.edit') }}</button>
            @endif
            @if($canDelete)
            <form id="{{ $deleteForm }}" method="POST" action="{{ route('comments.destroy', $comment->id) }}" data-comment-delete data-no-loading>
                @csrf
                @method('DELETE')
                <button type="button" class="btn-ghost btn-sm"
                        onclick="openConfirmModal('{{ $deleteForm }}', @js($isReply ? __('comments.item.delete_reply_confirm') : __('comments.item.delete_confirm')), @js(__('comments.item.delete_title')), @js(__('comments.item.delete')), 'fa-trash-can')">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>{{ __('comments.item.delete') }}
                </button>
            </form>
            @endif
        </div>

        @unless($isReply)
            @auth
            <form method="POST" action="{{ route('videos.comments.store', $testimony->id) }}" class="mt-2 flex gap-3" data-comment-form data-reply-form="{{ $comment->id }}" data-no-loading hidden>
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                @include('components.avatar', ['user' => Auth::user(), 'size' => 'sm'])
                <div class="min-w-0 flex-1">
                    <label for="reply-{{ $comment->id }}" class="sr-only">{{ __('comments.item.reply_to', ['name' => $comment->user->display_name]) }}</label>
                    <textarea id="reply-{{ $comment->id }}" name="body" rows="2" required maxlength="{{ \App\Http\Requests\CommentRequest::MAX_LENGTH }}"
                              class="form-input" placeholder="{{ __('comments.item.reply_placeholder') }}" data-mention-input data-mention-testimony="{{ $testimony->id }}"></textarea>
                    <div class="mt-2 flex justify-end gap-2">
                        <button type="button" class="btn-ghost btn-sm" data-reply-cancel>{{ __('comments.item.cancel') }}</button>
                        <button type="submit" class="btn-primary btn-sm">{{ __('comments.item.reply') }}</button>
                    </div>
                </div>
            </form>
            @endauth

            <div class="mt-3 space-y-4 empty:hidden" data-replies-list></div>
            <button type="button" class="btn-ghost btn-sm -ml-3 mt-1 text-slate-900" data-replies-load="{{ route('comments.replies', $comment->id) }}"
                    data-replies-count="{{ $comment->replies_count }}" @if($comment->replies_count < 1) hidden @endif>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                <span data-replies-label>{{ trans_choice('comments.item.view_replies', $comment->replies_count, ['count' => \App\Support\Locales::number($comment->replies_count)]) }}</span>
            </button>
        @endunless
    </div>
</article>
