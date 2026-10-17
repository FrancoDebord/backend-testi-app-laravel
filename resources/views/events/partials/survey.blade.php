{{--
    Sondage d'après l'événement (« Votre avis ») : résultats publics et formulaire.
    @include('events.partials.survey', ['event' => $event, 'myReview' => ?EventReview, 'canReview' => bool, 'manager' => bool])
--}}
@use('App\Models\EventReview')
@php
    $percent  = $event->confirmedPercent();
    $average  = $event->ratingAverage();
    $total    = $event->surveyTotal();
    $attended = old('attended', $myReview?->attended);
    $rating   = (int) old('rating', $myReview?->rating);
@endphp
<section class="card scroll-mt-24 p-5" id="votre-avis" aria-labelledby="event-survey-title">
    <h2 id="event-survey-title" class="card-title">{{ __('events.survey.title') }}</h2>

    @if($total > 0)
    <p class="mt-1 text-sm text-slate-600">
        @if($percent !== null)<span class="font-semibold text-slate-900">{{ __('events.survey.percent', ['percent' => $percent]) }}</span> {{ __('events.survey.confirmed') }} @endif
        @if($average !== null)· <span class="font-semibold text-amber-600"><i class="fa-solid fa-star text-xs" aria-hidden="true"></i> {{ \App\Support\Locales::number($average, 1) }}</span><span class="sr-only"> {{ __('events.survey.out_of_5') }}</span> @endif
        · {{ trans_choice('events.survey.reviews_count', $total, ['count' => \App\Support\Locales::number($total)]) }}
    </p>
    @else
    <p class="mt-1 text-sm text-slate-500">{{ __('events.survey.none') }}</p>
    @endif

    @if($manager)
        <p class="mt-3 text-xs text-slate-500">{{ __('events.errors.review_own') }}</p>
    @elseif(!auth()->check())
        @if($event->acceptsReviews())
        <a href="{{ route('login') }}" class="btn-secondary mt-4 w-full">{{ __('events.survey.login') }}</a>
        @endif
    @elseif($canReview)
        <form method="POST" action="{{ route('events.review', $event->id) }}" class="mt-4 space-y-4" data-loading-label="{{ __('events.sending') }}">
            @csrf
            <fieldset>
                <legend class="form-label">{{ __('events.survey.attended') }}</legend>
                <div class="mt-2 space-y-2">
                    @foreach(EventReview::attendedLabels() as $value => $label)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="attended" value="{{ $value }}" required @checked($attended === $value)>
                        {{ $label }}
                    </label>
                    @endforeach
                </div>
                @error('attended')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
            <fieldset>
                <legend class="form-label">{{ __('events.survey.rating') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @for($i = 1; $i <= 5; $i++)
                    <label class="chip cursor-pointer">
                        <input type="radio" name="rating" value="{{ $i }}" @checked($rating === $i)>
                        <i class="fa-solid fa-star text-amber-500" aria-hidden="true"></i>{{ $i }}
                    </label>
                    @endfor
                </div>
                @error('rating')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
            <div>
                <label for="review-body" class="form-label">{{ __('events.survey.body') }}</label>
                <textarea id="review-body" name="body" rows="3" maxlength="2000" class="form-input" placeholder="{{ __('events.survey.body_placeholder') }}">{{ old('body', $myReview?->body) }}</textarea>
                @error('body')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full">{{ $myReview ? __('events.survey.update') : __('events.survey.submit') }}</button>
            @if($myReview)
            <p class="text-center text-xs text-slate-500">{{ __('events.survey.editable_until', ['date' => $myReview->editableUntil()->translatedFormat(__('events.formats.date'))]) }}</p>
            @endif
        </form>
    @elseif($myReview)
        <p class="mt-3 text-sm text-slate-600">{{ __('events.survey.your_answer') }} <strong>{{ EventReview::attendedLabels()[$myReview->attended] ?? '' }}</strong>@if($myReview->rating) · {{ $myReview->rating }}/5 @endif.</p>
    @endif
</section>
