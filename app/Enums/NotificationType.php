<?php

namespace App\Enums;

enum NotificationType: string
{
    case Like               = 'like';
    // « Je prie » sur un témoignage (préférence push_prayers)
    case Prayer             = 'prayer';
    case Comment            = 'comment';
    case Reply              = 'reply';
    case Follow             = 'follow';
    case TestimonyApproved  = 'testimony_approved';
    case TestimonyRejected  = 'testimony_rejected';
    case Mention            = 'mention';
    case Share              = 'share';
    case NewFollowedTestimony = 'new_followed_testimony';
    case PendingCorrection  = 'pending_correction';
    // Témoignage modifié par son auteur et renvoyé en modération : prévient les modérateurs
    // (docs/fonctionnalites/gestion-des-temoignages.md)
    case TestimonyResubmitted = 'testimony_resubmitted';
    // Vidéo Bunny en traitement depuis plus de 30 minutes : super-administrateurs (docs/fonctionnalites/videos-bunny-stream.md)
    case VideoStuck = 'video_stuck';
    // Demande de suppression de compte faite sans connexion : super-administrateurs
    // (docs/fonctionnalites/confidentialite-et-suppression.md)
    case AccountDeletionRequested = 'account_deletion_requested';
    // Comptes organisation (docs/fonctionnalites/comptes-organisation.md)
    case OrganizationVerified = 'organization_verified';
    case OrganizationRejected = 'organization_rejected';
    // Comptes personnels vérifiés (docs/fonctionnalites/verification-des-comptes.md)
    case AccountVerified             = 'account_verified';
    case AccountVerificationRejected = 'account_verification_rejected';
    // Badge « Approuvé » retiré (personne ou organisation), par quelque chemin que ce soit :
    // distinct du refus d'une demande (docs/fonctionnalites/badge-approuve.md)
    case AccountApprovalRevoked      = 'account_approval_revoked';
    // Témoignages en direct (docs/fonctionnalites/lives.md)
    case LiveStarted          = 'live_started';
    // Requêtes et sessions de prière (docs/fonctionnalites/requetes-de-priere.md, sessions-de-priere.md)
    case PrayerEncouragement   = 'prayer_encouragement';
    case PrayerRequestPrayed   = 'prayer_request_prayed'; // « Je prie » sur une requête de prière
    case PrayerSessionStarted  = 'prayer_session_started';
    case PrayerSessionReminder = 'prayer_session_reminder';
    // Événements : report, annulation, sondage d'après l'événement (docs/fonctionnalites/evenements.md)
    case EventPostponed        = 'event_postponed';
    case EventCancelled        = 'event_cancelled';
    case EventSurvey           = 'event_survey';
    // Administrateurs de compte (docs/fonctionnalites/administrateurs-de-compte.md)
    case AccountManagerAdded   = 'account_manager_added';
    case AccountManagerRemoved = 'account_manager_removed';
    // « J'aime » sur un commentaire (témoignage, événement, verset du jour) : préférence push_likes
    // (docs/fonctionnalites/jaime-commentaires.md)
    case CommentLiked          = 'comment_liked';

    public function isSystem(): bool
    {
        return in_array($this, [
            self::TestimonyApproved,
            self::TestimonyRejected,
            self::PendingCorrection,
            self::TestimonyResubmitted,
            self::VideoStuck,
            self::AccountDeletionRequested,
            self::OrganizationVerified,
            self::OrganizationRejected,
            self::AccountVerified,
            self::AccountVerificationRejected,
            self::AccountApprovalRevoked,
            self::AccountManagerAdded,
            self::AccountManagerRemoved,
        ]);
    }

    public function isReaction(): bool
    {
        return in_array($this, [self::Like, self::Prayer, self::Follow, self::Share, self::CommentLiked]);
    }

    public function isComment(): bool
    {
        return in_array($this, [self::Comment, self::Reply, self::Mention]);
    }
}
