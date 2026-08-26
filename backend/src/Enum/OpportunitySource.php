<?php

declare(strict_types=1);

namespace App\Enum;

enum OpportunitySource: string
{
    case LINKEDIN = 'linkedin';
    case MALT = 'malt';
    case EMAIL = 'email';
    case WEBSITE = 'website';
    case RECOMMENDATION = 'recommendation';
    case MANUAL = 'manual';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LINKEDIN => 'LinkedIn',
            self::MALT => 'Malt',
            self::EMAIL => 'Email',
            self::WEBSITE => 'Site web',
            self::RECOMMENDATION => 'Recommandation',
            self::MANUAL => 'Saisie manuelle',
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
