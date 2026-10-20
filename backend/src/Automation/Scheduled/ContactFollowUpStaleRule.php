<?php

declare(strict_types=1);

namespace App\Automation\Scheduled;

use App\Enum\AlertRuleType;
use App\Repository\ContactRepository;
use App\Repository\FollowUpRepository;

/**
 * Règle « Contact sans relance récente » (contact_follow_up_stale).
 *
 * Contact sans aucune relance « À faire » dont la date de référence est
 * antérieure à maintenant − N jours. Référence : `completedAt` de sa
 * dernière relance terminée, ou sa date de création s'il n'en a aucune. Les
 * relances annulées sont ignorées, ainsi que les relances terminées sans
 * `completedAt`. Seules comptent les relances directement liées au contact.
 */
final class ContactFollowUpStaleRule implements ScheduledAlertRuleInterface
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly FollowUpRepository $followUpRepository,
    ) {
    }

    public function getType(): AlertRuleType
    {
        return AlertRuleType::CONTACT_FOLLOW_UP_STALE;
    }

    public function findCandidates(int $delayDays, \DateTimeImmutable $now): array
    {
        $threshold = RuleDelay::threshold($now, $delayDays);
        $withTodo = array_flip($this->followUpRepository->findContactIdsWithTodoFollowUp());
        $lastCompletedAt = $this->followUpRepository->findLastCompletedAtByContact();

        $candidates = [];

        foreach ($this->contactRepository->findAll() as $contact) {
            $id = $contact->getId();

            if (null !== $id && isset($withTodo[$id])) {
                continue;
            }

            $lastDone = null !== $id ? ($lastCompletedAt[$id] ?? null) : null;
            $reference = $lastDone ?? $contact->getCreatedAt();

            if ($reference >= $threshold) {
                continue;
            }

            $description = null !== $lastDone
                ? sprintf('Dernière relance terminée il y a plus de %s, aucune relance à faire.', RuleDelay::format($delayDays))
                : sprintf('Aucune relance depuis la création du contact, il y a plus de %s.', RuleDelay::format($delayDays));

            $candidates[] = new AlertCandidate(
                sprintf('Relancer %s', $contact->getFullName()),
                $description,
                contact: $contact,
            );
        }

        return $candidates;
    }
}
