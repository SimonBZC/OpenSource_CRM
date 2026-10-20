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

    /**
     * Identifiants des contacts ayant au moins une relance « À faire »
     * (règle contact_follow_up_stale).
     *
     * @return list<int>
     */
    public function findContactIdsWithTodoFollowUp(): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('DISTINCT IDENTITY(f.contact) AS contactId')
            ->andWhere('f.contact IS NOT NULL')
            ->andWhere('f.status = :todo')
            ->setParameter('todo', FollowUpStatus::TODO)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['contactId'], $rows);
    }

    /**
     * Date `completedAt` de la dernière relance terminée de chaque contact,
     * indexée par identifiant de contact (règle contact_follow_up_stale). Les
     * relances terminées sans `completedAt` sont ignorées.
     *
     * @return array<int, \DateTimeImmutable>
     */
    public function findLastCompletedAtByContact(): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.contact) AS contactId', 'MAX(f.completedAt) AS lastCompletedAt')
            ->andWhere('f.contact IS NOT NULL')
            ->andWhere('f.status = :done')
            ->andWhere('f.completedAt IS NOT NULL')
            ->setParameter('done', FollowUpStatus::DONE)
            ->groupBy('f.contact')
            ->getQuery()
            ->getScalarResult();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['contactId']] = new \DateTimeImmutable((string) $row['lastCompletedAt']);
        }

        return $result;
    }
}
