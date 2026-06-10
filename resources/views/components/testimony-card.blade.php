<div class="card testimony-card h-100 border-0 shadow-sm">
    {{-- Cover --}}
    <a href="{{ route('testimonies.show', $testimony->id) }}" class="text-decoration-none">
        @if($testimony->cover_url)
        <img src="{{ $testimony->cover_url }}" class="card-img-top" style="height:160px;object-fit:cover;" alt="">
        @elseif($testimony->type->value === 'audio')
        <div class="card-img-top d-flex align-items-center justify-content-center text-white" style="height:130px;background:linear-gradient(135deg,#7c3aed,#6366f1);">
            <i class="bi bi-mic-fill" style="font-size:3rem;opacity:.7;"></i>
        </div>
        @elseif($testimony->type->value === 'video')
        <div class="card-img-top d-flex align-items-center justify-content-center text-white" style="height:130px;background:linear-gradient(135deg,#ef4444,#f97316);">
            <i class="bi bi-camera-video-fill" style="font-size:3rem;opacity:.7;"></i>
        </div>
        @else
        <div class="card-img-top d-flex align-items-center justify-content-center text-white" style="height:100px;background:linear-gradient(135deg,#6366f1,#3b82f6);">
            <i class="bi bi-journal-text" style="font-size:2.5rem;opacity:.7;"></i>
        </div>
        @endif
    </a>

    <div class="card-body d-flex flex-column p-3">
        {{-- Badges --}}
        <div class="d-flex gap-2 mb-2 flex-wrap">
            @if($testimony->type->value === 'audio')
            <span class="badge text-white" style="background:#7c3aed;font-size:.7rem;"><i class="bi bi-mic-fill me-1"></i>Audio</span>
            @elseif($testimony->type->value === 'video')
            <span class="badge bg-danger" style="font-size:.7rem;"><i class="bi bi-camera-video-fill me-1"></i>Vidéo</span>
            @else
            <span class="badge bg-primary" style="font-size:.7rem;"><i class="bi bi-file-text-fill me-1"></i>Texte</span>
            @endif
            <span class="badge bg-light text-secondary border" style="font-size:.7rem;">{{ $testimony->category_slug }}</span>
            @if($testimony->is_featured)<span class="badge bg-warning text-dark" style="font-size:.7rem;"><i class="bi bi-star-fill me-1"></i>À la une</span>@endif
        </div>

        {{-- Title --}}
        <h6 class="fw-bold mb-2" style="line-height:1.4;">
            <a href="{{ route('testimonies.show', $testimony->id) }}" class="text-dark text-decoration-none">
                {{ Str::limit($testimony->title, 65) }}
            </a>
        </h6>

        {{-- Body preview --}}
        @if($testimony->body_text)
        <p class="small text-muted mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.5;">
            {{ $testimony->body_text }}
        </p>
        @endif

        {{-- Bible verse --}}
        @if($testimony->bible_ref)
        <p class="small text-primary mb-2 fst-italic mb-0">
            <i class="bi bi-book me-1"></i>{{ $testimony->bible_ref }}
        </p>
        @endif

        {{-- Author & stats --}}
        <div class="d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:26px;height:26px;font-size:.65rem;font-weight:700;">
                    {{ $testimony->user->initials }}
                </div>
                <div>
                    <p class="mb-0 small fw-medium lh-1">{{ $testimony->user->display_name }}</p>
                    <p class="mb-0 text-muted" style="font-size:.65rem;">{{ $testimony->created_at->diffForHumans() }}</p>
                </div>
            </div>
            <div class="d-flex gap-2 text-muted" style="font-size:.75rem;">
                <span><i class="bi bi-eye"></i> {{ number_format($testimony->views_count) }}</span>
                <span><i class="bi bi-hand-thumbs-up"></i> {{ number_format($testimony->like_count) }}</span>
                <span><i class="bi bi-chat"></i> {{ number_format($testimony->comment_count) }}</span>
            </div>
        </div>
    </div>
</div>
