<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimony;
use App\Services\TestimonyProofs;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Preuves d'un témoignage (application) : ajout (multipart « file », « position » 1 ou 2 facultative),
 * retrait, lecture. Auteur pour l'ajout et le retrait ; auteur et équipe de modération pour la lecture.
 * Voir docs/fonctionnalites/preuves.md
 */
class TestimonyProofController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TestimonyProofs $proofs) {}

    public function store(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony) return $this->notFound();
        if ($testimony->user_id !== $request->user()->id) {
            return $this->forbidden(__('testimonies.proofs_api.author_only_add'));
        }

        $request->validate([
            'file'     => TestimonyProofs::rules(required: true),
            'position' => 'nullable|integer|in:1,2',
        ], TestimonyProofs::messages('file'));

        $proof = $this->proofs->add($testimony, $request->file('file'), $request->user(), $request->integer('position') ?: null);

        return $this->created($this->present($testimony, $proof), __('testimonies.proofs_api.added'));
    }

    public function destroy(Request $request, string $id, string $proofId): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony) return $this->notFound();
        if ($testimony->user_id !== $request->user()->id) {
            return $this->forbidden(__('testimonies.proofs_api.author_only_remove'));
        }
        $proof = $testimony->proofs()->whereKey($proofId)->first();
        if (!$proof) return $this->notFound();

        $this->proofs->delete($proof);

        return $this->success(null, __('testimonies.proofs_api.removed'));
    }

    public function show(Request $request, string $id, string $proofId): StreamedResponse|JsonResponse
    {
        $testimony = Testimony::withTrashed()->find($id);
        if (!$testimony) return $this->notFound();
        if (!TestimonyProofs::canView($request->user() ?? $request->user('sanctum'), $testimony)) {
            return $this->forbidden(__('testimonies.proofs_api.private'));
        }
        $proof = $testimony->proofs()->whereKey($proofId)->first();
        if (!$proof) return $this->notFound();

        return Storage::disk($proof->disk)->response($proof->path, $proof->original_name, [
            'Content-Type'           => $proof->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => TestimonyProofs::arePublic($testimony) ? 'public, max-age=300' : 'private, max-age=300',
        ], 'inline');
    }

    private function present(Testimony $testimony, $proof): array
    {
        return [
            'id'       => $proof->id,
            'position' => $proof->position,
            'name'     => $proof->original_name,
            'mimeType' => $proof->mime_type,
            'size'     => $proof->size_bytes,
            'isPdf'    => $proof->isPdf(),
            'url'      => url("/api/v1/testimonies/{$testimony->id}/proofs/{$proof->id}"),
        ];
    }
}
