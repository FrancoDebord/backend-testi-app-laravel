<?php

namespace App\Services;

use App\Enums\RejectionReason;
use App\Enums\TestimonyStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\AppNotification;
use App\Models\ModerationLog;
use App\Models\Testimony;
use App\Models\User;
use App\Support\NotificationText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gestion des témoignages, commune à l'API et au back-office : modération (approuver, refuser,
 * renvoi après modification par l'auteur, approbation automatique), label « Témoignage approuvé »,
 * mise à la une, corbeille. Chaque action est consignée (moderation_logs + admin_audit_logs).
 * Les droits sont vérifiés par App\Policies\TestimonyPolicy avant l'appel.
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
class TestimonyModeration
{
    private const AUDITED = ['status', 'moderated_by', 'moderated_at', 'rejection_reason', 'is_verified', 'verified_by',
        'verified_at', 'is_featured', 'featured_order', 'featured_from', 'featured_until', 'deleted_at'];

    public static function requiresModeration(): bool
    {
        return (bool) config('testimonies.require_moderation', true);
    }

    // ─── Publication ─────────────────────────────────────────────────────────

    /**
     * Témoignage soumis (création, partage depuis le carnet) : sans modération obligatoire, approuvé
     * aussitôt (moderated_by = null, moderated_at = date de création).
     */
    public function submitted(Testimony $testimony): void
    {
        if (self::requiresModeration() || $testimony->status !== TestimonyStatus::Pending) {
            return;
        }
        $this->publish($testimony, null, $testimony->created_at ?? now());
    }

    public function approve(Testimony $testimony, User $moderator): void
    {
        $before = AdminAudit::snapshot($testimony, self::AUDITED);
        $this->publish($testimony, $moderator, now());

        ModerationLog::create(['testimony_id' => $testimony->id, 'moderator_id' => $moderator->id, 'action' => 'approved', 'created_at' => now()]);
        AdminAudit::record($moderator, 'testimony.approved', $testimony, $before, AdminAudit::snapshot($testimony, self::AUDITED));
        AppNotification::create([
            'recipient_id'    => $testimony->user_id,
            'actor_id'        => $moderator->id,
            'actor_name'      => $moderator->display_name,
            'type'            => 'testimony_approved',
            'testimony_id'    => $testimony->id,
            'testimony_title' => $testimony->title,
            ...NotificationText::fields('notifications.testimony_approved_message', ['title' => $testimony->title]),
            'created_at'      => now(),
        ]);
    }

    /**
     * Refus : motif lisible obligatoire ($reasonText, sinon libellé du code $code). Un témoignage déjà
     * publié est retiré du public. L'auteur reçoit le motif (notification + push).
     */
    public function reject(Testimony $testimony, User $moderator, ?string $reasonText, ?RejectionReason $code = null, ?string $note = null): void
    {
        $reason = trim((string) $reasonText) !== '' ? trim((string) $reasonText) : $code?->label();
        if ($reason === null || $reason === '') {
            throw new ContentActionException(__('testimonies.moderation.reason_required'), 422, ['rejection_reason' => [__('testimonies.moderation.reason_required')]]);
        }

        $before = AdminAudit::snapshot($testimony, self::AUDITED);
        $wasPublished = $testimony->status === TestimonyStatus::Approved;
        $testimony->update([
            'status'           => TestimonyStatus::Rejected->value,
            'moderated_by'     => $moderator->id,
            'moderated_at'     => now(),
            'rejection_reason' => $reason,
        ]);
        if ($wasPublished) {
            $this->uncount($testimony);
        }

        ModerationLog::create([
            'testimony_id'     => $testimony->id,
            'moderator_id'     => $moderator->id,
            'action'           => 'rejected',
            'rejection_reason' => $code?->value,
            'moderator_note'   => $note ?? ($reason !== $code?->label() ? $reason : null),
            'created_at'       => now(),
        ]);
        AdminAudit::record($moderator, 'testimony.rejected', $testimony, $before, AdminAudit::snapshot($testimony, self::AUDITED), $reason);
        AppNotification::create([
            'recipient_id'    => $testimony->user_id,
            'actor_id'        => $moderator->id,
            'actor_name'      => $moderator->display_name,
            'type'            => 'testimony_rejected',
            'testimony_id'    => $testimony->id,
            'testimony_title' => $testimony->title,
            ...NotificationText::fields('notifications.testimony_rejected_message', ['title' => $testimony->title, 'reason' => $reason],
                ['rejection_reason' => $reason, 'reason' => $code?->value]),
            'created_at'      => now(),
        ]);
        self::forgetFeatured();
    }

