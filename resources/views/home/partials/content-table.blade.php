{{--
    Accueil : tableau des contenus. « Gestion des contenus » (équipe de modération, onglets par statut)
    ou « Mes témoignages » (personne connectée). Tableau à partir de 768 px, fiches en dessous.
    Variables : $tableTitle, $items, $tabs (null : pas d'onglets), $moreUrl ; $contentTab, $pendingCount (équipe).
--}}
@php
    $currentTab = $contentTab ?? '';
    $openUrl = fn ($t) => $t->status->value === 'pending' && auth()->user()?->canModerate()
        ? route('moderation.show', $t->id)
        : route('testimonies.show', $t->id);
@endphp
<section id="gestion" class="card scroll-mt-24" aria-labelledby="home-content-title">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-3 border-b border-slate-100 px-5 py-4">
        <h2 id="home-content-title" class="card-title">{{ $tableTitle }}</h2>
        @if($tabs)
        <nav class="-mx-5 flex min-w-0 flex-1 gap-2 overflow-x-auto px-5 sm:mx-0 sm:px-0" aria-label="Filtrer par statut">
            @foreach($tabs as $key => $label)
            <a href="{{ route('home', array_filter(['gestion' => $key ?: null])) }}#gestion"
               class="{{ $currentTab === $key ? 'chip-active' : 'chip' }} text-xs" @if($currentTab === $key) aria-current="true" @endif>
                {{ $label }}@if($key === 'pending' && ($pendingCount ?? 0) > 0) ({{ $pendingCount }})@endif
            </a>
            @endforeach
        </nav>
        @else
        <span class="flex-1"></span>
        @endif
        <a href="{{ $moreUrl }}" class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Voir tout<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
    </div>

    @if($items->isEmpty())
    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ $tabs ? 'Aucun contenu dans cette catégorie.' : "Vous n'avez encore publié aucun témoignage." }}</p>
    @else
    {{-- Tableau (768 px et plus) --}}
    <div class="hidden overflow-x-auto md:block">
        <table class="min-w-full">
            <thead>
                <tr>
                    <th scope="col" class="table-th">Titre</th>
                    <th scope="col" class="table-th hidden 2xl:table-cell">Type</th>
                    <th scope="col" class="table-th hidden 2xl:table-cell">Catégorie</th>
                    @if($tabs)<th scope="col" class="table-th">Auteur</th>@endif
                    <th scope="col" class="table-th">Date</th>
                    <th scope="col" class="table-th">Statut</th>
                    <th scope="col" class="table-th text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $t)
                <tr>
                    <td class="table-td max-w-72">
                        <span class="flex min-w-0 items-center gap-3">
                            <span class="flex h-8 w-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-primary-50 text-xs text-primary-600" aria-hidden="true">
                                @if($t->cover_url)<img src="{{ $t->cover_url }}" alt="" class="h-full w-full object-cover" loading="lazy">@else<i class="fa-solid {{ ['video' => 'fa-video', 'audio' => 'fa-microphone', 'text' => 'fa-file-lines'][$t->type->value] ?? 'fa-file' }}"></i>@endif
                            </span>
                            <span class="truncate font-medium text-slate-900">{{ $t->title }}</span>
                        </span>
                    </td>
                    <td class="table-td hidden whitespace-nowrap 2xl:table-cell">{{ $t->type->label() }}</td>
                    <td class="table-td hidden 2xl:table-cell">@if($t->category)<span class="{{ $t->category->presentation()['badge'] }}">{{ $t->category->name }}</span>@else<span class="text-slate-400">—</span>@endif</td>
                    @if($tabs)<td class="table-td max-w-36"><span class="block truncate">{{ $t->user?->display_name }}</span></td>@endif
                    <td class="table-td whitespace-nowrap">{{ $t->created_at->format('d/m/Y') }}</td>
                    <td class="table-td"><span class="{{ $t->status->badgeClass() }}">{{ $t->status->label() }}</span></td>
                    <td class="table-td text-right">
                        <a href="{{ $openUrl($t) }}" class="{{ $t->status->value === 'pending' && $tabs ? 'action-btn-edit' : 'action-btn-view' }}"
                           title="{{ $t->status->value === 'pending' && $tabs ? 'Modérer' : 'Voir' }}" aria-label="{{ $t->status->value === 'pending' && $tabs ? 'Modérer' : 'Voir' }} « {{ $t->title }} »">
                            <i class="fa-solid {{ $t->status->value === 'pending' && $tabs ? 'fa-pen' : 'fa-eye' }}" aria-hidden="true"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Fiches (moins de 768 px) --}}
    <ul class="divide-y divide-slate-100 md:hidden">
        @foreach($items as $t)
        <li>
            <a href="{{ $openUrl($t) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                <span class="flex h-10 w-14 shrink-0 items-center justify-center overflow-hidden rounded-md bg-primary-50 text-primary-600" aria-hidden="true">
                    @if($t->cover_url)<img src="{{ $t->cover_url }}" alt="" class="h-full w-full object-cover" loading="lazy">@else<i class="fa-solid {{ ['video' => 'fa-video', 'audio' => 'fa-microphone', 'text' => 'fa-file-lines'][$t->type->value] ?? 'fa-file' }}"></i>@endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-primary-700">{{ $t->title }}</span>
                    <span class="block truncate text-[11px] text-slate-500">{{ $t->type->label() }}@if($tabs) · {{ $t->user?->display_name }}@endif · {{ $t->created_at->format('d/m/Y') }}</span>
                </span>
                <span class="{{ $t->status->badgeClass() }} shrink-0">{{ $t->status->label() }}</span>
            </a>
        </li>
        @endforeach
    </ul>
    @endif
</section>
