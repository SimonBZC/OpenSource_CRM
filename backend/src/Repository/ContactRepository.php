<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * Contacts actifs dont la case « Présenté » n'est pas cochée (règle
     * contact_not_presented ; les contacts inactifs sont ignorés).
     *
     * @return list<Contact>
     */
    public function findNotPresented(): array
    {
        return $this->findBy(['presentationSent' => false, 'active' => true], ['id' => 'ASC']);
    }
}
