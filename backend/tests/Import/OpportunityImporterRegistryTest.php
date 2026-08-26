<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Enum\OpportunitySource;
use App\Import\Exception\UnsupportedImportSourceException;
use App\Import\LinkedInImporter;
use App\Import\MaltImporter;
use App\Import\OpportunityImporterInterface;
use App\Import\OpportunityImporterRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de App\Import\OpportunityImporterRegistry.
 */
final class OpportunityImporterRegistryTest extends TestCase
{
    private function makeImporter(OpportunitySource $source): OpportunityImporterInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $importer = $this->createMock(OpportunityImporterInterface::class);
        $importer->method('supports')->willReturn($source);

        return $importer;
    }

    public function testGetResolvesTheRegisteredImporterForItsSource(): void
    {
        $linkedInImporter = new LinkedInImporter();
        $maltImporter = new MaltImporter();

        $registry = new OpportunityImporterRegistry([$linkedInImporter, $maltImporter]);

        self::assertSame($linkedInImporter, $registry->get(OpportunitySource::LINKEDIN));
        self::assertSame($maltImporter, $registry->get(OpportunitySource::MALT));
    }

    public function testGetThrowsUnsupportedImportSourceExceptionForUnregisteredSource(): void
    {
        $registry = new OpportunityImporterRegistry([new LinkedInImporter()]);

        $this->expectException(UnsupportedImportSourceException::class);

        $registry->get(OpportunitySource::EMAIL);
    }

    public function testExceptionMessageMentionsRequestedSourceAndAvailableSources(): void
    {
        $registry = new OpportunityImporterRegistry([new LinkedInImporter(), new MaltImporter()]);

        try {
            $registry->get(OpportunitySource::EMAIL);
            self::fail('UnsupportedImportSourceException expected.');
        } catch (UnsupportedImportSourceException $e) {
            self::assertStringContainsString('email', $e->getMessage());
            self::assertStringContainsString('linkedin', $e->getMessage());
            self::assertStringContainsString('malt', $e->getMessage());
        }
    }

    public function testExceptionMessageWithEmptyRegistryIndicatesNoSourceAvailable(): void
    {
        $registry = new OpportunityImporterRegistry([]);

        try {
            $registry->get(OpportunitySource::MANUAL);
            self::fail('UnsupportedImportSourceException expected.');
        } catch (UnsupportedImportSourceException $e) {
            self::assertStringContainsString('(aucune)', $e->getMessage());
        }
    }

    public function testGetSupportedSourcesReturnsAllRegisteredSourceValues(): void
    {
        $registry = new OpportunityImporterRegistry([
            $this->makeImporter(OpportunitySource::MANUAL),
            $this->makeImporter(OpportunitySource::LINKEDIN),
        ]);

        self::assertSame(['manual', 'linkedin'], $registry->getSupportedSources());
    }

    public function testGetSupportedSourcesReturnsEmptyArrayForEmptyRegistry(): void
    {
        $registry = new OpportunityImporterRegistry([]);

        self::assertSame([], $registry->getSupportedSources());
    }

    public function testLaterImporterForSameSourceOverridesEarlierOne(): void
    {
        // Comportement réel observé (pas présumé) : le tableau indexé par
        // source garde le dernier importeur rencontré pour une source
        // donnée en cas de doublon d'enregistrement.
        $first = $this->makeImporter(OpportunitySource::MANUAL);
        $second = $this->makeImporter(OpportunitySource::MANUAL);

        $registry = new OpportunityImporterRegistry([$first, $second]);

        self::assertSame($second, $registry->get(OpportunitySource::MANUAL));
    }
}
