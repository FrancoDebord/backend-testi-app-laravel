{{-- État vide : @include('components.empty-state', ['title' => '…', 'text' => '…', 'actionUrl' => '…', 'actionLabel' => '…']) --}}
<div class="card px-6 py-12 text-center">
    <p class="text-sm font-semibold text-slate-900">{{ $title }}</p>
    @if(!empty($text))<p class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ $text }}</p>@endif
    @if(!empty($actionUrl))
    <a href="{{ $actionUrl }}" class="{{ !empty($actionPrimary) ? 'btn-primary' : 'btn-secondary' }} mt-5">{{ $actionLabel }}</a>
    @endif
</div>
