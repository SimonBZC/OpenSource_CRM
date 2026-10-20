<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Interaction;
use App\Entity\Opportunity;
use App\Enum\InteractionType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyTest extends TestCase
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
        $company = new Company();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $company->getCreatedAt());
        self::assertLessThanOrEqual($after, $company->getCreatedAt());

        self::assertCount(0, $company->getContacts());
        self::assertCount(0, $company->getOpportunities());
        self::assertCount(0, $company->getInteractions());
    }

    public function testAddContactSynchronizesOwningSide(): void
    {
        $company = new Company();
        $contact = new Contact();

        $company->addContact($contact);

        self::assertTrue($company->getContacts()->contains($contact));
        self::assertSame($company, $contact->getCompany());
    }

    public function testAddContactTwiceDoesNotDuplicate(): void
    {
        $company = new Company();
        $contact = new Contact();

        $company->addContact($contact);
        $company->addContact($contact);

        self::assertCount(1, $company->getContacts());
    }

    public function testRemoveContactClearsOwningSide(): void
    {
        $company = new Company();
        $contact = new Contact();

        $company->addContact($contact);
        $company->removeContact($contact);

        self::assertFalse($company->getContacts()->contains($contact));
        self::assertNull($contact->getCompany());
    }

    public function testRemoveContactDoesNotClearOwningSideIfReassignedElsewhere(): void
    {
        $companyA = new Company();
        $companyB = new Company();
        $contact = new Contact();

        $companyA->addContact($contact);
        $companyB->addContact($contact);

        // Contact is now owned by companyB; removing from companyA's collection
        // must not null out a company relation it doesn't currently own.
        $companyA->removeContact($contact);

        self::assertSame($companyB, $contact->getCompany());
    }

    public function testAddOpportunitySynchronizesOwningSide(): void
    {
        $company = new Company();
        $opportunity = new Opportunity();

        $company->addOpportunity($opportunity);

        self::assertTrue($company->getOpportunities()->contains($opportunity));
        self::assertSame($company, $opportunity->getCompany());
    }

    public function testAddOpportunityTwiceDoesNotDuplicate(): void
    {
        $company = new Company();
        $opportunity = new Opportunity();

        $company->addOpportunity($opportunity);
        $company->addOpportunity($opportunity);

        self::assertCount(1, $company->getOpportunities());
    }

    public function testRemoveOpportunityClearsOwningSide(): void
    {
        $company = new Company();
        $opportunity = new Opportunity();

        $company->addOpportunity($opportunity);
        $company->removeOpportunity($opportunity);

        self::assertFalse($company->getOpportunities()->contains($opportunity));
        self::assertNull($opportunity->getCompany());
    }

    public function testAddInteractionSynchronizesOwningSide(): void
    {
        $company = new Company();
        $interaction = (new Interaction())->setType(InteractionType::EMAIL);

        $company->addInteraction($interaction);

        self::assertTrue($company->getInteractions()->contains($interaction));
        self::assertSame($company, $interaction->getCompany());
    }

    public function testAddInteractionTwiceDoesNotDuplicate(): void
    {
        $company = new Company();
        $interaction = (new Interaction())->setType(InteractionType::EMAIL);

        $company->addInteraction($interaction);
        $company->addInteraction($interaction);

        self::assertCount(1, $company->getInteractions());
    }

    public function testRemoveInteractionClearsOwningSide(): void
    {
        $company = new Company();
        $interaction = (new Interaction())->setType(InteractionType::EMAIL);

        $company->addInteraction($interaction);
        $company->removeInteraction($interaction);

        self::assertFalse($company->getInteractions()->contains($interaction));
        self::assertNull($interaction->getCompany());
    }

    public function testNameBlankProducesViolation(): void
    {
        $company = (new Company())->setName('');

        $violations = self::$validator->validate($company);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testNameTooLongProducesViolation(): void
    {
        $company = (new Company())->setName(str_repeat('a', 256));

        $violations = self::$validator->validate($company);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testInvalidWebsiteUrlProducesViolation(): void
    {
        $company = (new Company())->setName('Acme')->setWebsite('not-a-url');

        $violations = self::$validator->validate($company);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testValidWebsiteUrlProducesNoViolation(): void
    {
        $company = (new Company())->setName('Acme')->setWebsite('https://acme.example.com');

        $violations = self::$validator->validate($company);

        self::assertCount(0, $violations);
    }

    public function testSectorTooLongProducesViolation(): void
    {
        $company = (new Company())->setName('Acme')->setSector(str_repeat('a', 256));

        $violations = self::$validator->validate($company);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testLocationTooLongProducesViolation(): void
    {
        $company = (new Company())->setName('Acme')->setLocation(str_repeat('a', 256));

        $violations = self::$validator->validate($company);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testValidCompanyProducesNoViolation(): void
    {
        $company = (new Company())->setName('Acme');

        $violations = self::$validator->validate($company);

        self::assertCount(0, $violations);
    }

    public function testSetNameTrimsLeadingAndTrailingSpaces(): void
    {
        $company = (new Company())->setName('  Acme  ');

        self::assertSame('Acme', $company->getName());
    }
}
