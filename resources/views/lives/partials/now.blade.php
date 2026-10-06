{{--
    Directs à l'antenne (« En direct · Maintenant ») : accueil et « Mon fil ».
    @include('lives.partials.now', ['lives' => $lives, 'class' => 'mb-6'])  — rien n'est affiché si la liste est vide.
--}}
@if($lives->isNotEmpty())
<section class="card flex flex-wrap items-center gap-3 p-4 {{ $class ?? '' }}" aria-labelledby="lives-now">
    <h2 id="lives-now" class="flex items-center gap-2 text-sm font-bold text-primary-600"><span class="badge-live">En direct</span>Maintenant</h2>
    <ul class="flex min-w-0 flex-1 flex-wrap gap-2">
        @foreach($lives as $live)
        <li><a href="{{ route('lives.show', $live->id) }}" class="chip max-w-64"><i class="fa-solid fa-tower-broadcast text-error-500" aria-hidden="true"></i><span class="truncate">{{ $live->title }}</span></a></li>
        @endforeach
    </ul>
    <a href="{{ route('lives.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Tous les directs<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
</section>
@endif