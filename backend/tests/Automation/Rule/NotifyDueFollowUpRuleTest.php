<?php

declare(strict_types=1);

namespace App\Tests\Automation\Rule;

use App\Automation\Event\FollowUpDue;
use App\Automation\Event\OpportunityCreated;
use App\Automation\Notifier\NotifierInterface;
use App\Automation\Rule\NotifyDueFollowUpRule;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\FollowUp;
use App\Entity\Opportunity;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de App\Automation\Rule\NotifyDueFollowUpRule.
 */
final class NotifyDueFollowUpRuleTest extends TestCase
{
    public function testSupportsReturnsTrueOnlyForFollowUpDue(): void
    {
        $rule = new NotifyDueFollowUpRule($this->createMock(NotifierInterface::class));

        $followUp = (new FollowUp())->setTitle('Relance')->setScheduledAt(new \DateTimeImmutable('2026-08-25 10:00:00'));

        self::assertTrue($rule->supports(new FollowUpDue($followUp)));
        self::assertFalse($rule->supports(new OpportunityCreated(new Opportunity())));
        self::assertFalse($rule->supports(new \stdClass()));
    }

    public function testExecuteCallsNotifierWithExpectedMessageAndContext(): void
    {
        $opportunity = new Opportunity();
        $contact = new Contact();
        $company = new Company();

        $followUp = (new FollowUp())
            ->setTitle('Relance client')
            ->setScheduledAt(new \DateTimeImmutable('2026-08-25 10:00:00'))
            ->setOpportunity($opportunity)
            ->setContact($contact)
            ->setCompany($company);

        // getId() est en lecture seule (généré par Doctrine) : le followUp
        // n'a pas d'id ici, ce qui exerce volontairement la branche `?? '?'`
        // du message de la règle.
        self::assertNull($followUp->getId());

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(
                self::stringContains('Relance échue : #? prévue le 2026-08-25 10:00.'),
                [
                    'followUpId' => null,
                    'scheduledAt' => $followUp->getScheduledAt()->format(\DATE_ATOM),
                    'opportunityId' => $opportunity->getId(),
                    'contactId' => $contact->getId(),
                    'companyId' => $company->getId(),
                ],
            );

        $rule = new NotifyDueFollowUpRule($notifier);

        $rule->execute(new FollowUpDue($followUp));
    }

    public function testGetNameReturnsStableIdentifier(): void
    {
        $rule = new NotifyDueFollowUpRule($this->createMock(NotifierInterface::class));

        self::assertSame('notify_due_follow_up', $rule->getName());
    }
}
