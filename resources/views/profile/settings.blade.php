@extends('layouts.app')
@section('title', __('profile.settings_page.title'))
@php
    $header      = __('profile.settings_page.title');
    $subheader   = __('profile.settings_page.subheader');
    $breadcrumbs = [
        ['label' => __('profile.breadcrumb.home'), 'url' => route('home')],
        ['label' => __('profile.settings_page.breadcrumb')],
    ];
    $pushOptions = [
        ['push_comments', __('profile.settings_page.push.push_comments')],
        ['push_likes', __('profile.settings_page.push.push_likes')],
        ['push_prayers', __('profile.settings_page.push.push_prayers')],
        ['push_approval', __('profile.settings_page.push.push_approval')],
        ['push_new_followed', __('profile.settings_page.push.push_new_followed')],
        ['push_follows', __('profile.settings_page.push.push_follows')],
        ['push_events', __('profile.settings_page.push.push_events')],
        ['push_account', __('profile.settings_page.push.push_account')],
    ];
    // Équipe de modération seulement : témoignages renvoyés, vidéos bloquées (docs/fonctionnalites/notifications-push.md)
    if (auth()->user()?->canModerate()) {
        $pushOptions[] = ['push_moderation', __('profile.settings_page.push.push_moderation')];
    }
@endphp

@section('content')
<form method="POST" action="{{ route('profile.settings.update') }}" class="mx-auto max-w-2xl space-y-6" data-loading-label="{{ __('profile.edit.saving') }}">
    @csrf @method('PUT')

    <section class="card">
        <h2 class="card-title border-b border-slate-100 px-5 py-4">{{ __('profile.settings_page.privacy') }}</h2>
        <div class="divide-y divide-slate-100">
            <div class="flex items-center justify-between gap-4 px-5 py-4">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900">{{ __('profile.settings_page.private_account') }}</p>
                    <p class="text-sm text-slate-500">{{ __('profile.settings_page.private_account_hint') }}</p>
                </div>
                @include('components.switch', ['name' => 'is_private_account', 'checked' => (bool) $settings->is_private_account, 'label' => __('profile.settings_page.private_account')])
            </div>
            <div class="px-5 py-4">
                <p class="mb-2 text-sm font-medium text-slate-900">{{ __('profile.settings_page.who_can_comment') }}</p>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @foreach(['everyone' => __('profile.settings_page.comment_permission.everyone'), 'followers' => __('profile.settings_page.comment_permission.followers'), 'nobody' => __('profile.settings_page.comment_permission.nobody')] as $val => $label)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="comment_permission" value="{{ $val }}" @checked(($settings->comment_permission ?? 'everyone') === $val)>
                        {{ $label }}
                    </label>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <h2 class="card-title border-b border-slate-100 px-5 py-4">{{ __('profile.settings_page.push_title') }}</h2>
        <div class="divide-y divide-slate-100">
            @foreach($pushOptions as [$key, $label])
            <div class="flex items-center justify-between gap-4 px-5 py-3">
                <span class="text-sm text-slate-700">{{ $label }}</span>
                {{-- sendFalse : décocher enregistre bien le refus (consentement aux push) --}}
                @include('components.switch', ['name' => $key, 'checked' => (bool) ($settings->$key ?? true), 'label' => $label, 'sendFalse' => true])
            </div>
            @endforeach
        </div>
    </section>

    {{-- Pas de choix de thème (Clair / Sombre / Système) : l'application n'a pas de palette sombre.
         La colonne user_settings.app_theme reste, inutilisée. --}}

    <div class="flex justify-end">
        <button type="submit" class="btn-primary">{{ __('profile.settings_page.submit') }}</button>
    </div>
</form>

{{-- Localisation (classement de « Mon fil » par zone) : docs/fonctionnalites/localisation.md --}}
@include('profile.partials.location')

{{-- ── Administrateurs du compte (docs/fonctionnalites/administrateurs-de-compte.md) ─ --}}
{{-- Hors du formulaire des paramètres : chaque action a son propre formulaire. --}}
@php
    $maxManagers  = \App\Services\AccountDelegation::MAX_MANAGERS;
    $managersFull = $orgManagers->count() >= $maxManagers;
    $settingsUser = Auth::user();
