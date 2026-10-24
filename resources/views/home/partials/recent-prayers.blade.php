{{--
    Accueil : « Prières récentes », requêtes de prière publiques (les plus proches d'abord), à part des
    « Témoignages récents » pour que les deux grilles gardent des cartes de même hauteur.
    @include('home.partials.recent-prayers', ['prayers' => $recentPrayers])
    Jusqu'à 6 cartes : 3 sur mobile, 4 sur tablette, 6 sur grand écran. Masqué s'il n'y en a pas.
--}}
@if(collect($prayers ?? [])->isNotEmpty())
<section class="card p-5" aria-labelledby="home-prayers">
    <div class="mb-4 flex items-center justify-between gap-2">
        <h2 id="home-prayers" class="card-title flex items-center gap-2"><i class="fa-solid fa-hands-praying text-accent-500" aria-hidden="true"></i>Prières récentes</h2>
        <a href="{{ route('prayer.requests.index') }}" class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Voir tout<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($prayers as $prayerItem)
        <div class="{{ match ($loop->index) { 0, 1, 2 => '', 3 => 'hidden sm:block', default => 'hidden lg:block' } }} min-w-0">
            @include('prayer.partials.feed-card', ['request' => $prayerItem, 'compact' => true])
        </div>
        @endforeach
    </div>
</section>
@endif
