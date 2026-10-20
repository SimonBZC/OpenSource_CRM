<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Alert;
use App\Entity\Company;
use App\Entity\FollowUp;
use App\Entity\Interaction;
use App\Entity\Opportunity;
use App\Enum\InteractionType;
use App\Enum\OpportunitySource;
use App\Enum\OpportunityStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OpportunityTest extends TestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::$validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function makeValidOpportunity(): Opportunity
    {
        return (new Opportunity())->setTitle('Mission de développement');
    }

    public function testConstructorInitializesTimestampsAndDefaults(): void
    {
        $before = new \DateTimeImmutable();
        $opportunity = new Opportunity();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $opportunity->getCreatedAt());
        self::assertLessThanOrEqual($after, $opportunity->getCreatedAt());
        self::assertGreaterThanOrEqual($before, $opportunity->getUpdatedAt());
        self::assertLessThanOrEqual($after, $opportunity->getUpdatedAt());

        self::assertSame(OpportunityStatus::NEW, $opportunity->getStatus());
        self::assertSame(OpportunitySource::MANUAL, $opportunity->getSource());
        self::assertFalse($opportunity->isRemote());
        self::assertSame([], $opportunity->getTechnologies());

        self::assertCount(0, $opportunity->getInteractions());
        self::assertCount(0, $opportunity->getFollowUps());
        self::assertCount(0, $opportunity->getAlerts());
    }

    public function testOnPreUpdateRefreshesUpdatedAt(): void
    {
        $opportunity = new Opportunity();

        // Force a stale timestamp to detect whether onPreUpdate() actually refreshes it.
        $staleUpdatedAt = new \DateTimeImmutable('2000-01-01 00:00:00');
        $opportunity->setUpdatedAt($staleUpdatedAt);

        $before = new \DateTimeImmutable();
        $opportunity->onPreUpdate();
        $after = new \DateTimeImmutable();

        self::assertGreaterThan($staleUpdatedAt, $opportunity->getUpdatedAt());
        self::assertGreaterThanOrEqual($before, $opportunity->getUpdatedAt());
        self::assertLessThanOrEqual($after, $opportunity->getUpdatedAt());
    }

    public function testAddInteractionSynchronizesOwningSide(): void
    {
        $opportunity = new Opportunity();
        $interaction = (new Interaction())->setType(InteractionType::EMAIL);

        $opportunity->addInteraction($interaction);

        self::assertTrue($opportunity->getInteractions()->contains($interaction));
        self::assertSame($opportunity, $interaction->getOpportunity());
    }

    public function testAddInteractionTwiceDoesNotDuplicate(): void
    {
        $opportunity = new Opportunity();
        $interaction = (new Interaction())->setType(InteractionType::EMAIL);

        $opportunity->addInteraction($interaction);
        $opportunity->addInteraction($interaction);

        self::assertCount(1, $opportunity->getInteractions());
    }

    public function testRemoveInteractionClearsOwningSide(): void
    {
        $opportunity = new Opportunity();
        $interaction = (new Interaction())->setType(InteractionType::EMAIL);

        $opportunity->addInteraction($interaction);
        $opportunity->removeInteraction($interaction);

        self::assertFalse($opportunity->getInteractions()->contains($interaction));
        self::assertNull($interaction->getOpportunity());
    }

    public function testAddFollowUpSynchronizesOwningSide(): void
    {
        $opportunity = new Opportunity();
        $followUp = new FollowUp();

        $opportunity->addFollowUp($followUp);

        self::assertTrue($opportunity->getFollowUps()->contains($followUp));
        self::assertSame($opportunity, $followUp->getOpportunity());
    }

    public function testAddFollowUpTwiceDoesNotDuplicate(): void
    {
        $opportunity = new Opportunity();
        $followUp = new FollowUp();

        $opportunity->addFollowUp($followUp);
        $opportunity->addFollowUp($followUp);

        self::assertCount(1, $opportunity->getFollowUps());
    }

    public function testRemoveFollowUpClearsOwningSide(): void
    {
        $opportunity = new Opportunity();
        $followUp = new FollowUp();

        $opportunity->addFollowUp($followUp);
        $opportunity->removeFollowUp($followUp);

        self::assertFalse($opportunity->getFollowUps()->contains($followUp));
        self::assertNull($followUp->getOpportunity());
    }

    public function testAddAlertSynchronizesOwningSide(): void
    {
        $opportunity = new Opportunity();
        $alert = new Alert();

        $opportunity->addAlert($alert);

        self::assertTrue($opportunity->getAlerts()->contains($alert));
        self::assertSame($opportunity, $alert->getOpportunity());
    }

    public function testAddAlertTwiceDoesNotDuplicate(): void
    {
        $opportunity = new Opportunity();
        $alert = new Alert();

        $opportunity->addAlert($alert);
        $opportunity->addAlert($alert);

        self::assertCount(1, $opportunity->getAlerts());
    }

    public function testRemoveAlertClearsOwningSide(): void
    {
        $opportunity = new Opportunity();
        $alert = new Alert();

        $opportunity->addAlert($alert);
        $opportunity->removeAlert($alert);

        self::assertFalse($opportunity->getAlerts()->contains($alert));
        self::assertNull($alert->getOpportunity());
    }

    public function testNegativeDailyRateProducesViolationOnDailyRate(): void
    {
        $opportunity = $this->makeValidOpportunity()->setDailyRate(-1);

        $violations = self::$validator->validate($opportunity);

        self::assertCount(1, $violations);
        self::assertSame('dailyRate', $violations[0]->getPropertyPath());
        self::assertSame('Le TJM doit être positif ou nul.', $violations[0]->getMessage());
    }

    /**
     * @return iterable<string, array{0: ?int}>
     */
    public static function validDailyRateProvider(): iterable
    {
        yield 'null' => [null];
        yield 'zero' => [0];
        yield 'positive' => [600];
    }

    #[DataProvider('validDailyRateProvider')]
    public function testDailyRateProducesNoViolationWhenNullOrPositive(?int $dailyRate): void
    {
        $opportunity = $this->makeValidOpportunity()->setDailyRate($dailyRate);

        $violations = self::$validator->validate($opportunity);

        self::assertCount(0, $violations);
    }

    public function testGetCompanyNameReturnsNullWhenNoCompanyLinked(): void
    {
        $opportunity = $this->makeValidOpportunity();

        self::assertNull($opportunity->getCompany());
        self::assertNull($opportunity->getCompanyName());
    }

    public function testGetCompanyNameReturnsLinkedCompanyName(): void
    {
        $company = (new Company())->setName('Acme Corp');
        $opportunity = $this->makeValidOpportunity()->setCompany($company);

        self::assertSame('Acme Corp', $opportunity->getCompanyName());
    }

    public function testGetCompanyNameReturnsNullAfterCompanyIsDetached(): void
    {
        $company = (new Company())->setName('Acme Corp');
        $opportunity = $this->makeValidOpportunity()->setCompany($company);

        self::assertSame('Acme Corp', $opportunity->getCompanyName());

        $opportunity->setCompany(null);

        self::assertNull($opportunity->getCompanyName());
    }

    public function testTitleBlankProducesViolation(): void
    {
        $opportunity = (new Opportunity())->setTitle('');

        $violations = self::$validator->validate($opportunity);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testTitleTooLongProducesViolation(): void
    {
        $opportunity = $this->makeValidOpportunity()->setTitle(str_repeat('a', 256));

        $violations = self::$validator->validate($opportunity);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testTitleAtMaxLengthProducesNoViolation(): void
    {
        $opportunity = $this->makeValidOpportunity()->setTitle(str_repeat('a', 255));

        $violations = self::$validator->validate($opportunity);

        self::assertCount(0, $violations);
    }

    public function testLocationTooLongProducesViolation(): void
    {
        $opportunity = $this->makeValidOpportunity()->setLocation(str_repeat('a', 256));

        $violations = self::$validator->validate($opportunity);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testDurationTooLongProducesViolation(): void
    {
        $opportunity = $this->makeValidOpportunity()->setDuration(str_repeat('a', 101));

        $violations = self::$validator->validate($opportunity);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testValidOpportunityProducesNoViolation(): void
    {
        $opportunity = $this->makeValidOpportunity();

        $violations = self::$validator->validate($opportunity);

        self::assertCount(0, $violations);
    }
}
