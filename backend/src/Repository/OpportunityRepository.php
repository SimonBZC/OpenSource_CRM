<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Opportunity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Opportunity>
 */
class OpportunityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Opportunity::class);
    }

    /**
     * Recherche une Opportunity existante portant exactement ce titre et
     * rattachée à la Company donnée (ou sans Company si $company est null).
     *
     * Utilisé par App\Import\ManualImporter pour la détection de doublon :
     * deux annonces au même titre pour la même entreprise
     * (ou toutes deux sans entreprise) sont considérées comme la même
     * opportunité.
     */
    public function findOneByTitleAndCompany(string $title, ?Company $company): ?Opportunity
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.title = :title')
            ->setParameter('title', $title)
            ->setMaxResults(1);

        if (null === $company) {
            $qb->andWhere('o.company IS NULL');
        } else {
            $qb->andWhere('o.company = :company')
                ->setParameter('company', $company);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
