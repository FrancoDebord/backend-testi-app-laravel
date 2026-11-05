@extends('layouts.app')
@section('title', $user->display_name)
@php
    $header      = $user->display_name;
    $subheader   = __('admin.users.subheader_show');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.administration'), 'url' => route('admin.dashboard')],
        ['label' => __('layout.nav.users'), 'url' => route('admin.users.index')],
        ['label' => $user->display_name],
    ];
    $roles    = collect(\App\Enums\UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all();
    $statuses = collect(\App\Enums\UserAccountStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
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
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ __('admin.common.status') }}</dt><dd><span class="{{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span></dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ __('admin.common.role') }}</dt><dd class="text-slate-900">{{ $user->role->label() }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ __('admin.users.country') }}</dt><dd class="text-right text-slate-900">{{ $user->country ?: '—' }}</dd></div>
            {{-- Téléphone de contact, pour les vérifications (docs/fonctionnalites/telephone.md) --}}
            <div class="flex justify-between gap-4 py-2">
                <dt class="text-slate-500">{{ __('admin.users.phone') }}</dt>
                <dd class="min-w-0 text-right text-slate-900">
                    @if($user->phone)
                        <a href="tel:{{ $user->phone }}" class="tabular-nums hover:underline">{{ \App\Support\PhoneNumber::display($user->phone, $user->phone_country) }}</a>
                        <span class="mt-1 block"><span class="{{ $user->hasVerifiedPhone() ? 'badge-validated' : 'badge-neutral' }}">{{ $user->hasVerifiedPhone() ? __('admin.users.phone_verified') : __('admin.users.phone_unverified') }}</span></span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ __('admin.common.testimonies') }}</dt><dd class="text-slate-900">{{ $user->testimony_count }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ __('admin.users.followers') }}</dt><dd class="text-slate-900">{{ $user->follower_count }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ __('admin.users.likes_received') }}</dt><dd class="text-slate-900">{{ $user->like_count }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ __('admin.common.registered_on') }}</dt><dd class="text-slate-900">{{ $user->created_at->format(__('admin.formats.date')) }}</dd></div>
        </dl>
    </section>

    <div class="min-w-0 space-y-6 lg:col-span-2">
        {{-- Compte organisation : docs/fonctionnalites/comptes-organisation.md --}}
        @if($user->isOrganization())
        <section class="card p-5 sm:p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title">{{ __('admin.common.organization') }}</h2>
                @if($user->verification_status)
                <span class="{{ $user->verification_status->badgeClass() }}">{{ $user->verification_status->label() }}</span>
                @endif
            </div>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div class="min-w-0"><dt class="text-slate-500">{{ __('admin.users.org_name') }}</dt><dd class="break-words text-slate-900">{{ $user->organization_name ?: '—' }}</dd></div>
                <div class="min-w-0"><dt class="text-slate-500">{{ __('admin.common.type') }}</dt><dd class="text-slate-900">{{ $user->organization_type?->label() ?? '—' }}</dd></div>
                <div class="min-w-0"><dt class="text-slate-500">{{ __('admin.users.org_city') }}</dt><dd class="break-words text-slate-900">{{ $user->organization_city ?: '—' }}</dd></div>
                <div class="min-w-0">
                    <dt class="text-slate-500">{{ __('admin.users.org_website') }}</dt>
                    <dd class="break-all text-slate-900">
                        @if($user->organization_website)
                        <a href="{{ $user->organization_website }}" target="_blank" rel="noopener noreferrer nofollow" class="hover:underline">{{ $user->organization_website }} <i class="fa-solid fa-arrow-up-right-from-square text-xs text-slate-400" aria-hidden="true"></i></a>
                        @else — @endif
                    </dd>
                </div>
                @if($user->verified_at)
                <div class="min-w-0"><dt class="text-slate-500">{{ __('admin.users.org_verified_on') }}</dt><dd class="text-slate-900">{{ $user->verified_at->format(__('admin.formats.datetime')) }}@if($user->verifier) {{ __('admin.common.by', ['name' => $user->verifier->display_name]) }}@endif</dd></div>
                @endif
                @if($user->verification_note)
                <div class="min-w-0 sm:col-span-2"><dt class="text-slate-500">{{ __('admin.common.rejection_reason') }}</dt><dd class="break-words text-slate-900">{{ __('admin.common.quoted', ['text' => $user->verification_note]) }}</dd></div>
                @endif
            </dl>

            <div class="mt-5 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                @unless($user->isVerified())
                <form id="org-verify-form" method="POST" action="{{ route('admin.users.verify', $user->id) }}" class="flex flex-col" data-loading-label="{{ __('admin.common.verifying') }}">
                    @csrf
                    <p class="mb-3 text-sm text-slate-600">{{ __('admin.users.org_verify_hint') }}</p>
                    <button type="button" class="btn-primary mt-auto w-full"
                            onclick="openConfirmModal('org-verify-form', @js(__('admin.users.org_verify_confirm', ['name' => $user->organization_name ?? $user->display_name])), @js(__('admin.users.org_verify_title')), @js(__('admin.common.verify')), 'fa-circle-check')">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('admin.common.verify') }}
                    </button>
                </form>
                @endunless

                @if($user->verification_status?->value !== 'rejected')
                <form id="org-reject-form" method="POST" action="{{ route('admin.users.reject-verification', $user->id) }}" class="flex flex-col gap-3" data-loading-label="{{ __('admin.common.saving_rejection') }}">
                    @csrf
                    <div>
                        <label for="reason" class="form-label">{{ __('admin.users.org_reject_reason') }}</label>
                        <textarea id="reason" name="reason" rows="2" maxlength="255" class="form-input resize-none"
                                  placeholder="{{ __('admin.users.org_reject_placeholder') }}">{{ old('reason') }}</textarea>
                        @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <button type="button" class="btn-secondary mt-auto w-full text-red-700"
                            onclick="openConfirmModal('org-reject-form', @js(($user->isVerified() ? __('admin.users.org_badge_removed') : '') . __('admin.users.org_reject_confirm')), @js(__('admin.users.org_reject_title')), @js(__('admin.users.org_reject_button')), 'fa-xmark')">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>{{ $user->isVerified() ? __('admin.common.remove_verification') : __('admin.common.reject') }}
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
                <h2 class="card-title">{{ __('admin.users.account_verification') }}</h2>
                @if($user->isVerified())
                <span class="badge-validated">{{ __('admin.common.verified_account') }}</span>
                @elseif($lastVerification)
                <span class="{{ $lastVerification->statusBadgeClass() }}">{{ $lastVerification->statusLabel() }}</span>
                @endif
            </div>
            @if($lastVerification)
            <p class="mb-4 text-sm text-slate-600">{{ __('admin.users.last_request', ['date' => $lastVerification->created_at->format(__('admin.formats.date')), 'role' => $lastVerification->roleLabel()]) }} <a href="{{ route('admin.verifications.show', $lastVerification->id) }}" class="font-semibold text-primary-600 hover:underline">{{ __('admin.users.view_request') }}</a></p>
            @endif
            @if($user->isVerified())
            @include('admin.verifications.partials.revoke-form', ['account' => $user])
            @endif
        </section>
        @endif
        @endif

        {{-- Badge « Approuvé » : attribution ou retrait manuel, motif obligatoire (docs/fonctionnalites/badge-approuve.md) --}}
        @php($approvalSources = ['verification_form' => __('admin.users.source_verification_form'), 'organization_review' => __('admin.users.source_organization_review'), 'admin_manual' => __('admin.users.source_admin_manual')])
        @php($approvalLog = \App\Models\AdminAuditLog::where('subject_type', 'user')->where('subject_id', $user->id)->whereIn('action', ['user.badge_granted', 'user.badge_revoked'])->latest('created_at')->limit(5)->get())
        <section class="card p-5 sm:p-6" aria-labelledby="approval-title">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 id="approval-title" class="card-title">{{ __('admin.users.approval_title') }}</h2>
                @if($user->isApproved())
                <span class="badge-validated"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ $user->isOrganization() ? __('admin.users.org_approved') : __('admin.users.account_approved') }}</span>
                @else
                <span class="badge-neutral">{{ __('admin.users.no_badge') }}</span>
                @endif
            </div>
            @if($user->isApproved())
            <p class="mb-4 text-sm text-slate-600">
                {{ __('admin.users.approved_since', ['date' => $user->verified_at?->format(__('admin.formats.date')) ?? '—']) }}@if($user->verifier) · {{ __('admin.common.by', ['name' => $user->verifier->display_name]) }}@endif
                · {{ $approvalSources[$user->approval_source] ?? __('admin.users.unknown_source') }}
            </p>
            <form id="approval-revoke-form" method="POST" action="{{ route('admin.users.approval.revoke', $user->id) }}" class="space-y-3" data-loading-label="{{ __('admin.common.removing') }}">
                @csrf @method('DELETE')
                <div>
                    <label for="approval-revoke-reason" class="form-label">{{ __('admin.common.removal_reason') }}</label>
                    <textarea id="approval-revoke-reason" name="reason" rows="2" required minlength="3" maxlength="500" class="form-input resize-none">{{ old('reason') }}</textarea>
                    <p class="form-hint">{{ __('admin.users.revoke_hint') }}</p>
                    @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button type="button" class="btn-secondary btn-sm text-error-700"
                        onclick="openConfirmModal('approval-revoke-form', @js(__('admin.users.revoke_confirm')), @js(__('admin.users.revoke_title')), @js(__('admin.users.revoke_button')), 'fa-circle-minus')">
                    {{ __('admin.users.revoke_button') }}
                </button>
            </form>
            @else
            <form id="approval-grant-form" method="POST" action="{{ route('admin.users.approval.grant', $user->id) }}" class="space-y-3" data-loading-label="{{ __('admin.users.granting') }}">
                @csrf
                <div>
                    <label for="approval-grant-reason" class="form-label">{{ __('admin.users.grant_reason') }}</label>
                    <textarea id="approval-grant-reason" name="reason" rows="2" required minlength="3" maxlength="500" class="form-input resize-none" placeholder="{{ __('admin.users.grant_placeholder') }}">{{ old('reason') }}</textarea>
                    <p class="form-hint">{{ __('admin.users.grant_hint') }}</p>
                    @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button type="button" class="btn-primary btn-sm"
                        onclick="openConfirmModal('approval-grant-form', @js(__('admin.users.grant_confirm', ['name' => $user->display_name])), @js(__('admin.users.grant_title')), @js(__('admin.users.grant_button_short')), 'fa-circle-check')">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('admin.users.grant_button') }}
                </button>
            </form>
            @endif
            @if($approvalLog->isNotEmpty())
            <ul class="mt-4 divide-y divide-slate-100 border-t border-slate-100 text-sm">
                @foreach($approvalLog as $log)
                <li class="py-2">
                    <p class="text-slate-900"><span class="font-medium">{{ $log->label() }}</span> · {{ $log->actor_name }}</p>
                    @if($log->reason)<p class="text-slate-600">{{ __('admin.common.quoted', ['text' => $log->reason]) }}</p>@endif
                    <p class="text-xs text-slate-400">{{ $log->created_at->format(__('admin.formats.datetime')) }}</p>
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
            <form method="POST" action="{{ route('admin.users.publishing.update', $user->id) }}" class="mt-4" data-loading-label="{{ __('admin.common.saving') }}">
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
            <h2 class="card-title mb-4">{{ __('admin.users.edit_account') }}</h2>
            <form id="user-update-form" method="POST" action="{{ route('admin.users.update', $user->id) }}" data-loading-label="{{ __('admin.common.saving') }}">
                @csrf @method('PUT')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="role" class="form-label">{{ __('admin.common.role') }}</label>
                        <select id="role" name="role" class="form-input">
                            @foreach($roles as $v => $l)
                            <option value="{{ $v }}" @selected(old('role', $user->role->value) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                        @error('role')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="status" class="form-label">{{ __('admin.common.status') }}</label>
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
                            onclick="openConfirmModal('user-update-form', @js(__('admin.users.update_confirm', ['name' => $user->display_name])), @js(__('admin.users.update_title')), @js(__('admin.common.save')), 'fa-user-pen')">
                        {{ __('admin.common.save') }}
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
            <h2 class="card-title border-b border-slate-100 px-5 py-4">{{ __('admin.common.recent_testimonies') }}</h2>
            <ul class="divide-y divide-slate-100">
                @forelse($testimonies as $t)
                <li class="flex items-center gap-3 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('testimonies.show', $t->id) }}" class="block truncate text-sm font-medium text-slate-900 hover:underline">{{ $t->title }}</a>
                        <p class="text-xs text-slate-500">{{ $t->created_at->format(__('admin.formats.date')) }}</p>
                    </div>
                    <span class="{{ $t->status->badgeClass() }}">{{ $t->status->label() }}</span>
                </li>
                @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">{{ __('admin.common.no_testimony') }}</li>
                @endforelse
            </ul>
        </section>

        {{-- Suppression définitive (super-administrateurs) ─ docs/fonctionnalites/confidentialite-et-suppression.md --}}
        @php($deleteBlocked = match (true) {
            !auth()->user()->isSuperAdmin()                  => __('admin.users.delete_super_admin_only'),
            auth()->id() === $user->id                       => __('admin.flash.own_account_delete'),
            !\App\Services\AccountDeletion::allowed($user)   => __('admin.users.delete_super_admin_forbidden'),
            default                                          => null,
        })
        <section class="card p-5 sm:p-6" aria-labelledby="user-delete-title">
            <h2 id="user-delete-title" class="card-title">{{ __('admin.users.delete_heading') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('admin.users.delete_text') }}</p>
            @if($deleteBlocked)
            <p class="mt-3 text-sm text-slate-500">{{ $deleteBlocked }}</p>
            @else
            <form id="user-delete-form" method="POST" action="{{ route('admin.users.destroy', $user->id) }}" class="mt-4" data-loading-label="{{ __('admin.common.deleting') }}">
                @csrf @method('DELETE')
                <button type="button" class="btn-secondary text-error-700"
                        onclick="openConfirmModal('user-delete-form', @js(__('admin.users.delete_confirm', ['name' => $user->display_name, 'count' => $user->testimony_count])), @js(__('admin.users.delete_title')), @js(__('admin.common.delete_permanently')), 'fa-trash-can')">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>{{ __('admin.users.delete_heading') }}
                </button>
            </form>
            @endif
        </section>
    </div>
</div>
@endsection
