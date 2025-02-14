<?php

namespace App\Repository;

use App\Entity\Stage;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stage>
 */
class StageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stage::class);
    }

    /**
     * @return array<Stage>
     */
    public function findByTrip(Trip $trip): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.trip = :trip')
            ->setParameter('trip', $trip)
            ->addOrderBy('s.arrivingAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
