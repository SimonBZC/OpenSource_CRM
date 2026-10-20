<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\AlertPriority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AlertPriorityTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function backingValuesProvider(): iterable
    {
        yield 'low' => ['low'];
        yield 'medium' => ['medium'];
        yield 'high' => ['high'];
        yield 'urgent' => ['urgent'];
    }

    #[DataProvider('backingValuesProvider')]
    public function testFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, AlertPriority::from($value)->value);
    }

    #[DataProvider('backingValuesProvider')]
    public function testTryFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, AlertPriority::tryFrom($value)?->value);
    }

    public function testTryFromReturnsNullForUnknownValue(): void
    {
        self::assertNull(AlertPriority::tryFrom('does_not_exist'));
    }

    /**
     * @return iterable<string, array{0: AlertPriority}>
     */
    public static function casesProvider(): iterable
    {
        foreach (AlertPriority::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[DataProvider('casesProvider')]
    public function testLabelIsNotEmpty(AlertPriority $priority): void
    {
        self::assertNotSame('', $priority->label());
    }

    public function testValuesReturnsExactlyAllBackingValues(): void
    {
        self::assertSame(
            array_map(static fn (AlertPriority $case): string => $case->value, AlertPriority::cases()),
            AlertPriority::values(),
        );
    }

    public function testExpectedCasesAreLocked(): void
    {
        $names = array_map(static fn (AlertPriority $case): string => $case->name, AlertPriority::cases());

        self::assertSame(['LOW', 'MEDIUM', 'HIGH', 'URGENT'], $names);
    }
}
