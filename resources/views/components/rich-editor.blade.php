{{--
    Éditeur de texte riche, mise en forme visible pendant la saisie (resources/js/app.js, initRichEditor) :
    gras, italique, barré, titre, citation, listes, liens, images, émojis.
    Le contenu est enregistré en Markdown léger dans le champ caché (App\Support\RichText,
    docs/fonctionnalites/texte-riche-et-apercu.md).

    @include('components.rich-editor', [
        'name' => 'body_text', 'id' => 'body_text', 'value' => old('body_text'),
        'labelId' => 'body_text_label', 'placeholder' => '…', 'requiredMessage' => '…',
        'mentions' => true,                                 // facultatif : autocomplétion « @ » (inclure aussi components.mention-input)
        'imageUploadUrl' => route('testimonies.images.store'), // facultatif : bouton « Image »
    ])
--}}
@php
    $rtEmojis = ['🙏', '🙌', '✝️', '❤️', '🕊️', '🔥', '✨', '🌟', '😇', '😊', '🥰', '😍',
                 '🤗', '😌', '😢', '😭', '🎉', '👏', '💪', '🤲', '📖', '⛪', '🌈', '☀️',
                 '💖', '👑', '🎶', '🌿', '💡', '🙂', '✅', '💯'];
    $rtButtons = [
        ['command' => 'bold', 'icon' => 'fa-bold', 'label' => __('rich_editor.bold')],
        ['command' => 'italic', 'icon' => 'fa-italic', 'label' => __('rich_editor.italic')],
        ['command' => 'strikeThrough', 'icon' => 'fa-strikethrough', 'label' => __('rich_editor.strike')],
        null,
        ['block' => 'h3', 'icon' => 'fa-heading', 'label' => __('rich_editor.heading')],
        ['block' => 'blockquote', 'icon' => 'fa-quote-left', 'label' => __('rich_editor.quote')],
        ['command' => 'insertUnorderedList', 'icon' => 'fa-list-ul', 'label' => __('rich_editor.bullets')],
        ['command' => 'insertOrderedList', 'icon' => 'fa-list-ol', 'label' => __('rich_editor.numbers')],
    ];
@endphp
<div class="overflow-hidden rounded-lg border border-slate-300 bg-white shadow-sm focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500"
     data-rich-editor @if(!empty($imageUploadUrl)) data-rt-image-url="{{ $imageUploadUrl }}" data-rt-max-images="{{ \App\Support\RichText::MAX_IMAGES }}" @endif>
    <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5" role="toolbar" aria-label="{{ __('rich_editor.toolbar') }}">
        @foreach($rtButtons as $button)
        @if($button === null)
        <span class="mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>
        @else
        <button type="button" class="rt-btn" aria-pressed="false" aria-label="{{ $button['label'] }}" title="{{ $button['label'] }}"
                @isset($button['command']) data-rt-command="{{ $button['command'] }}" @else data-rt-block="{{ $button['block'] }}" @endisset>
            <i class="fa-solid {{ $button['icon'] }}" aria-hidden="true"></i>
        </button>
        @endif
        @endforeach
        <span class="mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>
        <button type="button" class="rt-btn" data-rt-link-toggle aria-expanded="false" aria-controls="{{ $id }}-link" aria-label="{{ __('rich_editor.link') }}" title="{{ __('rich_editor.link') }}">
            <i class="fa-solid fa-link" aria-hidden="true"></i>
        </button>
        @if(!empty($imageUploadUrl))
        <button type="button" class="rt-btn" data-rt-image aria-label="{{ __('rich_editor.image') }}" title="{{ __('rich_editor.image') }}">
            <i class="fa-regular fa-image" aria-hidden="true"></i>
        </button>
        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-rt-image-input data-autosave-ignore hidden>
        @endif
        <button type="button" class="rt-btn" data-rt-emoji-toggle aria-expanded="false" aria-controls="{{ $id }}-emojis" aria-label="{{ __('rich_editor.emoji') }}" title="{{ __('rich_editor.emoji') }}">
            <i class="fa-regular fa-face-smile" aria-hidden="true"></i>
        </button>
        <span class="ml-auto hidden text-xs text-slate-400 lg:inline">{{ __('rich_editor.shortcuts') }}</span>
    </div>

    {{-- Lien : adresse du lien sur le texte sélectionné (ou l'adresse elle-même si rien n'est sélectionné) --}}
    <div id="{{ $id }}-link" class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white p-2" data-rt-link-panel hidden>
        <input type="url" class="rt-input" inputmode="url" placeholder="https://…" aria-label="{{ __('rich_editor.link_url') }}" data-rt-link-url data-autosave-ignore>
        <button type="button" class="btn-primary btn-sm" data-rt-link-apply>{{ __('rich_editor.link_apply') }}</button>
        <button type="button" class="btn-ghost btn-sm" data-rt-link-remove>{{ __('rich_editor.link_remove') }}</button>
        <p class="form-error w-full" data-rt-link-error hidden>{{ __('rich_editor.link_invalid') }}</p>
    </div>

    <div id="{{ $id }}-emojis" class="grid grid-cols-8 gap-1 border-b border-slate-200 p-2 sm:grid-cols-12 md:grid-cols-16" data-rt-emojis hidden>
        @foreach($rtEmojis as $emoji)
        <button type="button" class="rt-emoji" data-rt-emoji="{{ $emoji }}" aria-label="{{ __('rich_editor.insert', ['emoji' => $emoji]) }}">{{ $emoji }}</button>
        @endforeach
    </div>

    <p class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600" data-rt-image-status role="status" hidden>
        <span class="spinner text-primary-600"></span>{{ __('rich_editor.image_uploading') }}
    </p>

    <div class="rt-content" contenteditable="true" role="textbox" aria-multiline="true" spellcheck="true"
         aria-labelledby="{{ $labelId }}" data-rt-content data-placeholder="{{ $placeholder ?? '' }}" @if(!empty($mentions)) data-mention-input @endif></div>

    <textarea name="{{ $name }}" id="{{ $id }}" data-rt-source hidden>{{ $value }}</textarea>
</div>
<p class="form-error" data-rt-error hidden>{{ $requiredMessage ?? __('rich_editor.required') }}</p>
