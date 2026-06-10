@extends('layouts.admin')
@section('title', 'Tableau de bord')
@section('page-title', 'Tableau de bord')

@section('content')

<div class="row g-3 mb-4">
    @foreach([
        ['Utilisateurs', $stats['totalUsers'], 'bi-people-fill', '#6366f1', 'Dont ' . $stats['newUsersToday'] . " aujourd'hui"],
        ['Témoignages', $stats['totalTestimonies'], 'bi-journal-text', '#10b981', $stats['pendingTestimonies'] . ' en attente'],
        ['Approuvés', $stats['approvedTestimonies'], 'bi-check-circle-fill', '#3b82f6', $stats['approvalRate'] . '% taux'],
        ['Vues ce mois', number_format($stats['viewsThisMonth']), 'bi-eye-fill', '#f59e0b', $stats['commentsThisMonth'] . ' commentaires'],
    ] as [$label, $value, $icon, $color, $sub])
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="rounded-2 d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:{{ $color }}20;">
                        <i class="bi {{ $icon }}" style="color:{{ $color }};font-size:1.2rem;"></i>
                    </div>
                </div>
                <p class="fw-bold fs-3 mb-0">{{ $value }}</p>
                <p class="text-muted small mb-0">{{ $label }}</p>
                <p class="small mt-1 mb-0" style="color:{{ $color }};font-size:.75rem;">{{ $sub }}</p>
            </div>
        </div>
    </div>
    @endforeach
</div>

@if($stats['pendingTestimonies'] > 0)
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
    <div class="flex-grow-1">
        <strong>{{ $stats['pendingTestimonies'] }} témoignage(s) en attente</strong>
        <p class="mb-0 small">Ces témoignages nécessitent votre révision avant publication.</p>
    </div>
    <a href="{{ route('moderation.index') }}" class="btn btn-warning btn-sm fw-semibold">Modérer →</a>
</div>
@endif

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between py-3">
                <span><i class="bi bi-journal-text me-2 text-primary"></i>Derniers témoignages</span>
                <a href="{{ route('admin.content.index') }}" class="small text-primary text-decoration-none">Voir tout</a>
            </div>
            <div class="list-group list-group-flush">
                @foreach($recentTestimonies as $t)
                <div class="list-group-item border-0 d-flex align-items-center gap-3 py-3">
                    <div class="flex-grow-1 min-w-0">
                        <p class="small fw-semibold mb-0 text-truncate">{{ $t->title }}</p>
                        <p class="text-muted mb-0" style="font-size:.75rem;">{{ $t->user->display_name }} · {{ $t->created_at->format('d M') }}</p>
                    </div>
                    <span class="badge {{ $t->status->badgeClass() }}">{{ $t->status->label() }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between py-3">
                <span><i class="bi bi-people me-2 text-primary"></i>Nouveaux utilisateurs</span>
                <a href="{{ route('admin.users.index') }}" class="small text-primary text-decoration-none">Voir tout</a>
            </div>
            <div class="list-group list-group-flush">
                @foreach($recentUsers as $user)
                <div class="list-group-item border-0 d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;font-size:.75rem;font-weight:700;">{{ $user->initials }}</div>
                    <div class="flex-grow-1 min-w-0">
                        <p class="small fw-semibold mb-0 text-truncate">{{ $user->display_name }}</p>
                        <p class="text-muted mb-0" style="font-size:.75rem;">{{ $user->email }} · {{ $user->created_at->format('d M') }}</p>
                    </div>
                    <span class="badge bg-light text-dark border" style="font-size:.7rem;">{{ $user->role->label() }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
