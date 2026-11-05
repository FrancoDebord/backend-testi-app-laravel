{{-- Onglets du carnet privé : témoignages · paroles prophétiques. Variable : $current (« testimonies » ou « prophecies »). --}}
<nav aria-label="{{ __('layout.nav.journal') }}" class="mb-4 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        <li>
            <a href="{{ route('journal.index') }}" class="{{ $current === 'testimonies' ? 'tab-active' : 'tab' }}" @if($current === 'testimonies') aria-current="page" @endif>
                <i class="fa-solid fa-book-open" aria-hidden="true"></i>{{ __('journal.tabs.testimonies') }}
            </a>
        </li>
        <li>
            <a href="{{ route('prophecies.index') }}" class="{{ $current === 'prophecies' ? 'tab-active' : 'tab' }}" @if($current === 'prophecies') aria-current="page" @endif>
                <i class="fa-solid fa-scroll" aria-hidden="true"></i>{{ __('layout.nav.prophecies') }}
            </a>
        </li>
    </ul>
</nav>
<p class="mb-5 flex items-start gap-2 text-sm text-slate-500">
    <i class="fa-solid fa-lock mt-0.5 text-xs" aria-hidden="true"></i>
    <span>{{ __('journal.tabs.privacy') }}</span>
</p>
