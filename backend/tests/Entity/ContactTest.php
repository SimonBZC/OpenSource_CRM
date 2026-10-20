<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Contact;
use App\Entity\Interaction;
use App\Entity\Opportunity;
use App\Enum\InteractionType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ContactTest extends TestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::$validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testConstructorInitializesCreatedAtAndEmptyCollections(): void
    {
        $before = new \DateTimeImmutable();
        $contact = new Contact();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $contact->getCreatedAt());
        self::assertLessThanOrEqual($after, $contact->getCreatedAt());

        self::assertCount(0, $contact->getInteractions());
        self::assertCount(0, $contact->getOpportunities());
    }

    public function testAddInteractionSynchronizesOwningSide(): void
    {
        $contact = new Contact();
        $interaction = (new Interaction())->setType(InteractionType::EMAIL);

        $contact->addInteraction($interaction);

        self::assertTrue($contact->getInteractions()->contains($interaction));
        self::assertSame($contact, $interaction->getContact());
    }

    public function testAddInteractionTwiceDoesNotDuplicate(): void
    {
        $contact = new Contact();
        $interaction = (new Interaction())->setType(InteractionType::EMAIL);

        $contact->addInteraction($interaction);
        $contact->addInteraction($interaction);

        self::assertCount(1, $contact->getInteractions());
    }

    public function testRemoveInteractionClearsOwningSide(): void
    {
        $contact = new Contact();
        $interaction = (new Interaction())->setType(InteractionType::EMAIL);

        $contact->addInteraction($interaction);
        $contact->removeInteraction($interaction);

        self::assertFalse($contact->getInteractions()->contains($interaction));
        self::assertNull($interaction->getContact());
    }

    public function testAddOpportunitySynchronizesOwningSide(): void
    {
        $contact = new Contact();
        $opportunity = new Opportunity();

        $contact->addOpportunity($opportunity);

        self::assertTrue($contact->getOpportunities()->contains($opportunity));
        self::assertSame($contact, $opportunity->getContact());
    }

    public function testAddOpportunityTwiceDoesNotDuplicate(): void
    {
        $contact = new Contact();
        $opportunity = new Opportunity();

        $contact->addOpportunity($opportunity);
        $contact->addOpportunity($opportunity);

        self::assertCount(1, $contact->getOpportunities());
    }

    public function testRemoveOpportunityClearsOwningSide(): void
    {
        $contact = new Contact();
        $opportunity = new Opportunity();

        $contact->addOpportunity($opportunity);
        $contact->removeOpportunity($opportunity);

        self::assertFalse($contact->getOpportunities()->contains($opportunity));
        self::assertNull($opportunity->getContact());
    }

    public function testLastNameBlankProducesViolation(): void
    {
        $contact = (new Contact())->setLastName('');

        $violations = self::$validator->validate($contact);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testLastNameTooLongProducesViolation(): void
    {
        $contact = (new Contact())->setLastName(str_repeat('a', 256));

        $violations = self::$validator->validate($contact);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testInvalidEmailProducesViolation(): void
    {
        $contact = (new Contact())->setLastName('Doe')->setEmail('not-an-email');

        $violations = self::$validator->validate($contact);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testValidEmailProducesNoViolation(): void
    {
        $contact = (new Contact())->setLastName('Doe')->setEmail('jane.doe@example.com');

        $violations = self::$validator->validate($contact);

        self::assertCount(0, $violations);
    }

    public function testFirstNameTooLongProducesViolation(): void
    {
        $contact = (new Contact())->setLastName('Doe')->setFirstName(str_repeat('a', 256));

        $violations = self::$validator->validate($contact);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testPhoneTooLongProducesViolation(): void
    {
        $contact = (new Contact())->setLastName('Doe')->setPhone(str_repeat('1', 51));

        $violations = self::$validator->validate($contact);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testLinkedinTooLongProducesViolation(): void
    {
        $contact = (new Contact())->setLastName('Doe')->setLinkedin(str_repeat('a', 256));

        $violations = self::$validator->validate($contact);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testJobTitleTooLongProducesViolation(): void
    {
        $contact = (new Contact())->setLastName('Doe')->setJobTitle(str_repeat('a', 256));

        $violations = self::$validator->validate($contact);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testValidContactProducesNoViolation(): void
    {
        $contact = (new Contact())->setLastName('Doe');

        $violations = self::$validator->validate($contact);

        self::assertCount(0, $violations);
    }

    public function testPresentationSentDefaultsToFalseAndCanBeSet(): void
    {
        $contact = new Contact();

        self::assertFalse($contact->isPresentationSent());
        self::assertTrue($contact->setPresentationSent(true)->isPresentationSent());
    }

    public function testActiveDefaultsToTrueAndCanBeToggled(): void
    {
        $contact = new Contact();

        self::assertTrue($contact->isActive());
        self::assertFalse($contact->setActive(false)->isActive());
        self::assertTrue($contact->setActive(true)->isActive());
    }

    public function testFullNameJoinsFirstAndLastName(): void
    {
        self::assertSame('Jean Dupont', (new Contact())->setFirstName('Jean')->setLastName('Dupont')->getFullName());
        self::assertSame('Dupont', (new Contact())->setLastName('Dupont')->getFullName());
    }
}
