<?php

namespace App\View\Components;

use App\Models\DailyVerse;
use App\Services\VerseDesignOfTheDay;
use App\Services\VerseStyles;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Carte « Verset du jour » de l'accueil, dans le design du jour (ou celui choisi par le membre).
 * <x-verse-of-the-day-card /> ou <x-verse-of-the-day-card :verse="$verse" />
 * Voir docs/fonctionnalites/designs-du-verset.md
 */
class VerseOfTheDayCard extends Component
{
    public ?DailyVerse $verse;

    public function __construct(?DailyVerse $verse = null)
    {
        $this->verse = $verse ?? DailyVerse::today();
    }

    public function shouldRender(): bool
    {
        return $this->verse !== null;
    }

    public function render(): View
    {
        // Dans la langue de la page (docs/fonctionnalites/verset-du-jour.md).
        $localized = app(\App\Services\DailyVerses::class)->localized($this->verse);
        $text = '« ' . trim($localized['text']) . ' »';
        $view = app(VerseStyles::class)->forView(Auth::user(), VerseDesignOfTheDay::todayDate());

        return view('components.verse-of-the-day-card', [
            ...$view,
            'text'      => $text,
            'reference' => $localized['reference'],
            'sizes' => VerseStyles::sizesFor($text, 'card'),
        ]);
    }
}
