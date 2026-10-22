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
        @else
        {{-- Compte personnel vérifié : docs/fonctionnalites/verification-des-comptes.md --}}
        @php($lastVerification = $user->verificationRequests()->first())
        @if($lastVerification || $user->isVerified())
        <section class="card p-5 sm:p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title">Vérification du compte</h2>
                @if($user->isVerified())
                <span class="badge-validated">Compte vérifié</span>
                @elseif($lastVerification)
                <span class="{{ $lastVerification->statusBadgeClass() }}">{{ $lastVerification->statusLabel() }}</span>
                @endif
            </div>
            @if($lastVerification)
            <p class="mb-4 text-sm text-slate-600">Dernière demande du {{ $lastVerification->created_at->format('d/m/Y') }} ({{ $lastVerification->roleLabel() }}). <a href="{{ route('admin.verifications.show', $lastVerification->id) }}" class="font-semibold text-primary-600 hover:underline">Voir la demande et les pièces</a></p>
            @endif
            @if($user->isVerified())
            @include('admin.verifications.partials.revoke-form', ['account' => $user])
            @endif
        </section>
        @endif
        @endif

        {{-- Badge « Approuvé » : attribution ou retrait manuel, motif obligatoire (docs/fonctionnalites/badge-approuve.md) --}}
        @php($approvalSources = ['verification_form' => 'Demande de vérification', 'organization_review' => 'Vérification de l’organisation', 'admin_manual' => 'Attribution manuelle'])
        @php($approvalLog = \App\Models\AdminAuditLog::where('subject_type', 'user')->where('subject_id', $user->id)->whereIn('action', ['user.badge_granted', 'user.badge_revoked'])->latest('created_at')->limit(5)->get())
        <section class="card p-5 sm:p-6" aria-labelledby="approval-title">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 id="approval-title" class="card-title">Badge « Approuvé »</h2>
                @if($user->isApproved())
                <span class="badge-validated"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ $user->isOrganization() ? 'Organisation approuvée' : 'Compte approuvé' }}</span>
                @else
                <span class="badge-neutral">Sans badge</span>
                @endif
            </div>
            @if($user->isApproved())
            <p class="mb-4 text-sm text-slate-600">
                Depuis le {{ $user->verified_at?->format('d/m/Y') ?? '—' }}@if($user->verifier) · par {{ $user->verifier->display_name }}@endif
                · {{ $approvalSources[$user->approval_source] ?? 'Origine non renseignée' }}
            </p>
            <form id="approval-revoke-form" method="POST" action="{{ route('admin.users.approval.revoke', $user->id) }}" class="space-y-3" data-loading-label="Retrait…">
                @csrf @method('DELETE')
                <div>
                    <label for="approval-revoke-reason" class="form-label">Motif du retrait *</label>
                    <textarea id="approval-revoke-reason" name="reason" rows="2" required minlength="3" maxlength="500" class="form-input resize-none">{{ old('reason') }}</textarea>
                    <p class="form-hint">Communiqué à la personne et consigné dans le journal d’audit.</p>
                    @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button type="button" class="btn-secondary btn-sm text-error-700"
                        onclick="openConfirmModal('approval-revoke-form', 'Le badge sera retiré et la personne prévenue avec le motif.', 'Retirer le badge « Approuvé »', 'Retirer le badge', 'fa-circle-minus')">
                    Retirer le badge
                </button>
            </form>
            @else
            <form id="approval-grant-form" method="POST" action="{{ route('admin.users.approval.grant', $user->id) }}" class="space-y-3" data-loading-label="Attribution…">
                @csrf
                <div>
                    <label for="approval-grant-reason" class="form-label">Motif de l’attribution *</label>
                    <textarea id="approval-grant-reason" name="reason" rows="2" required minlength="3" maxlength="500" class="form-input resize-none" placeholder="Ex. : pasteur connu de l’équipe, identité vérifiée lors de…">{{ old('reason') }}</textarea>
                    <p class="form-hint">Consigné dans le journal d’audit. La personne est prévenue.</p>
                    @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button type="button" class="btn-primary btn-sm"
                        onclick="openConfirmModal('approval-grant-form', @js('« ' . $user->display_name . ' » recevra le badge « Approuvé », visible sur son profil et ses publications.'), 'Attribuer le badge « Approuvé »', 'Attribuer le badge', 'fa-circle-check')">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>Attribuer le badge Approuvé
                </button>
            </form>
            @endif
            @if($approvalLog->isNotEmpty())
            <ul class="mt-4 divide-y divide-slate-100 border-t border-slate-100 text-sm">
                @foreach($approvalLog as $log)
                <li class="py-2">
                    <p class="text-slate-900"><span class="font-medium">{{ $log->label() }}</span> · {{ $log->actor_name }}</p>
                    @if($log->reason)<p class="text-slate-600">« {{ $log->reason }} »</p>@endif
                    <p class="text-xs text-slate-400">{{ $log->created_at->format('d/m/Y à H:i') }}</p>
                </li>
                @endforeach
            </ul>
            @endif
        </section>

        {{-- Types de témoignages autorisés pour ce profil ─ docs/fonctionnalites/types-de-temoignages.md --}}
        @isset($publishing)
        <section class="card p-5 sm:p-6" aria-labelledby="publishing-title">
            <h2 id="publishing-title" class="card-title">{{ __('publishing.admin.profile_title') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('publishing.admin.profile_help') }}</p>
            <form method="POST" action="{{ route('admin.users.publishing.update', $user->id) }}" class="mt-4" data-loading-label="Enregistrement…">
                @csrf @method('PUT')
                <div class="divide-y divide-slate-100">
                    @foreach(\App\Services\TestimonyTypePolicy::TYPES as $ptype)
                    {{-- Pas de bloc @ php…@ endphp ici : la page utilise déjà la forme courte @ php(…). --}}
                    @php($override = $publishing['overrides'][$ptype])
                    @php($choice = $override === null ? 'default' : ($override ? 'allow' : 'deny'))
                    @php($isAllowed = in_array($ptype, $publishing['allowed'], true))
                    <fieldset class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <legend class="sr-only">{{ __('publishing.admin.types.' . $ptype) }}</legend>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900" aria-hidden="true">{{ __('publishing.admin.types.' . $ptype) }}</p>
                            <p class="text-xs text-slate-500">{{ __('publishing.admin.effective', ['state' => $isAllowed ? __('publishing.admin.state_allowed') : __('publishing.admin.state_forbidden')]) }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3 text-sm">
                            @foreach(['default' => __('publishing.admin.default'), 'allow' => __('publishing.admin.allowed'), 'deny' => __('publishing.admin.forbidden')] as $value => $label)
                            <label class="inline-flex items-center gap-1.5">
                                <input type="radio" name="types[{{ $ptype }}]" value="{{ $value }}" @checked($choice === $value)>
                                {{ $label }}
                            </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @endforeach
                </div>
                <div class="mt-4 flex justify-end border-t border-slate-100 pt-4">
                    <button type="submit" class="btn-primary">{{ __('publishing.admin.save') }}</button>
                </div>
            </form>
        </section>
        @endisset

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

        {{-- Notification push de test (super-administrateurs) ─ docs/fonctionnalites/notifications-push.md --}}
        @if(auth()->user()?->isSuperAdmin())
        @php($pushTest = (session('push_test')['user_id'] ?? null) === $user->id ? session('push_test')['result'] : null)
        <section class="card p-5 sm:p-6" aria-labelledby="push-test-title">
            <h2 id="push-test-title" class="card-title">{{ __('layout.nav.notifications') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('notifications.test.help') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('notifications.test.devices', ['count' => $user->deviceTokens()->count()]) }}</p>
            <form method="POST" action="{{ route('admin.users.push-test', $user->id) }}" class="mt-4" data-loading-label="{{ __('notifications.test.sending') }}">
                @csrf
                <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>{{ __('notifications.test.button') }}</button>
            </form>
            @if($pushTest)
            <div class="mt-5 border-t border-slate-100 pt-4">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('notifications.test.result_title') }}</h3>
                @if($pushTest['devices'] === [])
                <p class="mt-1 text-sm text-slate-600">{{ $pushTest['disabled'] ? __('notifications.test.disabled') : __('notifications.test.no_device') }}</p>
                @else
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @foreach($pushTest['devices'] as $device)
                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2">
                        <span class="min-w-0">
                            <span class="font-medium text-slate-900">{{ $device['platform'] ?? '—' }}</span>
                            <span class="ml-1 font-mono text-xs break-all text-slate-500">{{ $device['token'] }}</span>
                        </span>
                        <span class="{{ $device['result'] === 'sent' ? 'badge-validated' : 'badge-rejected' }}">{{ __('notifications.test.results.' . $device['result']) }}</span>
                        @if($device['error'])
                        <span class="w-full text-xs break-words text-error-700">{{ $device['error'] }}</span>
                        @endif
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
            @endif
        </section>
        @endif

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

        {{-- Suppression définitive (super-administrateurs) ─ docs/fonctionnalites/confidentialite-et-suppression.md --}}
        @php($deleteBlocked = match (true) {
            !auth()->user()->isSuperAdmin()                  => 'Réservé aux super-administrateurs.',
            auth()->id() === $user->id                       => __('admin.flash.own_account_delete'),
            !\App\Services\AccountDeletion::allowed($user)   => 'Un compte super-administrateur ne peut pas être supprimé.',
            default                                          => null,
        })
        <section class="card p-5 sm:p-6" aria-labelledby="user-delete-title">
            <h2 id="user-delete-title" class="card-title">Supprimer le compte</h2>
            <p class="mt-1 text-sm text-slate-600">Le compte et toutes ses données sont supprimés définitivement : profil, témoignages et vidéos, sujets et sessions de prière, paroles prophétiques, événements, fichiers envoyés. Cette action est irréversible.</p>
            @if($deleteBlocked)
            <p class="mt-3 text-sm text-slate-500">{{ $deleteBlocked }}</p>
            @else
            <form id="user-delete-form" method="POST" action="{{ route('admin.users.destroy', $user->id) }}" class="mt-4" data-loading-label="Suppression…">
                @csrf @method('DELETE')
                <button type="button" class="btn-secondary text-error-700"
                        onclick="openConfirmModal('user-delete-form', @js('Le compte « ' . $user->display_name . ' » et ses ' . $user->testimony_count . ' témoignage(s) seront supprimés définitivement, avec toutes ses données. Cette action est irréversible.'), 'Supprimer ce compte', 'Supprimer définitivement', 'fa-trash-can')">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>Supprimer le compte
                </button>
            </form>
            @endif
        </section>
    </div>
</div>
@endsection
