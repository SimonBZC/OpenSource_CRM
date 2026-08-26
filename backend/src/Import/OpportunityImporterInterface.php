<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\OpportunitySource;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contrat d'un importeur d'opportunités pour une source externe donnée.
 *
 * Ajouter une nouvelle source = créer une nouvelle classe qui implémente
 * cette interface (elle est automatiquement collectée par
 * App\Import\OpportunityImporterRegistry grâce au tag ci-dessous, même
 * pattern que App\Automation\AutomationRuleInterface).
 */
#[AutoconfigureTag('app.opportunity_importer')]
interface OpportunityImporterInterface
{
    /**
     * Source d'opportunité gérée par cet importeur.
     */
    public function supports(): OpportunitySource;

    /**
     * Importe une annonce brute standardisée. Ne doit jamais lever
     * d'exception pour un cas métier "normal" (doublon, validation) — ces
     * cas sont représentés dans le App\Import\ImportResult retourné. Seule
     * une source volontairement non implémentée (voir
     * App\Import\Exception\NotImplementedImporterException) peut lever une
     * exception.
     */
    public function import(RawOpportunityData $data): ImportResult;
}
