<?php

declare(strict_types=1);

namespace App\Automation\Event;

use App\Ai\Dto\OpportunityAnalysisResult;
use App\Entity\Opportunity;

/**
 * Événement applicatif émis après qu'une analyse IA a été appliquée avec
 * succès et persistée sur une Opportunity (App\Controller\OpportunityAnalysisController).
 */
final class OpportunityAnalyzed
{
    /**
     * @param list<string> $appliedFields noms des champs de l'Opportunity
     *                                     effectivement écrits par
     *                                     App\Ai\OpportunityAnalysisApplier
     */
    public function __construct(
        public readonly Opportunity $opportunity,
        public readonly OpportunityAnalysisResult $analysis,
        public readonly array $appliedFields,
    ) {
    }
}
