<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Mentions;
use App\Support\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rédaction d'un témoignage sur le site : images insérées dans le texte riche et aperçu avant publication.
 * Voir docs/fonctionnalites/texte-riche-et-apercu.md
 */
class TestimonyComposerController extends Controller
{
    /** POST /publish/images : image insérée dans le texte (5 Mo au plus). Réponse : { url }. */
    public function storeImage(Request $request): JsonResponse
    {
        $request->validate(
            ['image' => 'required|image|mimes:jpeg,png,webp,gif|max:5120'],
            [
                'image.required' => __('testimonies.composer.image_required'),
                'image.image'    => __('testimonies.composer.image_type'),
                'image.mimes'    => __('testimonies.composer.image_type'),
                'image.max'      => __('testimonies.composer.image_max'),
            ],
        );

        $path = $request->file('image')->store(RichText::IMAGE_DIR, 'public');

        return response()->json(['success' => true, 'url' => asset('storage/' . $path)], 201);
    }

    /**
     * POST /publish/apercu : texte mis en forme exactement comme sur la page du témoignage
     * (mentions « @ » comprises). Réponse : { html }.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body_text'     => 'nullable|string|max:100000',
            'mention_ids'   => 'nullable|array|max:20',
            'mention_ids.*' => 'nullable|string',
        ]);
        $text = Mentions::prepare((string) ($data['body_text'] ?? ''), (array) ($data['mention_ids'] ?? []))['text'];

        return response()->json(['success' => true, 'html' => (string) RichText::toHtml($text)]);
    }
}
