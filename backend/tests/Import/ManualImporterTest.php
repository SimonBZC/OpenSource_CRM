<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Automation\DomainEventBus;
use App\Automation\Event\OpportunityCreated;
use App\Entity\Company;
use App\Entity\Opportunity;
use App\Enum\OpportunitySource;
use App\Import\ImportStatus;
use App\Import\ManualImporter;
use App\Import\RawOpportunityData;
use App\Repository\CompanyRepository;
use App\Repository\OpportunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests unitaires de App\Import\ManualImporter.
 *
 * App\Repository\CompanyRepository, App\Repository\OpportunityRepository et
 * Doctrine\ORM\EntityManagerInterface sont mockés (pas de DB réelle,
 * createMock() ne fait pas appel au constructeur parent des repositories).
 *
 * Symfony\Component\Validator\Validator\ValidatorInterface : un VRAI
 * validateur (Validation::createValidatorBuilder()->enableAttributeMapping())
 * est utilisé plutôt qu'un mock, pour obtenir des violations et des messages
 * réalistes issus des vraies contraintes de App\Entity\Opportunity (même
 * approche que tests/Entity/OpportunityTest.php et
 * tests/Controller/OpportunityAnalysisControllerTest.php).
 *
 * App\Automation\DomainEventBus est `final` (voir le même choix documenté
 * dans tests/Automation/State/OpportunityCreateProcessorTest.php) : une
 * instance réelle est construite avec [] comme règles et un vrai
 * Symfony\Component\EventDispatcher\EventDispatcher, avec un listener qui
 * enregistre les OpportunityCreated dispatchés.
 */
