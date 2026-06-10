@extends('layouts.admin')
@section('title', 'Catégories')
@section('page-title', 'Gestion des catégories')

@section('content')

{{-- Add form --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-plus-circle me-1 text-primary"></i>Nouvelle catégorie</h6>
        <form method="POST" action="{{ route('admin.categories.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-12 col-md">
                <input type="text" name="name" placeholder="Nom *" required class="form-control">
            </div>
            <div class="col-12 col-md">
                <input type="text" name="slug" placeholder="slug-unique *" required class="form-control">
            </div>
            <div class="col-auto">
                <input type="text" name="icon" placeholder="Icône (emoji)" class="form-control" style="width:120px;">
            </div>
            <div class="col-auto">
                <input type="text" name="color" placeholder="#Couleur" class="form-control" style="width:120px;">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

{{-- List --}}
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted text-uppercase ps-4">Ordre</th>
                    <th class="small text-muted text-uppercase">Catégorie</th>
                    <th class="small text-muted text-uppercase">Slug</th>
                    <th class="small text-muted text-uppercase">Témoignages</th>
                    <th class="small text-muted text-uppercase">Statut</th>
                    <th class="small text-muted text-uppercase text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $cat)
                <tr>
                    <td class="ps-4 text-muted small">{{ $cat->display_order }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @if($cat->icon)<span style="font-size:1.1rem;">{{ $cat->icon }}</span>@endif
                            <span class="fw-medium">{{ $cat->name }}</span>
                        </div>
                    </td>
                    <td><code class="small text-muted">{{ $cat->slug }}</code></td>
                    <td class="small">{{ number_format($cat->testimony_count) }}</td>
                    <td>
                        <span class="badge {{ $cat->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                            {{ $cat->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-flex gap-1 justify-content-end">
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#edit-cat-{{ $cat->id }}">
                                Modifier
                            </button>
                            <form method="POST" action="{{ route('admin.categories.destroy', $cat->id) }}"
                                  onsubmit="return confirm('Supprimer cette catégorie ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Suppr.</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <tr id="edit-cat-{{ $cat->id }}" class="collapse bg-primary bg-opacity-10">
                    <td colspan="6" class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.categories.update', $cat->id) }}"
                              class="row g-2 align-items-center">
                            @csrf @method('PUT')
                            <div class="col-12 col-md-3">
                                <input type="text" name="name" value="{{ $cat->name }}" class="form-control form-control-sm" placeholder="Nom">
                            </div>
                            <div class="col-auto">
                                <input type="text" name="icon" value="{{ $cat->icon }}" class="form-control form-control-sm" placeholder="Icône" style="width:90px;">
                            </div>
                            <div class="col-auto">
                                <input type="text" name="color" value="{{ $cat->color }}" class="form-control form-control-sm" placeholder="#Couleur" style="width:100px;">
                            </div>
                            <div class="col-auto">
                                <input type="number" name="display_order" value="{{ $cat->display_order }}" class="form-control form-control-sm" placeholder="Ordre" style="width:80px;">
                            </div>
                            <div class="col-auto">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" value="1" id="active-{{ $cat->id }}"
                                           {{ $cat->is_active ? 'checked' : '' }} class="form-check-input">
                                    <label class="form-check-label small" for="active-{{ $cat->id }}">Active</label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check-lg"></i> Enregistrer
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3 d-flex justify-content-center">
        {{ $categories->links() }}
    </div>
</div>
@endsection
