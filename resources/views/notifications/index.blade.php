@extends('layouts.app')
@section('title', 'Notifications')
@php
    $header      = 'Notifications';
    $subheader   = 'Réactions, commentaires et suivi de vos témoignages.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Notifications'],
    ];
    $currentFilter = $filter ?? 'all';
    $filters = ['all' => 'Toutes', 'unread' => 'Non lues', 'comments' => 'Commentaires', 'reactions' => 'Réactions'];
@endphp

@if($notifications->isNotEmpty())
@section('headerActions')
    <form method="POST" action="{{ route('notifications.read-all') }}" data-loading-label="Mise à jour…">
        @csrf
        <button type="submit" class="btn-secondary"><i class="fa-solid fa-check-double"></i>Tout marquer comme lu</button>
    </form>
@endsection
@endif

@section('content')
<div class="mx-auto max-w-3xl">
    <nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="Filtrer les notifications">
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
        @include('components.empty-state', ['title' => 'Aucune notification', 'text' => 'Vous serez averti ici des réactions et commentaires sur vos témoignages.'])
    @else
    <ul class="card mb-6 divide-y divide-slate-100">
        @foreach($notifications as $notif)
        @php
            $notifType = $notif->type->value;
            $notifIcon = match (true) {
                $notifType === 'live_started'        => 'fa-solid fa-tower-broadcast',
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
                    @if(!$notif->is_read)<span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-amber-500 align-middle" title="Non lue"></span>@endif
                    @if($notif->actor_name)<span class="font-semibold">{{ $notif->actor_name }}</span> @endif{{ $notif->message }}
                </p>
                @if($notif->testimony_title)
                <p class="mt-0.5 truncate text-sm text-slate-500 italic">« {{ $notif->testimony_title }} »</p>
                @endif
                <p class="mt-1 text-xs text-slate-400">{{ $notif->created_at->diffForHumans() }}</p>
            </div>
            @if(!$notif->is_read)
            <form method="POST" action="{{ route('notifications.read', $notif->id) }}" data-loading-label="Mise à jour…">
                @csrf
                <button type="submit" class="action-btn-success" title="Marquer comme lue" aria-label="Marquer comme lue">
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
