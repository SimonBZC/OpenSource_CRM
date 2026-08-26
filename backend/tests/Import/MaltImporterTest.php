<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Enum\OpportunitySource;
use App\Import\Exception\NotImplementedImporterException;
use App\Import\MaltImporter;
use App\Import\RawOpportunityData;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de App\Import\MaltImporter.
 *
 * Placeholder strict, sans aucune dépendance injectée (voir son
 * doc-comment) : instanciable sans argument, ce qui démontre à lui seul
 * qu'aucun effet de bord (DB, réseau) n'est possible.
 */
final class MaltImporterTest extends TestCase
{
    public function testSupportsReturnsMaltSource(): void
    {
        $importer = new MaltImporter();

        self::assertSame(OpportunitySource::MALT, $importer->supports());
    }

    public function testImportThrowsNotImplementedImporterException(): void
    {
        $importer = new MaltImporter();

        $this->expectException(NotImplementedImporterException::class);
        $this->expectExceptionMessage('Import depuis Malt non implémenté : nécessite une intégration API/scraping non prévue dans ce MVP.');

        $importer->import(RawOpportunityData::fromArray(['title' => 'Peu importe']));
    }

    public function testImporterHasNoConstructorDependencies(): void
    {
        $reflection = new \ReflectionClass(MaltImporter::class);
        $constructor = $reflection->getConstructor();

        self::assertTrue(null === $constructor || 0 === $constructor->getNumberOfParameters());
    }
}
