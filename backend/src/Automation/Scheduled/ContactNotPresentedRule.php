<?php

declare(strict_types=1);

namespace App\Automation\Scheduled;

use App\Enum\AlertRuleType;
use App\Repository\ContactRepository;

/**
 * Règle « Présentation non envoyée » (contact_not_presented).
 *
 * Contact dont la case « Présenté » n'est pas cochée et créé avant
 * maintenant − N jours. N = 0 vise tous les contacts non présentés.
 */
final class ContactNotPresentedRule implements ScheduledAlertRuleInterface
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
    ) {
    }

    public function getType(): AlertRuleType
    {
        return AlertRuleType::CONTACT_NOT_PRESENTED;
    }

    public function findCandidates(int $delayDays, \DateTimeImmutable $now): array
    {
        $threshold = RuleDelay::threshold($now, $delayDays);
        $candidates = [];

        foreach ($this->contactRepository->findNotPresented() as $contact) {
            if ($contact->isPresentationSent() || $contact->getCreatedAt() >= $threshold) {
                continue;
            }

            $candidates[] = new AlertCandidate(
                sprintf('Se présenter à %s', $contact->getFullName()),
                0 === $delayDays
                    ? 'Présentation non envoyée à ce contact (délai de la règle : 0 jour).'
                    : sprintf('Présentation non envoyée, contact créé il y a plus de %s.', RuleDelay::format($delayDays)),
                contact: $contact,
            );
        }

        return $candidates;
    }
}
