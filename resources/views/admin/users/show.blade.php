@extends('layouts.app')
@section('title', $user->display_name)
@php
    $header      = $user->display_name;
    $subheader   = 'Fiche du compte utilisateur.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Administration', 'url' => route('admin.dashboard')],
        ['label' => 'Utilisateurs', 'url' => route('admin.users.index')],
        ['label' => $user->display_name],
    ];
    $roles    = ['visiteur' => 'Visiteur', 'utilisateur' => 'Utilisateur', 'moderateur' => 'Modérateur', 'administrateur' => 'Administrateur'];
    $statuses = ['active' => 'Actif', 'suspended' => 'Suspendu', 'banned' => 'Banni'];
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <section class="card min-w-0 self-start p-5">
        <div class="flex items-center gap-3">
            @include('components.avatar', ['user' => $user, 'size' => 'lg'])
            <div class="min-w-0">
                <p class="truncate font-semibold text-slate-900">{{ $user->display_name }}</p>
                <p class="truncate text-sm text-slate-500">{{ $user->email ?? $user->phone }}</p>
            </div>
        </div>
        <dl class="mt-5 divide-y divide-slate-100 border-t border-slate-100 text-sm">
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Statut</dt><dd><span class="{{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span></dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Rôle</dt><dd class="text-slate-900">{{ $user->role->label() }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Pays</dt><dd class="text-right text-slate-900">{{ $user->country ?: '—' }}</dd></div>
            {{-- Téléphone de contact, pour les vérifications (docs/fonctionnalites/telephone.md) --}}
            <div class="flex justify-between gap-4 py-2">
                <dt class="text-slate-500">Téléphone</dt>
                <dd class="min-w-0 text-right text-slate-900">
                    @if($user->phone)
                        <a href="tel:{{ $user->phone }}" class="tabular-nums hover:underline">{{ \App\Support\PhoneNumber::display($user->phone, $user->phone_country) }}</a>
                        <span class="mt-1 block"><span class="{{ $user->hasVerifiedPhone() ? 'badge-validated' : 'badge-neutral' }}">{{ $user->hasVerifiedPhone() ? 'Vérifié par SMS' : 'Non vérifié' }}</span></span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Témoignages</dt><dd class="text-slate-900">{{ $user->testimony_count }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Abonnés</dt><dd class="text-slate-900">{{ $user->follower_count }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">J’aime reçus</dt><dd class="text-slate-900">{{ $user->like_count }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Inscrit le</dt><dd class="text-slate-900">{{ $user->created_at->format('d/m/Y') }}</dd></div>
        </dl>
    </section>

    <div class="min-w-0 space-y-6 lg:col-span-2">
        {{-- Compte organisation : docs/fonctionnalites/comptes-organisation.md --}}
        @if($user->isOrganization())
        <section class="card p-5 sm:p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title">Organisation</h2>
                @if($user->verification_status)
                <span class="{{ $user->verification_status->badgeClass() }}">{{ $user->verification_status->label() }}</span>
                @endif
            </div>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div class="min-w-0"><dt class="text-slate-500">Nom</dt><dd class="break-words text-slate-900">{{ $user->organization_name ?: '—' }}</dd></div>
                <div class="min-w-0"><dt class="text-slate-500">Type</dt><dd class="text-slate-900">{{ $user->organization_type?->label() ?? '—' }}</dd></div>
                <div class="min-w-0"><dt class="text-slate-500">Ville</dt><dd class="break-words text-slate-900">{{ $user->organization_city ?: '—' }}</dd></div>
                <div class="min-w-0">
                    <dt class="text-slate-500">Site internet</dt>
                    <dd class="break-all text-slate-900">
                        @if($user->organization_website)
                        <a href="{{ $user->organization_website }}" target="_blank" rel="noopener noreferrer nofollow" class="hover:underline">{{ $user->organization_website }} <i class="fa-solid fa-arrow-up-right-from-square text-xs text-slate-400" aria-hidden="true"></i></a>
                        @else — @endif
                    </dd>
                </div>
                @if($user->verified_at)
                <div class="min-w-0"><dt class="text-slate-500">Vérifiée le</dt><dd class="text-slate-900">{{ $user->verified_at->format('d/m/Y à H:i') }}@if($user->verifier) par {{ $user->verifier->display_name }}@endif</dd></div>
                @endif
                @if($user->verification_note)
                <div class="min-w-0 sm:col-span-2"><dt class="text-slate-500">Motif du refus</dt><dd class="break-words text-slate-900">« {{ $user->verification_note }} »</dd></div>
                @endif
            </dl>

            <div class="mt-5 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                @unless($user->isVerified())
                <form id="org-verify-form" method="POST" action="{{ route('admin.users.verify', $user->id) }}" class="flex flex-col" data-loading-label="Vérification…">
                    @csrf
                    <p class="mb-3 text-sm text-slate-600">Le badge « vérifiée » s’affichera sur le profil et les témoignages de l’organisation.</p>
                    <button type="button" class="btn-primary mt-auto w-full"
                            onclick="openConfirmModal('org-verify-form', @js('Confirmez que « ' . ($user->organization_name ?? $user->display_name) . ' » est bien une organisation réelle. Elle sera prévenue dans l’application.'), 'Vérifier cette organisation', 'Vérifier', 'fa-circle-check')">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>Vérifier
                    </button>
                </form>
                @endunless

                @if($user->verification_status?->value !== 'rejected')
                <form id="org-reject-form" method="POST" action="{{ route('admin.users.reject-verification', $user->id) }}" class="flex flex-col gap-3" data-loading-label="Enregistrement du refus…">
                    @csrf
                    <div>
                        <label for="reason" class="form-label">Motif du refus (facultatif)</label>
                        <textarea id="reason" name="reason" rows="2" maxlength="255" class="form-input resize-none"
                                  placeholder="Ex. : site internet introuvable, nom incomplet…">{{ old('reason') }}</textarea>
                        @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <button type="button" class="btn-secondary mt-auto w-full text-red-700"
                            onclick="openConfirmModal('org-reject-form', @js(($user->isVerified() ? 'Le badge « vérifiée » sera retiré. ' : '') . 'L’organisation sera informée du refus et du motif indiqué.'), 'Refuser la vérification', 'Confirmer le refus', 'fa-xmark')">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>{{ $user->isVerified() ? 'Retirer la vérification' : 'Refuser' }}
                    </button>
                </form>
                @endif
            </div>
        </section>
        @endif

        <section class="card p-5 sm:p-6">
            <h2 class="card-title mb-4">Modifier le compte</h2>
            <form id="user-update-form" method="POST" action="{{ route('admin.users.update', $user->id) }}" data-loading-label="Enregistrement…">
                @csrf @method('PUT')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="role" class="form-label">Rôle</label>
                        <select id="role" name="role" class="form-input">
                            @foreach($roles as $v => $l)
                            <option value="{{ $v }}" @selected(old('role', $user->role->value) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                        @error('role')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="status" class="form-label">Statut</label>
                        <select id="status" name="status" class="form-input">
                            @foreach($statuses as $v => $l)
                            <option value="{{ $v }}" @selected(old('status', $user->status->value) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-5 flex justify-end border-t border-slate-100 pt-5">
                    <button type="button" class="btn-primary"
                            onclick="openConfirmModal('user-update-form', @js('Le rôle et le statut de ' . $user->display_name . ' seront mis à jour. Un compte suspendu ou banni ne peut plus se connecter.'), 'Modifier ce compte', 'Enregistrer', 'fa-user-pen')">
                        Enregistrer
                    </button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2 class="card-title border-b border-slate-100 px-5 py-4">Derniers témoignages</h2>
            <ul class="divide-y divide-slate-100">
                @forelse($testimonies as $t)
                <li class="flex items-center gap-3 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('testimonies.show', $t->id) }}" class="block truncate text-sm font-medium text-slate-900 hover:underline">{{ $t->title }}</a>
                        <p class="text-xs text-slate-500">{{ $t->created_at->format('d/m/Y') }}</p>
                    </div>
                    <span class="{{ $t->status->badgeClass() }}">{{ $t->status->label() }}</span>
                </li>
                @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">Aucun témoignage.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
