<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Catalogue fixe des règles d'alerte configurables (Paramètres > Règles).
 * Chaque type correspond à une classe d'évaluation dans
 * App\Automation\Scheduled\.
 */
enum AlertRuleType: string
{
    case CONTACT_FOLLOW_UP_STALE = 'contact_follow_up_stale';
    case OPPORTUNITY_NO_ANSWER = 'opportunity_no_answer';
    case CONTACT_NOT_PRESENTED = 'contact_not_presented';

    public function label(): string
    {
        return match ($this) {
            self::CONTACT_FOLLOW_UP_STALE => 'Contact sans relance récente',
            self::OPPORTUNITY_NO_ANSWER => 'Opportunité sans réponse',
            self::CONTACT_NOT_PRESENTED => 'Présentation non envoyée',
        };
    }

    /**
     * Délai livré par défaut (jours), repris par la migration de création.
     */
    public function defaultDelayDays(): int
    {
        return match ($this) {
            self::CONTACT_FOLLOW_UP_STALE => 90,
            self::OPPORTUNITY_NO_ANSWER => 15,
            self::CONTACT_NOT_PRESENTED => 7,
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
