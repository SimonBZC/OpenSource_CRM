<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ImportOpportunityCommand;
use App\Entity\Opportunity;
use App\Enum\OpportunitySource;
use App\Import\ImportResult;
use App\Import\LinkedInImporter;
use App\Import\MaltImporter;
use App\Import\OpportunityImporterInterface;
use App\Import\OpportunityImporterRegistry;
use App\Import\RawOpportunityData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests unitaires de App\Command\ImportOpportunityCommand.
 *
 * App\Import\OpportunityImporterRegistry est `final` : impossible à mocker
 * directement (voir la même contrainte pour App\Automation\DomainEventBus
 * ailleurs dans la suite). Une VRAIE instance est donc construite à partir
 * d'App\Import\OpportunityImporterInterface mockés (interface, pas final),
 * ce qui permet de contrôler précisément l'App\Import\ImportResult retourné
 * par chaque test sans jamais toucher de base de données.
 */
final class ImportOpportunityCommandTest extends TestCase
{
    private function setId(Opportunity $opportunity, int $id): void
    {
        $reflection = new \ReflectionProperty($opportunity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($opportunity, $id);
    }

    private function makeMockImporter(OpportunitySource $source): OpportunityImporterInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $importer = $this->createMock(OpportunityImporterInterface::class);
        $importer->method('supports')->willReturn($source);

        return $importer;
    }

    private function makeCommand(OpportunityImporterInterface ...$importers): ImportOpportunityCommand
    {
        return new ImportOpportunityCommand(new OpportunityImporterRegistry($importers));
    }

    // --- Source inconnue (exit 2) ---------------------------------------------

