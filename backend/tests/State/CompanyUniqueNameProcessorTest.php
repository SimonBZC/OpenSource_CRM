<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Company;
use App\Repository\CompanyRepository;
use App\State\CompanyUniqueNameProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de App\State\CompanyUniqueNameProcessor.
 *
 * La comparaison insensible à la casse et aux espaces est faite en base par
 * CompanyRepository::findOneByNameIgnoreCase() (LOWER/TRIM en DQL) : elle est
 * vérifiée en boîte noire sur la stack de test (POST/PATCH /api/companies).
 * Ici, le repository est simulé : on vérifie que le processor lui transmet le
 * nom normalisé et l'identifiant à exclure, refuse un doublon par une
 * violation sur `name`, et ne persiste rien dans ce cas.
 */
final class CompanyUniqueNameProcessorTest extends TestCase
{
    /**
     * @return array{0: CompanyUniqueNameProcessor, 1: ProcessorInterface&MockObject, 2: CompanyRepository&MockObject}
     */
    private function makeProcessor(): array
    {
        $decorated = $this->createMock(ProcessorInterface::class);
        $repository = $this->createMock(CompanyRepository::class);

        return [new CompanyUniqueNameProcessor($decorated, $repository), $decorated, $repository];
    }

    private function companyWithId(string $name, int $id): Company
    {
        $company = (new Company())->setName($name);
        (new \ReflectionProperty(Company::class, 'id'))->setValue($company, $id);

        return $company;
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function duplicateNameProvider(): iterable
    {
        yield 'casse différente' => ['acme', 'acme'];
        yield 'majuscules' => ['ACME', 'ACME'];
        yield 'espaces en début et fin' => ['  Acme  ', 'Acme'];
    }

    #[DataProvider('duplicateNameProvider')]
    public function testDuplicateNameIsRejectedWithViolationOnName(string $submittedName, string $expectedLookup): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $existing = $this->companyWithId('Acme', 1);
        $repository->expects(self::once())
            ->method('findOneByNameIgnoreCase')
            ->with($expectedLookup, null)
            ->willReturn($existing);
        $decorated->expects(self::never())->method('process');

        $company = (new Company())->setName($submittedName);

        try {
            $processor->process($company, new Post());
            self::fail('Une ValidationException était attendue pour un doublon.');
        } catch (ValidationException $exception) {
            $violations = $exception->getConstraintViolationList();
            self::assertCount(1, $violations);
            self::assertSame('name', $violations[0]->getPropertyPath());
            self::assertSame(CompanyUniqueNameProcessor::DUPLICATE_NAME_MESSAGE, $violations[0]->getMessage());
        }
    }

    public function testFreeNameIsDelegatedToPersistProcessor(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $company = (new Company())->setName('Nouvelle SAS');
        $operation = new Post();

        $repository->method('findOneByNameIgnoreCase')->with('Nouvelle SAS', null)->willReturn(null);
        $decorated->expects(self::once())
            ->method('process')
            ->with($company, $operation, [], [])
            ->willReturn($company);

        self::assertSame($company, $processor->process($company, $operation));
    }

    public function testPatchOnItselfExcludesOwnIdAndIsAccepted(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $company = $this->companyWithId('Acme', 42);

        // Le repository reçoit l'id de l'entreprise modifiée : elle n'est pas
        // son propre doublon, aucune autre entreprise ne porte ce nom.
        $repository->expects(self::once())
            ->method('findOneByNameIgnoreCase')
            ->with('Acme', 42)
            ->willReturn(null);
        $decorated->expects(self::once())->method('process')->willReturn($company);

        self::assertSame($company, $processor->process($company, new Patch(), ['id' => 42]));
    }

    public function testPutRenamingToAnotherCompanyNameIsRejected(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $company = $this->companyWithId('ACME', 42);
        $other = $this->companyWithId('Acme', 7);

        $repository->method('findOneByNameIgnoreCase')->with('ACME', 42)->willReturn($other);
        $decorated->expects(self::never())->method('process');

        $this->expectException(ValidationException::class);

        $processor->process($company, new Put(), ['id' => 42]);
    }

    public function testNonCompanyDataIsDelegatedWithoutLookup(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $data = new \stdClass();
        $repository->expects(self::never())->method('findOneByNameIgnoreCase');
        $decorated->expects(self::once())->method('process')->willReturn($data);

        self::assertSame($data, $processor->process($data, new Post()));
    }

    public function testPutOnItselfUsesPreviousDataIdAndIsAccepted(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        // Sur un PUT, l'objet désérialisé est neuf (sans id) : l'id vient de
        // `previous_data`, l'entreprise n'est pas son propre doublon.
        $company = (new Company())->setName('Acme');
        $previous = $this->companyWithId('Acme', 42);

        $repository->expects(self::once())
            ->method('findOneByNameIgnoreCase')
            ->with('Acme', 42)
            ->willReturn(null);
        $decorated->expects(self::once())->method('process')->willReturn($company);

        self::assertSame($company, $processor->process($company, new Put(), ['id' => 42], ['previous_data' => $previous]));
    }

    public function testPutWithoutPreviousDataFallsBackOnUriId(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $company = (new Company())->setName('Acme');

        $repository->expects(self::once())
            ->method('findOneByNameIgnoreCase')
            ->with('Acme', 42)
            ->willReturn(null);
        $decorated->expects(self::once())->method('process')->willReturn($company);

        $processor->process($company, new Put(), ['id' => '42']);
    }
}
