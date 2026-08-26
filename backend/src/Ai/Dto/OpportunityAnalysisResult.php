<?php

declare(strict_types=1);

namespace App\Ai\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Typed, immutable representation of the structured output expected from
 * an LLM analysis of an opportunity.
 *
 * This DTO deliberately does NOT depend on any Doctrine entity: it is
 * produced by App\Ai\OpportunityAnalyzer from raw LLM output (validated via
 * Symfony Validator) and is the only thing the AI layer ever returns to
 * its caller. Mapping this DTO onto an App\Entity\Opportunity (setters,
 * persist, flush...) is an explicit, separate step performed by the
 * calling code — never by the AI layer itself.
 *
 * Properties are camelCase; the mapping from the LLM's snake_case JSON
 * keys (score, relevant, technologies, tjm_min, tjm_max, remote, location,
 * duration, start_date, reason, missing_information) happens in
 * App\Ai\OpportunityAnalyzer at parse time.
 */
#[Assert\Expression(
    expression: 'this.tjmMin === null or this.tjmMax === null or this.tjmMin <= this.tjmMax',
    message: 'Le TJM minimum doit être inférieur ou égal au TJM maximum.',
)]
final class OpportunityAnalysisResult
{
    /**
     * @param list<string> $technologies
     * @param list<string> $missingInformation
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Type('integer')]
        #[Assert\Range(min: 0, max: 100)]
        public readonly ?int $score,

        #[Assert\NotNull]
        #[Assert\Type('bool')]
        public readonly ?bool $relevant,

        #[Assert\All([new Assert\Type('string')])]
        public readonly array $technologies,

        #[Assert\Type('integer')]
        #[Assert\PositiveOrZero]
        public readonly ?int $tjmMin,

        #[Assert\Type('integer')]
        #[Assert\PositiveOrZero]
        public readonly ?int $tjmMax,

        #[Assert\Type('bool')]
        public readonly ?bool $remote,

        #[Assert\Length(max: 255)]
        public readonly ?string $location,

        #[Assert\Length(max: 100)]
        public readonly ?string $duration,

        public readonly ?\DateTimeImmutable $startDate,

        #[Assert\NotBlank]
        public readonly string $reason,

        #[Assert\All([new Assert\Type('string')])]
        public readonly array $missingInformation,
    ) {
    }
}
