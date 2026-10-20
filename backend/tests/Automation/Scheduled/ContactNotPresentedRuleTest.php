<?php

declare(strict_types=1);

namespace App\Tests\Automation\Scheduled;

use App\Automation\Scheduled\ContactNotPresentedRule;
use App\Entity\Contact;
use App\Enum\AlertRuleType;
use App\Repository\ContactRepository;
use PHPUnit\Framework\TestCase;

final class ContactNotPresentedRuleTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-20 12:00:00');
    }

    private function contact(string $createdAgo, bool $presented = false): Contact
    {
        return (new Contact())
            ->setFirstName('Jean')
            ->setLastName('Dupont')
            ->setPresentationSent($presented)
            ->setCreatedAt($this->now->modify($createdAgo));
    }

    /**
     * @param list<Contact> $contacts
     */
    private function rule(array $contacts): ContactNotPresentedRule
    {
        $repository = $this->createMock(ContactRepository::class);
        $repository->method('findNotPresented')->willReturn($contacts);

        return new ContactNotPresentedRule($repository);
    }

    public function testType(): void
    {
        self::assertSame(AlertRuleType::CONTACT_NOT_PRESENTED, $this->rule([])->getType());
    }

    public function testNotPresentedContactCreatedEightDaysAgoGivesOneAlert(): void
    {
        $contact = $this->contact('-8 days');
        $candidates = $this->rule([$contact])->findCandidates(7, $this->now);

        self::assertCount(1, $candidates);
        self::assertSame($contact, $candidates[0]->contact);
        self::assertNull($candidates[0]->opportunity);
        self::assertSame('Se présenter à Jean Dupont', $candidates[0]->title);
        self::assertStringContainsString('7 jours', $candidates[0]->description);
    }

    public function testPresentedContactGivesNoAlert(): void
    {
        self::assertSame([], $this->rule([$this->contact('-30 days', true)])->findCandidates(7, $this->now));
    }

    public function testRecentContactGivesNoAlert(): void
    {
        self::assertSame([], $this->rule([$this->contact('-3 days')])->findCandidates(7, $this->now));
    }

    public function testZeroDelayTargetsEveryNotPresentedContact(): void
    {
        $candidates = $this->rule([$this->contact('-1 second'), $this->contact('-2 days')])->findCandidates(0, $this->now);

        self::assertCount(2, $candidates);
        self::assertStringContainsString('0 jour', $candidates[0]->description);
    }

    public function testInactiveContactGivesNoAlert(): void
    {
        $inactive = $this->contact('-30 days')->setActive(false);
        $active = $this->contact('-30 days');

        $candidates = $this->rule([$inactive, $active])->findCandidates(0, $this->now);

        self::assertCount(1, $candidates);
        self::assertSame($active, $candidates[0]->contact);
    }
}
