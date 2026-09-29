<?php

namespace App\Console\Commands;

use App\Jobs\TranscodeMediaJob;
use App\Models\LiveSession;
use App\Models\MediaFile;
use App\Models\Testimony;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * (Re)produit les versions allégées des fichiers audio et vidéo existants
 * (docs/fonctionnalites/qualites-media.md).
 */
class TranscodeMedia extends Command
{
    protected $signature = 'media:transcode
                            {--missing : Uniquement les fichiers sans versions (jamais convertis ou en échec)}
                            {--id= : Identifiant d\'un fichier média (media_files.id)}
                            {--sync : Convertir immédiatement au lieu de passer par la file d\'attente}';

    protected $description = 'Produit les versions allégées (360p, 64k…) des fichiers audio et vidéo existants';

    public function handle(): int
    {
        if ($id = $this->option('id')) {
            $media = MediaFile::find($id);
            if (!$media || !$media->isTranscodable()) {
                $this->error("Fichier média audio/vidéo introuvable : {$id}");
                return self::FAILURE;
            }

            $this->process($media);
            $this->info('1 fichier traité.');

            return self::SUCCESS;
        }

        $created = $this->registerTestimonyMedia();
        if ($created > 0) {
            $this->info("{$created} fichier(s) de témoignage enregistré(s) dans media_files.");
        }

        $query = MediaFile::whereIn('type', ['audio', 'video'])->orderBy('created_at');
        if ($this->option('missing')) {
            $query->where('processing_status', '!=', MediaFile::STATUS_DONE);
        }

        $count = 0;
        $query->each(function (MediaFile $media) use (&$count) {
            $this->process($media);
            $count++;
        });

        $this->info($this->option('sync')
            ? "{$count} fichier(s) traité(s)."
            : "{$count} fichier(s) mis en file d'attente (php artisan queue:work).");

        return self::SUCCESS;
    }

    private function process(MediaFile $media): void
    {
        if (!$this->option('sync')) {
            $media->update(['processing_status' => MediaFile::STATUS_PENDING]);
            TranscodeMediaJob::dispatch($media->id);
            return;
        }

        try {
            TranscodeMediaJob::dispatchSync($media->id);
        } catch (Throwable $e) {
            $media->update(['processing_status' => MediaFile::STATUS_FAILED]);
            $this->warn("{$media->path} : {$e->getMessage()}");
            return;
        }

        $media->refresh();
        $this->line("{$media->path} : {$media->processing_status}, " . count($media->renditions ?? []) . ' version(s)');
    }

    /**
     * Témoignages audio/vidéo sans ligne media_files : fichier du disque « public » (envois antérieurs,
     * site web) ou enregistrement de direct (disque « recordings », S3). La ligne est créée pour
     * pouvoir le convertir.
     */
    private function registerTestimonyMedia(): int
    {
        $disk    = Storage::disk('public');
        $created = 0;

        Testimony::withTrashed()
            ->whereIn('type', ['audio', 'video'])
            ->whereNotNull('media_url')
            ->select(['id', 'user_id', 'type', 'media_url', 'duration_sec'])
            ->each(function (Testimony $testimony) use ($disk, &$created) {
                if (MediaFile::findForUrl($testimony->media_url)) {
                    return;
                }

                // Enregistrement de direct : URL LIVEKIT_RECORDING_PUBLIC_URL/{chemin}, ou direct lié au témoignage
                // (adresse publique modifiée depuis). L'existence du fichier est vérifiée par la tâche.
                $recording = $testimony->type->value === 'video'
                    ? (MediaFile::recordingPathFromUrl($testimony->media_url)
                        ?? LiveSession::where('testimony_id', $testimony->id)->value('recording_path'))
                    : null;
                if ($recording) {
                    MediaFile::forRecording($recording, $testimony->user_id, $testimony->media_url, (int) $testimony->duration_sec);
                    $created++;
                    return;
                }

                $path = MediaFile::publicPathFromUrl($testimony->media_url);
                if (!$path || !$disk->exists($path)) {
                    return; // autre stockage externe ou fichier absent
                }

                MediaFile::create([
                    'user_id'       => $testimony->user_id,
                    'disk'          => 'public',
                    'path'          => $path,
                    'url'           => $testimony->media_url,
                    'mime_type'     => mb_substr((string) ($disk->mimeType($path) ?: 'application/octet-stream'), 0, 50),
                    'type'          => $testimony->type->value,
                    'size_bytes'    => $disk->size($path),
                    'original_name' => basename($path),
                ]);
                $created++;
            });

        return $created;
    }
}
