@extends('layouts.app')
@section('title', __('privacy.deletion.title'))
@php
    // Suppression de compte, publique (exigence Google Play) : docs/fonctionnalites/confidentialite-et-suppression.md
    $header      = __('privacy.deletion.title');
    $subheader   = __('privacy.deletion.intro');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('privacy.deletion.breadcrumb')],
    ];
@endphp

@section('content')
<div class="mx-auto max-w-3xl space-y-6" lang="{{ $locale }}">
    <p class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $otherUrl }}" lang="{{ $locale === 'en' ? 'fr' : 'en' }}" class="btn-ghost btn-sm">
            <i class="fa-solid fa-language" aria-hidden="true"></i>{{ __('privacy.deletion.other_language') }}
        </a>
        <a href="{{ $privacyUrl }}" class="text-sm font-semibold text-primary-600 hover:text-primary-700 hover:underline">{{ __('privacy.link') }}</a>
    </p>

    <section class="card p-5 sm:p-6" aria-labelledby="deleted-title">
        <h2 id="deleted-title" class="card-title">{{ __('privacy.deletion.deleted_title') }}</h2>
        <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm text-slate-700">
            @foreach(__('privacy.deletion.deleted') as $line)
            <li>{{ $line }}</li>
            @endforeach
        </ul>

        <h2 class="card-title mt-6">{{ __('privacy.deletion.delays_title') }}</h2>
        <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm text-slate-700">
            @foreach(__('privacy.deletion.delays') as $line)
            <li>{{ $line }}</li>
            @endforeach
        </ul>

        <div class="alert-warning mt-6">
            <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
            <p>{{ __('privacy.deletion.irreversible') }}</p>
        </div>
    </section>

    {{-- Supprimer certaines données sans supprimer le compte --}}
    <section class="card p-5 sm:p-6" aria-labelledby="partial-title">
        <h2 id="partial-title" class="card-title">{{ __('privacy.deletion.partial_title') }}</h2>
        <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm text-slate-700">
            @foreach(__('privacy.deletion.partial_items') as $line)
            <li>{{ $line }}</li>
            @endforeach
            <li>{{ __('privacy.deletion.partial_other', ['email' => __('privacy.deletion.contact_email')]) }}</li>
        </ul>
        <a href="{{ request()->fullUrlWithQuery(['type' => 'partial']) }}#request-title" class="btn-ghost btn-sm mt-4">
            <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>{{ __('privacy.deletion.type_partial') }}
        </a>
    </section>

    @if($user)
    {{-- Connecté : suppression immédiate après la fenêtre de confirmation. --}}
    <section class="card p-5 sm:p-6" aria-labelledby="connected-title">
        <h2 id="connected-title" class="card-title">{{ __('privacy.deletion.connected_title') }}</h2>
        <p class="text-secondary mt-1">{{ __('privacy.deletion.connected_as', ['name' => $user->display_name]) }}</p>

        @if(!$canDelete)
        <div class="alert-info mt-4">
            <i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i>
            <p>{{ __('privacy.deletion.super_admin_forbidden') }}</p>
        </div>
        @else
        <form id="account-delete-form" method="POST" action="{{ route('account-deletion.destroy') }}" class="mt-4 space-y-4">
            @csrf @method('DELETE')
            <input type="hidden" name="lang" value="{{ $locale }}">
            @if(!empty($user->password))
            <div class="max-w-sm">
                <label for="delete-password" class="form-label">{{ __('privacy.deletion.password') }}</label>
                <input id="delete-password" type="password" name="password" required autocomplete="current-password" class="form-input">
                <p class="form-hint">{{ __('privacy.deletion.password_hint') }}</p>
                @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            @else
            <input type="hidden" name="confirm" value="1">
            @endif
            <button type="button" class="btn-secondary w-full sm:w-auto"
                    onclick="openConfirmModal('account-delete-form', @js(__('privacy.deletion.confirm_text')), @js(__('privacy.deletion.confirm_title')), @js(__('privacy.deletion.confirm_label')), 'fa-trash-can')">
                <i class="fa-solid fa-trash-can text-error-600" aria-hidden="true"></i>{{ __('privacy.deletion.submit') }}
            </button>
        </form>
        @endif
    </section>
    @endif

    {{-- Demande (compte entier ou certaines données), traitée par un super-administrateur. Connecté : suppression
         partielle proposée d'abord, le compte entier se supprime tout de suite au-dessus. --}}
    @php($requestType = old('type', request()->query('type') === 'partial' || $user ? 'partial' : 'account'))
    <section class="card p-5 sm:p-6" aria-labelledby="request-title">
        <h2 id="request-title" class="card-title">{{ __('privacy.deletion.request_title') }}</h2>
        <p class="text-secondary mt-1">{{ __('privacy.deletion.request_intro') }}</p>

        <form method="POST" action="{{ route('account-deletion.request') }}" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="lang" value="{{ $locale }}">
            {{-- Champ piège : invisible, laissé vide par les personnes. --}}
            <div class="hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>
            <fieldset>
                <legend class="form-label">{{ __('privacy.deletion.type_legend') }}</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach(['account' => 'fa-user-xmark', 'partial' => 'fa-eraser'] as $type => $icon)
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                        <input type="radio" name="type" value="{{ $type }}" class="mt-1" @checked($requestType === $type)>
                        <span>
                            <span class="block font-medium text-slate-900"><i class="fa-solid {{ $icon }} mr-1 text-slate-400" aria-hidden="true"></i>{{ __('privacy.deletion.type_' . $type) }}</span>
                            <span class="block text-xs text-slate-500">{{ __('privacy.deletion.type_' . $type . '_hint') }}</span>
                        </span>
                    </label>
                    @endforeach
                </div>
                @error('type')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
            <div>
                <label for="contact" class="form-label">{{ __('privacy.deletion.contact') }}</label>
                <input id="contact" type="text" name="contact" value="{{ old('contact', $user?->email ?? $user?->phone) }}" required maxlength="190" autocomplete="email"
                       class="form-input" placeholder="{{ __('privacy.deletion.contact_placeholder') }}">
                @error('contact')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="message" class="form-label">{{ __('privacy.deletion.message') }}</label>
                <textarea id="message" name="message" rows="3" maxlength="1000" class="form-input" aria-describedby="message-hint">{{ old('message') }}</textarea>
                <p id="message-hint" class="form-hint">{{ __('privacy.deletion.message_hint') }}</p>
                @error('message')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full sm:w-auto">{{ __('privacy.deletion.request_submit') }}</button>
        </form>

        @guest
        <p class="mt-6 border-t border-slate-200 pt-4 text-sm text-slate-600">
            {{ __('privacy.deletion.or_login') }}
            <a href="{{ route('login') }}" class="font-semibold text-primary-600 hover:text-primary-700 hover:underline">{{ __('privacy.deletion.login') }}</a>
            {{ __('privacy.deletion.login_suffix') }}
        </p>
        @endguest
    </section>
</div>
@endsection
