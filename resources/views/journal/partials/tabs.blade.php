{{-- Onglets du carnet privé : témoignages · paroles prophétiques. Variable : $current (« testimonies » ou « prophecies »). --}}
<nav aria-label="Carnet privé" class="mb-4 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        <li>
            <a href="{{ route('journal.index') }}" class="{{ $current === 'testimonies' ? 'tab-active' : 'tab' }}" @if($current === 'testimonies') aria-current="page" @endif>
                <i class="fa-solid fa-book-open" aria-hidden="true"></i>Témoignages
            </a>
        </li>
        <li>
            <a href="{{ route('prophecies.index') }}" class="{{ $current === 'prophecies' ? 'tab-active' : 'tab' }}" @if($current === 'prophecies') aria-current="page" @endif>
                <i class="fa-solid fa-scroll" aria-hidden="true"></i>Paroles prophétiques
            </a>
        </li>
    </ul>
</nav>
<p class="mb-5 flex items-start gap-2 text-sm text-slate-500">
    <i class="fa-solid fa-lock mt-0.5 text-xs" aria-hidden="true"></i>
    <span>Visible de vous seul : ni les autres utilisateurs ni l'équipe de modération ne voient votre carnet. Il est enregistré en ligne et se retrouve aussi dans l'application.</span>
</p>
