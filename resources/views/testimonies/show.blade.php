@extends('layouts.app')
@section('title', $testimony->title)

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-primary text-decoration-none">Accueil</a></li>
            <li class="breadcrumb-item"><a href="{{ route('home', ['category' => $testimony->category_slug]) }}" class="text-primary text-decoration-none">{{ $testimony->category_slug }}</a></li>
            <li class="breadcrumb-item active text-truncate" style="max-width:200px;">{{ $testimony->title }}</li>
        </ol>
    </nav>

    <div class="row g-4">

        {{-- Main --}}
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                @if($testimony->cover_url)
                <img src="{{ $testimony->cover_url }}" class="card-img-top" style="max-height:380px;object-fit:cover;" alt="{{ $testimony->title }}">
                @endif

                <div class="card-body p-4">
                    {{-- Badges --}}
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <span class="badge {{ $testimony->status->badgeClass() }}">{{ $testimony->status->label() }}</span>
                        <span class="badge bg-light text-dark border">{{ $testimony->category_slug }}</span>
                        @if($testimony->is_featured)<span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>À la une</span>@endif
                        @if($testimony->type->value === 'audio')<span class="badge text-white" style="background:#7c3aed"><i class="bi bi-mic-fill me-1"></i>Audio</span>@endif
                        @if($testimony->type->value === 'video')<span class="badge bg-danger"><i class="bi bi-camera-video-fill me-1"></i>Vidéo</span>@endif
                    </div>

                    <h2 class="fw-bold mb-3">{{ $testimony->title }}</h2>

                    {{-- Audio --}}
                    @if($testimony->media_url && $testimony->type->value === 'audio')
                    <div class="bg-light rounded-3 p-3 mb-4">
                        <p class="small fw-semibold text-muted mb-2"><i class="bi bi-mic-fill me-1 text-primary"></i>Témoignage audio</p>
                        <audio controls class="w-100"><source src="{{ $testimony->media_url }}" type="audio/mpeg"></audio>
                    </div>
                    @endif

                    {{-- Video --}}
                    @if($testimony->media_url && $testimony->type->value === 'video')
                    <div class="mb-4 rounded-3 overflow-hidden">
                        <video controls class="w-100" style="max-height:400px;background:#000;">
                            <source src="{{ $testimony->media_url }}" type="video/mp4">
                        </video>
                    </div>
                    @endif

                    {{-- Body --}}
                    @if($testimony->body_text)
                    <div class="mb-4" style="line-height:1.9;white-space:pre-wrap;">{{ $testimony->body_text }}</div>
                    @endif

                    {{-- Bible verse --}}
                    @if($testimony->bible_verse)
                    <div class="border-start border-4 border-primary bg-light rounded-3 p-3 mb-4">
                        <p class="fst-italic text-muted mb-1">« {{ $testimony->bible_verse }} »</p>
                        @if($testimony->bible_ref)<p class="small fw-bold text-primary mb-0">— {{ $testimony->bible_ref }}</p>@endif
                    </div>
                    @endif

                    {{-- Tags --}}
                    @if($testimony->tags && count($testimony->tags) > 0)
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        @foreach($testimony->tags as $tag)
                        <span class="badge bg-light text-secondary border">#{{ $tag }}</span>
                        @endforeach
                    </div>
                    @endif

                    {{-- Stats bar --}}
                    <div class="d-flex align-items-center gap-3 pt-3 border-top text-muted small mb-3">
                        <span><i class="bi bi-eye me-1"></i>{{ number_format($testimony->views_count) }}</span>
                        <span><i class="bi bi-chat me-1"></i><span id="comment-count">{{ $testimony->comment_count }}</span></span>
                        <span><i class="bi bi-hand-thumbs-up me-1"></i><span id="like-count">{{ $testimony->like_count }}</span></span>
                        <span><i class="bi bi-heart me-1"></i><span id="prayer-count">{{ $testimony->prayer_count }}</span></span>
                    </div>

                    {{-- Reaction buttons --}}
                    <div class="d-flex flex-wrap gap-2 align-items-center">

                        @php
                            $reactionDefs = [
                                ['like',  '👍', "J'aime"],
                                ['pray',  '🙏', 'Prière'],
                                ['amen',  '🙌', 'Amen'],
                                ['fire',  '🔥', 'Feu'],
                            ];
                        @endphp

                        @foreach($reactionDefs as [$rType, $rEmoji, $rLabel])
                        @php $active = in_array($rType, $userReactions); @endphp
                        <button
                            class="reaction-btn btn btn-sm {{ $active ? 'btn-primary' : 'btn-outline-secondary' }}"
                            data-type="{{ $rType }}"
                            data-auth="{{ Auth::check() ? '1' : '0' }}"
                            title="{{ $rLabel }}">
                            <span>{{ $rEmoji }}</span>
                            <span class="ms-1">{{ $rLabel }}</span>
                        </button>
                        @endforeach

                        @auth
                        <button id="save-btn"
                                class="btn btn-sm ms-auto {{ $isSaved ? 'btn-primary' : 'btn-outline-primary' }}"
                                data-saved="{{ $isSaved ? '1' : '0' }}">
                            <i class="bi {{ $isSaved ? 'bi-bookmark-fill' : 'bi-bookmark' }} me-1" id="save-icon"></i>
                            <span id="save-label">{{ $isSaved ? 'Sauvegardé' : 'Sauvegarder' }}</span>
                        </button>
                        @endauth
                    </div>
                </div>
            </div>

            {{-- Comments --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold py-3">
                    <i class="bi bi-chat-dots me-2 text-primary"></i>Commentaires (<span id="comment-count-header">{{ $testimony->comment_count }}</span>)
                </div>
                <div class="card-body p-4">

                    @auth
                    <form id="comment-form" class="mb-4">
                        @csrf
                        <div class="d-flex gap-3">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:36px;height:36px;font-size:.75rem;font-weight:700;margin-top:4px;">
                                {{ Auth::user()->initials }}
                            </div>
                            <div class="flex-grow-1">
                                <textarea name="body" id="comment-body" rows="2" required
                                          class="form-control mb-2"
                                          placeholder="Partagez votre réaction…"></textarea>
                                <button type="submit" id="comment-submit" class="btn btn-primary btn-sm px-4">
                                    <i class="bi bi-send me-1"></i>Commenter
                                </button>
                            </div>
                        </div>
                    </form>
                    @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm mb-4">
                        <i class="bi bi-chat me-1"></i>Connectez-vous pour commenter
                    </a>
                    @endauth

                    <div id="comments-list">
                        @forelse($comments as $comment)
                        <div class="d-flex gap-3 mb-4 comment-item">
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:36px;height:36px;font-size:.75rem;font-weight:700;margin-top:2px;">
                                {{ $comment->user->initials }}
                            </div>
                            <div class="flex-grow-1">
                                <div class="bg-light rounded-3 px-3 py-2">
                                    <p class="small fw-semibold mb-1">{{ $comment->user->display_name }}
                                        <span class="text-muted fw-normal">· {{ $comment->created_at->diffForHumans() }}</span>
                                    </p>
                                    <p class="mb-0 small">{{ $comment->body }}</p>
                                </div>
                            </div>
                        </div>
                        @empty
                        <p id="no-comments" class="text-muted text-center small py-3">Soyez le premier à commenter !</p>
                        @endforelse
                    </div>

                    @if($comments->hasPages())
                    <div class="mt-3">{{ $comments->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-12 col-lg-4">
            {{-- Author --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3"
                         style="width:64px;height:64px;font-size:1.3rem;font-weight:700;">
                        {{ $testimony->user->initials }}
                    </div>
                    <h6 class="fw-bold mb-1">{{ $testimony->user->display_name }}</h6>
                    @if($testimony->user->country)
                    <p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i>{{ $testimony->user->country }}</p>
                    @endif
                    <div class="row g-2 text-center mb-3">
                        <div class="col"><p class="fw-bold mb-0">{{ $testimony->user->testimony_count }}</p><p class="text-muted small mb-0">Témoin.</p></div>
                        <div class="col"><p class="fw-bold mb-0">{{ $testimony->user->follower_count }}</p><p class="text-muted small mb-0">Abonnés</p></div>
                    </div>
                    <a href="{{ route('profiles.show', $testimony->user->id) }}" class="btn btn-outline-primary btn-sm w-100">Voir le profil</a>
                </div>
            </div>

            {{-- Report --}}
            @auth
            @if($testimony->user_id !== Auth::id())
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 text-center">
                    <button class="btn btn-link text-danger btn-sm text-decoration-none"
                            data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="bi bi-flag me-1"></i>Signaler ce témoignage
                    </button>
                </div>
            </div>
            @endif
            @endauth
        </div>
    </div>
</div>

{{-- Report modal --}}
@auth
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Signaler ce témoignage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('testimonies.report', $testimony->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Raison</label>
                        <select name="reason" class="form-select" required>
                            <option value="inappropriateContent">Contenu inapproprié</option>
                            <option value="falseTestimony">Faux témoignage</option>
                            <option value="hateSpeech">Discours haineux</option>
                            <option value="spam">Spam</option>
                            <option value="other">Autre</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Détails (optionnel)</label>
                        <textarea name="details" rows="3" class="form-control" placeholder="Expliquez pourquoi vous signalez…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Envoyer le signalement</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endauth
@endsection

@push('scripts')
<script>
(function () {
    const CSRF        = document.querySelector('meta[name=csrf-token]').content;
    const TESTIMONY   = '{{ $testimony->id }}';
    const IS_AUTH     = {{ Auth::check() ? 'true' : 'false' }};
    const LOGIN_URL   = '{{ route('login') }}';

    // ── Helpers ──────────────────────────────────────────────────────
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function flash(msg, type = 'success') {
        const wrap = document.createElement('div');
        wrap.className = `position-fixed top-0 end-0 p-3`;
        wrap.style.cssText = 'z-index:9999;margin-top:70px';
        wrap.innerHTML = `
            <div class="toast show align-items-center text-bg-${type} border-0">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-${type === 'success' ? 'check-circle' : 'x-circle'} me-2"></i>${escHtml(msg)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.position-fixed').remove()"></button>
                </div>
            </div>`;
        document.body.appendChild(wrap);
        setTimeout(() => wrap.remove(), 3500);
    }

    // ── Réactions ────────────────────────────────────────────────────
    document.querySelectorAll('.reaction-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!IS_AUTH) { window.location = LOGIN_URL; return; }

            const type = this.dataset.type;
            this.disabled = true;

            try {
                const res  = await post(`/testimonies/${TESTIMONY}/reactions`, `type=${type}`);
                const data = await res.json();

                // Toggle style
                const active = data.reacted;
                this.classList.toggle('btn-primary', active);
                this.classList.toggle('btn-outline-secondary', !active);

                // Update global counts
                const likeEl   = document.getElementById('like-count');
                const prayEl   = document.getElementById('prayer-count');
                if (likeEl)   likeEl.textContent   = data.like_count;
                if (prayEl)   prayEl.textContent    = data.prayer_count;

                flash(active ? 'Réaction enregistrée !' : 'Réaction retirée.');
            } catch {
                flash('Une erreur est survenue.', 'danger');
            } finally {
                this.disabled = false;
            }
        });
    });

    // ── Sauvegarder ──────────────────────────────────────────────────
    const saveBtn = document.getElementById('save-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', async function () {
            this.disabled = true;

            try {
                const res  = await fetch(`/testimonies/${TESTIMONY}/save`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': CSRF,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: '_method=PUT',
                });
                const data = await res.json();

                const saved = data.saved;
                const icon  = document.getElementById('save-icon');
                const label = document.getElementById('save-label');

                icon.className  = saved ? 'bi bi-bookmark-fill me-1' : 'bi bi-bookmark me-1';
                label.textContent = saved ? 'Sauvegardé' : 'Sauvegarder';
                this.classList.toggle('btn-primary', saved);
                this.classList.toggle('btn-outline-primary', !saved);
                this.dataset.saved = saved ? '1' : '0';

                flash(saved ? 'Témoignage sauvegardé !' : 'Retiré des sauvegardes.');
            } catch {
                flash('Une erreur est survenue.', 'danger');
            } finally {
                this.disabled = false;
            }
        });
    }

    // ── Commentaires ─────────────────────────────────────────────────
    const commentForm = document.getElementById('comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const bodyEl = document.getElementById('comment-body');
            const body   = bodyEl.value.trim();
            if (!body) return;

            const submitBtn = document.getElementById('comment-submit');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Envoi…';

            try {
                const res  = await post(`/testimonies/${TESTIMONY}/comments`, `body=${encodeURIComponent(body)}`);
                if (!res.ok) throw new Error();
                const data = await res.json();

                // Clear textarea
                bodyEl.value = '';

                // Remove "no comments" placeholder
                document.getElementById('no-comments')?.remove();

                // Prepend new comment
                const list = document.getElementById('comments-list');
                list.insertAdjacentHTML('afterbegin', `
                    <div class="d-flex gap-3 mb-4 comment-item">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:36px;height:36px;font-size:.75rem;font-weight:700;margin-top:2px;">
                            ${escHtml(data.comment.initials)}
                        </div>
                        <div class="flex-grow-1">
                            <div class="bg-light rounded-3 px-3 py-2">
                                <p class="small fw-semibold mb-1">${escHtml(data.comment.display_name)}
                                    <span class="text-muted fw-normal">· à l'instant</span>
                                </p>
                                <p class="mb-0 small">${escHtml(data.comment.body)}</p>
                            </div>
                        </div>
                    </div>`);

                // Increment comment counts
                ['comment-count', 'comment-count-header'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = parseInt(el.textContent || '0') + 1;
                });

                flash('Commentaire ajouté !');
            } catch {
                flash('Impossible d\'envoyer le commentaire.', 'danger');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>Commenter';
            }
        });
    }
})();
</script>
@endpush