    /**
     * Modification par l'AUTEUR (PUT /testimonies/{id}, « Modifier et renvoyer ») :
     * - refusé → en attente (motif et modération effacés), modérateurs prévenus ;
     * - approuvé et modération obligatoire → en attente aussi (pas de contournement) ;
     * - le label « Témoignage approuvé » est retiré jusqu'à une nouvelle vérification.
     * Sans modération obligatoire, un témoignage approuvé le reste.
     */
    public function editedByAuthor(Testimony $testimony, User $author): void
    {
        if ($testimony->isInJournal()) {
            return;
        }
        $before = AdminAudit::snapshot($testimony, self::AUDITED);
        $status = $testimony->status;
        $resubmit = $status === TestimonyStatus::Rejected
            || $status === TestimonyStatus::Draft
            || ($status === TestimonyStatus::Approved && self::requiresModeration());

        $changes = $testimony->is_verified ? ['is_verified' => false, 'verified_by' => null, 'verified_at' => null] : [];
        if ($resubmit) {
            $changes += [
                'status'           => TestimonyStatus::Pending->value,
                'rejection_reason' => null,
                'moderated_by'     => null,
                'moderated_at'     => null,
            ];
        }
        if (!$changes) {
            return;
        }

        $testimony->update($changes);
        if ($status === TestimonyStatus::Approved && $resubmit) {
            $this->uncount($testimony);
        }
        if ($resubmit && $status !== TestimonyStatus::Pending) {
            AdminAudit::record($author, 'testimony.resubmitted', $testimony, $before, AdminAudit::snapshot($testimony, self::AUDITED));
            $this->notifyModerators($testimony, $author, $status);
        } elseif (isset($changes['is_verified'])) {
            AdminAudit::record($author, 'testimony.unverified', $testimony, $before, AdminAudit::snapshot($testimony, self::AUDITED), 'Modifié par l\'auteur');
        }
        self::forgetFeatured();

        // Sans modération obligatoire, un témoignage renvoyé (refusé ou brouillon) est republié aussitôt.
        if ($resubmit && !self::requiresModeration()) {
            $this->submitted($testimony->refresh());
        }
    }

    // ─── Label « Témoignage approuvé » (administrateurs) ─────────────────────

    public function setVerified(Testimony $testimony, User $admin, bool $verified, ?string $reason = null): void
    {
        if ($verified && $testimony->status !== TestimonyStatus::Approved) {
            throw new ContentActionException(__('testimonies.moderation.verify_approved_only'), 422);
        }
        $before = AdminAudit::snapshot($testimony, self::AUDITED);
        $testimony->update($verified
            ? ['is_verified' => true, 'verified_by' => $admin->id, 'verified_at' => now()]
            : ['is_verified' => false, 'verified_by' => null, 'verified_at' => null]);
        AdminAudit::record($admin, $verified ? 'testimony.verified' : 'testimony.unverified', $testimony, $before, AdminAudit::snapshot($testimony, self::AUDITED), $reason);
    }

    // ─── À la une ────────────────────────────────────────────────────────────

    public function setFeatured(Testimony $testimony, User $actor, bool $featured, ?int $order = null, ?Carbon $from = null, ?Carbon $until = null): void
    {
        if ($featured && ($testimony->status !== TestimonyStatus::Approved || $testimony->isInJournal())) {
            throw new ContentActionException(__('testimonies.moderation.feature_approved_only'), 422);
        }
        if ($featured && $from && $until && $until->lt($from)) {
            throw new ContentActionException(__('testimonies.moderation.feature_dates'), 422, ['featured_until' => [__('testimonies.moderation.feature_dates')]]);
        }
        $before = AdminAudit::snapshot($testimony, self::AUDITED);
        $testimony->update($featured
            ? ['is_featured' => true, 'featured_order' => $order ?? $testimony->featured_order ?? 0, 'featured_from' => $from, 'featured_until' => $until]
            : ['is_featured' => false, 'featured_order' => null, 'featured_from' => null, 'featured_until' => null]);
        AdminAudit::record($actor, $featured ? 'testimony.featured' : 'testimony.unfeatured', $testimony, $before, AdminAudit::snapshot($testimony, self::AUDITED));
        self::forgetFeatured();
    }

    // ─── Corbeille ───────────────────────────────────────────────────────────

    public function trash(Testimony $testimony, User $actor, ?string $reason = null): void
    {
        $before = AdminAudit::snapshot($testimony, self::AUDITED);
        $testimony->delete();
        AdminAudit::record($actor, 'testimony.deleted', $testimony, $before, AdminAudit::snapshot($testimony, self::AUDITED), $reason);
        self::forgetFeatured();
    }

