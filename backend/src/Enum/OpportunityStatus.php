<?php

declare(strict_types=1);

namespace App\Enum;

enum OpportunityStatus: string
{
    case NEW = 'new';
    case TO_REVIEW = 'to_review';
    case CONTACTED = 'contacted';
    case WAITING = 'waiting';
    case FOLLOW_UP = 'follow_up';
    case INTERVIEW = 'interview';
    case WON = 'won';
    case LOST = 'lost';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Nouvelle',
            self::TO_REVIEW => 'À examiner',
            self::CONTACTED => 'Contactée',
            self::WAITING => 'En attente',
            self::FOLLOW_UP => 'Relance',
            self::INTERVIEW => 'Entretien',
            self::WON => 'Gagnée',
            self::LOST => 'Perdue',
            self::ARCHIVED => 'Archivée',
        };
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
