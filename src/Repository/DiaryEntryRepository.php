<?php

namespace App\Repository;

use App\Entity\DiaryEntry;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DiaryEntry>
 *
 * @method DiaryEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method DiaryEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method DiaryEntry[]    findAll()
 * @method DiaryEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
        return $this->findBy(['trip' => $trip], ['arrivingAt' => 'ASC']);
    }
}
