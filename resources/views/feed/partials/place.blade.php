{{-- En tête de « Mon fil » : « Près de chez vous · Cotonou » (lien vers le réglage) et, tant que la
     position de l'appareil n'est pas utilisée, une invitation refermable (une fois, mémorisée dans le navigateur).
     Variable : $place = Recommendations::placeMeta() (label, precision, source) ou null. --}}
<div class="mb-4 space-y-3">
    @if($place && $place['label'])
    <a href="{{ route('profile.settings') }}#localisation"
       class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700 hover:bg-primary-100">
        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
        <span>Près de chez vous · {{ $place['label'] }}</span>
    </a>
    @endif
    @if(($place['source'] ?? null) !== 'gps')
    <div class="alert-info hidden" role="status" data-location-prompt>
        <i class="fa-solid fa-location-crosshairs mt-0.5" aria-hidden="true"></i>
        <p class="flex-1">Activez votre position pour des témoignages proches de vous.
            <a href="{{ route('profile.settings') }}#localisation" class="font-semibold underline">Activer</a></p>
        <button type="button" class="ml-2 text-slate-500 hover:text-slate-700" aria-label="Fermer" data-location-prompt-close>
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <script>
    (() => {
        const box = document.querySelector('[data-location-prompt]');
        if (!box) return;
        const key = 'testi.locationPromptDismissed';
        let dismissed = false;
        try { dismissed = localStorage.getItem(key) === '1'; } catch (e) {}
        if (!dismissed) box.classList.remove('hidden');
        box.querySelector('[data-location-prompt-close]').addEventListener('click', () => {
            box.classList.add('hidden');
            try { localStorage.setItem(key, '1'); } catch (e) {}
        });
    })();
    </script>
    @endif
</div>
