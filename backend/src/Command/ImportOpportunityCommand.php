<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\OpportunitySource;
use App\Import\Exception\NotImplementedImporterException;
use App\Import\Exception\UnsupportedImportSourceException;
use App\Import\ImportResult;
use App\Import\ImportStatus;
use App\Import\OpportunityImporterRegistry;
use App\Import\RawOpportunityData;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Point d'entrée unique pour importer une opportunité depuis une source
 * externe standardisée (voir App\Import\*). Volontairement une commande
 * console et NON un endpoint API : on n'élargit pas la surface publique de
 * l'API pour ce MVP (décision d'orchestration).
 *
 * Ergonomie des données brutes (JSON, clés en snake_case ou camelCase, voir
 * App\Import\RawOpportunityData::fromArray) :
 *   - `--data='{"title":"...","url":"..."}'` pour un JSON inline ;
 *   - `--file=chemin.json` pour lire le JSON depuis un fichier.
 * Si les deux sont fournies, `--data` est prioritaire. Si aucune n'est
 * fournie, un objet vide est utilisé (utile uniquement pour tester le
 * routage vers un importeur, ex. LinkedIn/Malt qui échouent avant de lire
 * les données).
 *
 * Codes de sortie :
 *   - 0 (SUCCESS) : import CREATED ;
 *   - 1 (FAILURE) : DUPLICATE, VALIDATION_ERROR, importeur non implémenté
 *     (LinkedIn/Malt), ou erreur d'entrée (JSON invalide, fichier
 *     introuvable) ;
 *   - 2 (INVALID) : source inconnue de l'enum OpportunitySource, ou source
 *     connue mais sans importeur enregistré.
 */
#[AsCommand(
    name: 'app:import:opportunity',
    description: "Importe une opportunité depuis une source externe standardisée (--data ou --file en JSON).",
)]
final class ImportOpportunityCommand extends Command
{
    public function __construct(
        private readonly OpportunityImporterRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                sprintf('Source de l\'opportunité (%s).', implode(', ', OpportunitySource::values())),
            )
            ->addOption(
                'data',
                null,
                InputOption::VALUE_REQUIRED,
                'Données brutes au format JSON, ex. \'{"title":"...","url":"..."}\'.',
            )
            ->addOption(
                'file',
                null,
                InputOption::VALUE_REQUIRED,
                'Chemin vers un fichier JSON contenant les données brutes (ignoré si --data est fourni).',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sourceValue = (string) $input->getArgument('source');
        $source = OpportunitySource::tryFrom($sourceValue);

        if (null === $source) {
            $io->error(sprintf(
                'Source inconnue : "%s". Sources valides : %s.',
                $sourceValue,
                implode(', ', OpportunitySource::values()),
            ));

            return Command::INVALID;
        }

        $payload = $this->resolvePayload($input, $io);
        if (null === $payload) {
            return Command::FAILURE;
        }

        try {
            $importer = $this->registry->get($source);
        } catch (UnsupportedImportSourceException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $rawData = RawOpportunityData::fromArray($payload);

        try {
            $result = $importer->import($rawData);
        } catch (NotImplementedImporterException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return match ($result->getStatus()) {
            ImportStatus::CREATED => $this->reportCreated($io, $result),
            ImportStatus::DUPLICATE => $this->reportDuplicate($io, $result),
            ImportStatus::VALIDATION_ERROR => $this->reportValidationError($io, $result),
            ImportStatus::NOT_IMPLEMENTED => $this->reportNotImplemented($io, $result),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePayload(InputInterface $input, SymfonyStyle $io): ?array
    {
        $dataOption = $input->getOption('data');
        $fileOption = $input->getOption('file');

        $json = null;

        if (null !== $dataOption) {
            $json = $dataOption;
        } elseif (null !== $fileOption) {
            if (!is_file($fileOption) || !is_readable($fileOption)) {
                $io->error(sprintf('Fichier introuvable ou illisible : "%s".', $fileOption));

                return null;
            }

            $json = file_get_contents($fileOption);
            if (false === $json) {
                $io->error(sprintf('Impossible de lire le fichier : "%s".', $fileOption));

                return null;
            }
        }

        if (null === $json) {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $io->error(sprintf('JSON invalide : %s', $e->getMessage()));

            return null;
        }

        if (!\is_array($decoded)) {
            $io->error('Le JSON fourni doit être un objet (clé/valeur).');

            return null;
        }

        return $decoded;
    }

    private function reportCreated(SymfonyStyle $io, ImportResult $result): int
    {
        $io->success(sprintf('Opportunité créée (id: %d).', $result->getOpportunity()?->getId()));

        return Command::SUCCESS;
    }

    private function reportDuplicate(SymfonyStyle $io, ImportResult $result): int
    {
        $io->warning(sprintf(
            'Doublon détecté : une opportunité existante correspond déjà (id: %d). Rien n\'a été créé.',
            $result->getOpportunity()?->getId(),
        ));

        return Command::FAILURE;
    }

    private function reportValidationError(SymfonyStyle $io, ImportResult $result): int
    {
        $io->error('Les données ne sont pas valides, rien n\'a été créé :');
        $io->listing($result->getMessages());

        return Command::FAILURE;
    }

    private function reportNotImplemented(SymfonyStyle $io, ImportResult $result): int
    {
        $io->error($result->getMessages()[0] ?? 'Import non implémenté pour cette source.');

        return Command::FAILURE;
    }
}
