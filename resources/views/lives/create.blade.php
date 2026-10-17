@extends('layouts.app')
@section('title', __('lives.start_live'))
@php
    $event       = $event ?? null; // direct d'un événement (docs/fonctionnalites/evenements.md)
    $header      = __('lives.start_live');
    $subheader   = $event
        ? __('lives.create.event_live', ['title' => $event->title])
        : __('lives.create.subheader');
    $breadcrumbs = $event
        ? [
            ['label' => __('lives.home'), 'url' => route('home')],
            ['label' => __('lives.events'), 'url' => route('events.index')],
            ['label' => Str::limit($event->title, 40), 'url' => route('events.show', $event->id)],
            ['label' => __('lives.start_live')],
        ]
        : [
            ['label' => __('lives.home'), 'url' => route('home')],
            ['label' => __('lives.lives'), 'url' => route('lives.index')],
            ['label' => __('lives.start_live')],
        ];
    $checks = [
        'secure'  => __('lives.create.check_secure'),
        'browser' => __('lives.create.check_browser'),
        'network' => __('lives.create.check_network'),
        'camera'  => __('lives.create.check_camera'),
        'micro'   => __('lives.create.check_micro'),
    ];
@endphp

@section('content')
@if(!$configured)
<div class="alert-warning mb-6" role="status">
    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
    <p>{!! __('lives.create.not_configured', ['vars' => '<code>LIVEKIT_URL</code>, <code>LIVEKIT_API_KEY</code>, <code>LIVEKIT_API_SECRET</code>', 'file' => '<code>.env</code>']) !!}</p>
</div>
@endif

@if($errors->any())
<div class="alert-error mb-6" role="alert">
    <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
    <p>{{ $errors->first() }}</p>
</div>
@endif

