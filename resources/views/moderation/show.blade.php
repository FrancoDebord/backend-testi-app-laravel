@extends('layouts.app')
{{-- Fiche d'un témoignage (back-office) : aperçu, preuves, historique, statistiques, actions.
     docs/fonctionnalites/gestion-des-temoignages.md --}}
@section('title', __('moderation.show.title', ['title' => $testimony->title]))
@php
    $header      = $testimony->title;
    $subheader   = $testimony->trashed() ? __('moderation.show.in_trash_since', ['date' => $testimony->deleted_at->format('d/m/Y')]) : __('moderation.show.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.moderation'), 'url' => route('moderation.index')],
        ['label' => Str::limit($testimony->title, 40)],
    ];
    $viewer   = auth()->user();
    $status   = $testimony->status->value;
    $hls      = $video['playback_url'] ?? null; // Bunny : HLS (hls.js), MP4 de secours sinon
    $mp4      = $video['mp4_url'] ?? null;
    $bunnyWaiting = $testimony->bunny_video_id && $testimony->video_status !== \App\Models\Testimony::VIDEO_READY;
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    {{-- ── Contenu ──────────────────────────────────────────────────────── --}}
    <div class="min-w-0 space-y-6 lg:col-span-2">
        <section class="card p-5 sm:p-6">
            <h2 class="card-title mb-3">{{ __('moderation.show.information') }}</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">{{ __('moderation.common.status') }}</dt>
                    <dd class="flex flex-wrap gap-1.5">
                        <span class="{{ $testimony->status->badgeClass() }}">{{ $testimony->status->label() }}</span>
                        @if($testimony->trashed())<span class="badge-rejected">{{ __('moderation.tabs.trash') }}</span>@endif
                        @if($testimony->is_verified)<span class="badge-blue"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('moderation.show.approved_testimony') }}</span>@endif
                        @if($testimony->isManuallyFeatured())<span class="badge-orange"><i class="fa-solid fa-star" aria-hidden="true"></i>{{ __('moderation.tabs.featured') }}</span>@endif
                        @if($testimony->isConversion())<span class="badge-yellow"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.badge') }}</span>@endif
                    </dd></div>
                @if($testimony->isConversion())
                @php $conversion = $testimony->conversionDetails(); @endphp
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">{{ __('conversion.details') }}</dt>
                    <dd class="text-right text-slate-900">{{ collect([$conversion['year'] ? __('conversion.converted_in', ['year' => $conversion['year']]) : null, $conversion['place'], $conversion['verse']])->filter()->implode(' · ') ?: '—' }}</dd></div>
                @endif
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">{{ __('moderation.common.author') }}</dt><dd class="text-slate-900">{{ $testimony->user?->display_name }}@if($testimony->user?->country) · {{ $testimony->user->country }}@endif</dd></div>
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">{{ __('moderation.common.submitted_on') }}</dt><dd class="text-slate-900">{{ $testimony->created_at->format(__('moderation.common.datetime_format')) }}</dd></div>
                @if($testimony->moderated_at)
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">{{ __('moderation.show.last_decision') }}</dt><dd class="text-slate-900">{{ $testimony->moderated_at->format(__('moderation.common.datetime_format')) }} · {{ $testimony->moderator?->display_name ?? __('moderation.show.automatic') }}</dd></div>
                @endif
                @if($testimony->rejection_reason)
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">{{ __('moderation.show.rejection_reason') }}</dt><dd class="text-slate-900">{{ $testimony->rejection_reason }}</dd></div>
                @endif
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">{{ __('moderation.show.type_category') }}</dt><dd class="text-slate-900">{{ $testimony->type->label() }} · {{ $testimony->category_slug ?: '—' }}</dd></div>
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">{{ __('moderation.show.visibility') }}</dt><dd class="text-slate-900">{{ $testimony->visibility->label() }}</dd></div>
            </dl>
        </section>

        <section class="card overflow-hidden">
            @if($testimony->cover_url && !$mp4 && !$hls)
            <img src="{{ $testimony->cover_url }}" alt="" class="max-h-72 w-full object-cover">
            @endif
            <div class="p-5 sm:p-6">
                <h2 class="card-title mb-3">{{ __('moderation.show.content') }}</h2>

                @if($testimony->isYouTube())
                <div class="relative mb-5 aspect-video overflow-hidden rounded-lg bg-black">
                    <iframe src="{{ $testimony->youtubeEmbedUrl() }}" title="{{ $testimony->title }}" class="absolute inset-0 h-full w-full"
                            allow="autoplay; encrypted-media; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                </div>
                @elseif($hls || $mp4)
                {{-- Vidéo Bunny Stream (HLS, MP4 de secours) ou ancienne vidéo du serveur --}}
                <div class="mb-5 overflow-hidden rounded-lg bg-black">
                    <video controls playsinline preload="metadata" class="aspect-video w-full" @if($video['thumbnail_url']) poster="{{ $video['thumbnail_url'] }}" @endif @if($hls) data-hls="{{ $hls }}" @if($mp4) data-hls-fallback="{{ $mp4 }}" @endif @endif>@unless($hls)<source src="{{ $mp4 }}" type="video/mp4">@endunless</video>
                </div>
                @elseif($bunnyWaiting)
                <p class="alert-warning mb-5" role="status"><i class="fa-solid fa-hourglass-half mt-0.5" aria-hidden="true"></i><span>{{ __('moderation.show.bunny_waiting', ['status' => $testimony->video_status]) }}</span></p>
                @elseif($testimony->media_url && $testimony->type->value === 'audio')
                <div class="mb-5 rounded-lg border border-slate-200 bg-slate-50 p-3"><audio controls class="w-full"><source src="{{ $testimony->media_url }}"></audio></div>
                @elseif($testimony->media_url && $testimony->type->value === 'video')
                <div class="mb-5 overflow-hidden rounded-lg bg-black"><video controls class="aspect-video w-full"><source src="{{ $testimony->media_url }}"></video></div>
                @endif

                @if($testimony->body_text)
                <div class="text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800 [&_strong]:font-semibold [&_strong]:text-slate-900">{{ $testimony->body_html }}</div>
                @else
                <p class="text-sm text-slate-500">{{ __('moderation.show.no_text') }}</p>
                @endif

                @if($testimony->bible_verse)
                <blockquote class="mt-6 border-l-2 border-slate-300 pl-4">
                    <p class="text-slate-700 italic">{{ __('moderation.common.quoted', ['text' => $testimony->bible_verse]) }}</p>
                    @if($testimony->bible_ref)<p class="mt-1 text-sm font-medium text-slate-500">{{ $testimony->bible_ref }}</p>@endif
                </blockquote>
                @endif

                {{-- Preuves jointes par l'auteur (docs/fonctionnalites/preuves.md) --}}
                @if($testimony->proofs->isNotEmpty())
                @include('testimonies.partials.proofs', ['testimony' => $testimony, 'proofs' => $testimony->proofs])
                @if($testimony->proofs_public)
                <form id="hide-proofs-form" method="POST" action="{{ route('moderation.hide-proofs', $testimony->id) }}" class="mt-3 flex flex-wrap items-center gap-3">
                    @csrf
                    <p class="min-w-0 flex-1 text-xs text-slate-600"><i class="fa-solid fa-globe mr-1 text-primary-600" aria-hidden="true"></i>{!! __('moderation.show.proofs_public') !!}</p>
                    <button type="button" class="btn-secondary btn-sm"
                            onclick="openConfirmModal('hide-proofs-form', @js(__('moderation.show.hide_proofs_confirm')), @js(__('moderation.show.hide_proofs')), @js(__('moderation.show.hide_proofs_button')), 'fa-eye-slash')">
                        <i class="fa-solid fa-eye-slash" aria-hidden="true"></i>{{ __('moderation.show.hide_proofs') }}
                    </button>
                </form>
                @endif
                @else
                <p class="mt-4 text-xs text-slate-500"><i class="fa-solid fa-file-shield mr-1 text-slate-400" aria-hidden="true"></i>{{ __('moderation.show.no_proofs') }}</p>
                @endif
            </div>
        </section>

        {{-- Statistiques (30 jours) --}}
        <section class="card p-5 sm:p-6" aria-labelledby="stats-title">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 id="stats-title" class="card-title">{{ __('moderation.show.stats_title') }}</h2>
                <a href="{{ route('moderation.stats', $testimony->id) }}" class="text-sm font-semibold text-primary-700 hover:underline">{{ __('moderation.show.stats_link') }}<i class="fa-solid fa-arrow-right ml-1 text-xs" aria-hidden="true"></i></a>
            </div>
            @include('moderation.partials.chart', [
                'days'  => $stats['days'],
                'lines' => [['key' => 'views', 'label' => __('moderation.common.views'), 'color' => '#184797'], ['key' => 'reactions', 'label' => __('moderation.common.reactions'), 'color' => '#F18717']],
                'label' => __('moderation.show.chart_label'),
            ])
            <p class="mt-3 text-xs text-slate-500">{{ __('moderation.show.since_publication', ['views' => \App\Support\Locales::number($testimony->views_count), 'reactions' => $testimony->like_count + $testimony->prayer_count, 'comments' => $testimony->comment_count, 'shares' => $testimony->share_count]) }}</p>
        </section>

        {{-- Historique : décisions de modération et journal d'audit --}}
        <section class="card p-5 sm:p-6" aria-labelledby="history-title">
            <h2 id="history-title" class="card-title mb-3">{{ __('moderation.show.history') }}</h2>
            @php
                $history = $audit->map(fn ($a) => ['at' => $a->created_at, 'title' => \App\Services\AdminAudit::label($a->action), 'who' => $a->actor_name, 'text' => $a->reason])
                    ->concat($testimony->moderationLogs->where('action', 'in_review')->map(fn ($l) => ['at' => $l->created_at, 'title' => __('moderation.show.opened_for_review'), 'who' => $l->moderator?->display_name, 'text' => null]))
                    ->concat($audit->isEmpty() ? $testimony->moderationLogs->whereIn('action', ['approved', 'rejected'])->map(fn ($l) => [
                        'at' => $l->created_at, 'title' => $l->action === 'approved' ? __('moderation.show.history_approved') : __('moderation.show.history_rejected'), 'who' => $l->moderator?->display_name,
                        'text' => implode(' — ', array_filter([$l->rejection_reason?->label(), $l->moderator_note])) ?: null]) : collect())
                    ->sortByDesc('at')->take(40);
            @endphp
            @if($history->isEmpty())
            <p class="text-sm text-slate-500">{{ __('moderation.show.no_history') }}</p>
            @else
            <ol class="divide-y divide-slate-100 text-sm">
                @foreach($history as $h)
                <li class="py-2">
                    <p class="text-slate-900"><span class="font-medium">{{ $h['title'] }}</span>@if($h['who']) · {{ $h['who'] }}@endif</p>
                    @if($h['text'])<p class="text-slate-600">{{ __('moderation.common.quoted', ['text' => $h['text']]) }}</p>@endif
                    <p class="text-xs text-slate-400">{{ $h['at']?->format(__('moderation.common.datetime_format')) }}</p>
                </li>
                @endforeach
            </ol>
            @endif
        </section>
    </div>

    {{-- ── Actions ─────────────────────────────────────────────────────── --}}
    <aside class="min-w-0 space-y-6">
        @if($testimony->trashed())
        <section class="card p-5">
            <h2 class="card-title mb-3">{{ __('moderation.tabs.trash') }}</h2>
            @if(\App\Services\TestimonyModeration::restorable($testimony))
            <form id="restore-form" method="POST" action="{{ route('moderation.restore', $testimony->id) }}">
                @csrf
                <button type="submit" class="btn-primary w-full"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i>{{ __('moderation.bulk_actions.restore') }}</button>
            </form>
            @else
            <p class="text-sm text-slate-600">{{ __('moderation.show.restore_expired') }}</p>
            @endif
            @can('forceDelete', $testimony)
            <form id="force-delete-form" method="POST" action="{{ route('moderation.force-delete', $testimony->id) }}" class="mt-3">
                @csrf @method('DELETE')
                <button type="button" class="btn-ghost w-full text-error-700"
                        onclick="openConfirmModal('force-delete-form', @js(__('moderation.show.force_delete_confirm')), @js(__('moderation.show.force_delete')), @js(__('moderation.show.force_delete')), 'fa-trash')">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>{{ __('moderation.show.force_delete') }}
                </button>
            </form>
            @endcan
        </section>
        @else
            @if(in_array($status, ['pending', 'rejected'], true))
            <section class="card p-5">
                <h2 class="card-title mb-3">{{ __('moderation.bulk_actions.approve') }}</h2>
                <p class="mb-4 text-sm text-slate-600">{{ __('moderation.show.approve_help') }}</p>
                <form id="approve-form" method="POST" action="{{ route('moderation.approve', $testimony->id) }}" data-loading-label="{{ __('moderation.show.publishing') }}">
                    @csrf
                    <button type="button" class="btn-primary w-full"
                            onclick="openConfirmModal('approve-form', @js(__('moderation.show.approve_confirm')), @js(__('moderation.show.approve_confirm_title')), @js(__('moderation.show.approve_publish')), 'fa-check')">
                        {{ __('moderation.show.approve_publish') }}
                    </button>
                </form>
            </section>
            @endif

            @if(in_array($status, ['pending', 'approved'], true))
            <section class="card p-5">
                <h2 class="card-title mb-3">{{ $status === 'approved' ? __('moderation.bulk_actions.unpublish') : __('moderation.bulk_actions.reject') }}</h2>
                <form id="reject-form" method="POST" action="{{ route('moderation.reject', $testimony->id) }}" class="space-y-4" data-loading-label="{{ __('moderation.common.saving') }}">
                    @csrf
                    <div>
                        <label for="reason" class="form-label">{{ __('moderation.show.reason') }}</label>
                        <select id="reason" name="reason" required class="form-input">
                            @foreach($reasons as $r)
                            <option value="{{ $r->value }}" @selected(old('reason') === $r->value)>{{ $r->label() }}</option>
                            @endforeach
                        </select>
                        @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="rejection_reason" class="form-label">{{ __('moderation.show.message_for_author') }}</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="1000" class="form-input resize-none"
                                  placeholder="{{ __('moderation.show.message_placeholder') }}">{{ old('rejection_reason') }}</textarea>
                        <p class="form-hint">{{ __('moderation.show.message_hint') }}</p>
                    </div>
                    <button type="button" class="btn-secondary w-full text-error-700"
                            onclick="openConfirmModal('reject-form', @js(__('moderation.show.reject_confirm')), @js(__('moderation.show.reject_confirm_title')), @js(__('moderation.show.reject_confirm_button')), 'fa-xmark')">
                        {{ __('moderation.show.reject_button') }}
                    </button>
                </form>
            </section>
            @endif

            @if($status === 'approved' && !$testimony->isInJournal())
            {{-- Mise à la une (modérateurs et administrateurs) --}}
            <section class="card p-5" aria-labelledby="feature-title">
                <h2 id="feature-title" class="card-title mb-3">{{ __('moderation.tabs.featured') }}</h2>
                <form method="POST" action="{{ route('moderation.feature', $testimony->id) }}" class="space-y-3" data-loading-label="{{ __('moderation.common.saving') }}">
                    @csrf
                    <input type="hidden" name="featured" value="1">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-1">
                        <div>
                            <label for="featured_order" class="form-label">{{ __('moderation.common.order') }}</label>
                            <input id="featured_order" type="number" min="0" max="9999" name="featured_order" value="{{ old('featured_order', $testimony->featured_order) }}" class="form-input" placeholder="{{ __('moderation.show.order_placeholder') }}">
                        </div>
                        <div>
                            <label for="featured_from" class="form-label">{{ __('moderation.show.featured_from') }}</label>
                            <input id="featured_from" type="datetime-local" name="featured_from" value="{{ old('featured_from', $testimony->featured_from?->format('Y-m-d\TH:i')) }}" class="form-input">
                        </div>
                        <div>
                            <label for="featured_until" class="form-label">{{ __('moderation.show.featured_until') }}</label>
                            <input id="featured_until" type="datetime-local" name="featured_until" value="{{ old('featured_until', $testimony->featured_until?->format('Y-m-d\TH:i')) }}" class="form-input">
                        </div>
                    </div>
                    <p class="form-hint">{{ __('moderation.show.dates_hint') }}</p>
                    <button type="submit" class="btn-secondary w-full"><i class="fa-solid fa-star" aria-hidden="true"></i>{{ $testimony->is_featured ? __('moderation.show.update_featured') : __('moderation.bulk_actions.feature') }}</button>
                </form>
                @if($testimony->is_featured)
                <form method="POST" action="{{ route('moderation.feature', $testimony->id) }}" class="mt-2">
                    @csrf <input type="hidden" name="featured" value="0">
                    <button type="submit" class="btn-ghost w-full">{{ __('moderation.bulk_actions.unfeature') }}</button>
                </form>
                @endif
            </section>
            @endif

            @can('verify', $testimony)
            @if($status === 'approved')
            {{-- Label « Témoignage approuvé » (administrateurs) --}}
            <section class="card p-5" aria-labelledby="label-title">
                <h2 id="label-title" class="card-title mb-2">{{ __('moderation.common.label_title') }}</h2>
                <p class="mb-3 text-sm text-slate-600">{{ __('moderation.show.label_help') }}</p>
                <form id="verify-form" method="POST" action="{{ route('moderation.verify', $testimony->id) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="verified" value="{{ $testimony->is_verified ? 0 : 1 }}">
                    <div>
                        <label for="verify-reason" class="form-label">{{ __('moderation.show.audit_note') }}</label>
                        <input id="verify-reason" type="text" name="reason" maxlength="500" class="form-input" placeholder="{{ __('moderation.common.optional') }}">
                    </div>
                    <button type="submit" class="{{ $testimony->is_verified ? 'btn-ghost' : 'btn-secondary' }} w-full">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ $testimony->is_verified ? __('moderation.show.remove_label') : __('moderation.show.grant_label') }}
                    </button>
                </form>
            </section>
            @endif
            @endcan

            @can('trash', $testimony)
            <form id="trash-form" method="POST" action="{{ route('moderation.trash', $testimony->id) }}">
                @csrf @method('DELETE')
                <button type="button" class="btn-ghost w-full text-error-700"
                        onclick="openConfirmModal('trash-form', @js(__('moderation.show.trash_confirm', ['days' => config('testimonies.trash_days', 30)])), @js(__('moderation.bulk_actions.trash')), @js(__('moderation.bulk_actions.trash')), 'fa-trash')">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>{{ __('moderation.bulk_actions.trash') }}
                </button>
            </form>
            @endcan
        @endif

        <a href="{{ route('moderation.index') }}" class="btn-ghost w-full"><i class="fa-solid fa-arrow-left"></i>{{ __('moderation.show.back_to_list') }}</a>
    </aside>
</div>
@endsection
