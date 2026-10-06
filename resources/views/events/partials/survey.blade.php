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
    <h2 id="event-survey-title" class="card-title">Votre avis</h2>

    @if($total > 0)
    <p class="mt-1 text-sm text-slate-600">
        @if($percent !== null)<span class="font-semibold text-slate-900">{{ $percent }} %</span> confirment qu'il a eu lieu @endif
        @if($average !== null)· <span class="font-semibold text-amber-600"><i class="fa-solid fa-star text-xs" aria-hidden="true"></i> {{ number_format($average, 1, ',', ' ') }}</span><span class="sr-only"> sur 5</span> @endif
        · {{ $total }} avis
    </p>
    @else
    <p class="mt-1 text-sm text-slate-500">Aucun avis pour le moment.</p>
    @endif

    @if($manager)
        <p class="mt-3 text-xs text-slate-500">Les organisateurs et gestionnaires ne répondent pas au sondage de leur propre événement.</p>
    @elseif(!auth()->check())
        @if($event->acceptsReviews())
        <a href="{{ route('login') }}" class="btn-secondary mt-4 w-full">Se connecter pour donner son avis</a>
        @endif
    @elseif($canReview)
        <form method="POST" action="{{ route('events.review', $event->id) }}" class="mt-4 space-y-4" data-loading-label="Envoi…">
            @csrf
            <fieldset>
                <legend class="form-label">L'événement a-t-il eu lieu ?</legend>
                <div class="mt-2 space-y-2">
                    @foreach(EventReview::ATTENDED as $value => $label)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="attended" value="{{ $value }}" required @checked($attended === $value)>
                        {{ $label }}
                    </label>
                    @endforeach
                </div>
                @error('attended')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
            <fieldset>
                <legend class="form-label">Note (si vous y étiez)</legend>
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
                <label for="review-body" class="form-label">Votre avis (facultatif)</label>
                <textarea id="review-body" name="body" rows="3" maxlength="2000" class="form-input" placeholder="Ce que vous avez vécu, l'organisation…">{{ old('body', $myReview?->body) }}</textarea>
                @error('body')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full">{{ $myReview ? 'Modifier mon avis' : 'Envoyer mon avis' }}</button>
            @if($myReview)
            <p class="text-center text-xs text-slate-500">Modifiable jusqu'au {{ $myReview->editableUntil()->translatedFormat('j F Y') }}.</p>
            @endif
        </form>
    @elseif($myReview)
        <p class="mt-3 text-sm text-slate-600">Votre réponse : <strong>{{ EventReview::ATTENDED[$myReview->attended] ?? '' }}</strong>@if($myReview->rating) · {{ $myReview->rating }}/5 @endif.</p>
    @endif
</section>
