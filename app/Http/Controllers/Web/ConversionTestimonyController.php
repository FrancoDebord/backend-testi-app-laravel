<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Testimony;
use App\Services\ContentActionException;
use App\Services\ConversionTestimonies;
use App\Services\TestimonyModeration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Section « Témoignages de conversion » du site (onglets : conversions des personnes, genèse des ministères des
 * organisations) et modification de son propre témoignage. Mêmes règles que l'API (App\Services\ConversionTestimonies).
 * docs/fonctionnalites/temoignage-de-conversion.md
 */
class ConversionTestimonyController extends Controller
{
    public function __construct(private readonly ConversionTestimonies $conversions) {}

    /** GET /temoignages-de-conversion  ?onglet=ministeres : genèses des ministères. */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $mine = $user ? $this->conversions->of($user) : null;
        $tab  = $request->query('onglet') === 'ministeres' ? 'ministeres' : 'conversions';

        $query = Testimony::query()->published()->with('user');
        $tab === 'ministeres' ? $query->ministryGenesis() : $query->personalConversion();

        return view('testimonies.conversions', [
            'testimonies'   => $query->latestPublished()->orderByDesc('id')->paginate(12)->withQueryString(),
            'tab'           => $tab,
            'mine'          => $mine,
            'canCreate'     => $user !== null && $mine === null && ConversionTestimonies::canPublish($user) && $user->canPublish(),
        ]);
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $testimony = $this->conversions->of($request->user());
        if (!$testimony) {
            return redirect()->route('publish', ['kind' => 'conversion']);
        }

        return view('testimonies.conversion-edit', ['testimony' => $testimony->load('user')]);
    }

    /** Titre, texte et champs propres ; renvoyé en modération comme toute modification par l'auteur. */
    public function update(Request $request, TestimonyModeration $moderation): RedirectResponse
    {
        $testimony = $this->conversions->of($request->user());
        abort_unless($testimony, 404);

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:200'],
            'body_text' => [$testimony->type->value === 'text' ? 'required' : 'nullable', 'string'],
            ...collect(ConversionTestimonies::rules())->except('kind')->all(),
        ], ConversionTestimonies::messages($request->user()));

        try {
            $fields = $this->conversions->attributesForUpdate($testimony, $request);
        } catch (ContentActionException $e) {
            return back()->withInput()->withErrors(['kind' => $e->getMessage()]);
        }

        $testimony->update([
            ...$fields,
            'title'     => $data['title'],
            'body_text' => array_key_exists('body_text', $data) && $data['body_text'] !== null
                ? \App\Services\Mentions::prepare($data['body_text'], [], $testimony->body_text)['text']
                : $testimony->body_text,
        ]);
        $moderation->editedByAuthor($testimony->refresh(), $request->user());

        return redirect()->route('testimonies.show', $testimony->id)
            ->with('success', ConversionTestimonies::label('updated', $request->user()));
    }
}
