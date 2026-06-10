<?php

namespace App\Enums;

enum RejectionReason: string
{
    case InappropriateContent = 'inappropriateContent';
    case FalseTestimony       = 'falseTestimony';
    case HateSpeech           = 'hateSpeech';
    case Spam                 = 'spam';
    case Other                = 'other';

    public function label(): string
    {
        return match($this) {
            self::InappropriateContent => 'Contenu inapproprié',
            self::FalseTestimony       => 'Faux témoignage',
            self::HateSpeech           => 'Discours haineux',
            self::Spam                 => 'Spam',
            self::Other                => 'Autre',
        };
    }
}
