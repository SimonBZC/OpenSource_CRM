<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Dto\OpportunityAnalysisInput;
use App\Ai\Dto\OpportunityAnalysisResult;
use App\Ai\Exception\InvalidAnalysisResponseException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Analyzes an opportunity with an LLM and returns a validated, structured
 * result.
 *
 * This service is entirely self-contained and NOT wired to any HTTP
 * endpoint or API Platform operation in this phase: nothing in the
 * application calls it yet. It never writes to the database — it has no
 * dependency on the EntityManager or any repository — and never mutates
 * the Opportunity it was built from. Persisting the resulting DTO (if and
 * when the CRM decides to do so) is an explicit, separate responsibility
 * left to future/calling code, never to this class.
 */
final class OpportunityAnalyzer
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are an assistant that analyzes freelance mission opportunities for a
        freelance developer. Given the opportunity details, you must return a
        single JSON object (no markdown, no commentary) with exactly these keys:
        {
          "score": <integer 0-100, relevance/quality score>,
          "relevant": <boolean, whether this opportunity is worth pursuing>,
          "technologies": [<string>, ...],
          "tjm_min": <integer or null, minimum daily rate>,
          "tjm_max": <integer or null, maximum daily rate>,
          "remote": <boolean>,
          "location": <string or null>,
          "duration": <string or null>,
          "start_date": <string "YYYY-MM-DD" or null>,
          "reason": <string explaining the score>,
          "missing_information": [<string>, ...]
        }
        PROMPT;

    public function __construct(
        private readonly LLMProviderInterface $llmProvider,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws InvalidAnalysisResponseException when the LLM response cannot be
     *                                            turned into a valid result
     * @throws \App\Ai\Exception\LLMProviderException when the LLM call itself fails
     *                                                  (e.g. missing API key, transport error)
     */
    public function analyze(OpportunityAnalysisInput $input): OpportunityAnalysisResult
    {
        $prompt = $this->buildPrompt($input);

        $data = $this->llmProvider->completeJson($prompt, self::SYSTEM_PROMPT);

        $result = $this->mapToResult($data);

        $violations = $this->validator->validate($result);

        if (\count($violations) > 0) {
            throw new InvalidAnalysisResponseException(
                'The LLM analysis response failed validation: '.(string) $violations,
                $violations,
            );
        }

        return $result;
    }

    private function buildPrompt(OpportunityAnalysisInput $input): string
    {
        $lines = [
            'Title: '.$input->title,
            'Company: '.($input->companyName ?? 'unknown'),
            'Description: '.($input->description ?? 'none'),
            'URL: '.($input->url ?? 'none'),
            'Known technologies: '.(empty($input->technologies) ? 'none' : implode(', ', $input->technologies)),
            'Daily rate range: '.($input->dailyRateMin ?? '?').' - '.($input->dailyRateMax ?? '?'),
            'Remote: '.($input->remote ? 'yes' : 'no'),
            'Location: '.($input->location ?? 'unknown'),
            'Duration: '.($input->duration ?? 'unknown'),
            'Start date: '.($input->startDate?->format('Y-m-d') ?? 'unknown'),
        ];

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapToResult(array $data): OpportunityAnalysisResult
    {
        try {
            $startDate = null;
            $rawStartDate = $data['start_date'] ?? null;
            if (\is_string($rawStartDate) && '' !== $rawStartDate) {
                $startDate = new \DateTimeImmutable($rawStartDate);
            }

            return new OpportunityAnalysisResult(
                score: $this->toIntOrNull($data['score'] ?? null),
                relevant: $this->toBoolOrNull($data['relevant'] ?? null),
                technologies: $this->toStringList($data['technologies'] ?? []),
                tjmMin: $this->toIntOrNull($data['tjm_min'] ?? null),
                tjmMax: $this->toIntOrNull($data['tjm_max'] ?? null),
                remote: $this->toBoolOrNull($data['remote'] ?? null),
                location: $this->toStringOrNull($data['location'] ?? null),
                duration: $this->toStringOrNull($data['duration'] ?? null),
                startDate: $startDate,
                reason: (string) ($data['reason'] ?? ''),
                missingInformation: $this->toStringList($data['missing_information'] ?? []),
            );
        } catch (\Throwable $e) {
            throw new InvalidAnalysisResponseException(
                'Could not map the LLM response into an analysis result: '.$e->getMessage(),
                null,
                $e,
            );
        }
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }

        if (\is_int($value)) {
            return $value;
        }

        if (\is_float($value) || (\is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return null;
    }

    private function toBoolOrNull(mixed $value): ?bool
    {
        return \is_bool($value) ? $value : null;
    }

    private function toStringOrNull(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function toStringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => \is_string($item)));
    }
}
