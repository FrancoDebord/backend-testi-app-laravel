<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Support\Mp4AudioRepair;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    use ApiResponse;

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:102400'], // 100MB max
        ]);

        $file     = $request->file('file');
        $mimeType = $file->getMimeType();
        // Enregistrement .m4a : finfo répond « video/mp4 » ; sans piste vidéo, c'est un audio
        // (dossier media/audios, conversion 32k / 64k / 128k). docs/fonctionnalites/lecture-audio.md
        if (str_starts_with((string) $mimeType, 'video/') && Mp4AudioRepair::isAudioOnly($file->getRealPath())) {
            $mimeType = 'audio/mp4';
        }
        $type     = $this->detectType($mimeType);
        $path     = $file->store("media/{$type}s", 'public');
        $url      = asset('storage/' . $path);
        // Trame de configuration AAC écrite comme son par certains téléphones : illisible dans Chrome.
        Mp4AudioRepair::repairStored('public', $path);

        $media = MediaFile::create([
            'user_id'       => $request->user()->id,
            'disk'          => 'public',
            'path'          => $path,
            'url'           => $url,
            'mime_type'     => $mimeType,
            'type'          => $type,
            'size_bytes'    => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
        ]);

        // Versions allégées (360p, 64k…) produites en file d'attente : docs/fonctionnalites/qualites-media.md
        $media->queueTranscoding();

        return $this->created([
            'id'  => $media->id,
            'url' => $url,
            'type' => $type,
        ], __('videos.upload.file_uploaded'));
    }

    public function presignedUrl(Request $request): JsonResponse
    {
        $request->validate([
            'filename'     => 'required|string',
            'content_type' => 'nullable|string',
        ]);

        // For local storage, return a direct upload URL
        // In production with S3, generate a presigned URL here
        $key = 'media/' . Str::uuid() . '/' . $request->filename;

        return $this->success([
            'url'        => url('/api/v1/media/upload'),
            'key'        => $key,
            'expires_at' => now()->addHour()->toIso8601String(),
        ]);
    }

    private function detectType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) return 'image';
        if (str_starts_with($mimeType, 'audio/')) return 'audio';
        if (str_starts_with($mimeType, 'video/')) return 'video';
        return 'file';
    }
}
