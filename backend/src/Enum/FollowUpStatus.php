<?php

declare(strict_types=1);

namespace App\Enum;

enum FollowUpStatus: string
{
    case TODO = 'todo';
    case DONE = 'done';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TODO => 'À faire',
            self::DONE => 'Effectuée',
            self::CANCELLED => 'Annulée',
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
