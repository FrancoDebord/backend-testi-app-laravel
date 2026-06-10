@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0"><i class="bi bi-bell-fill me-2 text-primary"></i>Notifications</h4>
                @if($notifications->isNotEmpty())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-check2-all me-1"></i>Tout marquer comme lu
                    </button>
                </form>
                @endif
            </div>

            {{-- Filter tabs --}}
            <ul class="nav nav-tabs mb-4">
                @foreach(['all' => 'Toutes', 'unread' => 'Non lues', 'comments' => 'Commentaires', 'reactions' => 'Réactions'] as $val => $label)
                <li class="nav-item">
                    <a class="nav-link small {{ ($filter ?? 'all') === $val ? 'active fw-semibold' : 'text-muted' }}"
                       href="{{ route('notifications.index', ['filter' => $val]) }}">{{ $label }}</a>
                </li>
                @endforeach
            </ul>

            @if($notifications->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bell-slash" style="font-size:4rem;opacity:.2;"></i>
                <p class="mt-3 fw-semibold">Aucune notification</p>
            </div>
            @else
            <div class="d-flex flex-column gap-2">
                @foreach($notifications as $notif)
                <div class="card border-0 shadow-sm {{ !$notif->is_read ? 'border-start border-4 border-primary' : '' }}" style="{{ !$notif->is_read ? 'border-left-color:#6366f1!important;' : '' }}">
                    <div class="card-body p-3 d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:42px;height:42px;font-size:1.2rem;">
                            {{ match(true) {
                                str_contains($notif->type->value, 'like') => '👍',
                                str_contains($notif->type->value, 'comment') => '💬',
                                str_contains($notif->type->value, 'follow') => '👤',
                                str_contains($notif->type->value, 'approved') => '✅',
                                str_contains($notif->type->value, 'rejected') => '❌',
                                str_contains($notif->type->value, 'pray') => '🙏',
                                default => '🔔'
                            } }}
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-1 small {{ !$notif->is_read ? 'fw-semibold' : '' }}">
                                @if($notif->actor_name)<strong>{{ $notif->actor_name }}</strong> @endif
                                {{ $notif->message }}
                            </p>
                            @if($notif->testimony_title)
                            <p class="text-muted small mb-1 fst-italic">« {{ Str::limit($notif->testimony_title, 60) }} »</p>
                            @endif
                            <p class="text-muted mb-0" style="font-size:.72rem;">{{ $notif->created_at->diffForHumans() }}</p>
                        </div>
                        @if(!$notif->is_read)
                        <form method="POST" action="{{ route('notifications.read', $notif->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-light">
                                <i class="bi bi-check2"></i>
                            </button>
                        </form>
                        @else
                        <span class="badge bg-success rounded-circle p-1"><i class="bi bi-check2"></i></span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-center mt-4">{{ $notifications->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
