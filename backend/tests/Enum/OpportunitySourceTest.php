<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\OpportunitySource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpportunitySourceTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function backingValuesProvider(): iterable
    {
        yield 'linkedin' => ['linkedin'];
        yield 'malt' => ['malt'];
        yield 'email' => ['email'];
        yield 'website' => ['website'];
        yield 'recommendation' => ['recommendation'];
        yield 'manual' => ['manual'];
        yield 'other' => ['other'];
    }

    #[DataProvider('backingValuesProvider')]
    public function testFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, OpportunitySource::from($value)->value);
    }

    #[DataProvider('backingValuesProvider')]
    public function testTryFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, OpportunitySource::tryFrom($value)?->value);
    }

    public function testTryFromReturnsNullForUnknownValue(): void
    {
        self::assertNull(OpportunitySource::tryFrom('does_not_exist'));
    }

    /**
     * @return iterable<string, array{0: OpportunitySource}>
     */
    public static function casesProvider(): iterable
    {
        foreach (OpportunitySource::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[DataProvider('casesProvider')]
    public function testLabelIsNotEmpty(OpportunitySource $source): void
    {
        self::assertNotSame('', $source->label());
    }

    public function testValuesReturnsExactlyAllBackingValues(): void
    {
        self::assertSame(
            array_map(static fn (OpportunitySource $case): string => $case->value, OpportunitySource::cases()),
            OpportunitySource::values(),
        );
    }

    public function testExpectedCasesAreLocked(): void
    {
        $names = array_map(static fn (OpportunitySource $case): string => $case->name, OpportunitySource::cases());

        self::assertSame(
            ['LINKEDIN', 'MALT', 'EMAIL', 'WEBSITE', 'RECOMMENDATION', 'MANUAL', 'OTHER'],
            $names,
        );
    }
}
