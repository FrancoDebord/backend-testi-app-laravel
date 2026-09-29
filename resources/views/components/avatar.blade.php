{{-- Avatar : @include('components.avatar', ['user' => $user, 'size' => 'sm|md|lg|xl']) --}}
@php
    $avatarSizes = [
        'xs' => 'h-7 w-7 text-[11px]',
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-9 w-9 text-xs',
        'lg' => 'h-16 w-16 text-lg',
        'xl' => 'h-20 w-20 text-xl',
    ];
    $avatarClass = $avatarSizes[$size ?? 'sm'] ?? $avatarSizes['sm'];
@endphp
@if(!empty($user?->avatar_url))
    <img src="{{ $user->avatar_url }}" alt="" class="{{ $avatarClass }} shrink-0 rounded-full object-cover">
@else
    <span class="{{ $avatarClass }} inline-flex shrink-0 items-center justify-center rounded-full bg-slate-200 font-semibold text-slate-700" aria-hidden="true">
        {{ $user?->initials }}
    </span>
@endif
