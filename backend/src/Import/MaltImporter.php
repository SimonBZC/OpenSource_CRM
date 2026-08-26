<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\OpportunitySource;
use App\Import\Exception\NotImplementedImporterException;

/**
 * Placeholder STRICT : le cahier des charges de la Phase 9 exclut
 * explicitement toute automatisation agressive de Malt (pas de scraping,
 * pas d'appel réseau, pas de dépendance à un SDK Malt).
 *
 * Cette classe ne fait donc RIEN d'autre que déclarer la source supportée
 * et lever immédiatement une exception à l'appel d'import(). Aucune
 * dépendance injectée, aucun accès base, aucun HttpClient, aucun log.
 * L'architecture d'import (App\Import\OpportunityImporterRegistry) est
 * prête à accueillir une vraie implémentation le jour où une intégration
 * API/scraping sera explicitement décidée et cadrée.
 */
final class MaltImporter implements OpportunityImporterInterface
{
    public function supports(): OpportunitySource
    {
        return OpportunitySource::MALT;
    }

    public function import(RawOpportunityData $data): ImportResult
    {
        throw new NotImplementedImporterException(
            'Import depuis Malt non implémenté : nécessite une intégration API/scraping non prévue dans ce MVP.',
        );
    }
}
