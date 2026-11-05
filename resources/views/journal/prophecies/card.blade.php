{{-- Carte d'une parole prophétique du carnet. Variable : $prophecy. --}}
@php
    $fulfilled = $prophecy->isFulfilled();
    $overdue   = !$fulfilled && $prophecy->due_on && $prophecy->due_on->isPast() && !$prophecy->due_on->isToday();
    $excerpt   = filled($prophecy->body_text) ? Str::limit($prophecy->body_text, 180) : null;
@endphp
<a href="{{ route('prophecies.show', $prophecy->id) }}" class="card flex h-full flex-col gap-3 p-4 hover:border-primary-200 sm:p-5">
    <div class="flex flex-wrap items-center gap-2">
        @if($fulfilled)
        <span class="badge-validated"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('prophecies.status.fulfilled') }}</span>
        @else
        <span class="badge-pending">{{ __('prophecies.status.waiting') }}</span>
        @endif
        @if($overdue)
        <span class="badge-orange">{{ __('prophecies.status.overdue') }}</span>
        @endif
        @if($prophecy->is_public)
        <span class="badge-blue">{{ __('prophecies.status.public') }}</span>
        @endif
    </div>

    <div class="min-w-0 flex-1">
        @if(filled($prophecy->title))
        <h3 class="font-semibold break-words text-slate-900">{{ $prophecy->title }}</h3>
        @endif
        @if($excerpt)
        <p class="{{ filled($prophecy->title) ? 'mt-1 text-sm text-slate-600' : 'text-[15px] text-slate-900' }} break-words whitespace-pre-line">{{ __('prophecies.quoted', ['text' => $excerpt]) }}</p>
        @elseif($prophecy->audio_url)
        <p class="text-sm text-slate-600"><i class="fa-solid fa-microphone mr-1 text-primary-600" aria-hidden="true"></i>{{ __('prophecies.card.audio_only') }}</p>
        @endif
    </div>

    <dl class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
        <div><dt class="sr-only">{{ __('prophecies.labels.received') }}</dt><dd><i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>{{ $prophecy->receivedLabel(__('prophecies.short_date_format')) }}</dd></div>
        @if(filled($prophecy->given_by))
        <div class="min-w-0"><dt class="sr-only">{{ __('prophecies.labels.given_by') }}</dt><dd class="truncate"><i class="fa-regular fa-user mr-1" aria-hidden="true"></i>{{ $prophecy->given_by }}</dd></div>
        @endif
        @if($prophecy->due_on && !$fulfilled)
        <div><dt class="sr-only">{{ __('prophecies.card.due') }}</dt><dd><i class="fa-regular fa-hourglass-half mr-1" aria-hidden="true"></i>{{ $prophecy->due_on->translatedFormat(__('prophecies.short_date_format')) }}</dd></div>
        @endif
        @if($fulfilled && $prophecy->fulfilled_on)
        <div><dt class="sr-only">{{ __('prophecies.labels.fulfilled_on') }}</dt><dd class="text-success-700"><i class="fa-solid fa-check mr-1" aria-hidden="true"></i>{{ $prophecy->fulfilled_on->translatedFormat(__('prophecies.short_date_format')) }}</dd></div>
        @endif
        <div><dt class="sr-only">{{ __('prophecies.card.prayers_label') }}</dt><dd><i class="fa-solid fa-hands-praying mr-1" aria-hidden="true"></i>{{ trans_choice('prophecies.card.prayers', $prophecy->prayer_count) }}</dd></div>
        @if($prophecy->audio_url && $excerpt)
        <div><dt class="sr-only">{{ __('prophecies.card.audio') }}</dt><dd><i class="fa-solid fa-microphone mr-1" aria-hidden="true"></i>{{ __('prophecies.card.audio') }}</dd></div>
        @endif
    </dl>
</a>
