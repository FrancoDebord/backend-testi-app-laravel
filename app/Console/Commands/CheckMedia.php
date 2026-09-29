<?php

namespace App\Console\Commands;

use App\Models\MediaFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Diagnostic des versions allégées (qualités) : ffmpeg, file d'attente, état des fichiers.
 * Sans versions, les lecteurs ne proposent que la qualité d'origine. Voir docs/fonctionnalites/qualites-media.md
 */
class CheckMedia extends Command
{
    protected $signature = 'media:check';

    protected $description = 'Vérifie que les versions allégées (360p, 64k…) peuvent être produites et indique quoi corriger';

    public function handle(): int
    {
        $problems = 0;

        if (!config('media.transcoding_enabled')) {
            $this->warn('Conversion désactivée (MEDIA_TRANSCODING_ENABLED=false) : aucun nouvel envoi ne sera converti.');
            $problems++;
        }

        foreach (['ffmpeg' => config('media.ffmpeg_binary'), 'ffprobe' => config('media.ffprobe_binary')] as $name => $binary) {
            $version = $this->version($binary);
            if ($version === null) {
                $this->error("{$name} introuvable (« {$binary} »). Installez-le (ex. sudo apt install ffmpeg) "
                    . 'ou indiquez son chemin dans ' . strtoupper($name) . '_BINARY.');
                $problems++;
            } else {
                $this->info("{$name} : {$version}");
            }
        }

        $queue = config('queue.default');
        $this->line("File d'attente : {$queue}" . ($queue === 'sync' ? ' (conversion pendant l\'envoi : déconseillé)' : ''));
        if ($queue === 'database') {
            $waiting = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $this->line("  tâches en attente : {$waiting} · tâches en échec : {$failedJobs}");
            if ($waiting > 0) {
                $this->warn("  Des tâches attendent : vérifiez que « php artisan queue:work » tourne en permanence.");
            }
        }

        $counts = MediaFile::whereIn('type', ['audio', 'video'])
            ->select('processing_status', DB::raw('count(*) as total'))
            ->groupBy('processing_status')->pluck('total', 'processing_status');
        $this->table(['État', 'Fichiers audio/vidéo'], collect(['done', 'pending', 'processing', 'failed', 'none'])
            ->map(fn ($s) => [$s, (int) ($counts[$s] ?? 0)])->all());

        if (($counts['failed'] ?? 0) > 0) {
            $this->warn('Fichiers en échec : une fois le problème corrigé, relancez « php artisan media:transcode --missing ».');
            $problems++;
        }

        if ($problems === 0) {
            $this->info('Tout est prêt : les nouveaux envois auront leurs versions allégées.');
        }

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function version(?string $binary): ?string
    {
        if (!$binary) {
            return null;
        }
        try {
            $result = Process::timeout(15)->run([$binary, '-version']);
        } catch (Throwable) {
            return null;
        }

        return $result->successful() ? strtok(trim($result->output()), "\n") : null;
    }
}
