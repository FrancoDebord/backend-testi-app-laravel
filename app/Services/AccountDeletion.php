<?php

namespace App\Services;

use App\Models\AccountDeletionRequest;
use App\Models\Event;
use App\Models\MediaFile;
use App\Models\PrayerRequest;
use App\Models\PrayerSession;
use App\Models\Prophecy;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Suppression DÉFINITIVE d'un compte et de ses données (docs/fonctionnalites/confidentialite-et-suppression.md) :
 * profil, témoignages (vidéos Bunny comprises), sujets de prière, paroles prophétiques, préférences,
 * jetons de notification et jetons de connexion, fichiers stockés. Le reste (réactions, commentaires,
 * abonnements…) part avec la ligne users (clés étrangères en cascade).
 * Les super-administrateurs ne peuvent pas être supprimés par cette voie.
 */
final class AccountDeletion
{
    /** Le compte peut-il être supprimé par son titulaire ? */
    public static function allowed(User $user): bool
    {
        return !$user->isSuperAdmin();
    }

    /**
     * @param User|null $actor super-administrateur qui traite une demande ou supprime depuis la fiche ; null : le titulaire lui-même
     * @param string|null $reason motif consigné dans le journal d'audit (par défaut selon $actor)
     * @throws \DomainException super-administrateur
     */
    public function delete(User $user, ?User $actor = null, ?string $reason = null): void
    {
        if (!self::allowed($user)) {
            throw new \DomainException(__('privacy.deletion.super_admin_forbidden'));
        }

        /** @var array<int, array{0: string, 1: string}> $files [disque, chemin] */
        $files = [];
        /** @var array<int, array{0: string, 1: string}> $directories */
        $directories = [];

        $summary = [
            'account_type' => $user->account_type?->value,
            'testimonies'  => Testimony::withTrashed()->where('user_id', $user->id)->count(),
        ];

        DB::transaction(function () use ($user, &$files, &$directories) {
            $this->collectUrl($user->avatar_url, $files);
            $this->collectUrl($user->cover_url, $files);

            // Témoignages, y compris ceux de la corbeille : suppression définitive (vidéo Bunny après la réponse,
            // Testimony::forceDeleted).
            Testimony::withTrashed()->where('user_id', $user->id)->each(function (Testimony $t) use (&$files, &$directories) {
                $this->collectUrl($t->media_url, $files);
                $this->collectUrl($t->cover_url, $files);
                $this->collectRenditions($t->renditions, $files);
                $directories[] = [TestimonyProofs::DISK, 'proofs/' . $t->id];
                $t->forceDelete();
            });

            Prophecy::withTrashed()->where('user_id', $user->id)->each(function (Prophecy $p) use (&$files) {
                $this->collectUrl($p->audio_url, $files);
                $p->forceDelete();
            });
            PrayerRequest::withTrashed()->where('user_id', $user->id)->each(fn (PrayerRequest $r) => $r->forceDelete());
            PrayerSession::withTrashed()->where('host_id', $user->id)->each(fn (PrayerSession $s) => $s->forceDelete());

            // Événements d'une organisation (ou de la personne) : images comprises.
            Event::withTrashed()->where('organizer_id', $user->id)->with('images')->each(function (Event $e) use (&$files) {
                foreach ($e->images as $image) {
                    $this->collectUrl($image->url, $files);
                }
                $e->forceDelete();
            });

            // Fichiers envoyés (audios, vidéos, images, enregistrements de directs) et leurs qualités.
            MediaFile::where('user_id', $user->id)->each(function (MediaFile $m) use (&$files) {
                $files[] = [$m->disk ?: 'public', $m->path];
                $this->collectRenditions($m->renditions, $files);
                $m->delete();
            });

            $user->settings()->delete();
            $user->verseStylePreference()->delete();
            $user->deviceTokens()->delete();
            $user->tokens()->delete();
            $user->drafts()->delete();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            // Compteurs des comptes liés par un abonnement (la ligne follows part en cascade).
            $following = DB::table('follows')->where('follower_id', $user->id)->pluck('following_id');
            User::whereIn('id', $following)->where('follower_count', '>', 0)->decrement('follower_count');
            $followers = DB::table('follows')->where('following_id', $user->id)->pluck('follower_id');
            User::whereIn('id', $followers)->where('following_count', '>', 0)->decrement('following_count');

            AccountDeletionRequest::where('user_id', $user->id)->pending()->update([
                'status' => AccountDeletionRequest::STATUS_DONE, 'handled_at' => now(),
            ]);

            // Pièces de vérification : User::deleted (PersonalVerification::purgeDocuments).
            $user->forceDelete();
        });

        // Fichiers effacés une fois la base à jour : un échec est journalisé, jamais propagé.
        foreach ($files as [$disk, $path]) {
            $this->quietly(fn () => Storage::disk($disk)->delete($path), $path);
        }
        foreach ($directories as [$disk, $path]) {
            $this->quietly(fn () => Storage::disk($disk)->deleteDirectory($path), $path);
        }

        FeaturedTestimonies::forget();
        // Aucune donnée personnelle dans le journal : seulement l'identifiant et des nombres.
        AdminAudit::record($actor, 'user.deleted', $user->id, $summary, null,
            $reason ?? ($actor ? 'Demande de suppression traitée' : 'Suppression par le titulaire'), 'user');
    }

    /** Fichier du disque public désigné par une URL « …/storage/{chemin} » (les URL externes sont ignorées). */
    private function collectUrl(?string $url, array &$files): void
    {
        if (!$url) {
            return;
        }
        if ($path = MediaFile::publicPathFromUrl($url)) {
            $files[] = ['public', $path];
        } elseif ($path = MediaFile::recordingPathFromUrl($url)) {
            $files[] = [MediaFile::RECORDINGS_DISK, $path];
        }
    }

    private function collectRenditions(mixed $renditions, array &$files): void
    {
        foreach ((array) $renditions as $r) {
            if (is_array($r) && !empty($r['path'])) {
                $files[] = [$r['disk'] ?? 'public', $r['path']];
            }
        }
    }

    private function quietly(callable $fn, string $path): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            Log::warning('Suppression de compte : fichier non supprimé.', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }
}
