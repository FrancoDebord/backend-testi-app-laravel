{{-- Carte d'une parole prophétique du carnet. Variable : $prophecy. --}}
@php
    $fulfilled = $prophecy->isFulfilled();
    $overdue   = !$fulfilled && $prophecy->due_on && $prophecy->due_on->isPast() && !$prophecy->due_on->isToday();
    $excerpt   = filled($prophecy->body_text) ? Str::limit($prophecy->body_text, 180) : null;
@endphp
<a href="{{ route('prophecies.show', $prophecy->id) }}" class="card flex h-full flex-col gap-3 p-4 hover:border-primary-200 sm:p-5">
    <div class="flex flex-wrap items-center gap-2">
        @if($fulfilled)
        <span class="badge-validated"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Accomplie</span>
        @else
        <span class="badge-pending">En attente</span>
        @endif
        @if($overdue)
        <span class="badge-orange">Échéance passée</span>
        @endif
        @if($prophecy->is_public)
        <span class="badge-blue">Publiée avec le témoignage</span>
        @endif
    </div>

    <div class="min-w-0 flex-1">
        @if(filled($prophecy->title))
        <h3 class="font-semibold break-words text-slate-900">{{ $prophecy->title }}</h3>
        @endif
        @if($excerpt)
        <p class="{{ filled($prophecy->title) ? 'mt-1 text-sm text-slate-600' : 'text-[15px] text-slate-900' }} break-words whitespace-pre-line">« {{ $excerpt }} »</p>
        @elseif($prophecy->audio_url)
        <p class="text-sm text-slate-600"><i class="fa-solid fa-microphone mr-1 text-primary-600" aria-hidden="true"></i>Parole enregistrée en audio</p>
        @endif
    </div>

    <dl class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
        <div><dt class="sr-only">Reçue le</dt><dd><i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>{{ $prophecy->received_on?->translatedFormat('j M Y') }}</dd></div>
        @if(filled($prophecy->given_by))
        <div class="min-w-0"><dt class="sr-only">Donnée par</dt><dd class="truncate"><i class="fa-regular fa-user mr-1" aria-hidden="true"></i>{{ $prophecy->given_by }}</dd></div>
        @endif
        @if($prophecy->due_on && !$fulfilled)
        <div><dt class="sr-only">Échéance</dt><dd><i class="fa-regular fa-hourglass-half mr-1" aria-hidden="true"></i>{{ $prophecy->due_on->translatedFormat('j M Y') }}</dd></div>
        @endif
        @if($fulfilled && $prophecy->fulfilled_on)
        <div><dt class="sr-only">Accomplie le</dt><dd class="text-success-700"><i class="fa-solid fa-check mr-1" aria-hidden="true"></i>{{ $prophecy->fulfilled_on->translatedFormat('j M Y') }}</dd></div>
        @endif
        <div><dt class="sr-only">Prières</dt><dd><i class="fa-solid fa-hands-praying mr-1" aria-hidden="true"></i>{{ $prophecy->prayer_count }} {{ $prophecy->prayer_count > 1 ? 'prières' : 'prière' }}</dd></div>
        @if($prophecy->audio_url && $excerpt)
        <div><dt class="sr-only">Audio</dt><dd><i class="fa-solid fa-microphone mr-1" aria-hidden="true"></i>Audio</dd></div>
        @endif
    </dl>
</a>
