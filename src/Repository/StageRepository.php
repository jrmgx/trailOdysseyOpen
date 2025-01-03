<?php

namespace App\Repository;

use App\Entity\Stage;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stage>
 *
 * @method Stage|null find($id, $lockMode = null, $lockVersion = null)
 * @method Stage|null findOneBy(array $criteria, array $orderBy = null)
 * @method Stage[]    findAll()
 * @method Stage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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

    public function findLastStage(Trip $trip): ?Stage
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.trip = :trip')
            ->setParameter('trip', $trip)
            ->addOrderBy('s.arrivingAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findFirstStage(Trip $trip): ?Stage
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.trip = :trip')
            ->setParameter('trip', $trip)
            ->addOrderBy('s.arrivingAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
