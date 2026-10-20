<?php

declare(strict_types=1);

namespace App\Tests\Automation\Scheduled;

use App\Automation\Scheduled\AlertCandidate;
use App\Automation\Scheduled\AlertRuleEvaluator;
use App\Automation\Scheduled\ScheduledAlertRuleInterface;
use App\Entity\Alert;
use App\Entity\AlertRule;
use App\Entity\Contact;
use App\Entity\Opportunity;
use App\Enum\AlertPriority;
use App\Enum\AlertRuleType;
use App\Enum\AlertStatus;
use App\Repository\AlertRepository;
use App\Repository\AlertRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AlertRuleEvaluatorTest extends TestCase
{
    /** @var AlertRepository&MockObject */
    private AlertRepository $alertRepository;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var list<Alert> */
    private array $persisted = [];

    protected function setUp(): void
    {
        $this->alertRepository = $this->createMock(AlertRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->persisted = [];
        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            self::assertInstanceOf(Alert::class, $entity);
            $this->persisted[] = $entity;
        });
    }

    /**
     * Règle simulée renvoyant toujours les mêmes candidats.
     *
     * @param list<AlertCandidate> $candidates
     */
    private function fakeRule(AlertRuleType $type, array $candidates, ?int &$receivedDelay = null): ScheduledAlertRuleInterface
    {
        return new class($type, $candidates, $receivedDelay) implements ScheduledAlertRuleInterface {
            /**
             * @param list<AlertCandidate> $candidates
             */
            public function __construct(private AlertRuleType $type, private array $candidates, private ?int &$receivedDelay)
            {
            }

            public function getType(): AlertRuleType
            {
                return $this->type;
            }

            public function findCandidates(int $delayDays, \DateTimeImmutable $now): array
            {
                $this->receivedDelay = $delayDays;

                return $this->candidates;
            }
        };
    }

    /**
     * @param list<AlertRule>                   $configs
     * @param list<ScheduledAlertRuleInterface> $rules
     */
    private function evaluator(array $configs, array $rules): AlertRuleEvaluator
    {
        $alertRuleRepository = $this->createMock(AlertRuleRepository::class);
        $alertRuleRepository->method('findAllOrdered')->willReturn($configs);

        return new AlertRuleEvaluator($alertRuleRepository, $this->alertRepository, $this->entityManager, $rules);
    }

    private function contactCandidate(string $name = 'Dupont'): AlertCandidate
    {
        return new AlertCandidate('Relancer '.$name, 'Description', contact: (new Contact())->setLastName($name));
    }

    public function testCreatesAlertsWithRulePriorityAndRuleType(): void
    {
        $config = (new AlertRule(AlertRuleType::CONTACT_FOLLOW_UP_STALE))->setPriority(AlertPriority::HIGH)->setDelayDays(30);
        $candidate = $this->contactCandidate();
        $receivedDelay = null;
        $this->alertRepository->method('hasOpenAlertForRule')->willReturn(false);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->evaluator([$config], [$this->fakeRule(AlertRuleType::CONTACT_FOLLOW_UP_STALE, [$candidate], $receivedDelay)])->evaluate();

        self::assertSame(30, $receivedDelay);
        self::assertSame(1, $result->created);
        self::assertSame(['contact_follow_up_stale' => 1], $result->byRule);
        self::assertCount(1, $this->persisted);
        $alert = $this->persisted[0];
        self::assertSame('Relancer Dupont', $alert->getTitle());
        self::assertSame('Description', $alert->getDescription());
        self::assertSame(AlertPriority::HIGH, $alert->getPriority());
        self::assertSame(AlertStatus::TODO, $alert->getStatus());
        self::assertSame(AlertRuleType::CONTACT_FOLLOW_UP_STALE, $alert->getRuleType());
        self::assertSame($candidate->contact, $alert->getContact());
        self::assertNull($alert->getOpportunity());
    }

    public function testOpportunityCandidateLinksOpportunityAndContact(): void
    {
        $contact = (new Contact())->setLastName('Dupont');
        $opportunity = (new Opportunity())->setTitle('Développeur Symfony')->setContact($contact);
        $candidate = new AlertCandidate('Pas de réponse : Développeur Symfony', 'Description', $opportunity, $contact);
        $this->alertRepository->expects(self::once())->method('hasOpenAlertForRule')
            ->with(AlertRuleType::OPPORTUNITY_NO_ANSWER, $opportunity, $contact)
            ->willReturn(false);

        $result = $this->evaluator(
            [new AlertRule(AlertRuleType::OPPORTUNITY_NO_ANSWER)],
            [$this->fakeRule(AlertRuleType::OPPORTUNITY_NO_ANSWER, [$candidate])],
        )->evaluate();

        self::assertSame(1, $result->created);
        self::assertSame($opportunity, $this->persisted[0]->getOpportunity());
        self::assertSame($contact, $this->persisted[0]->getContact());
        self::assertSame(AlertPriority::MEDIUM, $this->persisted[0]->getPriority());
    }

    public function testOpenAlertOfSameRuleForTargetPreventsDuplicate(): void
    {
        $this->alertRepository->method('hasOpenAlertForRule')->willReturn(true);
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->evaluator(
            [new AlertRule(AlertRuleType::CONTACT_NOT_PRESENTED)],
            [$this->fakeRule(AlertRuleType::CONTACT_NOT_PRESENTED, [$this->contactCandidate()])],
        )->evaluate();

        self::assertSame(0, $result->created);
        self::assertSame(['contact_not_presented' => 0], $result->byRule);
        self::assertSame([], $this->persisted);
    }

    public function testClosedPreviousAlertAllowsNewOne(): void
    {
        // Une alerte précédente terminée ou annulée n'est pas « ouverte » :
        // le dépôt répond false et une nouvelle alerte naît.
        $this->alertRepository->method('hasOpenAlertForRule')->willReturn(false);

        $result = $this->evaluator(
            [new AlertRule(AlertRuleType::CONTACT_NOT_PRESENTED)],
            [$this->fakeRule(AlertRuleType::CONTACT_NOT_PRESENTED, [$this->contactCandidate()])],
        )->evaluate();

        self::assertSame(1, $result->created);
    }

    public function testDisabledRuleCreatesNothingAndIsNotEvaluated(): void
    {
        $receivedDelay = null;
        $this->alertRepository->expects(self::never())->method('hasOpenAlertForRule');

        $result = $this->evaluator(
            [(new AlertRule(AlertRuleType::CONTACT_FOLLOW_UP_STALE))->setEnabled(false)],
            [$this->fakeRule(AlertRuleType::CONTACT_FOLLOW_UP_STALE, [$this->contactCandidate()], $receivedDelay)],
        )->evaluate();

        self::assertNull($receivedDelay);
        self::assertSame(0, $result->created);
        self::assertSame(['contact_follow_up_stale' => 0], $result->byRule);
    }

    public function testNothingToCreateReturnsZero(): void
    {
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->evaluator(
            array_map(static fn (AlertRuleType $type): AlertRule => new AlertRule($type), AlertRuleType::cases()),
            array_map(fn (AlertRuleType $type): ScheduledAlertRuleInterface => $this->fakeRule($type, []), AlertRuleType::cases()),
        )->evaluate();

        self::assertSame(
            ['created' => 0, 'byRule' => ['contact_follow_up_stale' => 0, 'opportunity_no_answer' => 0, 'contact_not_presented' => 0]],
            $result->toArray(),
        );
    }

    public function testCountsAreSplitByRule(): void
    {
        $this->alertRepository->method('hasOpenAlertForRule')->willReturnOnConsecutiveCalls(false, true, false);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->evaluator(
            [new AlertRule(AlertRuleType::CONTACT_FOLLOW_UP_STALE), new AlertRule(AlertRuleType::CONTACT_NOT_PRESENTED)],
            [
                $this->fakeRule(AlertRuleType::CONTACT_FOLLOW_UP_STALE, [$this->contactCandidate('A'), $this->contactCandidate('B')]),
                $this->fakeRule(AlertRuleType::CONTACT_NOT_PRESENTED, [$this->contactCandidate('C')]),
            ],
        )->evaluate();

        self::assertSame(2, $result->created);
        self::assertSame(['contact_follow_up_stale' => 1, 'contact_not_presented' => 1], $result->byRule);
    }
}
