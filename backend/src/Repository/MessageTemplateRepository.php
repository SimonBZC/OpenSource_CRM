<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MessageTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageTemplate>
 */
class MessageTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageTemplate::class);
    }

    /**
     * Recherche un modèle de message existant par titre, insensible à la casse
     * et aux espaces en début et fin de titre (« Relance » = «  RELANCE  »).
     *
     * Source unique de la règle de doublon de titre, utilisée par
     * App\State\MessageTemplateUniqueTitleProcessor.
     *
     * @param int|null $excludeId identifiant d'un modèle à ignorer (mise à jour
     *                            d'un modèle : il ne doit pas être considéré
     *                            comme son propre doublon)
     */
    public function findOneByTitleIgnoreCase(string $title, ?int $excludeId = null): ?MessageTemplate
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('LOWER(TRIM(m.title)) = LOWER(:title)')
            ->setParameter('title', trim($title))
            ->setMaxResults(1);

        if (null !== $excludeId) {
            $qb->andWhere('m.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
