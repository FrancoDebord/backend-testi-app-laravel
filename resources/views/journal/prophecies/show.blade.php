@extends('layouts.app')
@php
    $fulfilled   = $prophecy->isFulfilled();
    $overdue     = !$fulfilled && $prophecy->due_on && $prophecy->due_on->isPast() && !$prophecy->due_on->isToday();
    $fmtDate     = fn ($d) => $d?->translatedFormat(__('prophecies.date_format'));
    $audioLength = $prophecy->audio_duration ? sprintf('%d:%02d', intdiv($prophecy->audio_duration, 60), $prophecy->audio_duration % 60) : null;
    $testimony   = $prophecy->testimony_id ? $prophecy->testimony()->first() : null;
    $verse       = \App\Support\Encouragements::prophecyVerses()[0] ?? null;
    $weekdays    = \App\Http\Controllers\Web\ProphecyController::weekdays();
    $header      = $prophecy->title ?: __('prophecies.reminder_title', ['date' => $fmtDate($prophecy->received_on)]);
    $subheader   = $prophecy->is_public ? __('prophecies.show.subheader_public') : __('prophecies.show.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.journal'), 'url' => route('journal.index')],
        ['label' => __('layout.nav.prophecies'), 'url' => route('prophecies.index', $fulfilled ? ['statut' => 'fulfilled'] : [])],
        ['label' => Str::limit($header, 40)],
    ];
@endphp
@section('title', $header)

@section('headerActions')
    <button type="button" class="btn-secondary" data-proclaim-open><i class="fa-solid fa-bullhorn" aria-hidden="true"></i>{{ __('prophecies.show.proclaim') }}</button>
    <a href="{{ route('prophecies.edit', $prophecy->id) }}" class="btn-secondary"><i class="fa-solid fa-pen" aria-hidden="true"></i>{{ __('prophecies.show.edit') }}</a>
    <button type="button" class="btn-ghost text-error-700"
            onclick="openConfirmModal('prophecy-delete-form', @js($testimony ? __('prophecies.show.delete_confirm_testimony') : __('prophecies.show.delete_confirm')), @js(__('prophecies.show.delete_title')), @js(__('prophecies.show.delete')), 'fa-trash')">
        <i class="fa-solid fa-trash" aria-hidden="true"></i><span class="sr-only sm:not-sr-only">{{ __('prophecies.show.delete') }}</span>
    </button>
@endsection

@section('content')
<form id="prophecy-delete-form" method="POST" action="{{ route('prophecies.destroy', $prophecy->id) }}" data-loading-label="{{ __('prophecies.show.deleting') }}" hidden>
    @csrf @method('DELETE')
