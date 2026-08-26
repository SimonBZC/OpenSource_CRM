<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\InteractionType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InteractionTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function backingValuesProvider(): iterable
    {
        yield 'email' => ['email'];
        yield 'phone' => ['phone'];
        yield 'linkedin' => ['linkedin'];
        yield 'meeting' => ['meeting'];
        yield 'note' => ['note'];
        yield 'other' => ['other'];
    }

    #[DataProvider('backingValuesProvider')]
    public function testFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, InteractionType::from($value)->value);
    }

    #[DataProvider('backingValuesProvider')]
    public function testTryFromResolvesEachBackingValue(string $value): void
    {
        self::assertSame($value, InteractionType::tryFrom($value)?->value);
    }

    public function testTryFromReturnsNullForUnknownValue(): void
    {
        self::assertNull(InteractionType::tryFrom('does_not_exist'));
    }

    /**
     * @return iterable<string, array{0: InteractionType}>
     */
    public static function casesProvider(): iterable
    {
        foreach (InteractionType::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[DataProvider('casesProvider')]
    public function testLabelIsNotEmpty(InteractionType $type): void
    {
        self::assertNotSame('', $type->label());
    }

    public function testValuesReturnsExactlyAllBackingValues(): void
    {
        self::assertSame(
            array_map(static fn (InteractionType $case): string => $case->value, InteractionType::cases()),
            InteractionType::values(),
        );
    }

    public function testExpectedCasesAreLocked(): void
    {
        $names = array_map(static fn (InteractionType $case): string => $case->name, InteractionType::cases());

        self::assertSame(['EMAIL', 'PHONE', 'LINKEDIN', 'MEETING', 'NOTE', 'OTHER'], $names);
    }
}
