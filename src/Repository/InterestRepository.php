<?php

namespace App\Repository;

use App\Entity\Interest;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Interest>
 */
class InterestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interest::class);
    }

    /**
     * @return array<Interest>
     */
    public function findByTrip(Trip $trip): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.trip = :trip')
            ->setParameter('trip', $trip)
            ->orderBy('i.arrivingAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
