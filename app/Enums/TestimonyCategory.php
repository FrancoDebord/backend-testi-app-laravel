<?php

namespace App\Enums;

enum TestimonyCategory: string
{
    case Guerison        = 'guerison';
    case Delivrance      = 'delivrance';
    case Conversion      = 'conversion';
    case Mariage         = 'mariage';
    case Famille         = 'famille';
    case Finances        = 'finances';
    case Miracles        = 'miracles';
    case ProtectionDivine = 'protection_divine';
    case Ministere       = 'ministere';
    case Salut           = 'salut';

    public function label(): string
    {
        return match($this) {
            self::Guerison        => 'Guérison',
            self::Delivrance      => 'Délivrance',
            self::Conversion      => 'Conversion',
            self::Mariage         => 'Mariage',
            self::Famille         => 'Famille',
            self::Finances        => 'Finances',
            self::Miracles        => 'Miracles',
            self::ProtectionDivine => 'Protection Divine',
            self::Ministere       => 'Ministère',
            self::Salut           => 'Salut',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Guerison        => 'healing',
            self::Delivrance      => 'shield',
            self::Conversion      => 'autorenew',
            self::Mariage         => 'favorite',
            self::Famille         => 'people',
            self::Finances        => 'attach_money',
            self::Miracles        => 'auto_awesome',
            self::ProtectionDivine => 'security',
            self::Ministere       => 'church',
            self::Salut           => 'star',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Guerison        => '#10B981',
            self::Delivrance      => '#8B5CF6',
            self::Conversion      => '#F59E0B',
            self::Mariage         => '#EC4899',
            self::Famille         => '#3B82F6',
            self::Finances        => '#059669',
            self::Miracles        => '#6366F1',
            self::ProtectionDivine => '#0EA5E9',
            self::Ministere       => '#7C3AED',
            self::Salut           => '#EF4444',
        };
    }
}
