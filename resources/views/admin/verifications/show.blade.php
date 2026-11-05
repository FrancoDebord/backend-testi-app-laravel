@extends('layouts.app')
@section('title', __('admin.verifications.request'))
@php
    // docs/fonctionnalites/verification-des-comptes.md
    $applicant   = $verification->user;
    $header      = __('admin.verifications.request');
    $subheader   = $verification->full_name;
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.administration'), 'url' => route('admin.dashboard')],
        ['label' => __('layout.nav.verifications'), 'url' => route('admin.verifications.index')],
        ['label' => $verification->full_name],
    ];
    $documents = array_values($verification->documents ?? []);
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="card min-w-0 p-5 sm:p-6" aria-labelledby="applicant-title">
        <h2 id="applicant-title" class="card-title mb-4">{{ __('admin.verifications.account') }}</h2>
        @if($applicant)
        <div class="flex items-center gap-3">
            @include('components.avatar', ['user' => $applicant, 'size' => 'lg'])
            <div class="min-w-0">
                <p class="flex min-w-0 items-center gap-1 font-semibold text-slate-900"><span class="truncate">{{ $applicant->display_name }}</span>@include('components.verified-badge', ['user' => $applicant])</p>
                <p class="truncate text-sm text-slate-500">{{ $applicant->email }}</p>
                @if($applicant->trashed())
                <span class="badge-banned">{{ __('admin.verifications.account_deleted') }}</span>
                @else
                <a href="{{ route('admin.users.show', $applicant->id) }}" class="text-sm font-semibold text-primary-600 hover:underline">{{ __('admin.verifications.account_page') }}</a>
                @endif
            </div>
        </div>
        @endif
        <dl class="mt-4 divide-y divide-slate-100 text-sm">
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ __('admin.common.status') }}</dt><dd><span class="{{ $verification->statusBadgeClass() }}">{{ $verification->statusLabel() }}</span></dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ __('admin.verifications.sent_on') }}</dt><dd class="text-slate-900">{{ $verification->created_at->format(__('admin.formats.datetime')) }}</dd></div>
            @if($verification->reviewed_at)
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ __('admin.verifications.reviewed_on') }}</dt><dd class="text-right text-slate-900">{{ $verification->reviewed_at->format(__('admin.formats.datetime')) }}@if($verification->reviewer)<br>{{ __('admin.common.by', ['name' => $verification->reviewer->display_name]) }}@endif</dd></div>
            @endif
        </dl>
    </section>

    <div class="min-w-0 space-y-6 lg:col-span-2">
        <section class="card p-5 sm:p-6" aria-labelledby="request-title">
            <h2 id="request-title" class="card-title mb-4">{{ __('admin.verifications.declared') }}</h2>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div class="min-w-0"><dt class="text-slate-500">{{ __('admin.verifications.full_name') }}</dt><dd class="break-words text-slate-900">{{ $verification->full_name }}</dd></div>
                <div class="min-w-0"><dt class="text-slate-500">{{ __('admin.verifications.function') }}</dt><dd class="break-words text-slate-900">{{ $verification->roleLabel() }}</dd></div>
                <div class="min-w-0 sm:col-span-2"><dt class="text-slate-500">{{ __('admin.verifications.church') }}</dt><dd class="break-words text-slate-900">{{ $verification->church }}</dd></div>
                <div class="min-w-0 sm:col-span-2">
                    <dt class="text-slate-500">{{ __('admin.verifications.links') }}</dt>
                    <dd class="break-all text-slate-900">
                        @forelse($verification->links ?? [] as $link)
                        <a href="{{ $link }}" target="_blank" rel="noopener noreferrer nofollow" class="block hover:underline">{{ $link }} <i class="fa-solid fa-arrow-up-right-from-square text-xs text-slate-400" aria-hidden="true"></i></a>
                        @empty — @endforelse
                    </dd>
                </div>
                @if($verification->message)
                <div class="min-w-0 sm:col-span-2"><dt class="text-slate-500">{{ __('admin.verifications.message') }}</dt><dd class="whitespace-pre-line break-words text-slate-900">{{ $verification->message }}</dd></div>
                @endif
                @if($verification->review_note)
                <div class="min-w-0 sm:col-span-2"><dt class="text-slate-500">{{ __('admin.verifications.reason') }}</dt><dd class="break-words text-slate-900">{{ __('admin.common.quoted', ['text' => $verification->review_note]) }}</dd></div>
                @endif
            </dl>
        </section>

        <section class="card p-5 sm:p-6" aria-labelledby="documents-title">
            <h2 id="documents-title" class="card-title mb-1">{{ __('admin.verifications.documents') }}</h2>
            <p class="mb-4 text-xs text-slate-500"><i class="fa-solid fa-lock" aria-hidden="true"></i> {{ __('admin.verifications.documents_private') }}</p>
            @if($documents === [])
            <p class="text-sm text-slate-500">{{ __('admin.verifications.no_documents') }}</p>
            @else
            <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach($documents as $i => $doc)
                @php($docUrl = route('admin.verifications.document', [$verification->id, $i]))
                <li class="min-w-0 overflow-hidden rounded-xl border border-slate-200">
                    @if(str_starts_with((string) ($doc['mime'] ?? ''), 'image/'))
                    <a href="{{ $docUrl }}" target="_blank" rel="noopener">
                        <img src="{{ $docUrl }}" alt="{{ __('admin.verifications.document_alt', ['number' => $i + 1]) }}" class="h-48 w-full bg-slate-100 object-contain" loading="lazy">
                    </a>
                    @else
                    <a href="{{ $docUrl }}" target="_blank" rel="noopener" class="flex h-48 items-center justify-center bg-slate-100 text-slate-500">
                        <i class="fa-regular fa-file-pdf text-4xl" aria-hidden="true"></i>
                    </a>
                    @endif
                    <p class="truncate px-3 py-2 text-xs text-slate-600">{{ $doc['name'] ?? __('admin.verifications.document_name', ['number' => $i + 1]) }}</p>
                </li>
                @endforeach
            </ul>
            @endif
        </section>

        @if($verification->isPending())
        <section class="card p-5 sm:p-6" aria-labelledby="decision-title">
            <h2 id="decision-title" class="card-title mb-4">{{ __('admin.verifications.decision') }}</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <form id="verification-approve-form" method="POST" action="{{ route('admin.verifications.approve', $verification->id) }}" class="flex flex-col" data-loading-label="{{ __('admin.common.verifying') }}">
                    @csrf
                    <p class="mb-3 text-sm text-slate-600">{{ __('admin.verifications.approve_hint') }}</p>
                    <button type="button" class="btn-primary mt-auto w-full"
                            onclick="openConfirmModal('verification-approve-form', @js(__('admin.verifications.approve_confirm', ['name' => $verification->full_name])), @js(__('admin.verifications.approve_title')), @js(__('admin.common.verify')), 'fa-circle-check')">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('admin.verifications.approve') }}
                    </button>
                </form>
                <form id="verification-reject-form" method="POST" action="{{ route('admin.verifications.reject', $verification->id) }}" class="flex flex-col gap-3" data-loading-label="{{ __('admin.common.saving_rejection') }}">
                    @csrf
                    <div>
                        <label for="reason" class="form-label">{{ __('admin.verifications.reject_reason') }}</label>
                        <textarea id="reason" name="reason" rows="2" required minlength="3" maxlength="255" class="form-input resize-none"
                                  placeholder="{{ __('admin.verifications.reject_placeholder') }}">{{ old('reason') }}</textarea>
                        @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn-secondary mt-auto w-full text-red-700">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>{{ __('admin.common.reject') }}
                    </button>
                </form>
            </div>
        </section>
        @elseif($applicant && !$applicant->trashed() && $verification->status === \App\Models\VerificationRequest::APPROVED && $applicant->isVerified())
        <section class="card p-5 sm:p-6" aria-labelledby="revoke-title">
            <h2 id="revoke-title" class="card-title mb-4">{{ __('admin.common.remove_verification') }}</h2>
            @include('admin.verifications.partials.revoke-form', ['account' => $applicant])
        </section>
        @endif

        @if($history->isNotEmpty())
        <section class="card p-5 sm:p-6" aria-labelledby="history-title">
            <h2 id="history-title" class="card-title mb-3">{{ __('admin.verifications.history') }}</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach($history as $old)
                <li class="flex items-center justify-between gap-3 py-2">
                    <a href="{{ route('admin.verifications.show', $old->id) }}" class="min-w-0 truncate text-primary-600 hover:underline">{{ $old->created_at->format(__('admin.formats.date')) }} · {{ $old->roleLabel() }}</a>
                    <span class="{{ $old->statusBadgeClass() }} shrink-0">{{ $old->statusLabel() }}</span>
                </li>
                @endforeach
            </ul>
        </section>
        @endif
    </div>
</div>
@endsection
