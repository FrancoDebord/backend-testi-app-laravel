{{-- Pied de page : liens vers toutes les catégories de témoignages et les directs (maillage interne,
     docs/fonctionnalites/referencement.md). Liste des catégories gardée en cache une heure. --}}
@php
    $footerCategories = \Illuminate\Support\Facades\Cache::remember('seo:footer-categories:' . app()->getLocale(), 3600, fn () => \App\Models\Category::where('is_active', true)
        ->orderBy('display_order')->get(['id', 'slug', 'name', 'name_en'])
        ->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->displayName()])->all());
@endphp
<nav class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-slate-100 pb-3 text-xs text-slate-500" aria-label="{{ __('seo.footer.categories') }}">
    <span class="font-semibold text-slate-700">{{ __('seo.footer.categories') }}</span>
    @foreach($footerCategories as $footerCategory)
    <a href="{{ route('testimonies.category', $footerCategory['slug']) }}" class="hover:text-slate-900">{{ $footerCategory['name'] }}</a>
    @endforeach
    <a href="{{ route('lives.index') }}" class="hover:text-slate-900">{{ __('seo.footer.lives') }}</a>
</nav>
