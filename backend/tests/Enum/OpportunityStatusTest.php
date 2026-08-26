<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\OpportunityStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpportunityStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function backingValuesProvider(): iterable
    {
        yield 'new' => ['new'];
        yield 'to_review' => ['to_review'];
        yield 'contacted' => ['contacted'];
        yield 'waiting' => ['waiting'];
        yield 'follow_up' => ['follow_up'];
        yield 'interview' => ['interview'];
        yield 'won' => ['won'];
        yield 'lost' => ['lost'];
        yield 'archived' => ['archived'];
    }

    #[DataProvider('backingValuesProvider')]
    public function testFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, OpportunityStatus::from($value)->value);
    }

    #[DataProvider('backingValuesProvider')]
    public function testTryFromResolvesEachBackingValue(string $value): void
    {
        self::assertNotNull(OpportunityStatus::tryFrom($value));
        self::assertSame($value, OpportunityStatus::tryFrom($value)?->value);
    }

    public function testTryFromReturnsNullForUnknownValue(): void
    {
        self::assertNull(OpportunityStatus::tryFrom('does_not_exist'));
    }

    /**
     * @return iterable<string, array{0: OpportunityStatus}>
     */
    public static function casesProvider(): iterable
    {
        foreach (OpportunityStatus::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[DataProvider('casesProvider')]
    public function testLabelIsNotEmpty(OpportunityStatus $status): void
    {
        self::assertNotSame('', $status->label());
    }

    public function testValuesReturnsExactlyAllBackingValues(): void
    {
        self::assertSame(
            array_map(static fn (OpportunityStatus $case): string => $case->value, OpportunityStatus::cases()),
            OpportunityStatus::values(),
        );
    }

    public function testExpectedCasesAreLocked(): void
    {
        $names = array_map(static fn (OpportunityStatus $case): string => $case->name, OpportunityStatus::cases());

        self::assertSame(
            ['NEW', 'TO_REVIEW', 'CONTACTED', 'WAITING', 'FOLLOW_UP', 'INTERVIEW', 'WON', 'LOST', 'ARCHIVED'],
            $names,
        );
    }
}
