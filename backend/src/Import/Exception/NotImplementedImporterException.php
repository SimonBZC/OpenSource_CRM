<?php

declare(strict_types=1);

namespace App\Import\Exception;

/**
 * Levée par un importeur placeholder (ex. App\Import\LinkedInImporter,
 * App\Import\MaltImporter) dont la source est reconnue par l'application
 * mais dont l'import réel n'est volontairement pas implémenté (pas
 * d'automatisation agressive / scraping / SDK tiers dans ce MVP).
 */
final class NotImplementedImporterException extends \RuntimeException
{
}
