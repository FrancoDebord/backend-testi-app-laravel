@extends('layouts.app')
@section('title', __('notifications.page.title'))
@php
    $header      = __('notifications.page.title');
    $subheader   = __('notifications.page.subheader');
    $breadcrumbs = [
        ['label' => __('profile.breadcrumb.home'), 'url' => route('home')],
        ['label' => __('notifications.page.title')],
    ];
    $currentFilter = $filter ?? 'all';
    $filters = ['all' => __('notifications.page.filters.all'), 'unread' => __('notifications.page.filters.unread'), 'comments' => __('notifications.page.filters.comments'), 'reactions' => __('notifications.page.filters.reactions')];
@endphp

@if($notifications->isNotEmpty())
@section('headerActions')
    <form method="POST" action="{{ route('notifications.read-all') }}" data-loading-label="{{ __('notifications.page.updating') }}">
        @csrf
        <button type="submit" class="btn-secondary"><i class="fa-solid fa-check-double"></i>{{ __('notifications.page.mark_all') }}</button>
    </form>
@endsection
@endif

@section('content')
<div class="mx-auto max-w-3xl">
    <nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="{{ __('notifications.page.filters_aria') }}">
        <ul class="flex gap-6">
            @foreach($filters as $val => $label)
            <li>
                <a href="{{ route('notifications.index', ['filter' => $val]) }}"
                   class="{{ $currentFilter === $val ? 'tab-active' : 'tab' }}"
                   @if($currentFilter === $val) aria-current="page" @endif>{{ $label }}</a>
            </li>
            @endforeach
        </ul>
    </nav>

    @if($notifications->isEmpty())
        @include('components.empty-state', ['title' => __('notifications.page.empty_title'), 'text' => __('notifications.page.empty_text')])
    @else
    <ul class="card mb-6 divide-y divide-slate-100">
        @foreach($notifications as $notif)
        @php
            $notifType = $notif->type->value;
            $notifIcon = match (true) {
                $notifType === 'live_started'        => 'fa-solid fa-tower-broadcast',
                $notifType === 'comment_liked'       => 'fa-regular fa-heart',
                $notifType === 'account_verified'    => 'fa-solid fa-circle-check',
                $notifType === 'account_approval_revoked' => 'fa-solid fa-circle-minus',
                str_starts_with($notifType, 'event_') => 'fa-regular fa-calendar',
                str_contains($notifType, 'like')     => 'fa-regular fa-thumbs-up',
                str_contains($notifType, 'comment')  => 'fa-regular fa-comment',
                str_contains($notifType, 'follow')   => 'fa-solid fa-user-plus',
                str_contains($notifType, 'approved') => 'fa-solid fa-check',
                str_contains($notifType, 'rejected') => 'fa-solid fa-xmark',
                str_contains($notifType, 'pray')     => 'fa-solid fa-hands-praying',
                default                              => 'fa-regular fa-bell',
            };
        @endphp
        <li class="{{ $notif->is_read ? '' : 'bg-slate-50/70' }} flex items-start gap-3 px-4 py-4 sm:px-5">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm text-slate-500">
                <i class="{{ $notifIcon }}"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm break-words {{ $notif->is_read ? 'text-slate-700' : 'font-medium text-slate-900' }}">
                    @if(!$notif->is_read)<span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-amber-500 align-middle" title="{{ __('notifications.page.unread') }}"></span>@endif
                    {{-- Le message commence le plus souvent par le nom de l'auteur (« Marie a aimé… ») : le nom n'est alors
                         pas répété devant, seulement mis en gras. --}}
                    @php
                        $notifActor   = trim((string) $notif->actor_name);
                        $notifMessage = (string) $notif->message;
                        $actorInMessage = $notifActor !== '' && \Illuminate\Support\Str::startsWith($notifMessage, $notifActor);
                    @endphp
                    @if($actorInMessage)<span class="font-semibold">{{ $notifActor }}</span>{{ mb_substr($notifMessage, mb_strlen($notifActor)) }}@elseif($notifActor !== '')<span class="font-semibold">{{ $notifActor }}</span> {{ $notifMessage }}@else{{ $notifMessage }}@endif
                </p>
                @if($notif->testimony_title)
                <p class="mt-0.5 truncate text-sm text-slate-500 italic">{{ __('notifications.page.quoted', ['title' => $notif->testimony_title]) }}</p>
                @endif
                @if(is_array($notif->payload) && filled($notif->payload['event_id'] ?? null))
                <a href="{{ route('events.show', $notif->payload['event_id']) . ($notifType === 'event_survey' ? '#votre-avis' : '') }}" class="mt-1 inline-block text-sm font-semibold text-primary-600 hover:underline">{{ $notifType === 'event_survey' ? __('notifications.page.give_feedback') : __('notifications.page.view_event') }}</a>
                @endif
                <p class="mt-1 text-xs text-slate-400">{{ $notif->created_at->diffForHumans() }}</p>
            </div>
            @if(!$notif->is_read)
            <form method="POST" action="{{ route('notifications.read', $notif->id) }}" data-loading-label="{{ __('notifications.page.updating') }}">
                @csrf
                <button type="submit" class="action-btn-success" title="{{ __('notifications.page.mark_read') }}" aria-label="{{ __('notifications.page.mark_read') }}">
                    <i class="fa-solid fa-check"></i>
                </button>
            </form>
            @endif
        </li>
        @endforeach
    </ul>
    {{ $notifications->withQueryString()->links() }}
    @endif
</div>
@endsection
