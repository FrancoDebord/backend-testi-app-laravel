@extends('layouts.admin')
@section('title', 'Contenu')
@section('page-title', 'Gestion du contenu')

@section('content')
<form method="GET" action="{{ route('admin.content.index') }}" class="row g-2 mb-4">
    <div class="col-12 col-md">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control border-start-0 ps-0" placeholder="Rechercher...">
        </div>
    </div>
    <div class="col-auto">
        <select name="status" class="form-select">
            <option value="">Tous les statuts</option>
            @foreach(['pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Rejeté', 'draft' => 'Brouillon'] as $val => $label)
            <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="type" class="form-select">
            <option value="">Tous les types</option>
            <option value="text" {{ request('type') === 'text' ? 'selected' : '' }}>Texte</option>
            <option value="audio" {{ request('type') === 'audio' ? 'selected' : '' }}>Audio</option>
            <option value="video" {{ request('type') === 'video' ? 'selected' : '' }}>Vidéo</option>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrer</button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted text-uppercase ps-4">Titre</th>
                    <th class="small text-muted text-uppercase">Auteur</th>
                    <th class="small text-muted text-uppercase">Type</th>
                    <th class="small text-muted text-uppercase">Statut</th>
                    <th class="small text-muted text-uppercase">Vues</th>
                    <th class="small text-muted text-uppercase">Date</th>
                    <th class="small text-muted text-uppercase text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($testimonies as $t)
                <tr>
                    <td class="ps-4" style="max-width:260px;">
                        <p class="small fw-semibold mb-0 text-truncate">{{ $t->title }}</p>
                        <p class="text-muted mb-0" style="font-size:.72rem;">{{ $t->category_slug }}</p>
                    </td>
                    <td class="small text-muted">{{ $t->user->display_name }}</td>
                    <td><span class="small text-muted">{{ $t->type->label() }}</span></td>
                    <td>
                        <span class="badge {{ $t->status->badgeClass() }} small">{{ $t->status->label() }}</span>
                    </td>
                    <td class="small text-muted">{{ number_format($t->views_count) }}</td>
                    <td class="small text-muted">{{ $t->created_at->format('d M Y') }}</td>
                    <td class="text-end pe-4">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('testimonies.show', $t->id) }}" target="_blank"
                               class="btn btn-sm btn-outline-secondary">Voir</a>
                            @if($t->status->value === 'pending')
                            <a href="{{ route('moderation.show', $t->id) }}"
                               class="btn btn-sm btn-outline-primary">Modérer</a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-center">
        {{ $testimonies->withQueryString()->links() }}
    </div>
</div>
@endsection
