<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Enum\OpportunitySource;
use App\Import\Exception\NotImplementedImporterException;
use App\Import\LinkedInImporter;
use App\Import\RawOpportunityData;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de App\Import\LinkedInImporter.
 *
 * Placeholder strict, sans aucune dépendance injectée (voir son
 * doc-comment) : instanciable sans argument, ce qui démontre à lui seul
 * qu'aucun effet de bord (DB, réseau) n'est possible.
 */
final class LinkedInImporterTest extends TestCase
{
    public function testSupportsReturnsLinkedInSource(): void
    {
        $importer = new LinkedInImporter();

        self::assertSame(OpportunitySource::LINKEDIN, $importer->supports());
    }

    public function testImportThrowsNotImplementedImporterException(): void
    {
        $importer = new LinkedInImporter();

        $this->expectException(NotImplementedImporterException::class);
        $this->expectExceptionMessage('Import depuis LinkedIn non implémenté : nécessite une intégration API/scraping non prévue dans ce MVP.');

        $importer->import(RawOpportunityData::fromArray(['title' => 'Peu importe']));
    }

    public function testImporterHasNoConstructorDependencies(): void
    {
        // Preuve directe qu'aucune dépendance (EntityManager, repository...)
        // n'est injectée : le constructeur n'attend aucun argument.
        $reflection = new \ReflectionClass(LinkedInImporter::class);
        $constructor = $reflection->getConstructor();

        self::assertTrue(null === $constructor || 0 === $constructor->getNumberOfParameters());
    }
}
