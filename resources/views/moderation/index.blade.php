@extends('layouts.app')
@section('title', 'Modération')

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-shield-check me-2 text-primary"></i>File de modération</h4>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        @foreach([
            ['En attente', $stats['pending'], 'bi-hourglass-split', 'warning'],
            ['Approuvés aujourd\'hui', $stats['approvedToday'], 'bi-check-circle-fill', 'success'],
            ['Rejetés aujourd\'hui', $stats['rejectedToday'], 'bi-x-circle-fill', 'danger'],
            ['Total ce mois', $stats['totalThisMonth'], 'bi-calendar3', 'primary'],
        ] as [$label, $value, $icon, $color])
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="rounded-circle bg-{{ $color }} bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi {{ $icon }} text-{{ $color }} fs-5"></i>
                    </div>
                    <div>
                        <p class="fw-bold fs-4 mb-0">{{ $value }}</p>
                        <p class="text-muted small mb-0">{{ $label }}</p>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Status tabs --}}
    <ul class="nav nav-tabs mb-4">
        @foreach(['pending' => '⏳ En attente', 'approved' => '✅ Approuvés', 'rejected' => '❌ Rejetés', 'all' => 'Tous'] as $val => $label)
        <li class="nav-item">
            <a class="nav-link small {{ (request('status', 'pending')) === $val ? 'active fw-semibold' : 'text-muted' }}"
               href="{{ route('moderation.index', ['status' => $val]) }}">{{ $label }}</a>
        </li>
        @endforeach
    </ul>

    {{-- Items --}}
    @if($items->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-check2-circle" style="font-size:4rem;opacity:.2;"></i>
        <p class="mt-3 fw-semibold">Aucun élément à modérer !</p>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="fw-semibold small text-muted text-uppercase ps-4">Témoignage</th>
                        <th class="fw-semibold small text-muted text-uppercase">Auteur</th>
                        <th class="fw-semibold small text-muted text-uppercase">Type</th>
                        <th class="fw-semibold small text-muted text-uppercase">Statut</th>
                        <th class="fw-semibold small text-muted text-uppercase">Date</th>
                        <th class="fw-semibold small text-muted text-uppercase text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td class="ps-4" style="max-width:260px;">
                            <p class="fw-semibold mb-0 small text-truncate">{{ $item->title }}</p>
                            @if($item->body_text)<p class="text-muted mb-0" style="font-size:.75rem;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;">{{ $item->body_text }}</p>@endif
                        </td>
                        <td class="small">{{ $item->user->display_name }}</td>
                        <td><span class="badge bg-light text-dark border small">{{ $item->type->label() }}</span></td>
                        <td><span class="badge {{ $item->status->badgeClass() }} small">{{ $item->status->label() }}</span></td>
                        <td class="small text-muted">{{ $item->created_at->format('d M Y') }}</td>
                        <td class="text-end pe-4">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="{{ route('moderation.show', $item->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>Examiner
                                </a>
                                @if($item->status->value === 'pending')
                                <form method="POST" action="{{ route('moderation.approve', $item->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top py-3 d-flex justify-content-center">
            {{ $items->withQueryString()->links() }}
        </div>
    </div>
    @endif
</div>
@endsection
