<?php

namespace App\Enums;

enum NotificationType: string
{
    case Like               = 'like';
    case Comment            = 'comment';
    case Reply              = 'reply';
    case Follow             = 'follow';
    case TestimonyApproved  = 'testimony_approved';
    case TestimonyRejected  = 'testimony_rejected';
    case Mention            = 'mention';
    case Share              = 'share';
    case NewFollowedTestimony = 'new_followed_testimony';
    case PendingCorrection  = 'pending_correction';
    // Comptes organisation (docs/fonctionnalites/comptes-organisation.md)
    case OrganizationVerified = 'organization_verified';
    case OrganizationRejected = 'organization_rejected';
    // Témoignages en direct (docs/fonctionnalites/lives.md)
    case LiveStarted          = 'live_started';

    public function isSystem(): bool
    {
        return in_array($this, [
            self::TestimonyApproved,
            self::TestimonyRejected,
            self::PendingCorrection,
            self::OrganizationVerified,
            self::OrganizationRejected,
        ]);
    }

    public function isReaction(): bool
    {
        return in_array($this, [self::Like, self::Follow, self::Share]);
    }

    public function isComment(): bool
    {
        return in_array($this, [self::Comment, self::Reply, self::Mention]);
    }
}
