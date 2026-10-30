@extends('layouts.app')
@section('title', $playlist->title)
{{--
    Page d'une liste de témoignages (docs/fonctionnalites/listes-de-temoignages.md) : ouverte à tous, seuls les
    témoignages visibles par la personne y figurent ; l'auteur range, retire et ajoute ses témoignages.
--}}
@php
    $owner       = $playlist->user;
    $visibleCount = count($visibleIds);
    $header      = $playlist->title;
    $subheader   = __('playlists.show.by', ['name' => $owner?->display_name]) . ' · ' . trans_choice('playlists.mine.count', $visibleCount, ['count' => $visibleCount]);
    $breadcrumbs = $isOwner
        ? [['label' => __('testimonies.common.home'), 'url' => route('home')], ['label' => __('playlists.nav'), 'url' => route('playlists.mine')], ['label' => $playlist->title]]
        : [['label' => __('testimonies.common.home'), 'url' => route('home')], ['label' => $playlist->title]];
    $firstVisible = $items->first(fn ($t) => in_array($t->id, $visibleIds, true));
    $shareUrl    = rtrim((string) config('app.share_url'), '/') . route('playlists.show', $playlist->id, false);
@endphp

@section('headerActions')
    @if($firstVisible)
    <a href="{{ route('testimonies.show', ['id' => $firstVisible->id, 'list' => $playlist->id]) }}" class="btn-primary"><i class="fa-solid fa-play" aria-hidden="true"></i>{{ __('playlists.show.play_all') }}</a>
    @endif
@endsection

@section('content')
<div class="mx-auto max-w-4xl space-y-4">
    <section class="card p-4 sm:p-5">
        <div class="flex flex-wrap items-start gap-3">
            <a href="{{ $owner ? route('profiles.show', $owner->id) : '#' }}" class="flex min-w-0 items-center gap-3">
                @if($owner)@include('components.avatar', ['user' => $owner, 'size' => 'md'])@endif
                <span class="min-w-0 text-sm font-semibold break-words text-slate-900">{{ $owner?->display_name }}</span>
            </a>
            <div class="ml-auto flex flex-wrap gap-2">
                <button type="button" class="chip" data-share="{{ $shareUrl }}" data-share-title="{{ $playlist->title }}">
                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>{{ __('playlists.show.share') }}
                </button>
                @if($isOwner)
                <button type="button" class="chip" data-modal-open="playlist-edit-modal"><i class="fa-solid fa-pen" aria-hidden="true"></i>{{ __('playlists.show.edit') }}</button>
                <button type="button" class="chip text-error-600"
                        onclick="openConfirmModal('playlist-delete-form', @js(__('playlists.show.delete_confirm')), @js(__('playlists.show.delete_title')), @js(__('playlists.show.delete')), 'fa-trash')">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>{{ __('playlists.show.delete') }}
                </button>
                <form id="playlist-delete-form" method="POST" action="{{ route('playlists.destroy', $playlist->id) }}" hidden>@csrf @method('DELETE')</form>
                @endif
            </div>
        </div>
        @if(filled($playlist->description))
        <p class="mt-3 text-sm break-words whitespace-pre-line text-slate-700">{{ $playlist->description }}</p>
        @endif
    </section>

    @if($items->isEmpty())
        @include('components.empty-state', ['title' => __('playlists.show.empty')])
    @else
    <ol class="card divide-y divide-slate-100">
        @foreach($items as $i => $item)
        @php
            $visible   = in_array($item->id, $visibleIds, true);
            $type      = $item->type->value;
            $thumbIcon = ['text' => 'fa-quote-left', 'audio' => 'fa-microphone'][$type] ?? 'fa-play';
            $url       = route('testimonies.show', ['id' => $item->id, 'list' => $playlist->id]);
        @endphp
        <li class="flex flex-wrap items-center gap-3 p-3 sm:flex-nowrap">
            <span class="w-6 shrink-0 text-center text-sm text-slate-500 tabular-nums">{{ $i + 1 }}</span>
            <a href="{{ $url }}" class="flex min-w-0 flex-1 items-center gap-3">
                <span class="{{ $type === 'text' ? 'bg-sun-50 text-sun-400' : 'bg-slate-900 text-slate-600' }} relative flex aspect-video w-24 shrink-0 items-center justify-center overflow-hidden rounded-lg sm:w-32" aria-hidden="true">
                    @if($item->cover_url)
                    <img src="{{ $item->cover_url }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                    @else
                    <i class="fa-solid {{ $thumbIcon }}"></i>
                    @endif
                    @if($duration = $item->durationLabel())
                    <span class="absolute right-1 bottom-1 rounded bg-slate-900/80 px-1 text-[11px] font-medium text-white tabular-nums">{{ $duration }}</span>
                    @endif
                </span>
                <span class="min-w-0">
                    <span class="line-clamp-2 text-sm font-semibold break-words text-slate-900">{{ $item->title }}</span>
                    <span class="mt-0.5 block text-xs text-slate-500">{{ $item->viewsLabel() }}</span>
                    @unless($visible)
                    <span class="badge-pending mt-1">{{ __('playlists.show.not_visible') }}</span>
                    @endunless
                </span>
            </a>
            @if($isOwner)
            <div class="flex w-full shrink-0 justify-end gap-1.5 sm:w-auto">
                @foreach([['up', 'fa-arrow-up', __('playlists.show.move_up'), $i > 0], ['down', 'fa-arrow-down', __('playlists.show.move_down'), $i < $items->count() - 1]] as [$direction, $icon, $label, $enabled])
                <form method="POST" action="{{ route('playlists.items.move', [$playlist->id, $item->id]) }}">
                    @csrf
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <button type="submit" class="action-btn-edit" title="{{ $label }}" aria-label="{{ $label }}" @disabled(!$enabled)><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></button>
                </form>
                @endforeach
                <form method="POST" action="{{ route('playlists.items.destroy', [$playlist->id, $item->id]) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="action-btn-delete" title="{{ __('playlists.show.remove') }}" aria-label="{{ __('playlists.show.remove') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </form>
            </div>
            @endif
        </li>
        @endforeach
    </ol>
    @endif

    @if($isOwner)
    <section class="card p-4 sm:p-5" aria-labelledby="playlist-add-title">
        <h2 id="playlist-add-title" class="card-title">{{ __('playlists.show.add_title') }}</h2>
        @if($addable->isEmpty())
        <p class="mt-2 text-sm text-slate-500">{{ __('playlists.show.add_none') }}</p>
        @else
        <form method="POST" action="{{ route('playlists.items.store', $playlist->id) }}" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
            @csrf
            <div class="min-w-0 flex-1">
                <label for="playlist-add" class="sr-only">{{ __('playlists.show.add_choose') }}</label>
                <select id="playlist-add" name="testimony_id" required class="form-input">
                    <option value="">{{ __('playlists.show.add_choose') }}</option>
                    @foreach($addable as $candidate)
                    <option value="{{ $candidate->id }}">{{ Str::limit($candidate->title, 90) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-secondary"><i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('playlists.show.add') }}</button>
        </form>
        @endif
        @if($errors->any())
        <p class="form-error">{{ $errors->first() }}</p>
        @endif
    </section>
    @include('playlists.partials.form-modal', ['modalId' => 'playlist-edit-modal', 'playlist' => $playlist])
    @endif
</div>
@endsection
