<?php

namespace App\Enums;

enum TestimonyType: string
{
    case Text  = 'text';
    case Audio = 'audio';
    case Video = 'video';

    public function label(): string
    {
        return __('enums.testimony_type.' . $this->value);
    }

    public function icon(): string
    {
        return match($this) {
            self::Text  => 'text_fields',
            self::Audio => 'mic',
            self::Video => 'videocam',
        };
    }
}
