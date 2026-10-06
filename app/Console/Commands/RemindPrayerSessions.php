<?php

namespace App\Console\Commands;

use App\Services\PrayerSessions;
use Illuminate\Console\Command;

/** Rappel aux inscrits des sessions de prière qui commencent dans 15 minutes. docs/fonctionnalites/sessions-de-priere.md */
class RemindPrayerSessions extends Command
{
    protected $signature = 'prayer-sessions:remind';

    protected $description = 'Prévient les inscrits des sessions de prière qui commencent dans les 15 prochaines minutes';

    public function handle(PrayerSessions $sessions): int
    {
        $count = $sessions->sendReminders();
        $this->info("{$count} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
