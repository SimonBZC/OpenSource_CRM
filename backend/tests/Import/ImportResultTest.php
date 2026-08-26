<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Entity\Opportunity;
use App\Import\ImportResult;
use App\Import\ImportStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de App\Import\ImportResult et App\Import\ImportStatus.
 */
final class ImportResultTest extends TestCase
{
    public function testCreatedCarriesOpportunityAndStatusAndIsSuccess(): void
    {
        $opportunity = (new Opportunity())->setTitle('Mission');

        $result = ImportResult::created($opportunity);

        self::assertSame(ImportStatus::CREATED, $result->getStatus());
        self::assertSame($opportunity, $result->getOpportunity());
        self::assertSame([], $result->getMessages());
        self::assertTrue($result->isSuccess());
    }

    public function testDuplicateCarriesExistingOpportunityAndIsNotSuccess(): void
    {
        $existing = (new Opportunity())->setTitle('Mission existante');

        $result = ImportResult::duplicate($existing);

        self::assertSame(ImportStatus::DUPLICATE, $result->getStatus());
        self::assertSame($existing, $result->getOpportunity());
        self::assertSame([], $result->getMessages());
        self::assertFalse($result->isSuccess());
    }

    public function testValidationErrorCarriesMessagesAndNoOpportunityAndIsNotSuccess(): void
    {
        $messages = ['title: This value should not be blank.', 'url: This value is not a valid URL.'];

        $result = ImportResult::validationError($messages);

        self::assertSame(ImportStatus::VALIDATION_ERROR, $result->getStatus());
        self::assertNull($result->getOpportunity());
        self::assertSame($messages, $result->getMessages());
        self::assertFalse($result->isSuccess());
    }

    public function testValidationErrorWithEmptyMessagesIsStillNotSuccess(): void
    {
        $result = ImportResult::validationError([]);

        self::assertSame(ImportStatus::VALIDATION_ERROR, $result->getStatus());
        self::assertSame([], $result->getMessages());
        self::assertFalse($result->isSuccess());
    }

    public function testNotImplementedCarriesSingleMessageAndNoOpportunityAndIsNotSuccess(): void
    {
        $result = ImportResult::notImplemented('Import depuis LinkedIn non implémenté.');

        self::assertSame(ImportStatus::NOT_IMPLEMENTED, $result->getStatus());
        self::assertNull($result->getOpportunity());
        self::assertSame(['Import depuis LinkedIn non implémenté.'], $result->getMessages());
        self::assertFalse($result->isSuccess());
    }

    public function testOnlyCreatedStatusIsConsideredSuccess(): void
    {
        $opportunity = (new Opportunity())->setTitle('Mission');

        self::assertTrue(ImportResult::created($opportunity)->isSuccess());
        self::assertFalse(ImportResult::duplicate($opportunity)->isSuccess());
        self::assertFalse(ImportResult::validationError(['erreur'])->isSuccess());
        self::assertFalse(ImportResult::notImplemented('non implémenté')->isSuccess());
    }

    public function testImportStatusBackedValues(): void
    {
        self::assertSame('created', ImportStatus::CREATED->value);
        self::assertSame('duplicate', ImportStatus::DUPLICATE->value);
        self::assertSame('validation_error', ImportStatus::VALIDATION_ERROR->value);
        self::assertSame('not_implemented', ImportStatus::NOT_IMPLEMENTED->value);
    }

    public function testImportStatusHasExactlyFourCases(): void
    {
        self::assertCount(4, ImportStatus::cases());
    }
}
