<?php

namespace App\Repository;

use App\Entity\DiaryEntry;
use App\Entity\Interest;
use App\Entity\Routing;
use App\Entity\Segment;
use App\Entity\Stage;
use App\Entity\Tiles;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trip>
 *
 * @method Trip|null find($id, $lockMode = null, $lockVersion = null)
 * @method Trip|null findOneBy(array $criteria, array $orderBy = null)
 * @method Trip[]    findAll()
 * @method Trip[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    /**
     * @return array<int, Trip>
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->addOrderBy('t.updatedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<int, Trip>
     */
    public function findPublicForUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.stages', 's')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->andWhere('t.shareKey IS NOT NULL')
            ->addOrderBy('s.arrivingAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByShareKey(string $shareKey): ?Trip
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.shareKey = :shareKey')
            ->setParameter('shareKey', $shareKey)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function delete(Trip $trip): void
    {
        $tripId = $trip->getId();
        $entityManager = $this->getEntityManager();

        $entityManager->createQueryBuilder()
            ->delete(Routing::class, 'r')
            ->where('r.trip = :tripId')
            ->setParameter('tripId', $tripId)
            ->getQuery()
            ->execute();

        $entityManager->createQueryBuilder()
            ->delete(Stage::class, 's')
            ->where('s.trip = :tripId')
            ->setParameter('tripId', $tripId)
            ->getQuery()
            ->execute();

        $entityManager->createQueryBuilder()
            ->delete(Segment::class, 's')
            ->where('s.trip = :tripId')
            ->setParameter('tripId', $tripId)
            ->getQuery()
            ->execute();

        $entityManager->createQueryBuilder()
            ->delete(Interest::class, 'i')
            ->where('i.trip = :tripId')
            ->setParameter('tripId', $tripId)
            ->getQuery()
            ->execute();

        $entityManager->createQueryBuilder()
            ->delete(DiaryEntry::class, 'd')
            ->where('d.trip = :tripId')
            ->setParameter('tripId', $tripId)
            ->getQuery()
            ->execute();

        $entityManager->createQueryBuilder()
            ->delete(Tiles::class, 't')
            ->where('t.trip = :tripId')
            ->setParameter('tripId', $tripId)
            ->getQuery()
            ->execute();

        $entityManager->createQueryBuilder()
            ->delete(Trip::class, 't')
            ->where('t.id = :tripId')
            ->setParameter('tripId', $tripId)
            ->getQuery()
            ->execute();
    }
}
