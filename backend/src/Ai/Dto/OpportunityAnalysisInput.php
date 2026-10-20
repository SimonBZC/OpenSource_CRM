<?php

declare(strict_types=1);

namespace App\Ai\Dto;

/**
 * Plain, framework-agnostic snapshot of the opportunity data needed to
 * build an LLM analysis prompt.
 *
 * This DTO exists so that App\Ai\OpportunityAnalyzer never depends
 * directly on App\Entity\Opportunity: the AI layer only manipulates its
 * own DTOs, which keeps it free of any Doctrine coupling and makes it
 * trivial to unit-test (no entity/ORM setup required to build an input).
 *
 * Built from an Opportunity via App\Ai\OpportunityAnalysisInputFactory.
 */
final class OpportunityAnalysisInput
{
    /**
     * @param list<string> $technologies
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $companyName,
        public readonly array $technologies,
        public readonly ?int $dailyRate,
        public readonly bool $remote,
        public readonly ?string $location,
        public readonly ?string $duration,
        public readonly ?\DateTimeImmutable $startDate,
    ) {
    }
}
