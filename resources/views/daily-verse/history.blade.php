@extends('layouts.app')
{{--
    Versets précédents : historique des versets du jour, du plus récent au plus ancien, comme l'écran de
    l'application (VerseHistoryScreen) : un jour par carte (aujourd'hui mis en avant), date · thème, verset,
    référence, « J'aime », prières, commentaires et témoignages (réactions de la personne en couleur).
    Un clic ouvre le verset du jour concerné. docs/fonctionnalites/verset-du-jour.md
--}}
@section('title', __('daily_verse_history.title'))
{{-- Page publique indexable (docs/fonctionnalites/referencement.md) --}}
@section('robots', \App\Support\Seo::INDEX)
@php
    $header      = __('daily_verse_history.title');
    $subheader   = __('daily_verse_history.subtitle');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('daily_verse_history.today'), 'url' => route('daily-verse.show')],
        ['label' => __('daily_verse_history.title')],
    ];
    $today = \App\Services\VerseDesignOfTheDay::todayDate();
    $num   = fn ($n) => \App\Support\Locales::number($n);
@endphp

@section('headerActions')
    <a href="{{ route('daily-verse.show') }}" class="btn-primary"><i class="fa-solid fa-sun" aria-hidden="true"></i>{{ __('daily_verse_history.today') }}</a>
@endsection

@section('content')
<div class="mx-auto max-w-3xl">
    @if($items === [])
        @include('components.empty-state', ['title' => __('daily_verse_history.empty')])
    @else
    <ul class="space-y-4">
        @foreach($items as $item)
        @php
            $v       = $item['verse'];
            $isToday = $item['date'] === $today;
            $liked   = in_array('like', $item['mine'], true);
            $prayed  = in_array('pray', $item['mine'], true);
            $url     = $isToday ? route('daily-verse.show') : route('daily-verse.show', ['date' => $item['date']]);
            $shareUrl = \App\Services\DailyVerses::shareUrl($item['date']);
            $label   = collect([
                $isToday ? __('daily_verse_history.today_label') : \Illuminate\Support\Carbon::parse($item['date'])->translatedFormat('l j F Y'),
                $v->theme ? $v->themeLabel() : null,
            ])->filter()->implode(' · ');
        @endphp
        <li>
            <article class="{{ $isToday ? 'card ring-2 ring-sun-400' : 'card' }} group relative p-5 transition-shadow hover:shadow-lg">
                <p class="flex items-center justify-between gap-3 text-xs font-bold tracking-wide text-primary-600 uppercase">
                    <span class="min-w-0 truncate">@if($isToday)<i class="fa-solid fa-sun mr-1 text-sun-500" aria-hidden="true"></i>@endif{{ $label }}</span>
                    <i class="fa-solid fa-chevron-right shrink-0 text-slate-400" aria-hidden="true"></i>
                </p>
                <blockquote class="mt-2 text-lg leading-relaxed text-slate-900">
                    <a href="{{ $url }}" class="rounded-sm after:absolute after:inset-0 after:content-[''] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">« {{ \Illuminate\Support\Str::limit(trim($v->verse_text), 280) }} »</a>
                </blockquote>
                <p class="mt-1 text-sm font-bold text-primary-700">{{ $v->reference }}</p>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                    <li class="{{ $liked ? 'font-semibold text-error-700' : '' }}" @if($liked) title="{{ __('daily_verse_history.mine_like') }}" @endif>
                        <i class="{{ $liked ? 'fa-solid' : 'fa-regular' }} fa-heart mr-1" aria-hidden="true"></i>{{ trans_choice('daily_verse_history.likes', $v->like_count, ['count' => $num($v->like_count)]) }}
                    </li>
                    <li class="{{ $prayed ? 'font-semibold text-primary-600' : '' }}" @if($prayed) title="{{ __('daily_verse_history.mine_pray') }}" @endif>
                        <i class="fa-solid fa-hands-praying mr-1" aria-hidden="true"></i>{{ trans_choice('daily_verse_history.prayers', $v->prayer_count, ['count' => $num($v->prayer_count)]) }}
                    </li>
                    <li><i class="fa-regular fa-comment mr-1" aria-hidden="true"></i>{{ trans_choice('daily_verse_history.comments', $item['comments'], ['count' => $num($item['comments'])]) }}</li>
                    <li><i class="fa-solid fa-feather-pointed mr-1" aria-hidden="true"></i>{{ trans_choice('daily_verse_history.testimonies', $item['testimonies'], ['count' => $num($item['testimonies'])]) }}</li>
                </ul>
                {{-- Partage sans ouvrir le verset : au-dessus du lien étiré de la carte (z-10). Lien toujours daté. --}}
                <div class="relative z-10 flex shrink-0 items-center gap-1"
                     data-history-share data-share-url="{{ $shareUrl }}"
                     data-share-text="« {{ trim($v->verse_text) }} »&#10;— {{ $v->reference }}"
                     data-share-image="{{ route('daily-verse.image', ['date' => $item['date']]) }}" data-share-filename="verset-du-jour-{{ $item['date'] }}.png">
                    <button type="button" class="btn-secondary btn-sm" data-history-share-open>
                        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>{{ __('daily_verse_history.share') }}
                    </button>
                    <button type="button" class="btn-ghost btn-sm" data-history-share-copy title="{{ __('daily_verse_history.copy_link') }}" aria-label="{{ __('daily_verse_history.copy_link') }}">
                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                    </button>
                </div>
                </div>
            </article>
        </li>
        @endforeach
    </ul>
    @endif

    @if($lastPage > 1)
    <nav class="mt-8 flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="{{ __('daily_verse_history.pagination') }}">
        @if($page > 1)
        <a class="btn-secondary btn-sm" href="{{ route('daily-verse.history', ['page' => $page - 1]) }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>{{ __('daily_verse_history.newer') }}</a>
        @else <span></span> @endif
        <span class="text-slate-500">{{ __('daily_verse_history.page_of', ['page' => $page, 'last' => $lastPage]) }}</span>
        @if($page < $lastPage)
        <a class="btn-secondary btn-sm" href="{{ route('daily-verse.history', ['page' => $page + 1]) }}">{{ __('daily_verse_history.older') }}<i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        @else <span></span> @endif
    </nav>
    @endif
