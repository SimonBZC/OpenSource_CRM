<?php

declare(strict_types=1);

namespace App\Import;

use App\Automation\DomainEventBus;
use App\Automation\Event\OpportunityCreated;
use App\Entity\Company;
use App\Entity\Opportunity;
use App\Enum\OpportunitySource;
use App\Repository\CompanyRepository;
use App\Repository\OpportunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Importeur pour la source OpportunitySource::MANUAL : seul importeur
 * réellement fonctionnel de la Phase 9 (LinkedIn/Malt restent des
 * placeholders, voir App\Import\LinkedInImporter / App\Import\MaltImporter).
 *
 * Détection de doublon (règle arbitrée par l'orchestrateur) :
 *   1. si `url` est renseignée et qu'une Opportunity existe déjà avec cette
 *      même url -> DUPLICATE ;
 *   2. sinon, si une Opportunity existe avec le même `title` ET la même
 *      Company (même nom, ou toutes deux sans company) -> DUPLICATE.
 * Dans les deux cas, rien n'est créé (ni Opportunity, ni Company orpheline).
 *
 * Ordre des opérations volontaire pour éviter tout effet de bord en cas
 * d'échec : la Company est résolue/créée en mémoire uniquement (pas de
 * persist), la détection de doublon et la validation de l'entité sont
 * effectuées AVANT toute persistance. La Company n'est persistée qu'au
 * moment du flush final de l'Opportunity, jamais avant.
 */
final class ManualImporter implements OpportunityImporterInterface
{
    public function __construct(
        private readonly CompanyRepository $companyRepository,
        private readonly OpportunityRepository $opportunityRepository,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly DomainEventBus $eventBus,
    ) {
    }

    public function supports(): OpportunitySource
    {
        return OpportunitySource::MANUAL;
    }

    public function import(RawOpportunityData $data): ImportResult
    {
        $company = $this->resolveCompany($data->companyName);

        $duplicate = $this->findDuplicate($data, $company);
        if (null !== $duplicate) {
            return ImportResult::duplicate($duplicate);
        }

        $opportunity = $this->mapToOpportunity($data, $company);

        $violations = $this->validator->validate($opportunity);
        if (\count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }

            // Rien n'a été persisté : ni l'Opportunity, ni une éventuelle
            // Company nouvellement instanciée (elle n'a jamais été
            // persist()ée, donc rien à annuler).
            return ImportResult::validationError($messages);
        }

        if (null !== $company && null === $company->getId()) {
            $this->entityManager->persist($company);
        }

        $this->entityManager->persist($opportunity);
        $this->entityManager->flush();

        $this->eventBus->dispatch(new OpportunityCreated($opportunity));

        return ImportResult::created($opportunity);
    }

    private function resolveCompany(?string $companyName): ?Company
    {
        if (null === $companyName || '' === trim($companyName)) {
            return null;
        }

        $existing = $this->companyRepository->findOneByNameIgnoreCase($companyName);
        if (null !== $existing) {
            return $existing;
        }

        $company = new Company();
        $company->setName($companyName);
        $company->setSource(OpportunitySource::MANUAL);

        return $company;
    }

    private function findDuplicate(RawOpportunityData $data, ?Company $company): ?Opportunity
    {
        if (null !== $data->url && '' !== trim($data->url)) {
            $byUrl = $this->opportunityRepository->findOneByUrl($data->url);
            if (null !== $byUrl) {
                return $byUrl;
            }
        }

        // Une Company nouvellement instanciée (pas encore persistée) ne
        // peut par définition être partagée par aucune Opportunity
        // existante : inutile d'interroger la base dans ce cas.
        if (null !== $company && null === $company->getId()) {
            return null;
        }

        return $this->opportunityRepository->findOneByTitleAndCompany($data->title, $company);
    }

    private function mapToOpportunity(RawOpportunityData $data, ?Company $company): Opportunity
    {
        $opportunity = new Opportunity();
        $opportunity->setTitle($data->title);
        $opportunity->setDescription($data->description);
        $opportunity->setCompany($company);
        $opportunity->setSource(OpportunitySource::MANUAL);
        $opportunity->setUrl($data->url);
        $opportunity->setDailyRateMin($data->dailyRateMin);
        $opportunity->setDailyRateMax($data->dailyRateMax);
        $opportunity->setRemote($data->remote);
        $opportunity->setLocation($data->location);
        $opportunity->setTechnologies($data->technologies);

        return $opportunity;
    }
}
