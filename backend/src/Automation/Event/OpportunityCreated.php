<?php

declare(strict_types=1);

namespace App\Automation\Event;

use App\Entity\Opportunity;

/**
 * Événement applicatif émis après la persistance réussie d'une nouvelle
 * Opportunity (voir App\Automation\State\OpportunityCreateProcessor).
 *
 * Aucune règle d'automatisation n'écoute cet événement dans cette phase :
 * il existe uniquement pour l'extensibilité future (ex. Phase 9 : "nouvelle
 * opportunité d'une source -> analyse IA automatique"). Ne PAS en déduire un
 * comportement automatique non demandé.
 */
final class OpportunityCreated
{
    public function __construct(
        public readonly Opportunity $opportunity,
    ) {
    }
}
