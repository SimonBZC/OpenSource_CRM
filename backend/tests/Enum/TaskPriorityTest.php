<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\TaskPriority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskPriorityTest extends TestCase
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
        self::assertSame($value, TaskPriority::from($value)->value);
    }

    #[DataProvider('backingValuesProvider')]
    public function testTryFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, TaskPriority::tryFrom($value)?->value);
    }

    public function testTryFromReturnsNullForUnknownValue(): void
    {
        self::assertNull(TaskPriority::tryFrom('does_not_exist'));
    }

    /**
     * @return iterable<string, array{0: TaskPriority}>
     */
    public static function casesProvider(): iterable
    {
        foreach (TaskPriority::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[DataProvider('casesProvider')]
    public function testLabelIsNotEmpty(TaskPriority $priority): void
    {
        self::assertNotSame('', $priority->label());
    }

    public function testValuesReturnsExactlyAllBackingValues(): void
    {
        self::assertSame(
            array_map(static fn (TaskPriority $case): string => $case->value, TaskPriority::cases()),
            TaskPriority::values(),
        );
    }

    public function testExpectedCasesAreLocked(): void
    {
        $names = array_map(static fn (TaskPriority $case): string => $case->name, TaskPriority::cases());

        self::assertSame(['LOW', 'MEDIUM', 'HIGH', 'URGENT'], $names);
    }
}
