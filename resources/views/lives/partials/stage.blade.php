{{--
    Intervenants du direct (docs/fonctionnalites/lives-intervenants.md) — rempli et mis à jour par live.js.
    Diffuseur et modérateurs : file d'attente et intervenant en cours. Spectateurs : demande d'intervention.
    Une seule personne invitée ou à l'antenne à la fois.
--}}
@php
    $stageStaff = $live->canBeModeratedBy(Auth::user());
@endphp
@if($live->isActive())
<section class="card min-w-0" data-stage-panel aria-labelledby="stage-title">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
        <h2 id="stage-title" class="card-title">{{ $stageStaff ? __('lives.stage.speakers') : __('lives.stage.testify') }}</h2>
        @if($stageStaff)
        <label class="flex items-center gap-2 text-xs text-slate-600">
            <span>{{ __('lives.stage.accept_requests') }}</span>
            <input type="checkbox" class="peer sr-only" data-stage-enabled @checked($live->speakers_enabled) aria-label="{{ __('lives.stage.accept_requests_aria') }}">
            <span class="relative h-6 w-11 shrink-0 rounded-full bg-slate-300 transition-colors peer-checked:bg-emerald-500 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2
                         after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:after:translate-x-5" aria-hidden="true"></span>
        </label>
        @else
        <span class="text-xs text-slate-500" data-stage-queue-count hidden></span>
        @endif
    </div>

    {{-- Intervenant invité ou à l'antenne --}}
    <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3" data-stage-current hidden>
        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700" data-stage-current-initials></span>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-900" data-stage-current-name></p>
            <p class="flex items-center gap-1.5 text-xs text-slate-500"><span class="h-2 w-2 shrink-0 rounded-full bg-slate-400" data-stage-current-dot></span><span data-stage-current-status></span></p>
            @if($stageStaff)
            <p class="mt-1 text-xs break-words text-slate-600" data-stage-current-message hidden></p>
            @endif
        </div>
        @if($stageStaff)
        <button type="button" class="btn-secondary btn-sm shrink-0" data-stage-current-action hidden></button>
        @endif
    </div>

    @if($stageStaff)
        {{-- File d'attente --}}
        <ol class="max-h-72 divide-y divide-slate-100 overflow-y-auto" data-stage-queue aria-live="polite"></ol>
        <p class="px-4 py-4 text-center text-sm text-slate-500" data-stage-queue-empty>{{ __('lives.stage.queue_empty') }}</p>
        <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">{{ __('lives.stage.queue_rules', ['seconds' => config('livekit.stage_invite_timeout')]) }}</p>
    @elseif(!Auth::check())
        <p class="px-4 py-4 text-sm text-slate-600">
            {!! __('lives.stage.login', ['link' => '<a href="' . e(route('login')) . '" class="font-medium text-slate-900 underline">' . e(__('lives.connect')) . '</a>']) !!}
        </p>
    @else
        <div class="px-4 py-4">
            <form class="space-y-3" data-stage-request-form data-no-loading hidden>
                <div>
                    <label for="stage-message" class="form-label">{{ __('lives.stage.subject') }} <span class="font-normal text-slate-500">{{ __('lives.stage.optional') }}</span></label>
                    <textarea id="stage-message" name="message" rows="2" maxlength="{{ config('livekit.stage_message_length') }}" class="form-input"
                              placeholder="{{ __('lives.stage.subject_placeholder') }}"></textarea>
                    <p class="form-hint">{{ __('lives.stage.subject_hint') }}</p>
                </div>
                <button type="submit" class="btn-secondary w-full"><i class="fa-solid fa-hand" aria-hidden="true"></i>{{ __('lives.stage.request') }}</button>
            </form>

            <div class="space-y-3" data-stage-mine hidden>
                <p class="text-sm text-slate-700" data-stage-mine-text></p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-primary btn-sm" data-stage-open-invite hidden><i class="fa-solid fa-microphone" aria-hidden="true"></i>{{ __('lives.stage.answer_invite') }}</button>
                    <button type="button" class="btn-ghost btn-sm" data-stage-withdraw>{{ __('lives.stage.withdraw') }}</button>
                </div>
            </div>

            <p class="text-sm text-slate-500" data-stage-refusal hidden></p>
        </div>
    @endif
</section>

@if(Auth::check() && !$stageStaff)
{{-- Invitation à l'antenne --}}
<div id="stage-invite-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="stage-invite-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50"></div>
    <div class="card relative w-full max-w-md shadow-xl">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 id="stage-invite-title" class="text-base font-semibold text-primary-600">{{ __('lives.stage.invite_title') }}</h2>
            <p class="mt-1 text-sm text-slate-600">
                {{ $live->record ? __('lives.stage.invite_heard_recorded') : __('lives.stage.invite_heard') }}
                {!! __('lives.stage.invite_answer', ['countdown' => '<span class="font-semibold tabular-nums" data-stage-invite-countdown>60</span>']) !!}
            </p>
        </div>
        <div class="space-y-4 px-5 py-4">
            <div class="relative aspect-video overflow-hidden rounded-lg bg-slate-900">
                <video class="h-full w-full -scale-x-100 object-cover" autoplay muted playsinline data-stage-preview hidden></video>
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-sm text-slate-300" data-stage-preview-off>
                    <i class="fa-solid fa-microphone text-2xl text-slate-500" aria-hidden="true"></i>
                    <span>{{ __('lives.stage.mic_only') }}</span>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 text-sm text-slate-700" data-stage-camera-row>
                <span aria-hidden="true">{{ __('lives.stage.enable_camera') }}</span>
                @include('components.switch', ['name' => 'stage_camera', 'checked' => false, 'label' => __('lives.stage.enable_camera')])
            </div>
            <p class="text-xs text-slate-500">{{ __('lives.stage.invite_notice') }}</p>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-stage-invite-decline>{{ __('lives.stage.decline') }}</button>
            <button type="button" class="btn-primary" data-stage-invite-accept><i class="fa-solid fa-microphone" aria-hidden="true"></i><span>{{ __('lives.stage.join') }}</span></button>
        </div>
    </div>
</div>
@endif
@endif

{{-- Formulaire utilisé par openConfirmModal pour « Terminer l'intervention ». --}}
<form id="stage-confirm-form" data-no-loading hidden></form>
