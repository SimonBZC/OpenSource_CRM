<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AlertRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlertRule>
 */
class AlertRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlertRule::class);
    }

    /**
     * Toutes les règles du catalogue, dans l'ordre de création.
     *
     * @return list<AlertRule>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['id' => 'ASC']);
    }
}
