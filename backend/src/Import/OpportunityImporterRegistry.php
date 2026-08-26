<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\OpportunitySource;
use App\Import\Exception\UnsupportedImportSourceException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Résout l'App\Import\OpportunityImporterInterface à utiliser pour une
 * App\Enum\OpportunitySource donnée, parmi les importeurs collectés
 * automatiquement via le tag `app.opportunity_importer` (même pattern que
 * App\Automation\DomainEventBus pour les règles d'automatisation).
 */
final class OpportunityImporterRegistry
{
    /**
     * @var array<string, OpportunityImporterInterface>
     */
    private readonly array $importersBySource;

    /**
     * @param iterable<OpportunityImporterInterface> $importers
     */
    public function __construct(
        #[TaggedIterator('app.opportunity_importer')]
        iterable $importers,
    ) {
        $indexed = [];
        foreach ($importers as $importer) {
            $indexed[$importer->supports()->value] = $importer;
        }

        $this->importersBySource = $indexed;
    }

    public function get(OpportunitySource $source): OpportunityImporterInterface
    {
        return $this->importersBySource[$source->value]
            ?? throw new UnsupportedImportSourceException(sprintf(
                "Aucun importeur disponible pour la source « %s ». Sources disponibles : %s.",
                $source->value,
                implode(', ', $this->getSupportedSources()) ?: '(aucune)',
            ));
    }

    /**
     * @return list<string>
     */
    public function getSupportedSources(): array
    {
        return array_keys($this->importersBySource);
    }
}
