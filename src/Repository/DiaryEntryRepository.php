<?php

namespace App\Repository;

use App\Entity\DiaryEntry;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DiaryEntry>
 */
class DiaryEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiaryEntry::class);
    }

    /**
     * @return array<DiaryEntry>
     */
    public function findByTrip(Trip $trip): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.trip = :trip')
            ->setParameter('trip', $trip)
            ->orderBy('d.arrivingAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
