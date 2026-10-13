<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VerseStyles;
use App\Support\VerseDesigns;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Designs du verset du jour : catalogue (public) et préférences du membre, synchronisées entre ses
 * appareils. Voir docs/fonctionnalites/designs-du-verset.md
 */
class VerseStyleController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly VerseStyles $styles) {}

    // GET /verse-designs  (public, 24 h)
    public function designs(): JsonResponse
    {
        $catalogue = Cache::remember('verse-designs:catalogue:' . md5(serialize(config('verse_designs'))), now()->addDay(),
            fn () => VerseDesigns::catalogue());

        return $this->success($catalogue)->header('Cache-Control', 'public, max-age=86400');
    }

    // GET /me/verse-style
    public function show(Request $request): JsonResponse
    {
        return $this->success($this->styles->forUser($request->user()));
    }

    // PUT /me/verse-style  { design_id?, design_chosen_on?, design_always?, font?, text_size?, text_align? }
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(VerseStyles::rules(), VerseStyles::MESSAGES);

        return $this->success($this->styles->update($request->user(), $data), 'Préférences enregistrées.');
    }
}
