<?php

declare(strict_types=1);

namespace App\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Opportunity;
use App\Filter\OpportunitySearchFilter;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpportunitySearchFilterTest extends TestCase
{
    private OpportunitySearchFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new OpportunitySearchFilter();
    }

    public function testGetDescriptionExposesSearchParameter(): void
    {
        $description = $this->filter->getDescription(Opportunity::class);

        self::assertArrayHasKey('search', $description);

        $search = $description['search'];
        self::assertNull($search['property']);
        self::assertSame('string', $search['type']);
        self::assertFalse($search['required']);
        self::assertArrayHasKey('description', $search);
        self::assertIsString($search['description']);
        self::assertNotSame('', $search['description']);
        self::assertSame(['type' => 'string'], $search['schema']);
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
            ['filters' => ['search' => null]],
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
            ['filters' => ['search' => '']],
        );
    }

    public function testApplyDoesNothingWhenValueIsOnlyBlankSpaces(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['search' => '   ']],
        );
    }

    public function testApplyDoesNothingWhenValueIsNotAString(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['search' => ['not', 'a', 'string']]],
        );
    }

    public function testApplyBuildsOrConditionOnTitleAndDescriptionWithDistinctBoundParameters(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);

        $capturedParameters = [];
        $queryBuilder->expects(self::exactly(2))
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
        $generatedNames = ['search_p1', 'search_p2'];
        $queryNameGenerator->expects(self::exactly(2))
            ->method('generateParameterName')
            ->with('search')
            ->willReturnCallback(static function () use (&$generatedNames): string {
                return array_shift($generatedNames);
            });

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['search' => 'PHP']],
        );

        self::assertIsString($capturedWhere);
        self::assertSame(
            "LOWER(o.title) LIKE :search_p1 ESCAPE '!' OR LOWER(o.description) LIKE :search_p2 ESCAPE '!'",
            $capturedWhere,
        );

        // Deux paramètres distincts, liés à la même valeur (pattern).
        self::assertArrayHasKey('search_p1', $capturedParameters);
        self::assertArrayHasKey('search_p2', $capturedParameters);
        self::assertNotSame('search_p1', 'search_p2');
        self::assertSame($capturedParameters['search_p1'], $capturedParameters['search_p2']);
        self::assertSame('%php%', $capturedParameters['search_p1']);
    }

    public function testApplyLowercasesTheBoundValueForCaseInsensitiveComparison(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);

        $capturedParameters = [];
        $queryBuilder->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$capturedParameters, $queryBuilder) {
                $capturedParameters[$key] = $value;

                return $queryBuilder;
            });
        $queryBuilder->method('andWhere')->willReturnSelf();

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->method('generateParameterName')->willReturn('search_p1');

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['search' => 'DEVELOPPEUR']],
        );

        self::assertSame('%developpeur%', $capturedParameters['search_p1']);
    }

    /**
     * Comportement assumé et arbitré : le filtre est insensible à la casse
     * mais SENSIBLE aux accents (mb_strtolower ne fait pas de "accent
     * folding"). Ce test verrouille ce comportement actuel, il ne doit pas
     * être interprété comme une exigence d'accent-folding.
     */
    public function testApplyIsCaseInsensitiveButAccentSensitive(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);

        $capturedParameters = [];
        $queryBuilder->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$capturedParameters, $queryBuilder) {
                $capturedParameters[$key] = $value;

                return $queryBuilder;
            });
        $queryBuilder->method('andWhere')->willReturnSelf();

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->method('generateParameterName')->willReturn('search_p1');

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['search' => 'DÉVELOPPEUR']],
        );

        // La casse est neutralisée...
        self::assertSame('%développeur%', $capturedParameters['search_p1']);
        // ...mais l'accent est conservé : "developpeur" (sans accent) ne matcherait pas.
        self::assertNotSame('%developpeur%', $capturedParameters['search_p1']);
    }

    public function testApplyTrimsSurroundingWhitespaceBeforeBuildingPattern(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);

        $capturedParameters = [];
        $queryBuilder->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$capturedParameters, $queryBuilder) {
                $capturedParameters[$key] = $value;

                return $queryBuilder;
            });
        $queryBuilder->method('andWhere')->willReturnSelf();

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->method('generateParameterName')->willReturn('search_p1');

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['search' => '  php  ']],
        );

        self::assertSame('%php%', $capturedParameters['search_p1']);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function likeSpecialCharactersProvider(): iterable
    {
        yield 'percent sign' => ['100%', '%100!%%'];
        yield 'underscore' => ['foo_bar', '%foo!_bar%'];
        yield 'escape character itself' => ['a!b', '%a!!b%'];
        yield 'combination of all special characters' => ['100%_!x', '%100!%!_!!x%'];
    }

    #[DataProvider('likeSpecialCharactersProvider')]
    public function testApplyEscapesLikeSpecialCharactersInBoundPattern(string $rawValue, string $expectedPattern): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);

        $capturedParameters = [];
        $queryBuilder->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$capturedParameters, $queryBuilder) {
                $capturedParameters[$key] = $value;

                return $queryBuilder;
            });
        $queryBuilder->method('andWhere')->willReturnSelf();

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->method('generateParameterName')->willReturn('search_p1');

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['search' => $rawValue]],
        );

        self::assertSame($expectedPattern, $capturedParameters['search_p1']);
    }

    /**
     * L'expression DQL générée ne contient jamais la valeur brute demandée
     * par le client : seuls des noms de paramètres liés (:search_pX) y
     * figurent, la valeur transitant exclusivement via setParameter(). Ce
     * test garantit l'absence d'injection SQL/DQL par concaténation directe.
     */
    public function testApplyNeverConcatenatesRawValueIntoTheCondition(): void
    {
        $malicious = "PHP'); DROP TABLE opportunity; --";

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);
        $queryBuilder->method('setParameter')->willReturnSelf();

        $capturedWhere = null;
        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturnCallback(function (mixed $where) use (&$capturedWhere, $queryBuilder) {
                $capturedWhere = $where;

                return $queryBuilder;
            });

        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->method('generateParameterName')->willReturn('search_p1');

        $this->filter->apply(
            $queryBuilder,
            $queryNameGenerator,
            Opportunity::class,
            null,
            ['filters' => ['search' => $malicious]],
        );

        self::assertIsString($capturedWhere);
        self::assertStringNotContainsString($malicious, $capturedWhere);
        self::assertStringNotContainsString('DROP TABLE', $capturedWhere);
    }
}
