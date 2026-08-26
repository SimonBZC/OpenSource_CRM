<?php

declare(strict_types=1);

namespace App\Enum;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TODO => 'À faire',
            self::IN_PROGRESS => 'En cours',
            self::DONE => 'Terminée',
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
