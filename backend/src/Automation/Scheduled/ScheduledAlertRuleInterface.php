<?php

declare(strict_types=1);

namespace App\Automation\Scheduled;

use App\Enum\AlertRuleType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contrat d'une règle d'alerte configurable (Paramètres > Règles), évaluée à
 * la demande par App\Automation\Scheduled\AlertRuleEvaluator — et non par un
 * événement applicatif comme App\Automation\AutomationRuleInterface, dont
 * cet ensemble est volontairement distinct.
 *
 * Une classe par type du catalogue App\Enum\AlertRuleType, enregistrée
 * automatiquement grâce au tag ci-dessous. La règle ne persiste rien : elle
 * renvoie les cibles dont la condition est vraie ; l'évaluateur se charge de
 * la déduplication et de la création des alertes.
 */
#[AutoconfigureTag(self::TAG)]
interface ScheduledAlertRuleInterface
{
    public const TAG = 'app.scheduled_alert_rule';

    public function getType(): AlertRuleType;

    /**
     * Cibles dont la condition est vraie à $now pour un délai de $delayDays
     * jours (date de référence strictement antérieure à $now − $delayDays).
     *
     * @return list<AlertCandidate>
     */
    public function findCandidates(int $delayDays, \DateTimeImmutable $now): array;
}
