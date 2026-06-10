<?php

namespace App\Enums;

enum TestimonyType: string
{
    case Text  = 'text';
    case Audio = 'audio';
    case Video = 'video';

    public function label(): string
    {
        return match($this) {
            self::Text  => 'Texte',
            self::Audio => 'Audio',
            self::Video => 'Vidéo',
        };
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
