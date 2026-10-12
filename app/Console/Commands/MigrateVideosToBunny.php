<?php

namespace App\Console\Commands;

use App\Models\MediaFile;
use App\Models\Testimony;
use App\Services\BunnyStream;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reprise des anciennes vidéos stockées sur LWS par Bunny Stream : Bunny télécharge lui-même chaque
 * fichier à son URL publique (rien ne transite par ce serveur). Voir docs/fonctionnalites/videos-bunny-stream.md
 *
 * L'ancien fichier (media_url) reste lu tant que la vidéo Bunny n'est pas prête ; aucune notification à l'auteur.
 */
class MigrateVideosToBunny extends Command
{
    protected $signature = 'videos:migrate-to-bunny
        {--dry-run : Affiche les vidéos concernées sans rien envoyer}
        {--limit=10 : Nombre maximal de vidéos traitées}';

    protected $description = 'Fait reprendre par Bunny Stream les vidéos de témoignages encore stockées sur LWS';

    public function handle(BunnyStream $bunny): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = max(1, (int) $this->option('limit'));

        if (!$dryRun && !$bunny->isConfigured()) {
            $this->error('Bunny Stream n\'est pas configuré (BUNNY_STREAM_LIBRARY_ID, BUNNY_STREAM_API_KEY, BUNNY_STREAM_CDN_HOST).');
            return self::FAILURE;
        }

        $query = Testimony::where('type', 'video')
            ->whereNotNull('media_url')->where('media_url', '!=', '')
            ->whereNull('youtube_id')
            ->whereNull('bunny_video_id');

        $remaining   = (clone $query)->count();
        $testimonies = $query->orderBy('created_at')->limit($limit)->get();

        $rows = [];
        $sent = $failed = 0;

        foreach ($testimonies as $testimony) {
            $url = self::publicUrl($testimony->media_url);

            if ($dryRun) {
                $rows[] = [$testimony->id, mb_strimwidth($testimony->title, 0, 40, '…'), $url, 'à envoyer'];
                continue;
            }

            try {
                $guid = $bunny->fetchFromUrl($url, $testimony->title);
                $testimony->forceFill(['bunny_video_id' => $guid, 'video_status' => Testimony::VIDEO_PROCESSING])->save();
                $rows[] = [$testimony->id, mb_strimwidth($testimony->title, 0, 40, '…'), $url, "envoyée ({$guid})"];
                $sent++;
            } catch (Throwable $e) {
                $rows[] = [$testimony->id, mb_strimwidth($testimony->title, 0, 40, '…'), $url, 'échec : ' . mb_strimwidth($e->getMessage(), 0, 60, '…')];
                $failed++;
            }
        }

        if ($rows !== []) {
            $this->table(['Témoignage', 'Titre', 'URL', 'Résultat'], $rows);
        }

        $this->newLine();
        $this->line("Vidéos encore sur LWS avant traitement : {$remaining}");
        if ($dryRun) {
            $this->info('Simulation : ' . count($rows) . ' vidéo(s) seraient envoyées à Bunny (rien n\'a été modifié).');
        } else {
            $this->info("Envoyées à Bunny : {$sent} · Échecs : {$failed} · Restantes : " . max(0, $remaining - $sent));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** URL publique actuelle du fichier : recalculée depuis le chemin (APP_URL a pu changer depuis l'envoi). */
    private static function publicUrl(string $mediaUrl): string
    {
        if ($path = MediaFile::publicPathFromUrl($mediaUrl)) {
            return MediaFile::urlFor('public', $path);
        }
        if ($path = MediaFile::recordingPathFromUrl($mediaUrl)) {
            return MediaFile::urlFor(MediaFile::RECORDINGS_DISK, $path);
        }

        return $mediaUrl;
    }
}
