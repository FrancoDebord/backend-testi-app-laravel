<?php

namespace App\Console\Commands;

use App\Services\EventModeration;
use Illuminate\Console\Command;

/**
 * Sondage d'après l'événement : invite une seule fois (`survey_sent_at`) les participants des
 * événements terminés à dire s'ils ont bien eu lieu. docs/fonctionnalites/evenements.md
 */
class SendEventSurveys extends Command
{
    protected $signature = 'events:send-surveys';

    protected $description = "Invite les participants des événements terminés à répondre au sondage « A-t-il vraiment eu lieu ? »";

    public function handle(EventModeration $moderation): int
    {
        $count = $moderation->sendSurveys();
        $this->info("{$count} invitation(s) envoyée(s).");

        return self::SUCCESS;
    }
}
