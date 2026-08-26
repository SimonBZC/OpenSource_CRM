<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FollowUp;
use App\Enum\FollowUpStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FollowUp>
 */
class FollowUpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FollowUp::class);
    }

    /**
     * Relances dont le statut correspond et dont l'échéance est atteinte ou
     * dépassée par rapport à $upTo (utilisé par
     * App\Command\CheckDueFollowUpsCommand).
     *
     * @return list<FollowUp>
     */
    public function findDueFollowUps(\DateTimeImmutable $upTo, FollowUpStatus $status): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.status = :status')
            ->andWhere('f.scheduledAt <= :upTo')
            ->setParameter('status', $status)
            ->setParameter('upTo', $upTo)
            ->orderBy('f.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
