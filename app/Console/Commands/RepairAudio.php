<?php

namespace App\Console\Commands;

use App\Models\MediaFile;
use App\Models\Prophecy;
use App\Models\Testimony;
use App\Support\Mp4AudioRepair;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Répare les audios déjà envoyés que Chrome et Edge refusent de lire (trame de configuration AAC
 * écrite comme son) et reclasse en audio les fichiers MP4 sans image rangés comme vidéos.
 * Voir docs/fonctionnalites/lecture-audio.md
 */
class RepairAudio extends Command
{
    protected $signature = 'media:repair-audio
                            {--dry-run : Lister les fichiers concernés sans les modifier}';

    protected $description = 'Répare les audios illisibles dans Chrome / Edge et reclasse les MP4 sans image en audio';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $dry  = (bool) $this->option('dry-run');

        $files = collect($disk->allFiles('media'))
            ->filter(fn ($p) => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), Mp4AudioRepair::EXTENSIONS, true));

        $repaired = 0;
        foreach ($files as $path) {
            if (!Mp4AudioRepair::needsRepair($disk->path($path))) {
                continue;
            }
            $this->line(($dry ? 'À réparer : ' : 'Réparé : ') . $path);
            if ($dry) {
                $repaired++;
                continue;
            }
            if (!Mp4AudioRepair::repair($disk->path($path))) {
                $this->warn("Échec de la réparation : {$path}");
                continue;
            }
            $repaired++;
            $this->bumpUrls($path);
        }

        $reclassified = $this->reclassify($dry);

        $this->info(($dry ? 'Simulation : ' : '')
            . "{$repaired} fichier(s) " . ($dry ? 'à réparer' : 'réparé(s)')
            . ", {$reclassified} fichier(s) " . ($dry ? 'à reclasser' : 'reclassé(s)') . ' en audio'
            . " ({$files->count()} fichier(s) MP4 examiné(s)).");

        return self::SUCCESS;
    }

    /**
     * Nouvelle adresse (?v=…) pour les liens vers ce fichier : un navigateur qui a gardé l'ancienne
     * version en cache (Cache-Control 7 jours sur /storage) charge la version réparée.
     */
    private function bumpUrls(string $path): void
    {
        $version = (string) time();
        $bump = fn (?string $url) => $url && MediaFile::publicPathFromUrl($url) === $path
            ? strtok($url, '?#') . '?v=' . $version
            : null;

        MediaFile::where('disk', 'public')->where('path', $path)->each(function (MediaFile $media) use ($bump) {
            if ($url = $bump($media->url)) {
                $media->update(['url' => $url]);
            }
        });

        $like = '%/storage/' . str_replace(['%', '_'], ['\%', '\_'], $path) . '%';
        Testimony::withTrashed()->where('media_url', 'like', $like)->each(function (Testimony $testimony) use ($bump) {
            if ($url = $bump($testimony->media_url)) {
                $testimony->forceFill(['media_url' => $url])->saveQuietly();
            }
        });
        Prophecy::where('audio_url', 'like', $like)->each(function (Prophecy $prophecy) use ($bump) {
            if ($url = $bump($prophecy->audio_url)) {
                $prophecy->forceFill(['audio_url' => $url])->saveQuietly();
            }
        });
    }

    /** Fichiers enregistrés comme vidéos mais sans piste vidéo : audio, et conversion relancée. */
    private function reclassify(bool $dry): int
    {
        $count = 0;
        MediaFile::where('disk', 'public')->where('type', 'video')->each(function (MediaFile $media) use ($dry, &$count) {
            $file = Storage::disk('public')->path($media->path);
            if (!is_file($file) || !Mp4AudioRepair::isAudioOnly($file)) {
                return;
            }
            $count++;
            $this->line(($dry ? 'À reclasser en audio : ' : 'Reclassé en audio : ') . $media->path);
            if ($dry) {
                return;
            }
            $media->update(['type' => 'audio', 'mime_type' => 'audio/mp4']);
            if (!$media->isProcessed()) {
                $media->queueTranscoding();
            }
        });

        return $count;
    }
}
