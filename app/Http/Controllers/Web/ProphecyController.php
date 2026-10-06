<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Prophecy;
use App\Services\Prophecies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Paroles prophétiques du carnet privé, sur le site : garder, prier, proclamer, témoigner.
 * Visibles de leur seul auteur (404 pour tout autre). Règles communes avec l'API : App\Services\Prophecies.
 * Voir docs/fonctionnalites/paroles-prophetiques.md
 */
class ProphecyController extends Controller
{
    public const WEEKDAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    public function __construct(private readonly Prophecies $prophecies) {}

    /** ?statut = waiting (par défaut) | fulfilled */
    public function index(Request $request): View
    {
        $status = $request->query('statut') === Prophecy::FULFILLED ? Prophecy::FULFILLED : Prophecy::WAITING;
        $items  = $this->prophecies->list($request->user(), $status)->paginate(12)->withQueryString();
        $counts = $this->prophecies->counts($request->user());

        return view('journal.prophecies.index', compact('items', 'status', 'counts'));
    }

    public function create(): View
    {
        return view('journal.prophecies.form', ['prophecy' => new Prophecy(['received_on' => now()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $prophecy = $this->prophecies->create($request->user(), $data);

        return redirect()->route('prophecies.show', $prophecy->id)->with('success', 'Parole enregistrée dans votre carnet.');
    }

    public function show(Request $request, string $id): View
    {
        $prophecy = $this->prophecies->find($request->user(), $id);
        $prayers  = $prophecy->prayers()->paginate(20);

        return view('journal.prophecies.show', compact('prophecy', 'prayers'));
    }

    public function edit(Request $request, string $id): View
    {
        return view('journal.prophecies.form', ['prophecy' => $this->prophecies->find($request->user(), $id)]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $prophecy = $this->prophecies->find($request->user(), $id);
        $data = $this->validated($request, $prophecy);
        $this->prophecies->update($prophecy, $data);

        return redirect()->route('prophecies.show', $prophecy->id)->with('success', 'Parole mise à jour.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->prophecies->delete($this->prophecies->find($request->user(), $id));

        return redirect()->route('prophecies.index')->with('success', 'Parole supprimée de votre carnet.');
    }

    /** « Elle s'est accomplie », sans témoigner (témoigner reste possible ensuite). */
    public function fulfill(Request $request, string $id): RedirectResponse
    {
        $prophecy = $this->prophecies->find($request->user(), $id);
        $data = $request->validate(['fulfilled_on' => ['nullable', 'date', 'before_or_equal:today']], [
            'fulfilled_on.before_or_equal' => "La date d'accomplissement ne peut pas être dans le futur.",
        ]);
        $this->prophecies->update($prophecy, ['status' => Prophecy::FULFILLED, 'fulfilled_on' => $data['fulfilled_on'] ?? null]);

        return redirect()->route('prophecies.show', $prophecy->id)->with('success', 'Gloire à Dieu ! La parole est marquée accomplie.');
    }

    /** Retour « en attente » : impossible si un témoignage est déjà publié pour la parole. */
    public function reopen(Request $request, string $id): RedirectResponse
    {
        $prophecy = $this->prophecies->find($request->user(), $id);
        try {
            $this->prophecies->update($prophecy, ['status' => Prophecy::WAITING]);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('prophecies.show', $prophecy->id)->with('success', 'La parole est de nouveau en attente.');
    }

    // ─── Journal de prière ───────────────────────────────────────────────────

    public function pray(Request $request, string $id): RedirectResponse
    {
        $prophecy = $this->prophecies->find($request->user(), $id);
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $this->prophecies->pray($prophecy, $data['note'] ?? null);

        return redirect()->route('prophecies.show', $prophecy->id)->with('success', "Prière enregistrée. Dieu veille sur sa parole pour l'accomplir.");
    }

    public function deletePrayer(Request $request, string $id, int $prayer): RedirectResponse
    {
        $prophecy = $this->prophecies->find($request->user(), $id);
        $this->prophecies->deletePrayer($prophecy, $prayer);

        return redirect()->route('prophecies.show', $prophecy->id)->with('success', 'Prière retirée du journal.');
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    /**
     * Champs du formulaire : la parole en texte, en audio (fichier envoyé) ou les deux.
     * L'audio est rangé comme les médias du carnet (disque public, nom aléatoire).
     */
    private function validated(Request $request, ?Prophecy $current = null): array
    {
        $rules = $this->prophecies->rules(partial: true);
        unset($rules['audio_url']);
        $data = $request->validate([
            ...$rules,
            'due_on'       => ['nullable', 'date', 'after_or_equal:' . ($request->input('received_on') ?: now()->toDateString())],
            'audio'        => ['nullable', 'file', 'max:20480', 'mimetypes:audio/*,video/webm,video/mp4'],
            'remove_audio' => ['nullable', 'boolean'],
        ], Prophecies::MESSAGES + [
            'audio.max'       => "L'enregistrement ne doit pas dépasser 20 Mo.",
            'audio.mimetypes' => "Le fichier doit être un enregistrement audio (MP3, M4A, WAV…).",
        ]);

        $keepAudio = $current?->audio_url && !$request->boolean('remove_audio');
        if (blank($data['body_text'] ?? null) && !$request->hasFile('audio') && !$keepAudio) {
            throw ValidationException::withMessages(['body_text' => Prophecies::MESSAGES['body_text.required_without']]);
        }

        if ($request->hasFile('audio')) {
            $path = $request->file('audio')->store('media', 'public');
            $data['audio_url'] = asset('storage/' . $path);
            $data['audio_duration'] = (int) ($data['audio_duration'] ?? 0);
        } elseif ($current && !$keepAudio) {
            $data['audio_url'] = null;
            $data['audio_duration'] = 0;
        } else {
            unset($data['audio_duration']);
        }
        unset($data['audio'], $data['remove_audio']);
        $data['reminder_frequency'] ??= null;

        return $data;
    }
}