    /** Restauration pendant config('testimonies.trash_days') jours. */
    public function restore(Testimony $testimony, User $actor): void
    {
        if (!$testimony->trashed()) {
            return;
        }
        if (!self::restorable($testimony)) {
            throw new ContentActionException(__('testimonies.moderation.not_restorable', ['days' => config('testimonies.trash_days', 30)]), 422);
        }
        $before = AdminAudit::snapshot($testimony, self::AUDITED);
        $testimony->restore();
        AdminAudit::record($actor, 'testimony.restored', $testimony, $before, AdminAudit::snapshot($testimony, self::AUDITED));
        self::forgetFeatured();
    }

    public static function restorable(Testimony $testimony): bool
    {
        return $testimony->trashed() && $testimony->deleted_at->gte(now()->subDays((int) config('testimonies.trash_days', 30)));
    }

    /** Suppression définitive (super-administrateur, ou purge de la corbeille si $actor est null) : vidéo Bunny comprise. */
    public function forceDelete(Testimony $testimony, ?User $actor, ?string $reason = null): void
    {
        $before = AdminAudit::snapshot($testimony, self::AUDITED) + ['title' => $testimony->title, 'user_id' => $testimony->user_id];
        $wasPublished = !$testimony->trashed() && $testimony->status === TestimonyStatus::Approved;
        $testimony->forceDelete();
        if ($wasPublished) {
            $this->uncount($testimony);
        }
        AdminAudit::record($actor, 'testimony.force_deleted', $testimony->id, $before, null, $reason ?? ($actor ? null : 'Corbeille de plus de ' . config('testimonies.trash_days', 30) . ' jours'), 'testimony');
        self::forgetFeatured();
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    public static function forgetFeatured(): void
    {
        FeaturedTestimonies::forget();
    }

    /**
     * Passage à « approuvé ». À la PREMIÈRE publication seulement (approved_at vide) : abonnés prévenus ;
     * mentions à chaque approbation (sans doublon) ; le compteur de l'auteur augmente à chaque entrée dans le public.
     */
    private function publish(Testimony $testimony, ?User $moderator, Carbon $at): void
    {
        $firstPublication = $testimony->approved_at === null;
        $wasPublished = $testimony->status === TestimonyStatus::Approved;

        $testimony->update([
            'status'           => TestimonyStatus::Approved->value,
            'moderated_by'     => $moderator?->id,
            'moderated_at'     => $at,
            'rejection_reason' => null,
            'approved_by'      => $firstPublication ? $moderator?->id : $testimony->approved_by,
            'approved_at'      => $firstPublication ? now() : $testimony->approved_at,
        ]);

        if (!$wasPublished) {
            User::whereKey($testimony->user_id)->increment('testimony_count');
        }
        if ($firstPublication) {
            FollowerNotifications::newTestimony($testimony->fresh('user'));
        }
        // Personnes mentionnées : une seule fois chacune, y compris celles ajoutées en modifiant (Mentions dédoublonne).
        Mentions::notifyTestimony($testimony->fresh('user'));
        self::forgetFeatured();
    }

    private function uncount(Testimony $testimony): void
    {
        User::whereKey($testimony->user_id)->where('testimony_count', '>', 0)->decrement('testimony_count');
    }

    /** Modérateurs et administrateurs actifs prévenus d'un témoignage renvoyé (notification + push). */
    private function notifyModerators(Testimony $testimony, User $author, TestimonyStatus $previous): void
    {
        $text = NotificationText::fields(
            $previous === TestimonyStatus::Rejected ? 'notifications.testimony_resubmitted_rejected_message' : 'notifications.testimony_resubmitted_message',
            ['actor' => $author->display_name, 'title' => $testimony->title],
            ['previous_status' => $previous->value],
        );

        User::whereIn('role', [UserRole::Moderateur->value, UserRole::Administrateur->value])
            ->where('status', UserAccountStatus::Active->value)
            ->whereKeyNot($author->id)
            ->select('id')->chunkById(100, function ($moderators) use ($testimony, $author, $text) {
                foreach ($moderators as $m) {
                    AppNotification::create([
                        'recipient_id'    => $m->id,
                        'actor_id'        => $author->id,
                        'actor_name'      => $author->display_name,
                        'type'            => 'testimony_resubmitted',
                        'testimony_id'    => $testimony->id,
                        'testimony_title' => $testimony->title,
                        ...$text,
                        'created_at'      => now(),
                    ]);
                }
            });
    }
}