</form>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="min-w-0 space-y-6">

        {{-- ── La parole ─────────────────────────────────────────────── --}}
        <section class="card p-5 sm:p-6" aria-labelledby="prophecy-word-title">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="prophecy-word-title" class="card-title mr-auto">{{ __('prophecies.word') }}</h2>
                @if($fulfilled)
                <span class="badge-validated"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('prophecies.status.fulfilled') }}</span>
                @else
                <span class="badge-pending">{{ __('prophecies.status.waiting') }}</span>
                @endif
                @if($overdue)
                <span class="badge-orange">{{ __('prophecies.status.overdue') }}</span>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div class="min-w-0"><dt class="text-xs text-slate-500">{{ __('prophecies.labels.received') }}</dt><dd class="font-medium text-slate-900">{{ $prophecy->receivedLabel(__('prophecies.date_format')) }}</dd></div>
                @if(filled($prophecy->given_by))
                <div class="min-w-0"><dt class="text-xs text-slate-500">{{ __('prophecies.labels.given_by') }}</dt><dd class="font-medium break-words text-slate-900">{{ $prophecy->given_by }}</dd></div>
                @endif
                @if($prophecy->due_on)
                <div class="min-w-0"><dt class="text-xs text-slate-500">{{ __('prophecies.labels.due') }}</dt><dd class="font-medium {{ $overdue ? 'text-accent-700' : 'text-slate-900' }}">{{ $fmtDate($prophecy->due_on) }}</dd></div>
                @endif
                @if($fulfilled && $prophecy->fulfilled_on)
                <div class="min-w-0"><dt class="text-xs text-slate-500">{{ __('prophecies.labels.fulfilled_on') }}</dt><dd class="font-semibold text-success-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>{{ $fmtDate($prophecy->fulfilled_on) }}</dd></div>
                @endif
            </dl>

            @if(filled($prophecy->body_text))
            <blockquote class="mt-4 border-l-2 border-sun-400 pl-4 text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800">{{ $prophecy->body_text }}</blockquote>
            @endif
            @if($prophecy->audio_url)
            <div class="mt-4">
                <p class="mb-1 text-xs text-slate-500">{{ __('prophecies.show.audio') }} @if($audioLength)<span class="tabular-nums">({{ $audioLength }})</span>@endif</p>
                <audio controls preload="metadata" src="{{ $prophecy->audio_url }}" class="w-full" aria-label="{{ __('prophecies.show.listen') }}"></audio>
            </div>
            @endif
        </section>

        {{-- ── Accomplissement ───────────────────────────────────────── --}}
        <section class="card p-5 sm:p-6" aria-labelledby="fulfil-title">
            @if($testimony)
                <h2 id="fulfil-title" class="card-title">{{ __('prophecies.show.your_testimony') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    @if($prophecy->is_public)
                        {{ __('prophecies.show.shown_with_testimony') }}
                    @else
                        {{ __('prophecies.show.stays_private') }}
                    @endif
                </p>
                <a href="{{ route('testimonies.show', $testimony->id) }}" class="mt-4 flex items-center gap-3 rounded-lg border border-slate-200 p-3 hover:border-primary-200 hover:bg-primary-50">
                    <i class="fa-solid fa-pen-to-square text-primary-600" aria-hidden="true"></i>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-slate-900">{{ $testimony->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $testimony->isInJournal() ? __('prophecies.show.in_journal') : $testimony->status->label() }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400" aria-hidden="true"></i>
                </a>
            @elseif($fulfilled)
                <h2 id="fulfil-title" class="card-title">{{ __('prophecies.show.glory') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('prophecies.show.glory_text') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('publish', ['prophecy' => $prophecy->id]) }}" class="btn-cta"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>{{ __('prophecies.show.testify') }}</a>
                    <form method="POST" action="{{ route('prophecies.reopen', $prophecy->id) }}" data-loading-label="{{ __('prophecies.show.saving') }}">
                        @csrf
                        <button type="submit" class="btn-ghost"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i>{{ __('prophecies.show.reopen') }}</button>
                    </form>
                </div>
            @else
                <h2 id="fulfil-title" class="card-title">{{ __('prophecies.show.fulfilled_question') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('prophecies.show.fulfilled_question_text') }}</p>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <a href="{{ route('publish', ['prophecy' => $prophecy->id]) }}" class="btn-cta"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>{{ __('prophecies.show.testify') }}</a>
                    <form method="POST" action="{{ route('prophecies.fulfill', $prophecy->id) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end" data-loading-label="{{ __('prophecies.show.saving') }}">
                        @csrf
                        <div>
                            <label for="fulfilled_on" class="form-label">{{ __('prophecies.labels.fulfilled_on') }}</label>
                            <input id="fulfilled_on" type="date" name="fulfilled_on" value="{{ old('fulfilled_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-input" @error('fulfilled_on') aria-invalid="true" @enderror>
                        </div>
                        <button type="submit" class="btn-secondary"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('prophecies.show.mark_fulfilled') }}</button>
                    </form>
                </div>
                @error('fulfilled_on')<p class="form-error">{{ $message }}</p>@enderror
            @endif
        </section>

        {{-- ── Journal de prière ─────────────────────────────────────── --}}
        <section class="card p-5 sm:p-6" aria-labelledby="prayers-title">
            <h2 id="prayers-title" class="card-title">{{ __('prophecies.show.prayer_log') }} <span class="font-normal text-slate-500">· {{ $prophecy->prayer_count }}</span></h2>
            @if($prophecy->last_prayed_at)
            <p class="mt-1 text-sm text-slate-500">{{ __('prophecies.show.last_prayer', ['date' => $prophecy->last_prayed_at->translatedFormat(__('prophecies.date_format'))]) }}</p>
            @endif

            @unless($fulfilled)
            {{-- « J'ai prié » : temps de prière d'abord (resources/js/prayer-focus.js, docs/fonctionnalites/temps-de-priere.md) --}}
            <form method="POST" action="{{ route('prophecies.pray', $prophecy->id) }}" class="mt-4 space-y-2" data-loading-label="{{ __('prophecies.show.prayer_saving') }}"
                  data-prayer-focus="{{ route('prophecies.pray.start', $prophecy->id) }}" data-prayer-focus-kind="prophecy"
                  data-prayer-subject="{{ $prophecy->title }}" data-prayer-excerpt="{{ \Illuminate\Support\Str::limit((string) $prophecy->body_text, 600) }}">
                @csrf
                <label for="prayer-note" class="form-label">{{ __('prophecies.show.note') }}</label>
                <textarea id="prayer-note" name="note" rows="2" maxlength="1000" class="form-input" placeholder="{{ __('prophecies.show.note_placeholder') }}" @error('note') aria-invalid="true" @enderror>{{ old('note') }}</textarea>
                @error('note')<p class="form-error">{{ $message }}</p>@enderror
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-hands-praying" aria-hidden="true"></i>{{ __('prophecies.show.prayed') }}</button>
                </div>
            </form>
            @endunless

            @if($prayers->isEmpty())
            <p class="mt-4 text-sm text-slate-500">{{ $fulfilled ? __('prophecies.show.no_prayers') : __('prophecies.show.no_prayers_waiting') }}</p>
            @else
            <ul class="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                @foreach($prayers as $prayer)
                <li class="flex items-start gap-3 py-3">
                    <i class="fa-solid fa-hands-praying mt-1 text-sm text-primary-600" aria-hidden="true"></i>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-900">{{ $prayer->prayed_at?->translatedFormat(__('prophecies.show.prayed_format')) }}</p>
                        @if(filled($prayer->note))
                        <p class="mt-0.5 text-sm break-words whitespace-pre-line text-slate-600">{{ $prayer->note }}</p>
                        @endif
                    </div>
                    <form id="prayer-delete-{{ $prayer->id }}" method="POST" action="{{ route('prophecies.prayers.destroy', [$prophecy->id, $prayer->id]) }}" data-loading-label="{{ __('prophecies.show.removing') }}" hidden>
                        @csrf @method('DELETE')
                    </form>
                    <button type="button" class="action-btn-delete" aria-label="{{ __('prophecies.show.prayer_remove_label') }}" title="{{ __('prophecies.show.remove') }}"
                            onclick="openConfirmModal('prayer-delete-{{ $prayer->id }}', @js(__('prophecies.show.prayer_remove_confirm')), @js(__('prophecies.show.prayer_remove_title')), @js(__('prophecies.show.remove')), 'fa-trash')">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                </li>
                @endforeach
            </ul>
            <div class="mt-3">{{ $prayers->links() }}</div>
            @endif
        </section>
    </div>

    {{-- ── Colonne : verset et rappel ───────────────────────────────── --}}
    <aside class="min-w-0 space-y-6">
        @if($verse && !$fulfilled)
        <div class="card-insight p-5">
            <blockquote class="text-[15px] leading-relaxed break-words text-slate-900 italic">{{ __('prophecies.quoted', ['text' => $verse['text']]) }}</blockquote>
            <p class="mt-1 text-sm font-semibold text-primary-700">{{ $verse['ref'] }}</p>
        </div>
        @endif

        <section class="card p-5" aria-labelledby="reminder-title">
            <h2 id="reminder-title" class="card-title">{{ __('prophecies.reminder.title') }}</h2>
            @if($prophecy->reminder_frequency)
            <p class="mt-2 text-sm text-slate-900">
                <i class="fa-regular fa-bell mr-1 text-primary-600" aria-hidden="true"></i>
                {{ $prophecy->reminder_frequency === 'weekly'
                    ? __('prophecies.reminder.weekly', ['day' => Str::lower($weekdays[$prophecy->reminder_weekday] ?? ''), 'time' => $prophecy->reminder_time])
                    : __('prophecies.reminder.daily', ['time' => $prophecy->reminder_time]) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">{{ $fulfilled ? __('prophecies.reminder.stopped') : __('prophecies.reminder.sent_by_app') }}</p>
            @else
            <p class="mt-2 text-sm text-slate-500">{{ __('prophecies.reminder.none') }}</p>
            @endif
            <a href="{{ route('prophecies.edit', $prophecy->id) }}#reminder-title" class="btn-secondary btn-sm mt-3">{{ $prophecy->reminder_frequency ? __('prophecies.reminder.edit') : __('prophecies.reminder.schedule') }}</a>
        </section>
    </aside>
</div>

{{-- ── Proclamer : lecture en plein écran ─────────────────────────── --}}
<dialog id="proclaim-dialog" class="m-0 h-full max-h-none w-full max-w-none bg-white p-0 backdrop:bg-slate-900/60" aria-labelledby="proclaim-title">
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-6">
            <h2 id="proclaim-title" class="text-sm font-semibold text-primary-600"><i class="fa-solid fa-bullhorn mr-2" aria-hidden="true"></i>{{ __('prophecies.show.proclaim_title') }}</h2>
            <button type="button" class="btn-ghost btn-sm" data-proclaim-close aria-label="{{ __('prophecies.show.close') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-8 sm:px-10">
            <div class="mx-auto max-w-3xl">
                @if(filled($prophecy->title))
                <p class="text-lg font-bold break-words text-primary-700 sm:text-xl">{{ $prophecy->title }}</p>
                @endif
                @if(filled($prophecy->body_text))
                <p class="mt-4 text-2xl leading-relaxed font-medium break-words whitespace-pre-line text-slate-900 sm:text-3xl">{{ $prophecy->body_text }}</p>
                @endif
                @if($prophecy->audio_url)
                <audio controls preload="none" src="{{ $prophecy->audio_url }}" class="mt-6 w-full" aria-label="{{ __('prophecies.show.listen') }}"></audio>
                @endif
                <p class="mt-6 text-sm text-slate-500">
                    {{ __('prophecies.show.received_on', ['date' => $prophecy->receivedLabel(__('prophecies.date_format'))]) }}@if(filled($prophecy->given_by)) · {{ $prophecy->given_by }}@endif
                </p>
                @if($verse)
                <div class="card-insight mt-8 p-5">
                    <blockquote class="text-base leading-relaxed break-words text-slate-900 italic">{{ __('prophecies.quoted', ['text' => $verse['text']]) }}</blockquote>
                    <p class="mt-1 text-sm font-semibold text-primary-700">{{ $verse['ref'] }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
(function () {
    const dialog = document.getElementById('proclaim-dialog');
    document.querySelectorAll('[data-proclaim-open]').forEach(function (btn) {
        btn.addEventListener('click', function () { dialog.showModal(); });
    });
    dialog.querySelectorAll('[data-proclaim-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { dialog.close(); });
    });
    // L'audio s'arrête à la fermeture.
    dialog.addEventListener('close', function () {
        dialog.querySelectorAll('audio').forEach(function (a) { a.pause(); });
    });
})();
</script>
@endpush
