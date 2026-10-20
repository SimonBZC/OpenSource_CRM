<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Dto\OpportunityAnalysisInput;
use App\Entity\Opportunity;

/**
 * Builds an App\Ai\Dto\OpportunityAnalysisInput from an App\Entity\Opportunity.
 *
 * This mapping is deliberately kept out of the Opportunity entity itself:
 * the business domain (src/Entity) must not know anything about the AI
 * layer. It is the AI layer's responsibility to know how to read an
 * Opportunity, not the other way around.
 */
final class OpportunityAnalysisInputFactory
{
    public static function fromOpportunity(Opportunity $opportunity): OpportunityAnalysisInput
    {
        return new OpportunityAnalysisInput(
            title: $opportunity->getTitle(),
            description: $opportunity->getDescription(),
            companyName: $opportunity->getCompanyName(),
            technologies: $opportunity->getTechnologies(),
            dailyRate: $opportunity->getDailyRate(),
            remote: $opportunity->isRemote(),
            location: $opportunity->getLocation(),
            duration: $opportunity->getDuration(),
            startDate: $opportunity->getStartDate(),
        );
    }
}
