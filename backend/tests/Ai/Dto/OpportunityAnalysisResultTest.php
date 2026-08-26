<?php

declare(strict_types=1);

namespace App\Tests\Ai\Dto;

use App\Ai\Dto\OpportunityAnalysisResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OpportunityAnalysisResultTest extends TestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::$validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * @param list<string> $technologies
     * @param list<string> $missingInformation
     */
    private function makeResult(
        ?int $score = 80,
        ?bool $relevant = true,
        array $technologies = ['PHP', 'Symfony'],
        ?int $tjmMin = 400,
        ?int $tjmMax = 600,
        ?bool $remote = true,
        ?string $location = 'Paris',
        ?string $duration = '6 mois',
        ?\DateTimeImmutable $startDate = null,
        string $reason = 'Bonne correspondance avec le profil.',
        array $missingInformation = [],
    ): OpportunityAnalysisResult {
        return new OpportunityAnalysisResult(
            score: $score,
            relevant: $relevant,
            technologies: $technologies,
            tjmMin: $tjmMin,
            tjmMax: $tjmMax,
            remote: $remote,
            location: $location,
            duration: $duration,
            startDate: $startDate,
            reason: $reason,
            missingInformation: $missingInformation,
        );
    }

    public function testValidResultProducesNoViolation(): void
    {
        $result = $this->makeResult();

        $violations = self::$validator->validate($result);

        self::assertCount(0, $violations);
    }

    public function testScoreBelowMinimumProducesViolation(): void
    {
        $result = $this->makeResult(score: -1);

        $violations = self::$validator->validate($result);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testScoreAboveMaximumProducesViolation(): void
    {
        $result = $this->makeResult(score: 101);

        $violations = self::$validator->validate($result);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testScoreNullProducesViolation(): void
    {
        $result = $this->makeResult(score: null);

        $violations = self::$validator->validate($result);

        self::assertGreaterThan(0, \count($violations));
    }

    #[DataProvider('boundaryScoreProvider')]
    public function testScoreAtBoundariesProducesNoViolation(int $score): void
    {
        $result = $this->makeResult(score: $score);

        $violations = self::$validator->validate($result);

        self::assertCount(0, $violations);
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function boundaryScoreProvider(): iterable
    {
        yield 'lower bound' => [0];
        yield 'upper bound' => [100];
    }

    public function testRelevantNullProducesViolation(): void
    {
        $result = $this->makeResult(relevant: null);

        $violations = self::$validator->validate($result);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testTjmMinGreaterThanTjmMaxProducesViolation(): void
    {
        $result = $this->makeResult(tjmMin: 700, tjmMax: 500);

        $violations = self::$validator->validate($result);

        self::assertGreaterThan(0, \count($violations));
        $messages = array_map(static fn ($v) => $v->getMessage(), iterator_to_array($violations));
        self::assertContains('Le TJM minimum doit être inférieur ou égal au TJM maximum.', $messages);
    }

    /**
     * @return iterable<string, array{0: ?int, 1: ?int}>
     */
    public static function validTjmProvider(): iterable
    {
        yield 'min equals max' => [500, 500];
        yield 'min lower than max' => [400, 600];
        yield 'min null, max set' => [null, 600];
        yield 'min set, max null' => [400, null];
        yield 'both null' => [null, null];
    }

    #[DataProvider('validTjmProvider')]
    public function testTjmProducesNoExpressionViolationWhenConsistent(?int $min, ?int $max): void
    {
        $result = $this->makeResult(tjmMin: $min, tjmMax: $max);

        $violations = self::$validator->validate($result);

        self::assertCount(0, $violations);
    }

    public function testNegativeTjmMinProducesViolation(): void
    {
        $result = $this->makeResult(tjmMin: -100, tjmMax: 600);

        $violations = self::$validator->validate($result);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testNegativeTjmMaxProducesViolation(): void
    {
        $result = $this->makeResult(tjmMin: 400, tjmMax: -1);

        $violations = self::$validator->validate($result);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testLocationTooLongProducesViolation(): void
    {
        $result = $this->makeResult(location: str_repeat('a', 256));

        $violations = self::$validator->validate($result);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testDurationTooLongProducesViolation(): void
    {
        $result = $this->makeResult(duration: str_repeat('a', 101));

        $violations = self::$validator->validate($result);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testReasonEmptyProducesViolation(): void
    {
        $result = $this->makeResult(reason: '');

        $violations = self::$validator->validate($result);

        self::assertGreaterThan(0, \count($violations));
    }
}
