@extends('layouts.app')
@section('title', __('profile.show.verification.none'))
@php
    // Vérification des comptes personnels : docs/fonctionnalites/verification-des-comptes.md
    $header      = __('profile.show.verification.none');
    $headerIcon  = 'fa-circle-check';
    $subheader   = __('verification.page.subheader');
    $breadcrumbs = [
        ['label' => __('profile.breadcrumb.home'), 'url' => route('home')],
        ['label' => __('profile.breadcrumb.my_profile'), 'url' => route('profiles.show', Auth::id())],
        ['label' => __('verification.page.breadcrumb')],
    ];
    $status  = $state['status'];
    $request = $state['request'];
    $oldLinks = old('links', $request['links'] ?? []);
@endphp

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    @if($status === 'verified')
    <div class="alert-success" role="status">
        <i class="fa-solid fa-circle-check mt-0.5" aria-hidden="true"></i>
        <p class="min-w-0 break-words"><span class="font-semibold">{{ __('verification.page.verified') }}</span>@if($user->verified_at) {{ __('verification.page.verified_since', ['date' => $user->verified_at->translatedFormat(__('profile.date_format'))]) }}@endif {{ __('verification.page.verified_text') }}</p>
    </div>
    @elseif($status === 'pending')
    <div class="alert-info" role="status">
        <i class="fa-solid fa-hourglass-half mt-0.5" aria-hidden="true"></i>
        <p class="min-w-0 break-words"><span class="font-semibold">{{ __('verification.page.pending') }}</span> {{ __('verification.page.pending_text', ['date' => \Illuminate\Support\Carbon::parse($request['createdAt'])->translatedFormat(__('profile.date_format'))]) }}</p>
    </div>
    @elseif(in_array($status, ['rejected', 'revoked'], true))
    <div class="alert-warning" role="status">
        <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
        <p class="min-w-0 break-words"><span class="font-semibold">{{ $status === 'revoked' ? __('verification.api.revoked') : __('verification.api.rejected') }}</span>@if($state['reason']){{ __('profile.show.rejected_note', ['note' => rtrim($state['reason'], ' .')]) }}@endif.
            @if($state['canRequest']) {{ __('verification.page.can_request') }}@elseif($state['blockReason']) {{ $state['blockReason'] }}@endif</p>
    </div>
    @endif

    @if($state['canRequest'])
    <section class="card p-5 sm:p-6" aria-labelledby="verification-why">
        <h2 id="verification-why" class="card-title mb-2">{{ __('verification.page.why_title') }}</h2>
        <p class="text-sm text-slate-600">{{ __('verification.page.why_text') }}</p>
    </section>

    @if($errors->any())
    <div class="alert-error" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
        <p>{{ __('verification.page.failed') }}</p>
    </div>
    @endif

    <form method="POST" action="{{ route('profile.verification.store') }}" enctype="multipart/form-data" class="card space-y-5 p-5 sm:p-6"
          data-loading-label="{{ __('verification.page.sending') }}">
        @csrf
        <div>
            <label for="full_name" class="form-label">{{ __('verification.page.full_name') }}</label>
            <input id="full_name" type="text" name="full_name" required minlength="3" maxlength="150" autocomplete="name"
                   value="{{ old('full_name', $request['fullName'] ?? $user->display_name) }}" class="form-input">
            <p class="form-hint">{{ __('verification.page.full_name_hint') }}</p>
            @error('full_name')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="min-w-0">
                <label for="role" class="form-label">{{ __('verification.page.role') }}</label>
                <select id="role" name="role" required class="form-input">
                    <option value="">{{ __('profile.edit.choose') }}</option>
                    @foreach($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $request['role'] ?? null) === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                @error('role')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="min-w-0">
                <label for="role_other" class="form-label">{{ __('verification.page.role_other') }}</label>
                <input id="role_other" type="text" name="role_other" maxlength="100" value="{{ old('role_other', $request['roleOther'] ?? '') }}" class="form-input" placeholder="{{ __('verification.page.role_other_placeholder') }}">
                @error('role_other')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="church" class="form-label">{{ __('verification.page.church') }}</label>
            <input id="church" type="text" name="church" required minlength="2" maxlength="150"
                   value="{{ old('church', $request['church'] ?? '') }}" class="form-input" placeholder="{{ __('verification.page.church_placeholder') }}">
            @error('church')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <fieldset class="space-y-2">
            <legend class="form-label">{{ __('verification.page.links') }}</legend>
            <p class="form-hint">{{ __('verification.page.links_hint') }}</p>
            @for($i = 0; $i < \App\Services\PersonalVerification::MAX_LINKS; $i++)
            <label for="link-{{ $i }}" class="sr-only">{{ __('verification.page.link_n', ['n' => $i + 1]) }}</label>
            <input id="link-{{ $i }}" type="url" name="links[]" maxlength="255" value="{{ $oldLinks[$i] ?? '' }}" class="form-input" placeholder="https://">
            @error('links.' . $i)<p class="form-error">{{ $message }}</p>@enderror
            @endfor
            @error('links')<p class="form-error">{{ $message }}</p>@enderror
        </fieldset>

        <div>
            <label for="documents" class="form-label">{{ __('verification.page.documents') }}</label>
            <input id="documents" type="file" name="documents[]" multiple required accept="image/jpeg,image/png,image/webp,application/pdf" class="form-input" aria-describedby="documents-hint">
            <p id="documents-hint" class="form-hint">{{ __('verification.page.documents_hint') }}
                <span class="block"><i class="fa-solid fa-lock text-xs" aria-hidden="true"></i> {{ __('verification.page.documents_private') }}</span></p>
            @error('documents')<p class="form-error">{{ $message }}</p>@enderror
            @foreach($errors->get('documents.*') as $messages)
            <p class="form-error">{{ $messages[0] }}</p>
            @endforeach
        </div>

        <div>
            <label for="message" class="form-label">{{ __('verification.page.message') }}</label>
            <textarea id="message" name="message" rows="3" maxlength="1000" class="form-input" placeholder="{{ __('verification.page.message_placeholder') }}">{{ old('message') }}</textarea>
            @error('message')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-5">
            <a href="{{ route('profiles.show', Auth::id()) }}" class="btn-secondary">{{ __('profile.cancel') }}</a>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>{{ __('verification.page.submit') }}</button>
        </div>
    </form>
    @elseif($status === 'pending' && $request)
    <section class="card p-5 sm:p-6" aria-labelledby="verification-sent">
        <h2 id="verification-sent" class="card-title mb-4">{{ __('verification.page.your_request') }}</h2>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <div class="min-w-0"><dt class="text-slate-500">{{ __('verification.page.full_name_label') }}</dt><dd class="break-words text-slate-900">{{ $request['fullName'] }}</dd></div>
            <div class="min-w-0"><dt class="text-slate-500">{{ __('verification.page.role_label') }}</dt><dd class="break-words text-slate-900">{{ $request['roleLabel'] }}</dd></div>
            <div class="min-w-0 sm:col-span-2"><dt class="text-slate-500">{{ __('verification.page.church_label') }}</dt><dd class="break-words text-slate-900">{{ $request['church'] }}</dd></div>
            <div class="min-w-0"><dt class="text-slate-500">{{ __('verification.page.documents_label') }}</dt><dd class="text-slate-900">{{ __('verification.page.documents_count', ['count' => $request['documentCount']]) }}</dd></div>
        </dl>
    </section>
    @endif
</div>
@endsection
