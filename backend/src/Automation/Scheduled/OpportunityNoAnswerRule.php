<?php

declare(strict_types=1);

namespace App\Automation\Scheduled;

use App\Enum\AlertRuleType;
use App\Enum\OpportunityStatus;
use App\Repository\OpportunityRepository;

/**
 * Règle « Opportunité sans réponse » (opportunity_no_answer).
 *
 * Opportunité au statut « Contactée » ou « En attente » dont le dernier
 * changement de statut (`statusChangedAt`) est antérieur à maintenant − N
 * jours. Cible : l'opportunité, et son contact s'il existe. Une opportunité
 * dont le contact est inactif est ignorée ; sans contact, elle reste évaluée.
 */
final class OpportunityNoAnswerRule implements ScheduledAlertRuleInterface
{
    public const STATUSES = [OpportunityStatus::CONTACTED, OpportunityStatus::WAITING];

    public function __construct(
        private readonly OpportunityRepository $opportunityRepository,
    ) {
    }

    public function getType(): AlertRuleType
    {
        return AlertRuleType::OPPORTUNITY_NO_ANSWER;
    }

    public function findCandidates(int $delayDays, \DateTimeImmutable $now): array
    {
        $threshold = RuleDelay::threshold($now, $delayDays);
        $candidates = [];

        foreach ($this->opportunityRepository->findByStatuses(self::STATUSES) as $opportunity) {
            if (!\in_array($opportunity->getStatus(), self::STATUSES, true)) {
                continue;
            }

            if (false === $opportunity->getContact()?->isActive()) {
                continue;
            }

            if ($opportunity->getStatusChangedAt() >= $threshold) {
                continue;
            }

            $candidates[] = new AlertCandidate(
                sprintf('Pas de réponse : %s', $opportunity->getTitle()),
                sprintf(
                    'Opportunité au statut « %s » depuis plus de %s, sans réponse.',
                    $opportunity->getStatus()->label(),
                    RuleDelay::format($delayDays),
                ),
                opportunity: $opportunity,
                contact: $opportunity->getContact(),
            );
        }

        return $candidates;
    }
}
