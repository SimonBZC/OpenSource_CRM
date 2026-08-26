<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\FollowUpStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FollowUpStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function backingValuesProvider(): iterable
    {
        yield 'todo' => ['todo'];
        yield 'done' => ['done'];
        yield 'cancelled' => ['cancelled'];
    }

    #[DataProvider('backingValuesProvider')]
    public function testFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, FollowUpStatus::from($value)->value);
    }

    #[DataProvider('backingValuesProvider')]
    public function testTryFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, FollowUpStatus::tryFrom($value)?->value);
    }

    public function testTryFromReturnsNullForUnknownValue(): void
    {
        self::assertNull(FollowUpStatus::tryFrom('does_not_exist'));
    }

    /**
     * @return iterable<string, array{0: FollowUpStatus}>
     */
    public static function casesProvider(): iterable
    {
        foreach (FollowUpStatus::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[DataProvider('casesProvider')]
    public function testLabelIsNotEmpty(FollowUpStatus $status): void
    {
        self::assertNotSame('', $status->label());
    }

    public function testValuesReturnsExactlyAllBackingValues(): void
    {
        self::assertSame(
            array_map(static fn (FollowUpStatus $case): string => $case->value, FollowUpStatus::cases()),
            FollowUpStatus::values(),
        );
    }

    public function testExpectedCasesAreLocked(): void
    {
        $names = array_map(static fn (FollowUpStatus $case): string => $case->name, FollowUpStatus::cases());

        self::assertSame(['TODO', 'DONE', 'CANCELLED'], $names);
    }
}
