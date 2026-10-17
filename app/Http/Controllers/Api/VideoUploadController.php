<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InitVideoUploadRequest;
use App\Http\Resources\TestimonyResource;
use App\Models\Testimony;
use App\Services\BunnyStream;
use App\Services\BunnyStreamVideos;
use App\Services\ContentActionException;
use App\Services\TestimonyCreator;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi d'un témoignage vidéo directement à Bunny Stream, sans passer par ce serveur
 * (docs/fonctionnalites/videos-bunny-stream.md) :
 * upload-init → envoi TUS par l'application (upload-signature pour renouveler l'autorisation) → upload-complete.
 */
class VideoUploadController extends Controller
{
    use ApiResponse;

    public function __construct(private BunnyStreamVideos $videos) {}

    /** POST /videos/upload-init : témoignage en « uploading » (hors listes publiques) + vidéo Bunny. */
    public function init(InitVideoUploadRequest $request, TestimonyCreator $creator, BunnyStream $bunny): JsonResponse
    {
        if (!$bunny->isConfigured()) {
            return $this->error(__('videos.upload.unavailable'), 503);
        }

        try {
            // Échec chez Bunny : le témoignage n'est pas conservé.
            $payload = DB::transaction(fn () => $this->videos->initUpload(
                $creator->create($request, Testimony::VIDEO_UPLOADING)
            ));
        } catch (ContentActionException $e) {
            return $this->error($e->getMessage(), $e->status(), $e->errors());
        } catch (Throwable $e) {
            Log::error('Bunny Stream : création de la vidéo impossible.', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);
            return $this->error(__('videos.upload.init_failed'), 502);
        }

        return $this->created($payload, __('videos.upload.ready'));
    }

    /** POST /videos/{id}/upload-signature : nouvelle autorisation TUS pour la même vidéo Bunny. */
    public function signature(Request $request, string $id): JsonResponse
    {
        $testimony = $this->ownedUpload($request, $id);
        if ($testimony instanceof JsonResponse) {
            return $testimony;
        }

        if ($testimony->video_status !== Testimony::VIDEO_UPLOADING || !$testimony->bunny_video_id) {
            return $this->error(__('videos.upload.not_uploading'), 409);
        }

        return $this->success(app(BunnyStream::class)->uploadSignature($testimony->bunny_video_id));
    }

    /** POST /videos/{id}/upload-complete : envoi terminé, Bunny encode la vidéo. */
    public function complete(Request $request, string $id): JsonResponse
    {
        $testimony = $this->ownedUpload($request, $id);
        if ($testimony instanceof JsonResponse) {
            return $testimony;
        }

        // Sans effet si le webhook a déjà fait avancer la vidéo (processing, ready).
        if ($testimony->video_status === Testimony::VIDEO_UPLOADING) {
            $testimony->forceFill(['video_status' => Testimony::VIDEO_PROCESSING, 'video_processing_at' => now()])->save();
        }

        return $this->success(new TestimonyResource($testimony->load('user')), __('videos.upload.processing'));
    }

    /** Témoignage vidéo Bunny de l'utilisateur connecté, sinon la réponse d'erreur. */
    private function ownedUpload(Request $request, string $id): Testimony|JsonResponse
    {
        $testimony = Testimony::whereNotNull('video_status')->find($id);
        if (!$testimony) {
            return $this->notFound(__('videos.upload.not_found'));
        }

        if ($testimony->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        return $testimony;
    }
}
