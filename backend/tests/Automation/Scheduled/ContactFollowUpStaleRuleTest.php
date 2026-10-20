<?php

declare(strict_types=1);

namespace App\Tests\Automation\Scheduled;

use App\Automation\Scheduled\ContactFollowUpStaleRule;
use App\Entity\Contact;
use App\Enum\AlertRuleType;
use App\Repository\ContactRepository;
use App\Repository\FollowUpRepository;
use PHPUnit\Framework\TestCase;

/**
 * Règle « Contact sans relance récente » : dépôts simulés, les requêtes
 * (relances « À faire », dernière relance terminée, annulées ignorées) étant
 * résumées par leurs résultats.
 */
final class ContactFollowUpStaleRuleTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-20 12:00:00');
    }

    private function contact(int $id, string $createdAgo, ?string $firstName = 'Jean', string $lastName = 'Dupont'): Contact
    {
        $contact = (new Contact())
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setCreatedAt($this->now->modify($createdAgo));
        (new \ReflectionProperty(Contact::class, 'id'))->setValue($contact, $id);

        return $contact;
    }

    /**
     * @param list<Contact>                     $contacts
     * @param list<int>                         $withTodo
     * @param array<int, \DateTimeImmutable>    $lastCompletedAt
     */
    private function rule(array $contacts, array $withTodo = [], array $lastCompletedAt = []): ContactFollowUpStaleRule
    {
        $contactRepository = $this->createMock(ContactRepository::class);
        $contactRepository->method('findAll')->willReturn($contacts);
        $followUpRepository = $this->createMock(FollowUpRepository::class);
        $followUpRepository->method('findContactIdsWithTodoFollowUp')->willReturn($withTodo);
        $followUpRepository->method('findLastCompletedAtByContact')->willReturn($lastCompletedAt);

        return new ContactFollowUpStaleRule($contactRepository, $followUpRepository);
    }

    public function testType(): void
    {
        self::assertSame(AlertRuleType::CONTACT_FOLLOW_UP_STALE, $this->rule([])->getType());
    }

    public function testLastCompletedFollowUpOlderThanDelayGivesOneAlert(): void
    {
        $contact = $this->contact(1, '-200 days');
        $candidates = $this->rule([$contact], [], [1 => $this->now->modify('-100 days')])->findCandidates(90, $this->now);

        self::assertCount(1, $candidates);
        self::assertSame($contact, $candidates[0]->contact);
        self::assertNull($candidates[0]->opportunity);
        self::assertSame('Relancer Jean Dupont', $candidates[0]->title);
        self::assertStringContainsString('90 jours', $candidates[0]->description);
    }

    public function testTodoFollowUpPreventsAlert(): void
    {
        $contact = $this->contact(1, '-200 days');

        self::assertSame([], $this->rule([$contact], [1], [1 => $this->now->modify('-100 days')])->findCandidates(90, $this->now));
    }

    public function testContactNeverFollowedUpUsesCreationDate(): void
    {
        $contact = $this->contact(1, '-100 days');
        $candidates = $this->rule([$contact])->findCandidates(90, $this->now);

        self::assertCount(1, $candidates);
        self::assertStringContainsString('création du contact', $candidates[0]->description);
    }

    public function testRecentCompletedFollowUpGivesNoAlert(): void
    {
        $contact = $this->contact(1, '-200 days');

        self::assertSame([], $this->rule([$contact], [], [1 => $this->now->modify('-10 days')])->findCandidates(90, $this->now));
    }

    public function testRecentlyCreatedContactWithoutFollowUpGivesNoAlert(): void
    {
        self::assertSame([], $this->rule([$this->contact(1, '-10 days')])->findCandidates(90, $this->now));
    }

    public function testReferenceExactlyAtThresholdIsNotStrictlyOlder(): void
    {
        self::assertSame([], $this->rule([$this->contact(1, '-90 days')])->findCandidates(90, $this->now));
    }

    public function testOnlyMatchingContactsAreReturned(): void
    {
        $stale = $this->contact(1, '-100 days', null, 'Martin');
        $withTodo = $this->contact(2, '-100 days');
        $recent = $this->contact(3, '-5 days');

        $candidates = $this->rule([$stale, $withTodo, $recent], [2])->findCandidates(90, $this->now);

        self::assertCount(1, $candidates);
        self::assertSame($stale, $candidates[0]->contact);
        self::assertSame('Relancer Martin', $candidates[0]->title);
    }

    public function testInactiveContactGivesNoAlert(): void
    {
        $inactive = $this->contact(1, '-200 days')->setActive(false);
        $neverFollowedUp = $this->contact(2, '-200 days')->setActive(false);
        // Témoin : même situation, mais actif.
        $active = $this->contact(3, '-200 days', 'Paul', 'Martin');

        $candidates = $this->rule(
            [$inactive, $neverFollowedUp, $active],
            [],
            [1 => $this->now->modify('-100 days'), 3 => $this->now->modify('-100 days')],
        )->findCandidates(90, $this->now);

        self::assertCount(1, $candidates);
        self::assertSame($active, $candidates[0]->contact);
    }

    public function testReactivatedContactIsEvaluatedAgain(): void
    {
        $contact = $this->contact(1, '-100 days')->setActive(false);
        self::assertSame([], $this->rule([$contact])->findCandidates(90, $this->now));

        $contact->setActive(true);
        $candidates = $this->rule([$contact])->findCandidates(90, $this->now);

        self::assertCount(1, $candidates);
        self::assertSame($contact, $candidates[0]->contact);
    }
}