@endphp
<section class="card mx-auto mt-6 max-w-2xl scroll-mt-24" id="administrateurs" aria-labelledby="account-managers-title">
    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 px-5 py-4">
        <h2 id="account-managers-title" class="card-title">{{ __('profile.managers.title') }}</h2>
        @unless($isStaff)
        <span class="text-xs text-slate-500">{{ __('profile.managers.max', ['count' => $maxManagers]) }}</span>
        @endunless
    </div>
    <div class="space-y-4 px-5 py-4">
        @if($isStaff)
        <div class="alert-info">
            <i class="fa-solid fa-shield-halved mt-0.5" aria-hidden="true"></i>
            <p>{{ __('profile.managers.staff') }}</p>
        </div>
        @else
        <p class="text-sm text-slate-600">{{ __('profile.managers.intro', ['count' => $maxManagers]) }}</p>
        <ul class="space-y-1 text-sm text-slate-600">
            <li class="flex gap-2"><i class="fa-solid fa-lock mt-1 w-4 shrink-0 text-center text-primary-600" aria-hidden="true"></i><span>{{ __('profile.managers.no_credentials') }}</span></li>
            <li class="flex gap-2"><i class="fa-solid fa-user-lock mt-1 w-4 shrink-0 text-center text-primary-600" aria-hidden="true"></i><span>{{ __('profile.managers.no_managers') }}</span></li>
            <li class="flex gap-2"><i class="fa-solid fa-book-open mt-1 w-4 shrink-0 text-center text-primary-600" aria-hidden="true"></i><span>{{ __('profile.managers.no_journal') }}</span></li>
        </ul>
        @if($settingsUser->isOrganization() && !$settingsUser->isVerifiedOrganization())
        <div class="alert-warning">
            <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
            <p>{{ __('profile.managers.org_unverified') }}</p>
        </div>
        @endif

        @if($orgManagers->isEmpty())
        <p class="text-sm text-slate-500">{{ __('profile.managers.empty') }}</p>
        @else
        <ul class="divide-y divide-slate-100">
            @foreach($orgManagers as $orgManager)
            <li class="flex items-center gap-3 py-2.5">
                @include('components.avatar', ['user' => $orgManager, 'size' => 'sm'])
                <span class="min-w-0 flex-1">
                    <a href="{{ route('profiles.show', $orgManager->id) }}" class="block truncate text-sm font-semibold text-slate-900 hover:underline">{{ $orgManager->display_name }}</a>
                    @if($orgManager->pivot?->created_at)
                    <span class="block text-xs text-slate-500">{{ __('profile.managers.since', ['date' => $orgManager->pivot->created_at->translatedFormat(__('profile.date_format'))]) }}</span>
                    @endif
                </span>
                <form id="account-manager-remove-{{ $orgManager->id }}" method="POST" action="{{ route('profile.managers.destroy', $orgManager->id) }}" data-loading-label="{{ __('profile.managers.removing') }}">
                    @csrf @method('DELETE')
                    <button type="button" class="btn-ghost btn-sm shrink-0"
                            onclick="openConfirmModal('account-manager-remove-{{ $orgManager->id }}', @js(__('profile.managers.remove_confirm', ['name' => $orgManager->display_name])), @js(__('profile.managers.remove_title')), @js(__('profile.managers.remove')), 'fa-user-minus')">
                        {{ __('profile.managers.remove') }}
                    </button>
                </form>
            </li>
            @endforeach
        </ul>
        @endif

        <div class="border-t border-slate-100 pt-4">
            @if($managersFull)
            <p class="text-sm text-slate-500">{{ __('profile.managers.full', ['count' => $maxManagers]) }}</p>
            @else
            @include('components.user-picker', [
                'id'      => 'account-managers',
                'label'   => __('profile.managers.add'),
                'addUrl'  => route('profile.managers.store'),
                'exclude' => [Auth::id(), ...$orgManagers->pluck('id')],
                'results' => $pickerResults,
                'anchor'  => 'administrateurs',
            ])
            @endif
        </div>

        {{-- Journal : 20 dernières actions faites au nom du compte --}}
        <div class="scroll-mt-24 border-t border-slate-100 pt-4" id="journal-des-actions">
            <h3 class="text-sm font-semibold text-primary-700">{{ __('profile.managers.log_title') }}</h3>
            @if($delegateLog->isEmpty())
            <p class="mt-1 text-sm text-slate-500">{{ __('profile.managers.log_empty') }}</p>
            @else
            <p class="mt-1 text-xs text-slate-500">{{ __('profile.managers.log_intro') }}</p>
            <ul class="mt-2 divide-y divide-slate-100">
                @foreach($delegateLog as $logEntry)
                <li class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 py-2 text-sm">
                    <span class="min-w-0">
                        <span class="font-semibold text-slate-900">{{ $logEntry->actor_name }}</span>
                        <span class="text-slate-600">· {{ \App\Http\Controllers\Api\OrganizationManagerController::label($logEntry) }}</span>
                    </span>
                    @if($logEntry->created_at)
                    <time datetime="{{ $logEntry->created_at->toIso8601String() }}" title="{{ $logEntry->created_at->translatedFormat(__('verification.date_time_format')) }}" class="shrink-0 text-xs text-slate-500">{{ $logEntry->created_at->diffForHumans() }}</time>
                    @endif
                </li>
                @endforeach
            </ul>
            @endif
        </div>
        @endif
    </div>
