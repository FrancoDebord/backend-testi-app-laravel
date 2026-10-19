<?php

namespace App\Services;

/** Type d'événement encore utilisé, sans type de remplacement valide (docs/fonctionnalites/types-evenements.md). */
class EventTypeInUseException extends \RuntimeException
{
    public function __construct(public readonly int $eventsCount, public readonly bool $invalidReplacement = false)
    {
        parent::__construct($invalidReplacement
            ? __('event_types.errors.replace_invalid')
            : trans_choice('event_types.errors.in_use', $eventsCount, ['count' => $eventsCount]));
    }
}
