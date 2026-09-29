{{-- Panneau des commentaires d'un direct (studio et page spectateur). --}}
<section class="card flex min-h-0 min-w-0 flex-col" data-live-comments>
    <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
        <h2 class="card-title">Commentaires</h2>
        <span class="text-xs text-slate-500"><span data-live-comment-count>{{ $live->comment_count }}</span> au total</span>
    </div>

    {{-- Commentaire épinglé (rempli et mis à jour par live.js) --}}
    <div class="mx-3 mt-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2" data-live-pinned hidden>
        <i class="fa-solid fa-thumbtack mt-0.5 text-amber-600" aria-hidden="true"></i>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-amber-800" data-live-pinned-meta>Épinglé</p>
            <p class="text-sm break-words whitespace-pre-line text-slate-800" data-live-pinned-body></p>
        </div>
        @if($live->canBeModeratedBy(Auth::user()))
            {{-- Masqué par live.js si le message épinglé est celui du diffuseur et que l'on n'est pas le diffuseur. --}}
            <button type="button" class="action-btn-view h-7 min-w-7" data-live-unpin title="Désépingler" aria-label="Désépingler">
                <i class="fa-solid fa-xmark"></i>
            </button>
        @endif
    </div>

    <ol class="max-h-[32rem] min-h-64 flex-1 space-y-1.5 overflow-y-auto px-2 py-3 lg:max-h-[40rem]" data-live-comment-list aria-live="polite">
        <li class="py-10 text-center text-sm text-slate-500" data-live-comment-empty>
            <i class="fa-regular fa-comments mb-2 block text-2xl text-slate-300" aria-hidden="true"></i>
            Aucun commentaire pour le moment.
        </li>
    </ol>

    <div class="border-t border-slate-100 p-3">
        @php $liveCanModerate = $live->canBeModeratedBy(Auth::user()); @endphp
        @if(!$live->comments_enabled && !$liveCanModerate)
            <p class="text-center text-sm text-slate-500">Les commentaires sont désactivés pour ce direct.</p>
        @elseif(!Auth::check())
            <p class="text-center text-sm text-slate-500">
                <a href="{{ route('login') }}" class="font-medium text-slate-900 underline">Connectez-vous</a> pour commenter et réagir.
            </p>
        @else
            @if(!$live->comments_enabled)
                <p class="mb-2 text-center text-xs text-slate-500">Commentaires fermés au public : seuls le diffuseur et les modérateurs peuvent écrire.</p>
            @endif
            {{-- Zone d'écriture : le champ grandit avec le texte (live.js), Entrée envoie, Maj+Entrée va à la ligne. --}}
            <form class="rounded-2xl border border-slate-200 bg-slate-50 transition-colors focus-within:border-primary-500 focus-within:bg-white focus-within:ring-1 focus-within:ring-primary-500"
                  data-live-comment-form data-no-loading>
                <label for="live-comment-body" class="sr-only">Votre message</label>
                <textarea id="live-comment-body" name="body" rows="2" maxlength="{{ config('livekit.comment_max_length') }}" required
                          class="block max-h-32 min-h-12 w-full resize-none border-0 bg-transparent px-4 pt-3 pb-1 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none"
                          placeholder="Écrire un message…" aria-describedby="live-comment-help"></textarea>
                <div class="flex items-center justify-between gap-2 px-2 pb-2">
                    <div class="flex min-w-0 items-center gap-1.5">
                        @if($liveCanModerate)
                            <label class="chip cursor-pointer py-0.5 text-xs select-none has-checked:border-primary-600 has-checked:bg-primary-600 has-checked:text-white"
                                   title="Épingler ce message en haut du direct" data-live-pin-option>
                                <input type="checkbox" class="sr-only" data-live-comment-pin>
                                <i class="fa-solid fa-thumbtack" aria-hidden="true"></i><span>Épingler</span>
                            </label>
                        @endif
                        <span class="px-1 text-[11px] text-slate-400 tabular-nums" data-live-comment-counter aria-hidden="true">0 / {{ config('livekit.comment_max_length') }}</span>
                    </div>
                    <button type="submit" class="btn-primary btn-sm shrink-0 rounded-full" aria-label="Envoyer">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">Envoyer</span>
                    </button>
                </div>
            </form>
            <p id="live-comment-help" class="mt-1.5 px-1 text-[11px] text-slate-400">Entrée pour envoyer · Maj+Entrée pour aller à la ligne</p>
        @endif
    </div>
</section>

{{-- Formulaire utilisé par openConfirmModal pour les actions de modération (masquer, exclure). --}}
<form id="live-moderation-form" data-no-loading hidden></form>
