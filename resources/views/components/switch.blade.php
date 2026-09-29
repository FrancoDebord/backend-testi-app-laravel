{{-- Interrupteur : @include('components.switch', ['name' => '…', 'checked' => bool, 'label' => 'libellé accessible', 'sendFalse' => bool]) --}}
<label class="relative inline-flex shrink-0 cursor-pointer items-center">
    @if(!empty($sendFalse))<input type="hidden" name="{{ $name }}" value="0">@endif
    <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" {{ $checked ? 'checked' : '' }} aria-label="{{ $label }}">
    <span class="h-6 w-11 rounded-full bg-slate-300 transition-colors peer-checked:bg-emerald-500 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2
                 after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:after:translate-x-5"></span>
</label>
