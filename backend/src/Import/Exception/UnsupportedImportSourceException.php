<?php

declare(strict_types=1);

namespace App\Import\Exception;

/**
 * Levée par App\Import\OpportunityImporterRegistry lorsqu'aucun importeur
 * n'est enregistré pour la source demandée.
 */
final class UnsupportedImportSourceException extends \RuntimeException
{
}
