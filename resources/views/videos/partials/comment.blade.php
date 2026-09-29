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
            @if($isAuthor)<span class="badge-neutral">Auteur</span>@endif
            <time class="text-xs text-slate-500" datetime="{{ $comment->created_at->toIso8601String() }}" title="{{ $comment->created_at->translatedFormat('d F Y à H:i') }}">{{ $comment->created_at->diffForHumans() }}</time>
        </p>

        <p class="mt-0.5 text-sm break-words whitespace-pre-line text-slate-700" data-comment-body>{{ $comment->body }}</p>

        @if($canUpdate)
        <form method="POST" action="{{ route('comments.update', $comment->id) }}" class="mt-2" data-comment-edit data-no-loading hidden>
            @csrf
            @method('PUT')
            <label for="comment-edit-{{ $comment->id }}" class="sr-only">Modifier le commentaire</label>
            <textarea id="comment-edit-{{ $comment->id }}" name="body" rows="2" required maxlength="{{ \App\Http\Requests\CommentRequest::MAX_LENGTH }}" class="form-input">{{ $comment->body }}</textarea>
            <div class="mt-2 flex justify-end gap-2">
                <button type="button" class="btn-ghost btn-sm" data-edit-cancel>Annuler</button>
                <button type="submit" class="btn-primary btn-sm">Enregistrer</button>
            </div>
        </form>
        @endif

        <div class="mt-1 flex flex-wrap items-center gap-1" data-comment-actions>
            @auth
            <button type="button" class="btn-ghost btn-sm -ml-3" data-reply-toggle="{{ $rootId }}" @if($isReply) data-mention="{{ $comment->user->display_name }}" @endif>
                <i class="fa-regular fa-comment" aria-hidden="true"></i>Répondre
            </button>
            @else
            <a href="{{ route('login') }}" class="btn-ghost btn-sm -ml-3">Répondre</a>
            @endauth
            @if($canUpdate)
            <button type="button" class="btn-ghost btn-sm" data-edit-toggle><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Modifier</button>
            @endif
            @if($canDelete)
            <form id="{{ $deleteForm }}" method="POST" action="{{ route('comments.destroy', $comment->id) }}" data-comment-delete data-no-loading>
                @csrf
                @method('DELETE')
                <button type="button" class="btn-ghost btn-sm"
                        onclick="openConfirmModal('{{ $deleteForm }}', {{ $isReply ? "'Cette réponse sera définitivement supprimée.'" : "'Ce commentaire et ses réponses seront définitivement supprimés.'" }}, 'Supprimer le commentaire', 'Supprimer', 'fa-trash-can')">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>Supprimer
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
                    <label for="reply-{{ $comment->id }}" class="sr-only">Répondre à {{ $comment->user->display_name }}</label>
                    <textarea id="reply-{{ $comment->id }}" name="body" rows="2" required maxlength="{{ \App\Http\Requests\CommentRequest::MAX_LENGTH }}"
                              class="form-input" placeholder="Ajouter une réponse…"></textarea>
                    <div class="mt-2 flex justify-end gap-2">
                        <button type="button" class="btn-ghost btn-sm" data-reply-cancel>Annuler</button>
                        <button type="submit" class="btn-primary btn-sm">Répondre</button>
                    </div>
                </div>
            </form>
            @endauth

            <div class="mt-3 space-y-4 empty:hidden" data-replies-list></div>
            <button type="button" class="btn-ghost btn-sm -ml-3 mt-1 text-slate-900" data-replies-load="{{ route('comments.replies', $comment->id) }}"
                    data-replies-count="{{ $comment->replies_count }}" @if($comment->replies_count < 1) hidden @endif>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                <span data-replies-label>{{ $comment->replies_count > 1 ? 'Voir les ' . $comment->replies_count . ' réponses' : 'Voir la réponse' }}</span>
            </button>
        @endunless
    </div>
</article>