</div>
@endsection

@push('scripts')
<script>
// Partage depuis l'historique (docs/fonctionnalites/verset-du-jour.md › Lien de partage) : l'image du verset
// ET le lien daté dans la feuille du système, sinon texte et lien, sinon copie. Image chargée au survol ou
// au toucher du bouton pour que la feuille s'ouvre pendant le clic.
(() => {
    const files = new Map();
    const imageFile = (box) => {
        if (!navigator.canShare) return Promise.resolve(null);
        const src = box.dataset.shareImage;
        if (!files.has(src)) {
            files.set(src, fetch(src)
                .then((r) => (r.ok ? r.blob() : null))
                .then((b) => (b ? new File([b], box.dataset.shareFilename, { type: 'image/png' }) : null))
                .catch(() => null));
        }
        return files.get(src);
    };
    const copy = async (box) => {
        const url = box.dataset.shareUrl;
        try {
            await navigator.clipboard.writeText(url);
        } catch (e) {
            const field = Object.assign(document.createElement('textarea'), { value: url });
            document.body.append(field);
            field.select();
            document.execCommand('copy');
            field.remove();
        }
        window.flash?.(@json(__('daily_verse_history.link_copied')));
    };
    document.querySelectorAll('[data-history-share]').forEach((box) => {
        const open = box.querySelector('[data-history-share-open]');
        ['pointerenter', 'touchstart', 'focus'].forEach((ev) => open.addEventListener(ev, () => imageFile(box), { passive: true }));
        box.querySelector('[data-history-share-copy]').addEventListener('click', () => copy(box));
        open.addEventListener('click', async () => {
            const { shareUrl: url, shareText: text } = box.dataset;
            const title = @json(__('daily_verse_history.share_title'));
            if (!navigator.share) {
                copy(box);
                return;
            }
            try {
                const file = await imageFile(box);
                if (file && navigator.canShare({ files: [file] })) {
                    // Le lien va dans le texte : avec un fichier, beaucoup d'applications ignorent `url`.
                    try {
                        await navigator.share({ files: [file], title, text: text + '\n' + url });
                        return;
                    } catch (e) {
                        if (e.name === 'AbortError') return;
                    }
                }
                await navigator.share({ title, text, url });
            } catch (e) { /* partage annulé */ }
        });
    });
})();
</script>
@endpush
