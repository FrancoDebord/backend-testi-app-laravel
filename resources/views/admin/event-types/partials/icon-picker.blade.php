{{-- Choix de l'icône d'un type d'événement (clés App\Models\EventType::ICONS) ; $prefix : identifiants uniques, $selected : clé cochée. --}}
<div class="grid grid-cols-5 gap-2 sm:grid-cols-10" role="radiogroup">
    @foreach(\App\Models\EventType::ICONS as $key => $fa)
    <label class="relative cursor-pointer" title="{{ $key }}">
        <input type="radio" name="icon" value="{{ $key }}" id="{{ $prefix }}-icon-{{ $key }}" class="peer sr-only" @checked($selected === $key)>
        <span class="flex h-10 w-full items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 peer-checked:text-primary-600 peer-focus-visible:outline-2 peer-focus-visible:outline-primary-600 hover:border-primary-200">
            <i class="fa-solid {{ $fa }}" aria-hidden="true"></i>
            <span class="sr-only">{{ $key }}</span>
        </span>
    </label>
    @endforeach
</div>
