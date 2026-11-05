@extends('layouts.app')
@php
    $isAuthor    = $prayer->isAuthor($user);
    $moderator   = (bool) $user?->canModerate();
    $author      = $prayer->revealsAuthorTo($user) ? $prayer->user : null;
    $hasPrayed   = $prayer->hasPrayed($user);
    $prayWith    = $prayer->prayWithAuthorFor($user); // « Je prie avec Marie » (User::shortFirstName)
    $header      = __('prayer.requests.show.header');
    $subheader   = ($author ? $author->display_name : __('prayer.common.anonymous')) . ' · ' . $prayer->created_at?->translatedFormat('j F Y');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.prayer_requests'), 'url' => route('prayer.requests.index')],
        ['label' => Str::limit(\App\Services\Mentions::plain($prayer->body), 40)],
    ];
@endphp
@section('title', $header)

{{-- Aperçu de partage (WhatsApp, Facebook…) : docs/fonctionnalites/apercu-de-partage.md --}}
@push('meta')
    <x-share-meta :meta="\App\Support\ShareMeta::prayer($prayer)" />
@endpush

@section('headerActions')
    @if($prayer->canBeDeletedBy($user))
    <button type="button" class="btn-ghost text-error-700"
            onclick="openConfirmModal('prayer-delete-form', @js(__('prayer.requests.show.delete_confirm')), @js(__('prayer.requests.show.delete_title')), @js(__('prayer.common.delete')), 'fa-trash')">
        <i class="fa-solid fa-trash" aria-hidden="true"></i><span class="sr-only sm:not-sr-only">{{ __('prayer.common.delete') }}</span>
    </button>
    @endif
@endsection

@section('content')
@if($prayer->canBeDeletedBy($user))
<form id="prayer-delete-form" method="POST" action="{{ route('prayer.requests.destroy', $prayer->id) }}" data-loading-label="{{ __('prayer.common.deleting') }}" hidden>
    @csrf @method('DELETE')