    public function testUnknownSourceReturnsInvalidExitCodeAndDoesNotCallRegistry(): void
    {
        $importer = $this->makeMockImporter(OpportunitySource::MANUAL);
        $importer->expects(self::never())->method('import');

        $tester = new CommandTester($this->makeCommand($importer));
        $exitCode = $tester->execute(['source' => 'not-a-real-source']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Source inconnue', $tester->getDisplay());
    }

    // --- Source connue mais sans importeur enregistré (exit 2) ----------------

    public function testKnownSourceWithoutRegisteredImporterReturnsInvalidExitCode(): void
    {
        // Aucun importeur enregistré pour EMAIL dans ce registre.
        $tester = new CommandTester($this->makeCommand($this->makeMockImporter(OpportunitySource::MANUAL)));
        $exitCode = $tester->execute(['source' => OpportunitySource::EMAIL->value]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Aucun importeur disponible', $tester->getDisplay());
    }

    // --- JSON invalide (exit 1) ------------------------------------------------

    public function testInvalidInlineJsonReturnsFailureExitCodeAndDoesNotCallRegistry(): void
    {
        $importer = $this->makeMockImporter(OpportunitySource::MANUAL);
        $importer->expects(self::never())->method('import');

        $tester = new CommandTester($this->makeCommand($importer));
        $exitCode = $tester->execute([
            'source' => OpportunitySource::MANUAL->value,
            '--data' => '{not-valid-json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('JSON invalide', $tester->getDisplay());
    }

    public function testJsonThatIsNotAnObjectReturnsFailureExitCode(): void
    {
        $importer = $this->makeMockImporter(OpportunitySource::MANUAL);
        $importer->expects(self::never())->method('import');

        $tester = new CommandTester($this->makeCommand($importer));
        $exitCode = $tester->execute([
            'source' => OpportunitySource::MANUAL->value,
            '--data' => '"just a string"',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('objet', $tester->getDisplay());
    }

    // --- Fichier introuvable (exit 1) -----------------------------------------

    public function testUnreadableFileReturnsFailureExitCodeAndDoesNotCallRegistry(): void
    {
        $importer = $this->makeMockImporter(OpportunitySource::MANUAL);
        $importer->expects(self::never())->method('import');

        $tester = new CommandTester($this->makeCommand($importer));
        $exitCode = $tester->execute([
            'source' => OpportunitySource::MANUAL->value,
            '--file' => '/tmp/this-file-does-not-exist-for-freecrm-tests.json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Fichier introuvable', $tester->getDisplay());
    }

    // --- Priorité de --data sur --file -----------------------------------------

    public function testDataOptionTakesPrecedenceOverFileOption(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'freecrm_import_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, json_encode(['title' => 'Depuis le fichier']));

        try {
            $importer = $this->makeMockImporter(OpportunitySource::MANUAL);

            $capturedData = null;
            $importer->method('import')->willReturnCallback(function (RawOpportunityData $data) use (&$capturedData): ImportResult {
                $capturedData = $data;
                $opportunity = (new Opportunity())->setTitle($data->title);
                $this->setId($opportunity, 1);

                return ImportResult::created($opportunity);
            });

            $tester = new CommandTester($this->makeCommand($importer));
            $exitCode = $tester->execute([
                'source' => OpportunitySource::MANUAL->value,
                '--data' => json_encode(['title' => 'Depuis --data']),
                '--file' => $tmpFile,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertSame('Depuis --data', $capturedData?->title);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testFileOptionIsUsedWhenDataOptionIsAbsent(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'freecrm_import_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, json_encode(['title' => 'Depuis le fichier']));

        try {
            $importer = $this->makeMockImporter(OpportunitySource::MANUAL);

            $capturedData = null;
            $importer->method('import')->willReturnCallback(function (RawOpportunityData $data) use (&$capturedData): ImportResult {
                $capturedData = $data;
                $opportunity = (new Opportunity())->setTitle($data->title);
                $this->setId($opportunity, 1);

                return ImportResult::created($opportunity);
            });

            $tester = new CommandTester($this->makeCommand($importer));
            $exitCode = $tester->execute([
                'source' => OpportunitySource::MANUAL->value,
                '--file' => $tmpFile,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertSame('Depuis le fichier', $capturedData?->title);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testNoDataOrFileOptionUsesEmptyPayload(): void
    {
        $importer = $this->makeMockImporter(OpportunitySource::MANUAL);

        $capturedData = null;
        $importer->method('import')->willReturnCallback(function (RawOpportunityData $data) use (&$capturedData): ImportResult {
            $capturedData = $data;
            $opportunity = (new Opportunity())->setTitle($data->title);
            $this->setId($opportunity, 1);

            return ImportResult::created($opportunity);
        });

        $tester = new CommandTester($this->makeCommand($importer));
        $tester->execute(['source' => OpportunitySource::MANUAL->value]);

        self::assertSame('', $capturedData?->title);
    }

    // --- Branches de résultat (CREATED / DUPLICATE / VALIDATION_ERROR) --------

    public function testCreatedResultReturnsSuccessExitCode(): void
    {
        $importer = $this->makeMockImporter(OpportunitySource::MANUAL);
        $opportunity = (new Opportunity())->setTitle('Mission');
        $this->setId($opportunity, 42);
        $importer->method('import')->willReturn(ImportResult::created($opportunity));

        $tester = new CommandTester($this->makeCommand($importer));
        $exitCode = $tester->execute(['source' => OpportunitySource::MANUAL->value]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('42', $tester->getDisplay());
    }

    public function testDuplicateResultReturnsFailureExitCode(): void
    {
        $importer = $this->makeMockImporter(OpportunitySource::MANUAL);
        $existing = (new Opportunity())->setTitle('Mission existante');
        $this->setId($existing, 7);
        $importer->method('import')->willReturn(ImportResult::duplicate($existing));

        $tester = new CommandTester($this->makeCommand($importer));
        $exitCode = $tester->execute(['source' => OpportunitySource::MANUAL->value]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Doublon détecté', $tester->getDisplay());
    }

    public function testValidationErrorResultReturnsFailureExitCode(): void
    {
        $importer = $this->makeMockImporter(OpportunitySource::MANUAL);
        $importer->method('import')->willReturn(ImportResult::validationError(['title: This value should not be blank.']));

        $tester = new CommandTester($this->makeCommand($importer));
        $exitCode = $tester->execute(['source' => OpportunitySource::MANUAL->value]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('ne sont pas valides', $tester->getDisplay());
        self::assertStringContainsString('This value should not be blank.', $tester->getDisplay());
    }

    // --- LinkedIn / Malt : NotImplementedImporterException (exit 1) -----------

    public function testLinkedInSourceFailsCleanlyWithoutUncaughtException(): void
    {
        $tester = new CommandTester($this->makeCommand(new LinkedInImporter(), new MaltImporter()));

        $exitCode = $tester->execute(['source' => OpportunitySource::LINKEDIN->value]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('non implémenté', $tester->getDisplay());
    }

    public function testMaltSourceFailsCleanlyWithoutUncaughtException(): void
    {
        $tester = new CommandTester($this->makeCommand(new LinkedInImporter(), new MaltImporter()));

        $exitCode = $tester->execute(['source' => OpportunitySource::MALT->value]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('non implémenté', $tester->getDisplay());
    }
}
