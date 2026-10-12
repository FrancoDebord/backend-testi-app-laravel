<?php

namespace App\Enums;

/** Rôle déclaré dans une demande de vérification de compte personnel (docs/fonctionnalites/verification-des-comptes.md). */
enum PersonalVerificationRole: string
{
    case Pastor         = 'pastor';
    case Evangelist     = 'evangelist';
    case Singer         = 'singer';
    case MinistryLeader = 'ministry_leader';
    case PublicFigure   = 'public_figure';
    case Other          = 'other';

    public function label(): string
    {
        return match($this) {
            self::Pastor         => 'Pasteur',
            self::Evangelist     => 'Évangéliste',
            self::Singer         => 'Chantre',
            self::MinistryLeader => 'Responsable de ministère',
            self::PublicFigure   => 'Personnalité publique',
            self::Other          => 'Autre',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