</form>
@endif

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="min-w-0 space-y-6">

        {{-- ── La requête ───────────────────────────────────────────── --}}
        <section class="card p-5 sm:p-6" aria-labelledby="prayer-title">
            <h2 id="prayer-title" class="sr-only">{{ __('prayer.requests.show.section') }}</h2>
            <div class="flex items-start gap-3">
                @if($author)
                    @include('components.avatar', ['user' => $author, 'size' => 'md'])
                @else
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500" aria-hidden="true"><i class="fa-solid fa-user-secret"></i></span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900">
                        @if($author)
                            <a href="{{ route('profiles.show', $author->id) }}" class="hover:underline">{{ $author->display_name }}</a>
                            @include('components.verified-badge', ['user' => $author])
                        @else {{ __('prayer.common.anonymous') }} @endif
                    </p>
                    <p class="text-xs text-slate-500">{{ $prayer->created_at?->diffForHumans() }} · {{ \App\Models\PrayerRequest::visibilityLabels()[$prayer->visibility] }}@if($prayer->is_anonymous) · {{ __('prayer.common.anonymous_lower') }} @endif</p>
                </div>
                @if($prayer->isAnswered())
                <span class="badge-validated shrink-0"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('prayer.common.answered') }}</span>
                @endif
            </div>

            @if($prayer->isHidden())
            <p class="alert-warning mt-4 text-sm"><i class="fa-solid fa-eye-slash mt-0.5" aria-hidden="true"></i><span>{{ $prayer->hidden_reason ?: __('prayer.common.hidden_default') }} {{ __('prayer.requests.show.hidden_suffix') }}</span></p>
            @endif

            <p class="mt-4 text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800">{{ \App\Services\Mentions::html($prayer->body) }}</p>
            <x-link-preview :text="$prayer->body" />

            @if($prayer->event)
            <a href="{{ route('events.show', $prayer->event_id) }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-700 hover:underline">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>{{ $prayer->event->title }}
            </a>
            @endif
            @if($prayer->source_testimony_id && $prayer->sourceTestimony)
            <a href="{{ route('testimonies.show', $prayer->source_testimony_id) }}" class="mt-3 flex min-w-0 items-center gap-1 text-sm font-medium text-primary-700 hover:underline">
                <i class="fa-solid fa-book-open shrink-0" aria-hidden="true"></i><span class="truncate">{{ __('prayer.common.after_testimony', ['title' => $prayer->sourceTestimony->title]) }}</span>
            </a>
            @endif

            @if($prayer->isAnswered())
            <div class="card-insight mt-4 p-4">
                <p class="text-sm font-semibold text-success-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>{{ __('prayer.requests.show.answered_on', ['date' => $prayer->answered_at?->translatedFormat('j F Y')]) }}</p>
                @if(filled($prayer->answer_note))<p class="mt-1 text-sm break-words whitespace-pre-line text-slate-700">{{ $prayer->answer_note }}</p>@endif
                @if($prayer->testimony_id)
                <a href="{{ route('testimonies.show', $prayer->testimony_id) }}" class="mt-2 inline-flex text-sm font-medium text-primary-700 hover:underline">{{ __('prayer.requests.show.read_testimony') }}</a>
                @endif
            </div>
            @endif

            <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                @if($user && !$prayer->isHidden())
                {{-- Ajouter une prière : temps de prière d'abord (resources/js/prayer-focus.js, docs/fonctionnalites/temps-de-priere.md) --}}
                <form method="POST" action="{{ route('prayer.requests.pray', $prayer->id) }}" data-loading-label="{{ __('prayer.common.saving') }}"
                      data-prayer-focus="{{ route('prayer.requests.pray.start', $prayer->id) }}"
                      data-prayer-excerpt="{{ Str::limit(\App\Services\Mentions::plain($prayer->body), 600) }}">
                    @csrf
                    <button type="submit" class="{{ $hasPrayed ? 'btn-primary' : 'btn-secondary' }}" aria-pressed="{{ $hasPrayed ? 'true' : 'false' }}"
                            @if($prayWith && !$hasPrayed) title="{{ __('reactions.i_pray_with', ['name' => $prayWith->display_name]) }}" @endif>
                        <i class="fa-solid fa-hands-praying" aria-hidden="true"></i>{{ $hasPrayed ? __('reactions.prayer_times.pray_again') : ($prayWith ? __('reactions.i_pray_with', ['name' => $prayWith->shortFirstName()]) : __('reactions.i_pray')) }}
                    </button>
                </form>
                @elseif(!$user)
                <a href="{{ route('login') }}" class="btn-secondary"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>{{ __('prayer.common.login_to_pray') }}</a>
                @endif
                {{-- Qui prie (et qui aime) : docs/fonctionnalites/qui-a-reagi.md --}}
                <button type="button" class="btn-ghost btn-sm" data-reaction-people="{{ route('reactions.people', ['kind' => 'prayer_request', 'id' => $prayer->id, 'type' => 'pray']) }}" aria-haspopup="dialog" title="{{ __('reactions.people.open_prayers') }}">
                    <span class="font-semibold tabular-nums text-slate-900">{{ $prayer->prayer_count }}</span> {{ trans_choice('prayer.requests.show.people_praying', $prayer->prayer_count) }}
                </button>
            </div>
            {{-- Nombre de fois que la personne a prié (docs/fonctionnalites/prier-plusieurs-fois.md) --}}
            @if($user && !$prayer->isHidden())
            <div class="mt-2">@include('components.prayer-times', ['times' => $prayer->prayerTimesOf($user), 'action' => route('prayer.requests.pray', $prayer->id), 'fields' => ['last' => 1]])</div>
            @endif
        </section>

        {{-- ── Encouragements ───────────────────────────────────────── --}}
        <section id="encouragements" class="card scroll-mt-24 p-5 sm:p-6" aria-labelledby="messages-title">
            <h2 id="messages-title" class="card-title">{{ __('prayer.requests.show.encouragements') }} <span class="tabular-nums text-slate-500">({{ $prayer->message_count }})</span></h2>

            @if($messages->isEmpty())
            <p class="mt-2 text-sm text-slate-500">{{ __('prayer.requests.show.no_messages') }}</p>
            @else
            <ul class="mt-4 space-y-4">
                @foreach($messages as $message)
                <li class="flex gap-3">
                    @include('components.avatar', ['user' => $message->user, 'size' => 'sm'])
                    <div class="min-w-0 flex-1 rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500"><span class="font-semibold text-slate-900">{{ $message->user?->display_name }}</span> · {{ $message->created_at?->diffForHumans() }}</p>
                        <p class="mt-1 text-sm break-words whitespace-pre-line text-slate-800">{{ \App\Services\Mentions::html($message->body) }}</p>
                        <x-link-preview :text="$message->body" />
                        @if($message->bible_reference)
                        <p class="mt-1 text-xs font-semibold text-primary-700"><i class="fa-solid fa-book-bible mr-1" aria-hidden="true"></i>{{ $message->bible_reference }}</p>
                        @endif
                    </div>
                    @if($message->canBeDeletedBy($user, $prayer))
                    <form method="POST" action="{{ route('prayer.requests.messages.destroy', [$prayer->id, $message->id]) }}" data-loading-label="{{ __('prayer.common.deleting') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="action-btn-delete" title="{{ __('prayer.requests.show.delete_message') }}" aria-label="{{ __('prayer.requests.show.delete_message') }}"><i class="fa-solid fa-trash"></i></button>
                    </form>
                    @endif
                </li>
                @endforeach
            </ul>
            {{ $messages->links() }}
            @endif

            @if($prayer->acceptsMessagesFrom($user))
            <form method="POST" action="{{ route('prayer.requests.messages.store', $prayer->id) }}" class="mt-5 space-y-3 border-t border-slate-100 pt-4" data-loading-label="{{ __('prayer.common.sending') }}">
                @csrf
                <div>
                    <label for="message" class="form-label">{{ __('prayer.requests.show.message') }}</label>
                    <textarea id="message" name="message" rows="3" maxlength="1000" required class="form-input" placeholder="{{ __('prayer.requests.show.message_placeholder') }}" data-mention-input>{{ old('message') }}</textarea>
                    @include('components.mention-input')
                    @error('message')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:w-1/2">
                    <label for="bible_reference" class="form-label">{{ __('prayer.requests.show.verse') }}</label>
                    <input id="bible_reference" type="text" name="bible_reference" maxlength="100" value="{{ old('bible_reference') }}" class="form-input" placeholder="{{ __('prayer.requests.show.verse_placeholder') }}">
                </div>
                <div class="flex justify-end"><button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>{{ __('prayer.common.send') }}</button></div>
            </form>
            @elseif(!$user)
            <p class="mt-4 text-sm text-slate-500">{!! __('prayer.requests.show.login_to_encourage', ['link' => '<a href="' . e(route('login')) . '" class="font-medium text-primary-700 hover:underline">' . e(__('prayer.requests.show.login')) . '</a>']) !!}</p>
            @endif
        </section>
    </div>

    {{-- ── Colonne latérale ─────────────────────────────────────────── --}}
    <aside class="min-w-0 space-y-6">
        @if($isAuthor)
        <section class="card p-5" aria-labelledby="answer-title">
            <h2 id="answer-title" class="card-title">{{ __('prayer.requests.show.answer_title') }}</h2>
            @if($prayer->isAnswered())
                <p class="mt-1 text-sm text-slate-600">{{ __('prayer.requests.show.answered_text') }}</p>
                <a href="{{ route('publish') }}" class="btn-cta mt-4 w-full"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>{{ __('prayer.requests.show.testify') }}</a>
                @unless($prayer->testimony_id)
                <form method="POST" action="{{ route('prayer.requests.reopen', $prayer->id) }}" class="mt-2 text-center" data-loading-label="{{ __('prayer.common.updating') }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost btn-sm">{{ __('prayer.requests.show.reopen') }}</button>
                </form>
                @endunless
            @else
                <p class="mt-1 text-sm text-slate-600">{{ __('prayer.requests.show.answer_question') }}</p>
                <form method="POST" action="{{ route('prayer.requests.answered', $prayer->id) }}" class="mt-3 space-y-3" data-loading-label="{{ __('prayer.common.saving') }}">
                    @csrf
                    <label for="note" class="sr-only">{{ __('prayer.requests.show.answer_note') }}</label>
                    <textarea id="note" name="note" rows="3" maxlength="1000" class="form-input" placeholder="{{ __('prayer.requests.show.answer_note') }}"></textarea>
                    <button type="submit" class="btn-primary w-full"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('prayer.requests.show.mark_answered') }}</button>
                </form>
            @endif
        </section>
        @endif

        @if($moderator && !$isAuthor)
        <section class="card p-5" aria-labelledby="moderate-title">
            <h2 id="moderate-title" class="card-title">{{ __('prayer.requests.show.moderation') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ trans_choice('prayer.requests.show.reports', $prayer->report_count, ['count' => $prayer->report_count]) }}</p>
            @if($prayer->isHidden())
            <form method="POST" action="{{ route('prayer.moderation.restore', $prayer->id) }}" class="mt-3" data-loading-label="{{ __('prayer.common.restoring') }}">
                @csrf
                <button type="submit" class="btn-secondary w-full"><i class="fa-solid fa-eye" aria-hidden="true"></i>{{ __('prayer.common.restore') }}</button>
            </form>
            @else
            <form method="POST" action="{{ route('prayer.moderation.hide', $prayer->id) }}" class="mt-3 space-y-2" data-loading-label="{{ __('prayer.common.removing') }}">
                @csrf
                <label for="hide-reason" class="sr-only">{{ __('prayer.requests.show.hide_reason') }}</label>
                <input id="hide-reason" type="text" name="reason" maxlength="300" class="form-input" placeholder="{{ __('prayer.requests.show.hide_placeholder') }}">
                <button type="submit" class="btn-secondary w-full text-error-700"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i>{{ __('prayer.requests.show.hide') }}</button>
            </form>
            @endif
        </section>
        @endif

        @if($user && !$isAuthor && !$prayer->isHidden())
        <details class="card p-5">
            <summary class="cursor-pointer text-sm font-medium text-slate-700"><i class="fa-regular fa-flag mr-1" aria-hidden="true"></i>{{ __('prayer.requests.show.report') }}</summary>
            <form method="POST" action="{{ route('prayer.requests.report', $prayer->id) }}" class="mt-3 space-y-3" data-loading-label="{{ __('prayer.common.sending') }}">
                @csrf
                <label for="reason" class="form-label">{{ __('prayer.requests.show.report_reason') }}</label>
                <select id="reason" name="reason" required class="form-input">
                    @foreach($reasons as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label for="report-comment" class="sr-only">{{ __('prayer.requests.show.report_details') }}</label>
                <textarea id="report-comment" name="comment" rows="2" maxlength="500" class="form-input" placeholder="{{ __('prayer.requests.show.report_details_placeholder') }}"></textarea>
                <button type="submit" class="btn-secondary w-full">{{ __('prayer.requests.show.report_submit') }}</button>
            </form>
        </details>
        @endif
    </aside>
</div>
@endsection