<form method="POST" action="{{ route('lives.store') }}" id="live-create-form" data-loading-label="{{ __('lives.create.creating') }}" data-online-only
      class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    @csrf
    @if($event)
    <input type="hidden" name="event_id" value="{{ $event->id }}">
    <div class="alert-info lg:col-span-2" role="status">
        <i class="fa-solid fa-calendar-days mt-0.5"></i>
        <p>{!! __('lives.create.event_notice', ['link' => '<a href="' . e(route('events.show', $event->id)) . '" class="font-semibold underline">' . e($event->title) . '</a>']) !!}</p>
    </div>
    @endif

    {{-- ── Caméra IP : mode d'emploi (à la place des vérifications de l'appareil) ── --}}
    <section class="card-insight min-w-0 space-y-3 self-start p-5 sm:p-6" data-live-camera-help hidden aria-labelledby="camera-help-title">
        <h2 id="camera-help-title" class="flex items-center gap-2 text-base font-bold text-sun-700"><i class="fa-solid fa-video" aria-hidden="true"></i>{{ __('lives.create.help_title') }}</h2>
        <ol class="list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li>{!! __('lives.create.help_rtmp') !!}</li>
            <li>{!! __('lives.create.help_url') !!}</li>
            <li>{!! __('lives.create.help_rtsp', ['command' => '<code class="rounded bg-white px-1 text-xs">ffmpeg -i rtsp://… -c:v libx264 -c:a aac -f flv &lt;' . e(__('lives.create.command_address')) . '&gt;/&lt;' . e(__('lives.create.command_key')) . '&gt;</code>']) !!}</li>
            <li>{!! __('lives.create.help_studio') !!}</li>
        </ol>
        <p class="text-xs text-slate-600">{{ __('lives.create.help_bitrate') }}</p>
    </section>

    {{-- ── Vérifications ──────────────────────────────────────────────── --}}
    <section class="card min-w-0 p-5 sm:p-6" data-live-preflight>
        <h2 class="card-title mb-4">{{ __('lives.create.checks') }}</h2>

        <div class="relative mb-4 aspect-video overflow-hidden rounded-lg bg-slate-900">
            <video class="h-full w-full -scale-x-100 object-cover" autoplay muted playsinline data-preflight-video></video>
            <p class="absolute inset-0 flex items-center justify-center p-4 text-center text-sm text-slate-300" data-preflight-placeholder>
                {{ __('lives.create.camera_preview') }}
            </p>
        </div>

        <div class="mb-4">
            <p class="mb-1 text-xs text-slate-500">{{ __('lives.create.mic_level') }}</p>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full w-0 rounded-full bg-emerald-500 transition-[width] duration-100" data-preflight-level></div>
            </div>
        </div>

        <ul class="divide-y divide-slate-100 text-sm">
            @foreach($checks as $key => $label)
            <li class="flex items-start gap-3 py-2" data-check="{{ $key }}">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-slate-400" data-check-dot></span>
                <div class="min-w-0 flex-1">
                    <p class="text-slate-900">{{ $label }}</p>
                    <p class="text-xs text-slate-500" data-check-detail>{{ __('lives.create.check_pending') }}</p>
                </div>
            </li>
            @endforeach
            <li class="flex items-start gap-3 py-2" data-check="battery" data-optional>
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-slate-400" data-check-dot></span>
                <div class="min-w-0 flex-1">
                    <p class="text-slate-900">{{ __('lives.create.battery') }}</p>
                    <p class="text-xs text-slate-500" data-check-detail>{{ __('lives.create.battery_na') }}</p>
                </div>
            </li>
        </ul>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="btn-secondary" data-preflight-run><i class="fa-solid fa-rotate-right"></i>{{ __('lives.create.rerun') }}</button>
            <button type="button" class="btn-ghost" data-preflight-switch hidden><i class="fa-solid fa-camera-rotate"></i>{{ __('lives.create.switch_camera') }}</button>
        </div>

        <input type="checkbox" name="checks_passed" value="1" class="sr-only" tabindex="-1" aria-hidden="true" data-required-check data-preflight-ok>

        <div class="alert-info mt-4">
            <i class="fa-solid fa-circle-info mt-0.5 text-slate-400"></i>
            <p>{{ __('lives.create.during_tips') }}</p>
        </div>
    </section>

    {{-- ── Informations ───────────────────────────────────────────────── --}}
    <section class="card min-w-0 space-y-4 p-5 sm:p-6">
        <h2 class="card-title">{{ __('lives.create.the_live') }}</h2>

        {{-- Source vidéo : caméra de l'appareil, caméra IP / encodeur (docs/fonctionnalites/lives-camera-ip.md) --}}
        @php($currentSource = old('source', 'browser'))
        <fieldset>
            <legend class="form-label">{{ __('lives.create.camera_used') }}</legend>
            <div class="grid gap-2">
                @foreach([
                    'browser' => ['fa-mobile-screen', __('lives.create.source_browser'), __('lives.create.source_browser_hint')],
                    'rtmp'    => ['fa-video', __('lives.create.source_rtmp'), __('lives.create.source_rtmp_hint')],
                    'url'     => ['fa-link', __('lives.create.source_url'), __('lives.create.source_url_hint')],
                ] as $value => [$icon, $label, $hint])
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                    <input type="radio" name="source" value="{{ $value }}" class="mt-1" @checked($currentSource === $value) data-live-source>
                    <span class="min-w-0">
                        <span class="flex items-center gap-2 font-semibold text-slate-900"><i class="fa-solid {{ $icon }} text-primary-600" aria-hidden="true"></i>{{ $label }}</span>
                        <span class="block text-xs text-slate-500">{{ $hint }}</span>
                    </span>
                </label>
                @endforeach
            </div>
            @error('source')<p class="form-error">{{ $message }}</p>@enderror
        </fieldset>
        {{-- Caméra IP : formulaire de connexion (adresse composée dans le navigateur → camera_url).
             Mot de passe et phrase secrète sans attribut name : jamais envoyés à part ni remis en session. --}}
        @php($cameraMode = old('camera_mode', 'form'))
        <fieldset class="space-y-3" data-live-source-panel="url" data-ipcam @if($currentSource !== 'url') hidden disabled @endif>
            <legend class="sr-only">{{ __('lives.create.ipcam_legend') }}</legend>
            <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="{{ __('lives.create.ipcam_input') }}">
                @foreach(['form' => ['fa-sliders', __('lives.create.mode_form')], 'url' => ['fa-link', __('lives.create.mode_url')]] as $mode => [$icon, $label])
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                    <input type="radio" name="camera_mode" value="{{ $mode }}" class="sr-only" @checked($cameraMode === $mode) data-ipcam-mode>
                    <i class="fa-solid {{ $icon }} text-primary-600" aria-hidden="true"></i>{{ $label }}
                </label>
                @endforeach
            </div>

            <fieldset class="space-y-3" data-ipcam-panel="form">
                <legend class="sr-only">{{ __('lives.create.ipcam_form') }}</legend>
                <input type="hidden" name="camera_url" data-ipcam-composed>
                <div>
                    <label for="camera_preset" class="form-label">{{ __('lives.create.camera_model') }}</label>
                    <select id="camera_preset" name="camera_preset" class="form-input" data-ipcam-field="preset">
                        @foreach([
                            'hikvision' => __('lives.create.preset_hikvision'), 'dahua' => __('lives.create.preset_dahua'), 'amcrest' => 'Amcrest', 'reolink' => 'Reolink',
                            'axis' => 'Axis', 'tapo' => 'TP-Link Tapo', 'foscam' => 'Foscam', 'onvif' => __('lives.create.preset_onvif'), 'other' => __('lives.create.preset_other'),
                        ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('camera_preset', 'other') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="form-hint" data-ipcam-preset-hint></p>
                </div>
                <div class="grid grid-cols-2 gap-2" data-ipcam-streams hidden>
                    @foreach(['main' => __('lives.create.main_stream'), 'sub' => __('lives.create.sub_stream')] as $value => $label)
                    <label class="flex cursor-pointer items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                        <input type="radio" name="camera_stream" value="{{ $value }}" class="sr-only" @checked(old('camera_stream', 'main') === $value) data-ipcam-stream>{{ $label }}
                    </label>
                    @endforeach
                </div>
                <div>
                    <label for="camera_protocol" class="form-label">{{ __('lives.create.protocol') }}</label>
                    <select id="camera_protocol" name="camera_protocol" class="form-input" data-ipcam-field="protocol">
                        @foreach(['rtsp' => 'RTSP', 'rtsps' => __('lives.create.encrypted', ['name' => 'RTSPS']), 'rtmp' => 'RTMP', 'rtmps' => __('lives.create.encrypted', ['name' => 'RTMPS']), 'http' => 'HTTP (HLS)', 'https' => 'HTTPS (HLS)', 'srt' => 'SRT'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('camera_protocol', 'rtsp') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <div class="min-w-0 flex-1">
                        <label for="camera_host" class="form-label">{{ __('lives.create.host') }}</label>
                        <input id="camera_host" type="text" name="camera_host" value="{{ old('camera_host') }}" maxlength="253" class="form-input" autocomplete="off" spellcheck="false" autocapitalize="off"
                               placeholder="{{ __('lives.create.host_placeholder') }}" data-ipcam-field="host">
                    </div>
                    <div class="w-24 shrink-0">
                        <label for="camera_port" class="form-label">{{ __('lives.create.port') }}</label>
                        <input id="camera_port" type="number" name="camera_port" value="{{ old('camera_port', 554) }}" min="1" max="65535" class="form-input" inputmode="numeric" data-ipcam-field="port">
                    </div>
                </div>
                <div class="alert-warning" role="status" data-ipcam-private hidden>
                    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                    <p>{!! __('lives.create.private_warning') !!}</p>
                </div>
                <div>
                    <label for="camera_path" class="form-label" data-ipcam-path-label>{{ __('lives.create.path') }}</label>
                    <input id="camera_path" type="text" name="camera_path" value="{{ old('camera_path') }}" maxlength="300" class="form-input font-mono text-sm" autocomplete="off" spellcheck="false" autocapitalize="off"
                           placeholder="/stream1" data-ipcam-field="path">
                </div>
                <div class="grid gap-3 sm:grid-cols-2" data-ipcam-credentials>
                    <div>
                        <label for="camera_username" class="form-label">{{ __('lives.create.username') }}</label>
                        <input id="camera_username" type="text" name="camera_username" value="{{ old('camera_username') }}" maxlength="100" class="form-input" autocomplete="off" spellcheck="false" autocapitalize="off"
                               placeholder="admin" data-ipcam-field="username">
                    </div>
                    <div>
                        <label for="camera_password" class="form-label">{{ __('lives.create.password') }}</label>
                        <div class="relative">
                            <input id="camera_password" type="password" maxlength="100" class="form-input pr-10" autocomplete="new-password" data-ipcam-field="password">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-slate-500 hover:text-slate-800" data-reveal="camera_password" data-reveal-label="{{ __('lives.create.the_password') }}" aria-label="{{ __('lives.create.show_password') }}"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2" data-ipcam-srt hidden>
                    <div>
                        <label for="camera_stream_id" class="form-label">{{ __('lives.create.stream_id') }}</label>
                        <input id="camera_stream_id" type="text" name="camera_stream_id" value="{{ old('camera_stream_id') }}" maxlength="200" class="form-input" autocomplete="off" spellcheck="false" data-ipcam-field="streamId">
                    </div>
                    <div>
                        <label for="camera_passphrase" class="form-label">{{ __('lives.create.passphrase') }}</label>
                        <div class="relative">
                            <input id="camera_passphrase" type="password" maxlength="79" class="form-input pr-10" autocomplete="new-password" data-ipcam-field="passphrase">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-slate-500 hover:text-slate-800" data-reveal="camera_passphrase" data-reveal-label="{{ __('lives.create.the_passphrase') }}" aria-label="{{ __('lives.create.show_passphrase') }}"><i class="fa-solid fa-eye"></i></button>
                        </div>
                        <p class="form-hint">{{ __('lives.create.passphrase_hint') }}</p>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs text-slate-500">{{ __('lives.create.composed') }}</p>
                    <p class="break-all font-mono text-sm text-slate-900" data-ipcam-preview>—</p>
                </div>
            </fieldset>

            <fieldset data-ipcam-panel="url" hidden disabled>
                <legend class="sr-only">{{ __('lives.create.mode_url') }}</legend>
                <label for="camera_url" class="form-label">{{ __('lives.create.stream_url') }}</label>
                <div class="relative">
                    <input id="camera_url" type="password" name="camera_url" maxlength="500" class="form-input pr-10 font-mono text-sm" autocomplete="off" spellcheck="false" autocapitalize="off"
                           placeholder="{{ __('lives.create.url_placeholder') }}" aria-describedby="camera-url-hint" pattern="(rtsps?|rtmps?|https?|srt)://\S+" data-ipcam-raw>
                    <button type="button" class="absolute inset-y-0 right-0 px-3 text-slate-500 hover:text-slate-800" data-reveal="camera_url" data-reveal-label="{{ __('lives.create.the_address') }}" aria-label="{{ __('lives.create.show_address') }}"><i class="fa-solid fa-eye"></i></button>
                </div>
                <p id="camera-url-hint" class="form-hint">{{ __('lives.create.url_hint') }}</p>
            </fieldset>
            @error('camera_url')<p class="form-error">{{ $message }}</p>@enderror

            <div class="alert-info" role="note">
                <i class="fa-solid fa-globe mt-0.5 text-slate-400"></i>
                <p>{!! __('lives.create.reachable') !!}</p>
            </div>
        </fieldset>

        <div>
            <label for="title" class="form-label">{{ __('lives.create.title') }}</label>
            <input id="title" type="text" name="title" value="{{ old('title', $event?->title ? Str::limit($event->title, 150, '') : null) }}" required maxlength="150" class="form-input"
                   placeholder="{{ __('lives.create.title_placeholder') }}">
            @error('title')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="description" class="form-label">{{ __('lives.create.description') }}</label>
            <textarea id="description" name="description" rows="3" maxlength="1000" class="form-input">{{ old('description') }}</textarea>
        </div>
        <div>
            <label for="category_slug" class="form-label">{{ __('lives.create.category') }}</label>
            <select id="category_slug" name="category_slug" class="form-input">
                <option value="">{{ __('lives.create.no_category') }}</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->slug }}" @selected(old('category_slug') === $cat->slug)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3">
            <div>
                <p class="text-sm font-medium text-slate-900">{{ __('lives.create.allow_comments') }}</p>
                <p class="text-xs text-slate-500">{{ __('lives.create.allow_comments_hint') }}</p>
            </div>
            @include('components.switch', ['name' => 'comments_enabled', 'checked' => (bool) old('comments_enabled', true), 'label' => __('lives.create.allow_comments'), 'sendFalse' => true])
        </div>

        <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3">
            <div>
                <p class="text-sm font-medium text-slate-900">{{ __('lives.create.record') }}</p>
                @if($recordingConfigured)
                <p class="text-xs text-slate-500">{{ __('lives.create.record_hint') }}</p>
                @else
                <p class="text-xs text-slate-500">{{ __('lives.create.record_unavailable') }}</p>
                @endif
            </div>
            @if($recordingConfigured)
                @include('components.switch', ['name' => 'record', 'checked' => (bool) old('record', true), 'label' => __('lives.create.record'), 'sendFalse' => true])
            @else
                <span class="badge-draft">{{ __('lives.create.unavailable') }}</span>
            @endif
        </div>

        <div class="border-t border-slate-100 pt-4">
            <p class="mb-3 text-xs text-slate-500">{{ __('lives.create.visible_notice') }}</p>
            <div class="flex flex-wrap justify-end gap-2">
                <a href="{{ $event ? route('events.show', $event->id) : route('lives.index') }}" class="btn-secondary">{{ __('lives.create.cancel') }}</a>
                <button type="submit" class="btn-primary" data-submit-guard disabled @disabled(!$configured)>
                    <i class="fa-solid fa-video"></i>{{ __('lives.create.open_studio') }}
                </button>
            </div>
        </div>
    </section>
</form>
@endsection

@push('scripts')
@vite('resources/js/live.js')
<script>
// Caméra IP : pas de vérification de la caméra de l'appareil ; adresse du flux en mode « url ».
(function () {
    const form = document.getElementById('live-create-form');
    const check = form.querySelector('[data-preflight-ok]');
    const sync = () => {
        const source = form.querySelector('[data-live-source]:checked')?.value || 'browser';
        const device = source === 'browser';
        form.querySelector('[data-live-preflight]').hidden = !device;
        form.querySelector('[data-live-camera-help]').hidden = device;
        // Panneau masqué : désactivé aussi (ni validé ni envoyé).
        form.querySelectorAll('[data-live-source-panel]').forEach((el) => { el.hidden = el.dataset.liveSourcePanel !== source; el.disabled = el.hidden; });
        check.toggleAttribute('data-required-check', device);
        check.dispatchEvent(new Event('change', { bubbles: true }));
    };
    form.addEventListener('change', (e) => { if (e.target.matches('[data-live-source]')) sync(); });
    sync();
})();

// Caméra IP : formulaire → adresse du flux (même logique que l'application,
// lib/features/live/models/ip_camera_config.dart). Le mot de passe n'est ni
// mémorisé ni envoyé à part : seule l'adresse composée part dans camera_url.
(function () {
    const root = document.querySelector('[data-ipcam]');
    if (!root) return;
    const STORE = 'testiapp:live:ip-camera';
    const T = @js(__('lives.create.js'));
    const T = @js(__('lives.create.js'));
    const PORTS = { rtsp: 554, rtsps: 322, rtmp: 1935, rtmps: 443, http: 80, https: 443, srt: 9000 };
    const PRESETS = {
        hikvision: { main: '/Streaming/Channels/101', sub: '/Streaming/Channels/102', hint: T.hint_hikvision },
        dahua: { main: '/cam/realmonitor?channel=1&subtype=0', sub: '/cam/realmonitor?channel=1&subtype=1', hint: T.hint_dahua },
        amcrest: { main: '/cam/realmonitor?channel=1&subtype=0', sub: '/cam/realmonitor?channel=1&subtype=1', hint: T.hint_amcrest },
        reolink: { main: '/h264Preview_01_main', sub: '/h264Preview_01_sub', hint: T.hint_reolink },
        axis: { main: '/axis-media/media.amp', sub: '/axis-media/media.amp?resolution=640x360', hint: T.hint_axis },
        tapo: { main: '/stream1', sub: '/stream2', hint: T.hint_tapo },
        foscam: { port: 88, main: '/videoMain', sub: '/videoSub', hint: T.hint_foscam },
        onvif: { hint: T.hint_onvif },
        other: { hint: T.hint_other },
    };
    const $ = (s) => root.querySelector(s);
    const f = (name) => root.querySelector(`[data-ipcam-field="${name}"]`);
    const composed = $('[data-ipcam-composed]');
    const raw = $('[data-ipcam-raw]');
    let protocol = f('protocol').value;

    const enc = (v) => encodeURIComponent(v).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
    const dec = (v) => { try { return decodeURIComponent(v); } catch { return v; } };
    const fmtHost = (h) => (h.includes(':') && !h.startsWith('[') ? `[${h}]` : h);
    const mode = () => root.querySelector('[data-ipcam-mode]:checked')?.value || 'form';
    const stream = () => root.querySelector('[data-ipcam-stream]:checked')?.value || 'main';

    function config() {
        return {
            protocol: f('protocol').value, host: f('host').value.trim(), port: f('port').value.trim() || String(PORTS[f('protocol').value]),
            path: f('path').value.trim(), username: f('username').value.trim(), password: f('password').value,
            streamId: f('streamId').value.trim(), passphrase: f('passphrase').value,
        };
    }

    function compose(c, mask) {
        let url = `${c.protocol}://`;
        if (c.protocol !== 'srt' && c.username) {
            url += enc(c.username) + (c.password ? ':' + (mask ? '••••' : enc(c.password)) : '') + '@';
        }
        url += `${fmtHost(c.host)}:${c.port}`;
        let path = c.path;
        if (c.protocol === 'srt') {
            const params = [];
            if (c.streamId) params.push('streamid=' + enc(c.streamId));
            if (c.passphrase) params.push('passphrase=' + (mask ? '••••' : enc(c.passphrase)));
            if (params.length) path += (path.includes('?') ? '&' : '?') + params.join('&');
        }
        if (path && !path.startsWith('/') && !path.startsWith('?')) path = '/' + path;
        return url + path;
    }

    function parse(value) {
        const m = /^([a-z][a-z0-9+.-]*):\/\/(.*)$/is.exec(value.trim());
        if (!m || !(m[1].toLowerCase() in PORTS)) return null;
        const c = { protocol: m[1].toLowerCase(), username: '', password: '', streamId: '', passphrase: '' };
        let rest = m[2];
        const q = rest.indexOf('?');
        const at = (q < 0 ? rest : rest.slice(0, q)).lastIndexOf('@');
        if (at >= 0) {
            const info = rest.slice(0, at);
            rest = rest.slice(at + 1);
            const colon = info.indexOf(':');
            c.username = dec(colon < 0 ? info : info.slice(0, colon));
            c.password = colon < 0 ? '' : dec(info.slice(colon + 1));
        }
        const end = rest.search(/[/?#]/);
        const authority = end < 0 ? rest : rest.slice(0, end);
        let path = end < 0 ? '' : rest.slice(end);
        let host = authority, port = '';
        if (authority.startsWith('[')) {
            const close = authority.indexOf(']');
            if (close < 0) return null;
            host = authority.slice(1, close);
            if (authority[close + 1] === ':') port = authority.slice(close + 2);
        } else if (authority.includes(':')) {
            host = authority.slice(0, authority.lastIndexOf(':'));
            port = authority.slice(authority.lastIndexOf(':') + 1);
        }
        if (!host) return null;
        if (c.protocol === 'srt' && path.includes('?')) {
            const i = path.indexOf('?');
            const kept = [];
            path.slice(i + 1).split('&').forEach((pair) => {
                const [k, ...v] = pair.split('=');
                if (k.toLowerCase() === 'streamid') c.streamId = dec(v.join('='));
                else if (k.toLowerCase() === 'passphrase') c.passphrase = dec(v.join('='));
                else if (pair) kept.push(pair);
            });
            path = path.slice(0, i) + (kept.length ? '?' + kept.join('&') : '');
        }
        Object.assign(c, { host, port: /^\d+$/.test(port) ? port : String(PORTS[c.protocol]), path });
        c.preset = 'other'; c.stream = 'main';
        for (const [id, p] of Object.entries(PRESETS)) {
            if (p.main && p.main.toLowerCase() === path.toLowerCase()) { c.preset = id; break; }
            if (p.sub && p.sub.toLowerCase() === path.toLowerCase()) { c.preset = id; c.stream = 'sub'; break; }
        }
        return c;
    }

    function ipv4(h) {
        const parts = h.split('.');
        if (parts.length !== 4 || !parts.every((p) => /^\d{1,3}$/.test(p) && +p <= 255)) return null;
        return parts.map(Number);
    }

    function isPrivate(value) {
        let h = value.trim().toLowerCase().replace(/^\[(.*)\]$/, '$1');
        if (!h) return false;
        if (h === 'localhost' || /\.(localhost|local|lan|home\.arpa)$/.test(h)) return true;
        const v4 = ipv4(h);
        if (v4) {
            const [a, b] = v4;
            return a === 10 || a === 127 || a === 0 || (a === 172 && b >= 16 && b <= 31) || (a === 192 && b === 168)
                || (a === 169 && b === 254) || (a === 100 && b >= 64 && b <= 127);
        }
        if (h.includes(':')) {
            if (h === '::1' || h === '::') return true;
            const n = parseInt(h.split(':')[0] || '0', 16);
            return (n & 0xfe00) === 0xfc00 || (n & 0xffc0) === 0xfe80;
        }
        return false;
    }

    function hostError(value) {
        const h = value.trim();
        if (!h) return T.host_required;
        if (h.includes('://')) return T.host_no_scheme;
        if (/[\s/@?#]/.test(h)) return T.host_chars;
        const bare = h.replace(/^\[(.*)\]$/, '$1');
        if (bare.includes(':')) return /^[0-9a-f:.]+$/i.test(bare) ? '' : T.ipv6_invalid;
        if (/^\d+(\.\d+){3}$/.test(bare)) return ipv4(bare) ? '' : T.ip_invalid;
        return /^(?=.{1,253}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*\.?$/i.test(bare) ? '' : T.hostname_invalid;
    }

    function fill(c) {
        f('protocol').value = c.protocol; protocol = c.protocol;
        ['host', 'port', 'path', 'username', 'password', 'streamId', 'passphrase'].forEach((k) => { if (c[k] !== undefined) f(k).value = c[k]; });
        if (c.preset) f('preset').value = c.preset;
        const radio = root.querySelector(`[data-ipcam-stream][value="${c.stream || 'main'}"]`);
        if (radio) radio.checked = true;
    }

    function applyPreset() {
        const p = PRESETS[f('preset').value] || PRESETS.other;
        if (p.main) {
            f('protocol').value = protocol = 'rtsp';
            f('port').value = p.port || PORTS.rtsp;
            f('path').value = stream() === 'sub' && p.sub ? p.sub : p.main;
        }
    }

    function render() {
        const c = config();
        const p = PRESETS[f('preset').value] || PRESETS.other;
        $('[data-ipcam-preset-hint]').textContent = p.hint || '';
        $('[data-ipcam-streams]').hidden = !p.sub;
        const srt = c.protocol === 'srt';
        $('[data-ipcam-credentials]').hidden = srt;
        $('[data-ipcam-srt]').hidden = !srt;
        $('[data-ipcam-path-label]').textContent = srt ? @js(__('lives.create.path_srt')) : @js(__('lives.create.path'));

        const isUrl = mode() === 'url';
        root.querySelectorAll('[data-ipcam-panel]').forEach((el) => {
            const off = el.dataset.ipcamPanel !== mode();
            el.hidden = off;
            el.disabled = off || root.disabled;
        });
        raw.required = isUrl;
        f('host').required = !isUrl;

        const err = hostError(c.host);
        f('host').setCustomValidity(c.host ? err : '');
        const port = Number(c.port);
        f('port').setCustomValidity(Number.isInteger(port) && port >= 1 && port <= 65535 ? '' : T.port_range);
        f('path').setCustomValidity(/\s/.test(c.path) ? T.path_space : '');
        f('passphrase').setCustomValidity(srt && c.passphrase && (c.passphrase.length < 10 || c.passphrase.length > 79) ? T.passphrase_length : '');

        composed.value = c.host ? compose(c, false) : '';
        $('[data-ipcam-preview]').textContent = c.host ? compose(c, true) : '—';
        const host = isUrl ? (parse(raw.value)?.host || '') : c.host;
        $('[data-ipcam-private]').hidden = !isPrivate(host);
    }

    root.addEventListener('input', render);
    root.addEventListener('change', (e) => {
        const t = e.target;
        if (t === f('preset') || t.matches('[data-ipcam-stream]')) {
            applyPreset();
        } else if (t === f('protocol')) {
            const current = f('port').value.trim();
            if (!current || Number(current) === PORTS[protocol]) f('port').value = PORTS[t.value];
            protocol = t.value;
        } else if (t.matches('[data-ipcam-mode]')) {
            if (t.value === 'url') {
                raw.value = f('host').value.trim() ? compose(config(), false) : raw.value;
            } else if (raw.value.trim()) {
                const parsed = parse(raw.value);
                if (parsed) fill(parsed);
            }
        }
        render();
    });

    // Dernier réglage mémorisé dans ce navigateur, sans mot de passe ni phrase secrète.
    const form = root.closest('form');
    try {
        const saved = JSON.parse(localStorage.getItem(STORE) || 'null');
        if (saved && !f('host').value) fill(saved);
    } catch { /* stockage indisponible */ }
    form.addEventListener('submit', () => {
        if (root.disabled || mode() !== 'form') return;
        const c = config();
        try {
            localStorage.setItem(STORE, JSON.stringify({
                protocol: c.protocol, host: c.host, port: c.port, path: c.path, username: c.username,
                streamId: c.streamId, preset: f('preset').value, stream: stream(),
            }));
        } catch { /* stockage indisponible */ }
    });
    // Le choix de la source (fieldset activé / désactivé) se fait dans le script ci-dessus.
    form.addEventListener('change', (e) => { if (e.target.matches('[data-live-source]')) render(); });
    render();
})();
</script>
@endpush