</section>

@if($managedOrgs->isNotEmpty())
<section class="card mx-auto mt-6 max-w-2xl scroll-mt-24" id="comptes-administres" aria-labelledby="managed-accounts-title">
    <h2 id="managed-accounts-title" class="card-title border-b border-slate-100 px-5 py-4">{{ __('profile.managed.title') }}</h2>
    <div class="px-5 py-4">
        <p class="text-sm text-slate-600">{{ __('profile.managed.intro') }}</p>
        <ul class="mt-3 divide-y divide-slate-100">
            @foreach($managedOrgs as $org)
            @php
                $orgName = $org->organization_name ?: $org->display_name;
                $canAct  = \App\Services\AccountDelegation::canActFor($settingsUser, $org);
            @endphp
            <li class="flex flex-wrap items-center gap-3 py-2.5">
                @include('components.avatar', ['user' => $org, 'size' => 'sm'])
                <span class="flex min-w-0 flex-1 items-center gap-1.5">
                    <a href="{{ route('profiles.show', $org->id) }}" class="truncate text-sm font-semibold text-slate-900 hover:underline">{{ $orgName }}</a>
                    @include('components.verified-badge', ['user' => $org])
                </span>
                <span class="flex flex-wrap items-center gap-2">
                    @if($canAct)
                    <form method="POST" action="{{ route('accounts.switch', $org->id) }}" data-loading-label="{{ __('layout.accounts.switching') }}">
                        @csrf
                        <button type="submit" class="btn-secondary btn-sm shrink-0"><i class="fa-solid fa-right-left" aria-hidden="true"></i>{{ __('profile.managed.act') }}</button>
                    </form>
                    @else
                    <span class="text-xs text-slate-500">{{ __('profile.managed.unavailable') }}</span>
                    @endif
                    <form id="managed-account-leave-{{ $org->id }}" method="POST" action="{{ route('profile.managed.leave', $org->id) }}" data-loading-label="{{ __('profile.managers.removing') }}">
                        @csrf @method('DELETE')
                        <button type="button" class="btn-ghost btn-sm shrink-0"
                                onclick="openConfirmModal('managed-account-leave-{{ $org->id }}', @js(__('profile.managed.leave_confirm', ['name' => $orgName])), @js(__('profile.managed.leave')), @js(__('profile.managed.leave')), 'fa-user-minus')">
                            {{ __('profile.managed.leave') }}
                        </button>
                    </form>
                </span>
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

{{-- Supprimer le compte : page publique, aussi utilisée sans connexion (docs/fonctionnalites/confidentialite-et-suppression.md) --}}
<section class="card mx-auto mt-6 max-w-2xl">
    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h2 class="card-title">{{ __('privacy.delete_link') }}</h2>
            <p class="text-sm text-slate-500">{{ __('privacy.deletion.irreversible') }}</p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <a href="{{ \App\Services\PrivacyPolicy::url(app()->getLocale()) }}" class="btn-ghost btn-sm">{{ __('privacy.link') }}</a>
            <a href="{{ route(app()->getLocale() === 'en' ? 'account-deletion.en' : 'account-deletion') }}" class="btn-secondary btn-sm"><i class="fa-solid fa-trash-can text-error-600" aria-hidden="true"></i>{{ __('privacy.delete_link') }}</a>
        </div>
    </div>
</section>
@endsection
