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
        return __('enums.rejection_reason.' . $this->value);
    }
}
