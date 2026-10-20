<?php

declare(strict_types=1);

namespace App\Automation\Scheduled;

/**
 * Outils communs aux règles configurables : seuil de comparaison et
 * formulation du délai dans les descriptions d'alerte.
 */
final class RuleDelay
{
    /**
     * « maintenant − N jours » : une date de référence strictement antérieure
     * à ce seuil déclenche la règle.
     */
    public static function threshold(\DateTimeImmutable $now, int $delayDays): \DateTimeImmutable
    {
        return $now->sub(new \DateInterval(sprintf('P%dD', max(0, $delayDays))));
    }

    public static function format(int $delayDays): string
    {
        return sprintf('%d %s', $delayDays, $delayDays > 1 ? 'jours' : 'jour');
    }
}
