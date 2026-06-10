@extends('layouts.admin')
@section('title', 'Utilisateurs')
@section('page-title', 'Gestion des utilisateurs')

@section('content')
<form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 mb-4">
    <div class="col-12 col-md">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control border-start-0 ps-0" placeholder="Rechercher...">
        </div>
    </div>
    <div class="col-auto">
        <select name="role" class="form-select">
            <option value="">Tous les rôles</option>
            @foreach(['visiteur' => 'Visiteur', 'utilisateur' => 'Utilisateur', 'moderateur' => 'Modérateur', 'administrateur' => 'Administrateur'] as $v => $l)
            <option value="{{ $v }}" {{ request('role') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="status" class="form-select">
            <option value="">Tous statuts</option>
            <option value="active">Actif</option>
            <option value="suspended">Suspendu</option>
            <option value="banned">Banni</option>
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
                    <th class="small text-muted text-uppercase ps-4">Utilisateur</th>
                    <th class="small text-muted text-uppercase">Contact</th>
                    <th class="small text-muted text-uppercase">Rôle</th>
                    <th class="small text-muted text-uppercase">Statut</th>
                    <th class="small text-muted text-uppercase">Inscrit le</th>
                    <th class="small text-muted text-uppercase text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;font-size:.75rem;font-weight:700;">{{ $user->initials }}</div>
                            <div>
                                <p class="small fw-semibold mb-0">{{ $user->display_name }}</p>
                                @if($user->country)<p class="text-muted mb-0" style="font-size:.72rem;">{{ $user->country }}</p>@endif
                            </div>
                        </div>
                    </td>
                    <td class="small text-muted">{{ $user->email }}</td>
                    <td><span class="badge bg-light text-dark border small">{{ $user->role->label() }}</span></td>
                    <td><span class="badge {{ $user->status->badgeClass() }} small">{{ $user->status->label() }}</span></td>
                    <td class="small text-muted">{{ $user->created_at->format('d M Y') }}</td>
                    <td class="text-end pe-4">
                        <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-sm btn-outline-primary">Gérer</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-center">
        {{ $users->withQueryString()->links() }}
    </div>
</div>
@endsection