final class ManualImporterTest extends TestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::$validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function setId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }

    /**
     * @return array{0: ManualImporter, 1: CompanyRepository&\PHPUnit\Framework\MockObject\MockObject, 2: OpportunityRepository&\PHPUnit\Framework\MockObject\MockObject, 3: EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject, 4: \ArrayObject<int, OpportunityCreated>}
     */
    private function makeImporter(): array
    {
        // Pas de stub par défaut ici : chaque test configure explicitement
        // le comportement des repositories dont il a besoin. Empiler
        // plusieurs ->method(...)->with(...) pour la même méthode sur le
        // même mock est fragile (PHPUnit ne garantit pas que le stub le
        // plus spécifique gagne), donc chaque test définit une unique
        // configuration cohérente par méthode.
        $companyRepository = $this->createMock(CompanyRepository::class);
        $opportunityRepository = $this->createMock(OpportunityRepository::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $capturedEvents = new \ArrayObject();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            OpportunityCreated::class,
            static function (OpportunityCreated $event) use ($capturedEvents): void {
                $capturedEvents[] = $event;
            },
        );
        $eventBus = new DomainEventBus([], $dispatcher, new NullLogger());

        $importer = new ManualImporter($companyRepository, $opportunityRepository, self::$validator, $entityManager, $eventBus);

        return [$importer, $companyRepository, $opportunityRepository, $entityManager, $capturedEvents];
    }

    // --- supports() ----------------------------------------------------------

    public function testSupportsReturnsManualSource(): void
    {
        [$importer] = $this->makeImporter();

        self::assertSame(OpportunitySource::MANUAL, $importer->supports());
    }

    // --- CREATED --------------------------------------------------------------

    public function testImportCreatesOpportunityWithExpectedMappingWhenDataIsValid(): void
    {
        [$importer, , , $entityManager, $capturedEvents] = $this->makeImporter();

        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Opportunity::class));
        $entityManager->expects(self::once())->method('flush');

        $data = RawOpportunityData::fromArray([
            'title' => 'Développeur Symfony senior',
            'description' => 'Mission longue durée',
            'url' => 'https://example.com/jobs/42',
            'daily_rate_min' => 400,
            'daily_rate_max' => 600,
            'remote' => true,
            'location' => 'Paris',
            'technologies' => ['PHP', 'Symfony'],
        ]);

        $result = $importer->import($data);

        self::assertSame(ImportStatus::CREATED, $result->getStatus());
        self::assertTrue($result->isSuccess());

        $opportunity = $result->getOpportunity();
        self::assertInstanceOf(Opportunity::class, $opportunity);
        self::assertSame('Développeur Symfony senior', $opportunity->getTitle());
        self::assertSame('Mission longue durée', $opportunity->getDescription());
        self::assertSame('https://example.com/jobs/42', $opportunity->getUrl());
        self::assertSame(OpportunitySource::MANUAL, $opportunity->getSource());
        self::assertSame(400, $opportunity->getDailyRateMin());
        self::assertSame(600, $opportunity->getDailyRateMax());
        self::assertTrue($opportunity->isRemote());
        self::assertSame('Paris', $opportunity->getLocation());
        self::assertSame(['PHP', 'Symfony'], $opportunity->getTechnologies());
        self::assertNull($opportunity->getCompany());

        self::assertCount(1, $capturedEvents);
        self::assertSame($opportunity, $capturedEvents[0]->opportunity);
    }

    public function testImportReusesExistingCompanyFoundByNameIgnoreCaseWithoutDuplicatingIt(): void
    {
        [$importer, $companyRepository, , $entityManager] = $this->makeImporter();

        $existingCompany = (new Company())->setName('Acme Corp')->setSource(OpportunitySource::WEBSITE);
        $this->setId($existingCompany, 7);

        $companyRepository->method('findOneByNameIgnoreCase')
            ->with('ACME CORP')
            ->willReturn($existingCompany);

        // La Company existante ne doit JAMAIS être persistée à nouveau
        // (elle a déjà un id) : seule l'Opportunity doit l'être.
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Opportunity::class));
        $entityManager->expects(self::once())->method('flush');

        $data = RawOpportunityData::fromArray([
            'title' => 'Mission chez Acme',
            'company_name' => 'ACME CORP',
        ]);

        $result = $importer->import($data);

        self::assertSame(ImportStatus::CREATED, $result->getStatus());
        self::assertSame($existingCompany, $result->getOpportunity()?->getCompany());
    }

    public function testImportCreatesNewCompanyWhenNoneMatchesByName(): void
    {
        [$importer, $companyRepository, , $entityManager] = $this->makeImporter();

        $companyRepository->method('findOneByNameIgnoreCase')->with('New Company')->willReturn(null);

        $persisted = [];
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $entityManager->expects(self::once())->method('flush');

        $data = RawOpportunityData::fromArray([
            'title' => 'Mission chez New Company',
            'company_name' => 'New Company',
        ]);

        $result = $importer->import($data);

        self::assertSame(ImportStatus::CREATED, $result->getStatus());

        $company = $result->getOpportunity()?->getCompany();
        self::assertInstanceOf(Company::class, $company);
        self::assertSame('New Company', $company->getName());
        self::assertSame(OpportunitySource::MANUAL, $company->getSource());

        self::assertCount(2, $persisted);
        self::assertContains($company, $persisted);
        self::assertContains($result->getOpportunity(), $persisted);
    }

    public function testImportDispatchesOpportunityCreatedExactlyOnceOnSuccessOnly(): void
    {
        [$importer, , , , $capturedEvents] = $this->makeImporter();

        $data = RawOpportunityData::fromArray(['title' => 'Mission valide']);

        $result = $importer->import($data);

        self::assertSame(ImportStatus::CREATED, $result->getStatus());
        self::assertCount(1, $capturedEvents);
        self::assertInstanceOf(OpportunityCreated::class, $capturedEvents[0]);
        self::assertSame($result->getOpportunity(), $capturedEvents[0]->opportunity);
    }

    // --- VALIDATION_ERROR -------------------------------------------------

    public function testImportReturnsValidationErrorWithoutAnySideEffectWhenTitleIsBlank(): void
    {
        [$importer, , , $entityManager, $capturedEvents] = $this->makeImporter();

        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $data = RawOpportunityData::fromArray(['title' => '']);

        $result = $importer->import($data);

        self::assertSame(ImportStatus::VALIDATION_ERROR, $result->getStatus());
        self::assertFalse($result->isSuccess());
        self::assertNull($result->getOpportunity());
        self::assertNotEmpty($result->getMessages());
        self::assertStringContainsString('title', $result->getMessages()[0]);

        self::assertCount(0, $capturedEvents);
    }

    public function testImportReturnsValidationErrorWithoutAnySideEffectWhenUrlIsInvalid(): void
    {
        [$importer, , , $entityManager, $capturedEvents] = $this->makeImporter();

        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $data = RawOpportunityData::fromArray([
            'title' => 'Mission valide',
            'url' => 'ceci-nest-pas-une-url',
        ]);

        $result = $importer->import($data);

        self::assertSame(ImportStatus::VALIDATION_ERROR, $result->getStatus());
        self::assertNotEmpty($result->getMessages());
        self::assertStringContainsString('url', $result->getMessages()[0]);

        self::assertCount(0, $capturedEvents);
    }

    public function testImportDoesNotPersistANewlyInstantiatedCompanyWhenValidationFails(): void
    {
        [$importer, $companyRepository, , $entityManager] = $this->makeImporter();

        $companyRepository->method('findOneByNameIgnoreCase')->willReturn(null);

        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $data = RawOpportunityData::fromArray([
            'title' => '',
            'company_name' => 'Nouvelle Entreprise',
        ]);

        $result = $importer->import($data);

        self::assertSame(ImportStatus::VALIDATION_ERROR, $result->getStatus());
    }

    // --- DUPLICATE ----------------------------------------------------------

    public function testImportReturnsDuplicateWhenAnOpportunityWithTheSameUrlAlreadyExists(): void
    {
        [$importer, , $opportunityRepository, $entityManager, $capturedEvents] = $this->makeImporter();

        $existing = (new Opportunity())->setTitle('Mission déjà connue');
        $this->setId($existing, 99);

        $opportunityRepository->method('findOneByUrl')
            ->with('https://example.com/jobs/42')
            ->willReturn($existing);

        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $data = RawOpportunityData::fromArray([
            'title' => 'Un autre titre',
            'url' => 'https://example.com/jobs/42',
        ]);

        $result = $importer->import($data);

        self::assertSame(ImportStatus::DUPLICATE, $result->getStatus());
        self::assertFalse($result->isSuccess());
        self::assertSame($existing, $result->getOpportunity());
        self::assertCount(0, $capturedEvents);
    }

    public function testImportReturnsDuplicateWhenSameTitleAndSameCompanyAlreadyExist(): void
    {
        [$importer, $companyRepository, $opportunityRepository, $entityManager, $capturedEvents] = $this->makeImporter();

        $existingCompany = (new Company())->setName('Acme Corp')->setSource(OpportunitySource::MANUAL);
        $this->setId($existingCompany, 3);
        $companyRepository->method('findOneByNameIgnoreCase')->with('acme corp')->willReturn($existingCompany);

        $existingOpportunity = (new Opportunity())->setTitle('Développeur Symfony')->setCompany($existingCompany);
        $this->setId($existingOpportunity, 55);

        $opportunityRepository->method('findOneByTitleAndCompany')
            ->with('Développeur Symfony', $existingCompany)
            ->willReturn($existingOpportunity);

        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $data = RawOpportunityData::fromArray([
            'title' => 'Développeur Symfony',
            'company_name' => 'acme corp',
        ]);

        $result = $importer->import($data);

        self::assertSame(ImportStatus::DUPLICATE, $result->getStatus());
        self::assertSame($existingOpportunity, $result->getOpportunity());
        self::assertCount(0, $capturedEvents);
    }

    public function testImportDoesNotCheckTitleAndCompanyDuplicateWhenCompanyIsNewlyCreated(): void
    {
        // Une Company nouvellement instanciée (pas encore persistée, donc
        // sans id) ne peut par définition être partagée par aucune
        // Opportunity existante : findOneByTitleAndCompany ne doit même pas
        // être appelée.
        [$importer, $companyRepository, $opportunityRepository, $entityManager] = $this->makeImporter();

        $companyRepository->method('findOneByNameIgnoreCase')->willReturn(null);
        $opportunityRepository->expects(self::never())->method('findOneByTitleAndCompany');

        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $data = RawOpportunityData::fromArray([
            'title' => 'Mission chez nouvelle entreprise',
            'company_name' => 'Entreprise Inconnue',
        ]);

        $result = $importer->import($data);

        self::assertSame(ImportStatus::CREATED, $result->getStatus());
    }
}
