<?php

namespace App\Repository;

use App\Entity\Segment;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Segment>
 *
 * @method Segment|null find($id, $lockMode = null, $lockVersion = null)
 */
class SegmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Segment::class);
    }

    /**
     * @return array<int, Segment>
     */
    public function findByTrip(Trip $trip): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.trip = :trip')
            ->setParameter('trip', $trip)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param array<int, int> $ids
     *
     * @return array<int, Segment>
     */
    public function findByIds(array $ids): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;
    }
}
