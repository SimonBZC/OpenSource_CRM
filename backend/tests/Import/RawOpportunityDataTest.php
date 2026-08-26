<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Import\RawOpportunityData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de App\Import\RawOpportunityData.
 *
 * Le DTO lui-même ne fait aucune validation métier (voir son doc-comment) :
 * ces tests couvrent uniquement le comportement RÉEL de la fabrique
 * tolérante `fromArray()` (défauts, snake_case/camelCase, coercions).
 */
final class RawOpportunityDataTest extends TestCase
{
    public function testFromArrayWithEmptyArrayUsesAllDefaults(): void
    {
        $data = RawOpportunityData::fromArray([]);

        self::assertSame('', $data->title);
        self::assertNull($data->description);
        self::assertNull($data->url);
        self::assertNull($data->companyName);
        self::assertNull($data->dailyRateMin);
        self::assertNull($data->dailyRateMax);
        self::assertFalse($data->remote);
        self::assertNull($data->location);
        self::assertSame([], $data->technologies);
        self::assertNull($data->postedAt);
    }

    public function testFromArrayAcceptsSnakeCaseKeys(): void
    {
        $data = RawOpportunityData::fromArray([
            'title' => 'Développeur Symfony',
            'description' => 'Mission longue durée',
            'url' => 'https://example.com/job/42',
            'company_name' => 'Acme Corp',
            'daily_rate_min' => 400,
            'daily_rate_max' => 600,
            'remote' => true,
            'location' => 'Paris',
            'technologies' => ['PHP', 'Symfony'],
            'posted_at' => '2026-08-01',
        ]);

        self::assertSame('Développeur Symfony', $data->title);
        self::assertSame('Mission longue durée', $data->description);
        self::assertSame('https://example.com/job/42', $data->url);
        self::assertSame('Acme Corp', $data->companyName);
        self::assertSame(400, $data->dailyRateMin);
        self::assertSame(600, $data->dailyRateMax);
        self::assertTrue($data->remote);
        self::assertSame('Paris', $data->location);
        self::assertSame(['PHP', 'Symfony'], $data->technologies);
        self::assertInstanceOf(\DateTimeImmutable::class, $data->postedAt);
        self::assertSame('2026-08-01', $data->postedAt->format('Y-m-d'));
    }

    public function testFromArrayAcceptsCamelCaseKeys(): void
    {
        $data = RawOpportunityData::fromArray([
            'title' => 'Développeur Symfony',
            'companyName' => 'Acme Corp',
            'dailyRateMin' => 400,
            'dailyRateMax' => 600,
            'postedAt' => '2026-08-01',
        ]);

        self::assertSame('Acme Corp', $data->companyName);
        self::assertSame(400, $data->dailyRateMin);
        self::assertSame(600, $data->dailyRateMax);
        self::assertInstanceOf(\DateTimeImmutable::class, $data->postedAt);
    }

    public function testSnakeCaseTakesPrecedenceOverCamelCaseWhenBothArePresent(): void
    {
        $data = RawOpportunityData::fromArray([
            'company_name' => 'Snake Corp',
            'companyName' => 'Camel Corp',
            'daily_rate_min' => 100,
            'dailyRateMin' => 200,
            'posted_at' => '2026-01-01',
            'postedAt' => '2026-02-02',
        ]);

        self::assertSame('Snake Corp', $data->companyName);
        self::assertSame(100, $data->dailyRateMin);
        self::assertSame('2026-01-01', $data->postedAt?->format('Y-m-d'));
    }

    public function testFromArrayWithNonStringTitleFallsBackToDefault(): void
    {
        $data = RawOpportunityData::fromArray(['title' => 123]);

        self::assertSame('', $data->title);
    }

    public function testFromArrayWithEmptyStringOptionalFieldsBecomesNull(): void
    {
        $data = RawOpportunityData::fromArray([
            'description' => '',
            'url' => '',
            'company_name' => '',
            'location' => '',
        ]);

        self::assertNull($data->description);
        self::assertNull($data->url);
        self::assertNull($data->companyName);
        self::assertNull($data->location);
    }

    public function testFromArrayWithNonStringOptionalFieldsBecomesNull(): void
    {
        $data = RawOpportunityData::fromArray([
            'description' => 42,
            'url' => ['not', 'a', 'string'],
        ]);

        self::assertNull($data->description);
        self::assertNull($data->url);
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function dailyRateProvider(): array
    {
        return [
            'integer' => [400, 400],
            'float' => [400.9, 400],
            'numeric string' => ['400', 400],
        ];
    }

    #[DataProvider('dailyRateProvider')]
    public function testFromArrayCoercesDailyRateToInt(mixed $rawValue, int $expected): void
    {
        $data = RawOpportunityData::fromArray(['daily_rate_min' => $rawValue]);

        self::assertSame($expected, $data->dailyRateMin);
    }

    public function testFromArrayWithNonNumericStringDailyRateReturnsNull(): void
    {
        $data = RawOpportunityData::fromArray(['daily_rate_min' => 'not-a-number']);

        self::assertNull($data->dailyRateMin);
    }

    public function testFromArrayWithEmptyStringDailyRateReturnsNull(): void
    {
        $data = RawOpportunityData::fromArray(['daily_rate_min' => '']);

        self::assertNull($data->dailyRateMin);
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function remoteProvider(): array
    {
        return [
            'bool true' => [true, true],
            'bool false' => [false, false],
            'string "1"' => ['1', true],
            'string "true"' => ['true', true],
            'string "TRUE" (case-insensitive)' => ['TRUE', true],
            'string "yes"' => ['yes', true],
            'string "oui"' => ['oui', true],
            'string "no"' => ['no', false],
            'string "0"' => ['0', false],
            'random string' => ['maybe', false],
            'int 1' => [1, true],
            'int 0' => [0, false],
        ];
    }

    #[DataProvider('remoteProvider')]
    public function testFromArrayCoercesRemoteToBool(mixed $rawValue, bool $expected): void
    {
        $data = RawOpportunityData::fromArray(['remote' => $rawValue]);

        self::assertSame($expected, $data->remote);
    }

    public function testFromArrayFiltersOutNonStringAndEmptyTechnologies(): void
    {
        $data = RawOpportunityData::fromArray([
            'technologies' => ['PHP', '', 42, null, 'Symfony', false],
        ]);

        self::assertSame(['PHP', 'Symfony'], $data->technologies);
    }

    public function testFromArrayWithNonArrayTechnologiesReturnsEmptyArray(): void
    {
        $data = RawOpportunityData::fromArray(['technologies' => 'PHP, Symfony']);

        self::assertSame([], $data->technologies);
    }

    public function testFromArrayWithInvalidPostedAtStringReturnsNull(): void
    {
        $data = RawOpportunityData::fromArray(['posted_at' => 'not-a-date']);

        self::assertNull($data->postedAt);
    }

    public function testFromArrayWithNonStringPostedAtReturnsNull(): void
    {
        $data = RawOpportunityData::fromArray(['posted_at' => 12345]);

        self::assertNull($data->postedAt);
    }

    public function testFromArrayWithEmptyStringPostedAtReturnsNull(): void
    {
        $data = RawOpportunityData::fromArray(['posted_at' => '']);

        self::assertNull($data->postedAt);
    }

    public function testFromArrayAcceptsRelativeDateStrings(): void
    {
        $data = RawOpportunityData::fromArray(['posted_at' => '2026-08-20 10:00:00']);

        self::assertInstanceOf(\DateTimeImmutable::class, $data->postedAt);
        self::assertSame('2026-08-20 10:00:00', $data->postedAt->format('Y-m-d H:i:s'));
    }
}
