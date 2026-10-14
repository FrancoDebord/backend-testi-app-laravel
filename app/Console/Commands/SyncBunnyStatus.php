<?php

namespace App\Console\Commands;

use App\Models\Testimony;
use App\Services\BunnyStream;
use App\Services\BunnyVideoSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Filet de sécurité du webhook Bunny Stream : relit chez Bunny l'état de chaque vidéo en cours
 * (envoi, traitement) ou sans lien de lecture (media_url vide), avec la même logique que le webhook
 * (App\Services\BunnyVideoSync). Puis alerte les super-administrateurs pour les vidéos en traitement
 * depuis plus de 30 minutes (une fois). Planifiée toutes les 10 minutes, lancée aussi par POST /_deploy.
 *
 *   php artisan videos:sync-bunny-status
 *   php artisan videos:sync-bunny-status --guid=c51171c5-a620-408d-9745-85373ff8b0b7
 *
 * Voir docs/fonctionnalites/videos-bunny-stream.md
 */
class SyncBunnyStatus extends Command
{
    protected $signature = 'videos:sync-bunny-status {--guid= : Traiter une seule vidéo Bunny (GUID)}';

    protected $description = 'Relit chez Bunny Stream l\'état des vidéos en cours ou sans lien de lecture';

    public function handle(BunnyStream $bunny, BunnyVideoSync $sync): int
    {
        if (!$bunny->isConfigured()) {
            $this->warn('Bunny Stream non configuré (BUNNY_STREAM_LIBRARY_ID, BUNNY_STREAM_API_KEY, BUNNY_STREAM_CDN_HOST) : rien à faire.');
            return self::SUCCESS;
        }

        $guid = trim((string) $this->option('guid'));
        $query = Testimony::withTrashed()->whereNotNull('bunny_video_id');
        if ($guid !== '') {
            $query->where('bunny_video_id', $guid);
        } else {
            // Échecs exclus (relus seulement avec --guid) : ils ne changent plus.
            $query->where(fn ($q) => $q->whereNull('video_status')->orWhere('video_status', '!=', Testimony::VIDEO_FAILED))
                ->where(fn ($q) => $q->whereIn('video_status', [Testimony::VIDEO_UPLOADING, Testimony::VIDEO_PROCESSING])
                ->orWhereNull('video_status')
                ->orWhereNull('media_url')->orWhere('media_url', '')
                ->orWhere(fn ($r) => $r->where('video_status', Testimony::VIDEO_READY)->where('media_url', 'not like', '%.m3u8')));
        }

        $counts = ['updated' => 0, 'unchanged' => 0, 'error' => 0];
        $found = 0;
        $query->chunkById(50, function ($items) use ($sync, &$counts, &$found) {
            foreach ($items as $t) {
                $found++;
                $r = $sync->syncFromBunny($t);
                $counts[$r['action']]++;
                $this->line(sprintf('%s  %s  bunny=%s  → %s (%s)', $t->bunny_video_id, $t->id, $r['bunny_status'] ?? '?', $r['video_status'] ?? '—', $r['action']));
                Log::info('Bunny Stream : synchronisation', ['guid' => $t->bunny_video_id, 'testimony_id' => $t->id, 'bunny_status' => $r['bunny_status'], 'action' => $r['action']]);
            }
        });

        if ($guid !== '' && $found === 0) {
            $this->unknownGuid($bunny, $guid);
        }

        $alerted = $sync->alertStuck();
        $this->info("Vidéos relues : {$found} · mises à jour : {$counts['updated']} · inchangées : {$counts['unchanged']} · erreurs : {$counts['error']} · alertes : {$alerted}");

        return self::SUCCESS;
    }

    /** GUID sans témoignage : la vidéo existe-t-elle chez Bunny, et quels témoignages pourraient lui correspondre ? */
    private function unknownGuid(BunnyStream $bunny, string $guid): void
    {
        $title = null;
        try {
            $video = $bunny->getVideo($guid);
            $title = $video['title'] ?? null;
            $this->warn("Aucun témoignage n'a bunny_video_id = {$guid}. Chez Bunny : « {$title} », statut " . ($video['status'] ?? '?') . '.');
        } catch (Throwable $e) {
            $this->warn("Aucun témoignage n'a bunny_video_id = {$guid}, et Bunny ne la renvoie pas : {$e->getMessage()}");
        }

        $candidates = Testimony::withTrashed()->where('type', 'video')->whereNull('bunny_video_id')->whereNull('youtube_id')
            ->where(fn ($q) => $q->whereNull('media_url')->orWhere('media_url', ''))
            ->when($title, fn ($q) => $q->orderByRaw('CASE WHEN title = ? THEN 0 ELSE 1 END', [$title]))
            ->latest()->limit(5)->get(['id', 'title', 'created_at']);
        Log::warning('Bunny Stream : GUID sans témoignage.', ['guid' => $guid, 'bunny_title' => $title, 'candidates' => $candidates->map->only(['id', 'title'])->all()]);
        foreach ($candidates as $c) {
            $this->line("  candidat : {$c->id}  « {$c->title} »  ({$c->created_at})");
        }
    }
}
