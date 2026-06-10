@extends('layouts.app')
@section('title', 'Révision — ' . $testimony->title)

@section('content')
<div class="container-fluid px-4 py-4">

    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('moderation.index') }}" class="text-decoration-none">Modération</a></li>
            <li class="breadcrumb-item active text-truncate" style="max-width:300px;">{{ $testimony->title }}</li>
        </ol>
    </nav>

    <div class="row g-4">

        {{-- Content preview --}}
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge {{ $testimony->status->badgeClass() }}">{{ $testimony->status->label() }}</span>
                        <span class="text-muted small">{{ $testimony->type->label() }} &bull; {{ $testimony->category_slug }}</span>
                    </div>

                    <h4 class="fw-bold mb-4">{{ $testimony->title }}</h4>

                    {{-- Author --}}
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-4">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:40px;height:40px;font-weight:700;">
                            {{ $testimony->user->initials }}
                        </div>
                        <div>
                            <p class="fw-medium mb-0">{{ $testimony->user->display_name }}</p>
                            <p class="text-muted small mb-0">
                                {{ $testimony->user->country ?? 'Non spécifié' }}
                                &bull; Soumis le {{ $testimony->created_at->format('d M Y à H:i') }}
                            </p>
                        </div>
                    </div>

                    {{-- Cover --}}
                    @if($testimony->cover_url)
                    <img src="{{ $testimony->cover_url }}" class="img-fluid rounded-3 mb-4 w-100"
                         style="max-height:280px;object-fit:cover;" alt="">
                    @endif

                    {{-- Media --}}
                    @if($testimony->media_url && $testimony->type->value === 'audio')
                    <div class="mb-4">
                        <audio controls class="w-100"><source src="{{ $testimony->media_url }}"></audio>
                    </div>
                    @elseif($testimony->media_url && $testimony->type->value === 'video')
                    <div class="mb-4 ratio ratio-16x9 rounded-3 overflow-hidden bg-black">
                        <video controls><source src="{{ $testimony->media_url }}"></video>
                    </div>
                    @endif

                    {{-- Body --}}
                    @if($testimony->body_text)
                    <div class="text-secondary lh-lg mb-4" style="white-space:pre-line;">{{ $testimony->body_text }}</div>
                    @endif

                    {{-- Bible verse --}}
                    @if($testimony->bible_verse)
                    <div class="p-4 bg-primary bg-opacity-10 border-start border-4 border-primary rounded-end mb-4">
                        <p class="fst-italic text-primary mb-1">"{{ $testimony->bible_verse }}"</p>
                        @if($testimony->bible_ref)
                        <p class="text-primary fw-medium small mb-0">— {{ $testimony->bible_ref }}</p>
                        @endif
                    </div>
                    @endif

                    {{-- Moderation history --}}
                    @if($testimony->moderationLogs->isNotEmpty())
                    <div class="border-top pt-4">
                        <p class="text-muted small fw-semibold text-uppercase mb-3">Historique de modération</p>
                        @foreach($testimony->moderationLogs->sortByDesc('created_at') as $log)
                        <div class="text-muted small mb-2">
                            <span class="fw-semibold">{{ $log->action }}</span>
                            @if($log->moderator) par {{ $log->moderator->display_name }}@endif
                            @if($log->rejection_reason) &mdash; {{ $log->rejection_reason->label() }}@endif
                            @if($log->moderator_note) &mdash; "{{ $log->moderator_note }}"@endif
                            <span class="text-muted ms-1">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Actions sidebar --}}
        <div class="col-12 col-lg-4">
            @if($testimony->status->value === 'pending')

            {{-- Approve --}}
            <form method="POST" action="{{ route('moderation.approve', $testimony->id) }}" class="mb-3">
                @csrf
                <button type="submit" class="btn btn-success w-100 py-3 fw-semibold">
                    <i class="bi bi-check-circle-fill me-1"></i>Approuver et publier
                </button>
            </form>

            {{-- Reject --}}
            <div class="card border-danger border-opacity-25 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-semibold text-danger mb-3">
                        <i class="bi bi-x-circle me-1"></i>Rejeter
                    </h6>
                    <form method="POST" action="{{ route('moderation.reject', $testimony->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small">Raison *</label>
                            <select name="reason" required class="form-select">
                                @foreach($reasons as $r)
                                <option value="{{ $r->value }}">{{ $r->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Note pour l'auteur (optionnelle)</label>
                            <textarea name="moderator_note" rows="3" placeholder="Message pour l'auteur..."
                                      class="form-control" style="resize:none;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            Confirmer le rejet
                        </button>
                    </form>
                </div>
            </div>

            @else
            <div class="alert alert-secondary text-center">
                <i class="bi bi-info-circle me-1"></i>
                Ce témoignage a déjà été traité ({{ $testimony->status->label() }}).
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
