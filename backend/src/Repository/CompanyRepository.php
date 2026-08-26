<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /**
     * Recherche une Company existante par nom, insensible à la casse.
     *
     * Utilisé par App\Import\ManualImporter pour éviter de créer des
     * doublons d'entreprise (ex. "Acme" vs "ACME") lors d'un import.
     */
    public function findOneByNameIgnoreCase(string $name): ?Company
    {
        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
