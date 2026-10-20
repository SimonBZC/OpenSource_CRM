<?php

declare(strict_types=1);

namespace App\Tests\Automation\Scheduled;

use App\Automation\Scheduled\OpportunityNoAnswerRule;
use App\Entity\Contact;
use App\Entity\Opportunity;
use App\Enum\AlertRuleType;
use App\Enum\OpportunityStatus;
use App\Repository\OpportunityRepository;
use PHPUnit\Framework\TestCase;

final class OpportunityNoAnswerRuleTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-20 12:00:00');
    }

    private function opportunity(OpportunityStatus $status, string $statusChangedAgo): Opportunity
    {
        return (new Opportunity())
            ->setTitle('Développeur Symfony')
            ->setStatus($status)
            ->setStatusChangedAt($this->now->modify($statusChangedAgo));
    }

    /**
     * @param list<Opportunity> $opportunities
     */
    private function rule(array $opportunities): OpportunityNoAnswerRule
    {
        $repository = $this->createMock(OpportunityRepository::class);
        $repository->method('findByStatuses')
            ->with([OpportunityStatus::CONTACTED, OpportunityStatus::WAITING])
            ->willReturn($opportunities);

        return new OpportunityNoAnswerRule($repository);
    }

    public function testType(): void
    {
        self::assertSame(AlertRuleType::OPPORTUNITY_NO_ANSWER, $this->rule([])->getType());
    }

    public function testWaitingSinceTwentyDaysGivesOneAlert(): void
    {
        $contact = (new Contact())->setFirstName('Jean')->setLastName('Dupont');
        $opportunity = $this->opportunity(OpportunityStatus::WAITING, '-20 days')->setContact($contact);

        $candidates = $this->rule([$opportunity])->findCandidates(15, $this->now);

        self::assertCount(1, $candidates);
        self::assertSame($opportunity, $candidates[0]->opportunity);
        self::assertSame($contact, $candidates[0]->contact);
        self::assertSame('Pas de réponse : Développeur Symfony', $candidates[0]->title);
        self::assertStringContainsString('15 jours', $candidates[0]->description);
        self::assertStringContainsString('En attente', $candidates[0]->description);
    }

    public function testOpportunityWithoutContactTargetsOnlyOpportunity(): void
    {
        $candidates = $this->rule([$this->opportunity(OpportunityStatus::CONTACTED, '-20 days')])->findCandidates(15, $this->now);

        self::assertCount(1, $candidates);
        self::assertNull($candidates[0]->contact);
    }

    public function testOtherStatusGivesNoAlert(): void
    {
        self::assertSame([], $this->rule([$this->opportunity(OpportunityStatus::INTERVIEW, '-60 days')])->findCandidates(15, $this->now));
    }

    public function testRecentStatusChangeGivesNoAlert(): void
    {
        self::assertSame([], $this->rule([$this->opportunity(OpportunityStatus::CONTACTED, '-5 days')])->findCandidates(15, $this->now));
    }

    public function testEditWithoutStatusChangeKeepsReference(): void
    {
        $opportunity = $this->opportunity(OpportunityStatus::CONTACTED, '-20 days');
        // Titre modifié hier, statut inchangé : la référence ne bouge pas.
        $opportunity->setTitle('Développeur Symfony senior')
            ->setStatus(OpportunityStatus::CONTACTED)
            ->setUpdatedAt($this->now->modify('-1 day'));

        $candidates = $this->rule([$opportunity])->findCandidates(15, $this->now);

        self::assertCount(1, $candidates);
        self::assertSame('Pas de réponse : Développeur Symfony senior', $candidates[0]->title);
    }
}
