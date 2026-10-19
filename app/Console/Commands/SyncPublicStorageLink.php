<?php

namespace App\Console\Commands;

use App\Support\PublicStorageLink;
use Illuminate\Console\Command;

/**
 * Met le lien public/storage en accord avec STORAGE_PUBLIC_LINK (retiré par défaut : Laravel sert les fichiers).
 * Même action que le déploiement (POST /api/v1/_deploy). Voir App\Support\PublicStorageLink.
 */
class SyncPublicStorageLink extends Command
{
    protected $signature = 'storage:public-link';

    protected $description = 'Retire (ou crée, si STORAGE_PUBLIC_LINK=true) le lien public/storage';

    public function handle(): int
    {
        $this->info('public/storage : ' . PublicStorageLink::sync());

        return self::SUCCESS;
    }
}
