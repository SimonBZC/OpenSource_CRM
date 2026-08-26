<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskStatusTest extends TestCase
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
        self::assertSame($value, TaskStatus::from($value)->value);
    }

    #[DataProvider('backingValuesProvider')]
    public function testTryFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, TaskStatus::tryFrom($value)?->value);
    }

    public function testTryFromReturnsNullForUnknownValue(): void
    {
        self::assertNull(TaskStatus::tryFrom('does_not_exist'));
    }

    /**
     * @return iterable<string, array{0: TaskStatus}>
     */
    public static function casesProvider(): iterable
    {
        foreach (TaskStatus::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[DataProvider('casesProvider')]
    public function testLabelIsNotEmpty(TaskStatus $status): void
    {
        self::assertNotSame('', $status->label());
    }

    public function testValuesReturnsExactlyAllBackingValues(): void
    {
        self::assertSame(
            array_map(static fn (TaskStatus $case): string => $case->value, TaskStatus::cases()),
            TaskStatus::values(),
        );
    }

    public function testExpectedCasesAreLocked(): void
    {
        $names = array_map(static fn (TaskStatus $case): string => $case->name, TaskStatus::cases());

        self::assertSame(['TODO', 'IN_PROGRESS', 'DONE', 'CANCELLED'], $names);
    }
}
