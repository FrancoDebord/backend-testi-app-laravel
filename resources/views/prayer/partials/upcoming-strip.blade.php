{{--
    Bandeau « Sessions de prière à venir » de l'accueil (rien n'est affiché si la liste est vide).
    @include('prayer.partials.upcoming-strip', ['sessions' => $sessions])  — PrayerSessions::list($viewer, 'upcoming')
    Voir docs/fonctionnalites/sessions-de-priere.md
--}}
@if($sessions->isNotEmpty())
<section class="card p-5" aria-labelledby="home-prayer-sessions">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 id="home-prayer-sessions" class="card-title flex items-center gap-2"><i class="fa-solid fa-people-group text-accent-700" aria-hidden="true"></i>Sessions de prière à venir</h2>
        <a href="{{ route('prayer.sessions.index') }}" class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Tout voir<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
    </div>
    <ul class="-mx-5 flex snap-x gap-4 overflow-x-auto px-5 pb-1 [scrollbar-width:none]">
        @foreach($sessions as $session)
        <li class="w-72 shrink-0 snap-start">@include('prayer.partials.session-card', ['session' => $session])</li>
        @endforeach
    </ul>
</section>
@endif
