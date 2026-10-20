<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\AlertStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AlertStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function backingValuesProvider(): iterable
    {
        yield 'todo' => ['todo'];
        yield 'in_progress' => ['in_progress'];
        yield 'done' => ['done'];
        yield 'cancelled' => ['cancelled'];
    }

    #[DataProvider('backingValuesProvider')]
    public function testFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, AlertStatus::from($value)->value);
    }

    #[DataProvider('backingValuesProvider')]
    public function testTryFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, AlertStatus::tryFrom($value)?->value);
    }

    public function testTryFromReturnsNullForUnknownValue(): void
    {
        self::assertNull(AlertStatus::tryFrom('does_not_exist'));
    }

    /**
     * @return iterable<string, array{0: AlertStatus}>
     */
    public static function casesProvider(): iterable
    {
        foreach (AlertStatus::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[DataProvider('casesProvider')]
    public function testLabelIsNotEmpty(AlertStatus $status): void
    {
        self::assertNotSame('', $status->label());
    }

    public function testValuesReturnsExactlyAllBackingValues(): void
    {
        self::assertSame(
            array_map(static fn (AlertStatus $case): string => $case->value, AlertStatus::cases()),
            AlertStatus::values(),
        );
    }

    public function testExpectedCasesAreLocked(): void
    {
        $names = array_map(static fn (AlertStatus $case): string => $case->name, AlertStatus::cases());

        self::assertSame(['TODO', 'IN_PROGRESS', 'DONE', 'CANCELLED'], $names);
    }
}
