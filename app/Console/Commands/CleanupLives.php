<?php

namespace App\Console\Commands;

use App\Services\LiveService;
use Illuminate\Console\Command;

class CleanupLives extends Command
{
    protected $signature = 'lives:cleanup';

    protected $description = 'Clôture les directs abandonnés (préparation trop longue) ou dont le diffuseur a quitté la salle';

    public function handle(LiveService $lives): int
    {
        $count = $lives->cleanup();
        $this->info("{$count} direct(s) clôturé(s).");

        return self::SUCCESS;
    }
}
