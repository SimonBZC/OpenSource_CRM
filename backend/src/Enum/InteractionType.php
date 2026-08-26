<?php

declare(strict_types=1);

namespace App\Enum;

enum InteractionType: string
{
    case EMAIL = 'email';
    case PHONE = 'phone';
    case LINKEDIN = 'linkedin';
    case MEETING = 'meeting';
    case NOTE = 'note';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::PHONE => 'Téléphone',
            self::LINKEDIN => 'LinkedIn',
            self::MEETING => 'Rendez-vous',
            self::NOTE => 'Note',
            self::OTHER => 'Autre',
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
