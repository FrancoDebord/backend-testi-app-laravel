<?php

namespace App\Console\Commands;

use App\Models\ContentLocation;
use App\Models\Event;
use App\Services\Locations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remplit le lieu des contenus existants (témoignages, requêtes et sessions de prière) depuis le
 * profil actuel de leurs auteurs (ou l'événement lié). Idempotent : un contenu qui a déjà un lieu
 * n'est pas modifié (sauf --refresh). Voir docs/fonctionnalites/localisation.md
 */
class BackfillContentLocations extends Command
{
    protected $signature = 'locations:backfill {--refresh : recalcule aussi les contenus qui ont déjà un lieu (sauf GPS)}';

    protected $description = 'Remplit le lieu grossier des contenus existants depuis le profil des auteurs';

    public function handle(Locations $locations): int
    {
        $sources = [
            ContentLocation::TESTIMONY      => ['testimonies', 'user_id', false],
            ContentLocation::PRAYER_REQUEST => ['prayer_requests', 'user_id', true],
            ContentLocation::PRAYER_SESSION => ['prayer_sessions', 'host_id', true],
        ];
        $refresh = (bool) $this->option('refresh');

        foreach ($sources as $type => [$table, $authorKey, $hasEvent]) {
            $done = 0;
            DB::table($table)
                ->when(!$refresh, fn ($q) => $q->whereNotExists(fn ($e) => $e->from('content_locations')
                    ->whereColumn('content_locations.subject_id', "$table.id")->where('content_locations.subject_type', $type)))
                ->when($refresh, fn ($q) => $q->whereNotExists(fn ($e) => $e->from('content_locations')
                    ->whereColumn('content_locations.subject_id', "$table.id")->where('content_locations.subject_type', $type)
                    ->where('content_locations.source', 'gps')))
                ->select(array_filter(['id', $authorKey, $hasEvent ? 'event_id' : null]))
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($locations, $type, $authorKey, &$done) {
                    $authors = $locations->authorPlaces($rows->pluck($authorKey)->all());
                    $events = Event::whereIn('id', $rows->pluck('event_id')->filter()->all())->get()->keyBy('id');
                    foreach ($rows as $row) {
                        $place = (($row->event_id ?? null) ? Locations::eventPlace($events->get($row->event_id)) : null)
                            ?? ($authors[$row->{$authorKey}] ?? null);
                        if (!$place || $place->isEmpty()) continue;
                        $locations->snapshot($type, $row->id, $row->{$authorKey}, $place);
                        $done++;
                    }
                });
            $this->info("{$table} : {$done} lieu(x) enregistré(s).");
        }

        return self::SUCCESS;
    }
}
