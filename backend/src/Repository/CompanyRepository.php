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
     * Recherche une Company existante par nom, insensible à la casse et aux
     * espaces en début et fin de nom (« Acme » = « ACME » = «  acme  »).
     *
     * Source unique de la règle de doublon d'entreprise : utilisée par
     * App\State\CompanyUniqueNameProcessor (refus d'un doublon via l'API) et
     * par App\Import\ManualImporter (réutilisation d'une entreprise existante
     * lors d'un import).
     *
     * @param int|null $excludeId identifiant d'une Company à ignorer (mise à
     *                            jour d'une entreprise : elle ne doit pas être
     *                            considérée comme son propre doublon)
     */
    public function findOneByNameIgnoreCase(string $name, ?int $excludeId = null): ?Company
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('LOWER(TRIM(c.name)) = LOWER(:name)')
            ->setParameter('name', trim($name))
            ->setMaxResults(1);

        if (null !== $excludeId) {
            $qb->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
