<?php

namespace App\Console\Commands;

use App\Services\BunnyStreamVideos;
use Illuminate\Console\Command;

/** Envois de vidéos jamais terminés : docs/fonctionnalites/videos-bunny-stream.md (planifiée chaque heure). */
class ExpireVideoUploads extends Command
{
    protected $signature = 'videos:expire-uploads {--hours=' . BunnyStreamVideos::UPLOAD_EXPIRY_HOURS . ' : Délai en heures depuis la création}';

    protected $description = 'Passe en échec les témoignages vidéo restés en envoi (uploading) trop longtemps et supprime leur vidéo Bunny';

    public function handle(BunnyStreamVideos $videos): int
    {
        $count = $videos->expireStaleUploads(max(1, (int) $this->option('hours')));
        $this->info("{$count} envoi(s) de vidéo abandonné(s) passé(s) en échec.");

        return self::SUCCESS;
    }
}
