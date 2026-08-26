<?php

declare(strict_types=1);

namespace App\Automation\Rule;

use App\Automation\AutomationRuleInterface;
use App\Automation\Event\FollowUpDue;
use App\Automation\Notifier\NotifierInterface;

/**
 * Sur FollowUpDue : produit une notification "stub" (voir
 * App\Automation\Notifier\LoggerNotifier) — un simple log structuré, PAS
 * d'email/push/SMS. Le canal réel de notification est laissé derrière
 * NotifierInterface pour rester remplaçable sans changer cette règle.
 */
final class NotifyDueFollowUpRule implements AutomationRuleInterface
{
    public function __construct(
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function getName(): string
    {
        return 'notify_due_follow_up';
    }

    public function supports(object $event): bool
    {
        return $event instanceof FollowUpDue;
    }

    public function execute(object $event): void
    {
        if (!$event instanceof FollowUpDue) {
            return;
        }

        $followUp = $event->followUp;

        $this->notifier->notify(
            sprintf(
                'Relance échue : #%s prévue le %s.',
                $followUp->getId() ?? '?',
                $followUp->getScheduledAt()->format('Y-m-d H:i'),
            ),
            [
                'followUpId' => $followUp->getId(),
                'scheduledAt' => $followUp->getScheduledAt()->format(\DATE_ATOM),
                'opportunityId' => $followUp->getOpportunity()?->getId(),
                'contactId' => $followUp->getContact()?->getId(),
                'companyId' => $followUp->getCompany()?->getId(),
            ],
        );
    }
}
