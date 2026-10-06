{{--
    Preuves d'un témoignage (images ou PDF) : visibles de l'auteur et de l'équipe de modération,
    et du public si l'auteur l'a accepté (App\Services\TestimonyProofs::arePublic).
    @include('testimonies.partials.proofs', ['testimony' => $t, 'proofs' => $t->proofs])
    Voir docs/fonctionnalites/preuves.md
--}}
<div class="card-insight mt-4 p-4" aria-labelledby="proofs-title-{{ $testimony->id }}">
    <h3 id="proofs-title-{{ $testimony->id }}" class="flex items-center gap-2 text-sm font-bold text-sun-700">
        <i class="fa-solid fa-file-shield" aria-hidden="true"></i>Preuves du témoignage
    </h3>
    @php($proofsArePublic = \App\Services\TestimonyProofs::arePublic($testimony))
    <p class="mt-0.5 text-xs text-slate-600">
        @if($proofsArePublic)
            Documents partagés par l’auteur pour confirmer ce témoignage.
        @elseif($testimony->proofs_public)
            L’auteur accepte leur publication : elles seront visibles de tous une fois le témoignage publié.
        @else
            Visibles seulement de l’auteur et de l’équipe de modération.
        @endif
    </p>
    <ul class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach($proofs as $proof)
        @php($proofUrl = route('testimonies.proof', [$testimony->id, $proof->id]))
        <li class="min-w-0">
            <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-3 rounded-xl border border-sun-200 bg-white p-2.5 hover:border-primary-200">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-primary-50 text-primary-600" aria-hidden="true">
                    @if($proof->isPdf())
                    <i class="fa-solid fa-file-pdf text-2xl text-error-500"></i>
                    @else
                    <img src="{{ $proofUrl }}" alt="" class="h-full w-full object-cover" loading="lazy">
                    @endif
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-primary-700">Preuve {{ $proof->position }}</span>
                    <span class="block truncate text-xs text-slate-500">{{ $proof->original_name }} · {{ $proof->sizeLabel() }}</span>
                </span>
                <i class="fa-solid fa-arrow-up-right-from-square ml-auto shrink-0 text-xs text-slate-400" aria-hidden="true"></i>
            </a>
        </li>
        @endforeach
    </ul>
</div>
