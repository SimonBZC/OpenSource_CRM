<?php

declare(strict_types=1);

namespace App\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Opportunity;
use App\Filter\TechnologyFilter;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class TechnologyFilterTest extends TestCase
{
    private TechnologyFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new TechnologyFilter();
    }

    public function testGetDescriptionExposesTechnologyParameter(): void
    {
        $description = $this->filter->getDescription(Opportunity::class);

        self::assertArrayHasKey('technology', $description);

        $technology = $description['technology'];
        self::assertSame('technologies', $technology['property']);
        self::assertSame('string', $technology['type']);
        self::assertFalse($technology['required']);
        self::assertTrue($technology['is_collection']);
        self::assertArrayHasKey('description', $technology);
        self::assertIsString($technology['description']);
        self::assertSame(
            ['type' => 'array', 'items' => ['type' => 'string']],
            $technology['schema'],
        );
    }

    public function testApplyDoesNothingWhenFilterIsAbsent(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('getRootAliases');
        $queryBuilder->expects(self::never())->method('andWhere');
        $queryBuilder->expects(self::never())->method('setParameter');

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->expects(self::never())->method('generateParameterName');

        $this->filter->apply($queryBuilder, $queryNameGenerator, Opportunity::class, null, []);
    }

    public function testApplyDoesNothingWhenValueIsNull(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['technology' => null]],
        );
    }

    public function testApplyDoesNothingWhenValueIsEmptyString(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['technology' => '']],
        );
    }

    public function testApplyDoesNothingWhenValueIsEmptyArray(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['technology' => []]],
        );
    }

    public function testApplyDoesNothingWhenValueIsOnlyBlankStrings(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['technology' => ['', '   ']]],
        );
    }

    public function testApplyBuildsSingleConditionForScalarValue(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);
        $queryBuilder->method('expr')->willReturn(new Expr());

        $capturedParameters = [];
        $queryBuilder->expects(self::once())
            ->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$capturedParameters, $queryBuilder) {
                $capturedParameters[$key] = $value;

                return $queryBuilder;
            });

        $capturedWhere = null;
        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturnCallback(function (mixed $where) use (&$capturedWhere, $queryBuilder) {
                $capturedWhere = $where;

                return $queryBuilder;
            });

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->method('generateParameterName')
            ->with('technology')
            ->willReturn('technology_p1');

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['technology' => 'PHP']],
        );

        self::assertInstanceOf(Orx::class, $capturedWhere);
        self::assertCount(1, $capturedWhere->getParts());
        self::assertSame(
            'JSONB_CONTAINS(o.technologies, :technology_p1) = TRUE',
            $capturedWhere->getParts()[0],
        );

        self::assertArrayHasKey('technology_p1', $capturedParameters);
        self::assertSame('["PHP"]', $capturedParameters['technology_p1']);
    }

    public function testApplyBuildsOrConditionForMultipleValues(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);
        $queryBuilder->method('expr')->willReturn(new Expr());

        $capturedParameters = [];
        $queryBuilder->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$capturedParameters, $queryBuilder) {
                $capturedParameters[$key] = $value;

                return $queryBuilder;
            });

        $capturedWhere = null;
        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturnCallback(function (mixed $where) use (&$capturedWhere, $queryBuilder) {
                $capturedWhere = $where;

                return $queryBuilder;
            });

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $generatedNames = ['technology_p1', 'technology_p2'];
        $queryNameGenerator->method('generateParameterName')
            ->with('technology')
            ->willReturnCallback(static function () use (&$generatedNames): string {
                return array_shift($generatedNames);
            });

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['technology' => ['PHP', 'Symfony']]],
        );

        self::assertInstanceOf(Orx::class, $capturedWhere);
        self::assertCount(2, $capturedWhere->getParts());
        self::assertSame(
            'JSONB_CONTAINS(o.technologies, :technology_p1) = TRUE',
            $capturedWhere->getParts()[0],
        );
        self::assertSame(
            'JSONB_CONTAINS(o.technologies, :technology_p2) = TRUE',
            $capturedWhere->getParts()[1],
        );

        self::assertSame('["PHP"]', $capturedParameters['technology_p1']);
        self::assertSame('["Symfony"]', $capturedParameters['technology_p2']);
    }

    public function testApplyIgnoresBlankValuesMixedWithValidOnes(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);
        $queryBuilder->method('expr')->willReturn(new Expr());

        $capturedWhere = null;
        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturnCallback(function (mixed $where) use (&$capturedWhere, $queryBuilder) {
                $capturedWhere = $where;

                return $queryBuilder;
            });

        $queryBuilder->method('setParameter')->willReturnSelf();

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->method('generateParameterName')->willReturn('technology_p1');

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['technology' => ['', '  PHP  ', '   ']]],
        );

        self::assertInstanceOf(Orx::class, $capturedWhere);
        self::assertCount(1, $capturedWhere->getParts());
    }

    /**
     * Comportement réel observé pour un type "inattendu" scalaire non-string
     * (int, bool...) : contrairement à null/''/[] (silencieusement ignorés),
     * ces valeurs ne sont PAS ignorées. `array_map('trim', (array) $value)`
     * s'exécute sans lever de TypeError malgré `declare(strict_types=1)` du
     * fichier (le typage strict ne s'applique pas aux appels indirects via
     * un callback comme array_map), et PHP caste silencieusement la valeur
     * en chaîne ("123" pour l'int 123, "1" pour le bool true). Le filtre
     * traite donc ces valeurs comme une technologie littérale au lieu de les
     * ignorer. Voir le rapport pour signaler cette ambiguïté par rapport à
     * la description du filtre ("type inattendu" -> ignoré).
     */
    public function testApplyCoercesNonStringScalarValueToStringInsteadOfIgnoringIt(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);
        $queryBuilder->method('expr')->willReturn(new Expr());
        $queryBuilder->method('setParameter')->willReturnSelf();

        $capturedWhere = null;
        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturnCallback(function (mixed $where) use (&$capturedWhere, $queryBuilder) {
                $capturedWhere = $where;

                return $queryBuilder;
            });

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->method('generateParameterName')->willReturn('technology_p1');

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['technology' => 123]],
        );

        self::assertInstanceOf(Orx::class, $capturedWhere);
        self::assertCount(1, $capturedWhere->getParts());
    }

    /**
     * L'expression SQL générée ne contient jamais la valeur brute demandée par
     * le client : seule un nom de paramètre lié (:technology_pX) y figure, la
     * valeur (potentiellement malveillante) transitant exclusivement via
     * setParameter(). Ce test garantit l'absence d'injection SQL/DQL par
     * concaténation directe.
     */
    public function testApplyNeverConcatenatesRawValueIntoTheCondition(): void
    {
        $malicious = "PHP'); DROP TABLE opportunity; --";

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);
        $queryBuilder->method('expr')->willReturn(new Expr());

        $capturedParameters = [];
        $queryBuilder->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$capturedParameters, $queryBuilder) {
                $capturedParameters[$key] = $value;

                return $queryBuilder;
            });

        $capturedWhere = null;
        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturnCallback(function (mixed $where) use (&$capturedWhere, $queryBuilder) {
                $capturedWhere = $where;

                return $queryBuilder;
            });

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->method('generateParameterName')->willReturn('technology_p1');

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['technology' => $malicious]],
        );

        self::assertInstanceOf(Orx::class, $capturedWhere);
        self::assertStringNotContainsString($malicious, $capturedWhere->getParts()[0]);
        self::assertStringNotContainsString('DROP TABLE', (string) $capturedWhere);
        self::assertSame(json_encode([$malicious], \JSON_THROW_ON_ERROR), $capturedParameters['technology_p1']);
    }
}
