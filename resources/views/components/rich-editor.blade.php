{{--
    Éditeur de texte mis en forme (gras, italique, émojis).
    Le contenu est enregistré en Markdown léger (**gras**, *italique*) dans le champ caché.

    @include('components.rich-editor', [
        'name' => 'body_text', 'id' => 'body_text', 'value' => old('body_text'),
        'labelId' => 'body_text_label', 'placeholder' => '…', 'requiredMessage' => '…',
    ])
--}}
@php
    $rtEmojis = ['🙏', '🙌', '✝️', '❤️', '🕊️', '🔥', '✨', '🌟', '😇', '😊', '🥰', '😍',
                 '🤗', '😌', '😢', '😭', '🎉', '👏', '💪', '🤲', '📖', '⛪', '🌈', '☀️',
                 '💖', '👑', '🎶', '🌿', '💡', '🙂', '✅', '💯'];
@endphp
<div class="overflow-hidden rounded-lg border border-slate-300 bg-white shadow-sm focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500"
     data-rich-editor>
    <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5" role="toolbar" aria-label="Mise en forme du texte">
        <button type="button" class="rt-btn" data-rt-command="bold" aria-pressed="false" aria-label="Gras" title="Gras (Ctrl+B)">
            <i class="fa-solid fa-bold"></i>
        </button>
        <button type="button" class="rt-btn" data-rt-command="italic" aria-pressed="false" aria-label="Italique" title="Italique (Ctrl+I)">
            <i class="fa-solid fa-italic"></i>
        </button>
        <span class="mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>
        <button type="button" class="rt-btn" data-rt-emoji-toggle aria-expanded="false" aria-controls="{{ $id }}-emojis" aria-label="Insérer un émoji" title="Insérer un émoji">
            <i class="fa-regular fa-face-smile"></i>
        </button>
        <span class="ml-auto hidden text-xs text-slate-400 sm:inline">Ctrl+B : gras · Ctrl+I : italique</span>
    </div>

    <div id="{{ $id }}-emojis" class="grid grid-cols-8 gap-1 border-b border-slate-200 p-2 sm:grid-cols-12 md:grid-cols-16" data-rt-emojis hidden>
        @foreach($rtEmojis as $emoji)
        <button type="button" class="rt-emoji" data-rt-emoji="{{ $emoji }}" aria-label="Insérer {{ $emoji }}">{{ $emoji }}</button>
        @endforeach
    </div>

    <div class="rt-content" contenteditable="true" role="textbox" aria-multiline="true" spellcheck="true"
         aria-labelledby="{{ $labelId }}" data-rt-content data-placeholder="{{ $placeholder ?? '' }}"></div>

    <textarea name="{{ $name }}" id="{{ $id }}" data-rt-source hidden>{{ $value }}</textarea>
</div>
<p class="form-error" data-rt-error hidden>{{ $requiredMessage ?? 'Ce champ est obligatoire.' }}</p>
