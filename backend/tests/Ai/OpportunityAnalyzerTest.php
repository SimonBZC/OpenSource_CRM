<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\Dto\OpportunityAnalysisInput;
use App\Ai\Exception\InvalidAnalysisResponseException;
use App\Ai\Exception\LLMRequestFailedException;
use App\Ai\Exception\MissingApiKeyException;
use App\Ai\LLMProviderInterface;
use App\Ai\OpportunityAnalyzer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OpportunityAnalyzerTest extends TestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::$validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function makeInput(): OpportunityAnalysisInput
    {
        return new OpportunityAnalysisInput(
            title: 'Développeur Symfony senior',
            description: 'Mission longue durée sur une plateforme SaaS.',
            companyName: 'Acme Corp',
            url: 'https://example.com/mission',
            technologies: ['PHP', 'Symfony'],
            dailyRateMin: 400,
            dailyRateMax: 600,
            remote: true,
            location: 'Paris',
            duration: '6 mois',
            startDate: new \DateTimeImmutable('2026-09-01'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validLlmPayload(): array
    {
        return [
            'score' => 80,
            'relevant' => true,
            'technologies' => ['PHP', 'Symfony'],
            'tjm_min' => 400,
            'tjm_max' => 600,
            'remote' => true,
            'location' => 'Paris',
            'duration' => '6 mois',
            'start_date' => '2026-09-01',
            'reason' => 'Bonne correspondance avec le profil recherché.',
            'missing_information' => ['Nom du client final'],
        ];
    }

    public function testAnalyzeReturnsValidResultAndMapsSnakeCaseFields(): void
    {
        $provider = $this->createMock(LLMProviderInterface::class);
        $provider->expects(self::once())
            ->method('completeJson')
            ->with(self::isType('string'), self::isType('string'))
            ->willReturn($this->validLlmPayload());

        $analyzer = new OpportunityAnalyzer($provider, self::$validator);

        $result = $analyzer->analyze($this->makeInput());

        self::assertSame(80, $result->score);
        self::assertTrue($result->relevant);
        self::assertSame(['PHP', 'Symfony'], $result->technologies);
        self::assertSame(400, $result->tjmMin);
        self::assertSame(600, $result->tjmMax);
        self::assertTrue($result->remote);
        self::assertSame('Paris', $result->location);
        self::assertSame('6 mois', $result->duration);
        self::assertInstanceOf(\DateTimeImmutable::class, $result->startDate);
        self::assertSame('2026-09-01', $result->startDate->format('Y-m-d'));
        self::assertSame('Bonne correspondance avec le profil recherché.', $result->reason);
        self::assertSame(['Nom du client final'], $result->missingInformation);
    }

    public function testAnalyzeCallsProviderWithPromptContainingOpportunityDataAndNonEmptySystemPrompt(): void
    {
        $input = $this->makeInput();

        $provider = $this->createMock(LLMProviderInterface::class);
        $provider->expects(self::once())
            ->method('completeJson')
            ->with(
                self::callback(static function (string $prompt) use ($input): bool {
                    return str_contains($prompt, $input->title)
                        && str_contains($prompt, (string) $input->companyName)
                        && str_contains($prompt, (string) $input->location);
                }),
                self::callback(static fn (?string $systemPrompt): bool => null !== $systemPrompt && '' !== trim($systemPrompt)),
            )
            ->willReturn($this->validLlmPayload());

        $analyzer = new OpportunityAnalyzer($provider, self::$validator);

        $analyzer->analyze($input);
    }

    public function testAnalyzeThrowsInvalidAnalysisResponseExceptionWhenScoreOutOfBounds(): void
    {
        $payload = $this->validLlmPayload();
        $payload['score'] = 150;

        $provider = $this->createMock(LLMProviderInterface::class);
        $provider->method('completeJson')->willReturn($payload);

        $analyzer = new OpportunityAnalyzer($provider, self::$validator);

        try {
            $analyzer->analyze($this->makeInput());
            self::fail('Expected InvalidAnalysisResponseException to be thrown.');
        } catch (InvalidAnalysisResponseException $e) {
            self::assertNotNull($e->getViolations());
            self::assertGreaterThan(0, \count($e->getViolations()));
        }
    }

    public function testAnalyzeThrowsInvalidAnalysisResponseExceptionWhenTjmMinGreaterThanTjmMax(): void
    {
        $payload = $this->validLlmPayload();
        $payload['tjm_min'] = 800;
        $payload['tjm_max'] = 500;

        $provider = $this->createMock(LLMProviderInterface::class);
        $provider->method('completeJson')->willReturn($payload);

        $analyzer = new OpportunityAnalyzer($provider, self::$validator);

        try {
            $analyzer->analyze($this->makeInput());
            self::fail('Expected InvalidAnalysisResponseException to be thrown.');
        } catch (InvalidAnalysisResponseException $e) {
            self::assertNotNull($e->getViolations());
            self::assertGreaterThan(0, \count($e->getViolations()));
        }
    }

    public function testAnalyzeThrowsInvalidAnalysisResponseExceptionWhenScoreIsMissing(): void
    {
        $payload = $this->validLlmPayload();
        unset($payload['score']);

        $provider = $this->createMock(LLMProviderInterface::class);
        $provider->method('completeJson')->willReturn($payload);

        $analyzer = new OpportunityAnalyzer($provider, self::$validator);

        $this->expectException(InvalidAnalysisResponseException::class);

        $analyzer->analyze($this->makeInput());
    }

    public function testAnalyzeThrowsInvalidAnalysisResponseExceptionWhenReasonIsMissing(): void
    {
        $payload = $this->validLlmPayload();
        unset($payload['reason']);

        $provider = $this->createMock(LLMProviderInterface::class);
        $provider->method('completeJson')->willReturn($payload);

        $analyzer = new OpportunityAnalyzer($provider, self::$validator);

        $this->expectException(InvalidAnalysisResponseException::class);

        $analyzer->analyze($this->makeInput());
    }

    /**
     * Documented, currently-implemented behaviour: an invalid start_date string
     * cannot be parsed into a \DateTimeImmutable, and OpportunityAnalyzer wraps
     * the resulting parsing \Throwable into an InvalidAnalysisResponseException
     * (with no violation list, since validation is never reached).
     */
    public function testAnalyzeThrowsInvalidAnalysisResponseExceptionWhenStartDateIsNotParseable(): void
    {
        $payload = $this->validLlmPayload();
        $payload['start_date'] = 'pas-une-date';

        $provider = $this->createMock(LLMProviderInterface::class);
        $provider->method('completeJson')->willReturn($payload);

        $analyzer = new OpportunityAnalyzer($provider, self::$validator);

        try {
            $analyzer->analyze($this->makeInput());
            self::fail('Expected InvalidAnalysisResponseException to be thrown.');
        } catch (InvalidAnalysisResponseException $e) {
            self::assertNull($e->getViolations(), 'No violation list is expected: the failure happens at mapping time, before validation.');
        }
    }

    /**
     * Documented, currently-implemented behaviour: when "technologies" is not
     * an array (e.g. a string), OpportunityAnalyzer::toStringList() silently
     * coerces it to an empty array instead of failing loudly. This is tolerant
     * parsing, not strict validation - it does not raise an exception.
     */
    public function testAnalyzeCoercesNonArrayTechnologiesToEmptyArrayInsteadOfFailing(): void
    {
        $payload = $this->validLlmPayload();
        $payload['technologies'] = 'PHP, Symfony';

        $provider = $this->createMock(LLMProviderInterface::class);
        $provider->method('completeJson')->willReturn($payload);

        $analyzer = new OpportunityAnalyzer($provider, self::$validator);

        $result = $analyzer->analyze($this->makeInput());

        self::assertSame([], $result->technologies);
    }

    public function testAnalyzeLetsMissingApiKeyExceptionPropagate(): void
    {
        $provider = $this->createMock(LLMProviderInterface::class);
        $provider->method('completeJson')->willThrowException(new MissingApiKeyException('missing key'));

        $analyzer = new OpportunityAnalyzer($provider, self::$validator);

        $this->expectException(MissingApiKeyException::class);

        $analyzer->analyze($this->makeInput());
    }

    public function testAnalyzeLetsLLMRequestFailedExceptionPropagate(): void
    {
        $provider = $this->createMock(LLMProviderInterface::class);
        $provider->method('completeJson')->willThrowException(new LLMRequestFailedException('boom'));

        $analyzer = new OpportunityAnalyzer($provider, self::$validator);

        $this->expectException(LLMRequestFailedException::class);

        $analyzer->analyze($this->makeInput());
    }

    /**
     * Architectural guarantee: OpportunityAnalyzer must never write to the
     * database. It must not depend on the EntityManager or any repository -
     * checked via reflection on its constructor parameter types.
     */
    public function testAnalyzerHasNoDatabaseWritingDependency(): void
    {
        $reflection = new \ReflectionClass(OpportunityAnalyzer::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            $typeName = $type->getName();

            self::assertStringNotContainsStringIgnoringCase('EntityManager', $typeName, "Constructor parameter '{$parameter->getName()}' must not be an EntityManager dependency.");
            self::assertStringNotContainsStringIgnoringCase('ObjectManager', $typeName, "Constructor parameter '{$parameter->getName()}' must not be an ObjectManager dependency.");
            self::assertStringNotContainsStringIgnoringCase('Repository', $typeName, "Constructor parameter '{$parameter->getName()}' must not be a Repository dependency.");
        }
    }
}
