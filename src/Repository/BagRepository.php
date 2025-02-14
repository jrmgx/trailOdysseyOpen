<?php

namespace App\Repository;

use App\Entity\Bag;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bag>
 *
 * @method Bag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Bag|null findOneBy(array $criteria, array $orderBy = null)
 * @method Bag[]    findAll()
 * @method Bag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bag::class);
    }

    /**
     * @return array<int, Bag>
     */
    public function findByTripAndUser(Trip $trip, User $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.trip = :trip')
            ->setParameter('trip', $trip)
            ->andWhere('b.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<int, Bag>
     */
    public function findBagsForTripAndUser(Trip $trip, User $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.trip = :trip')
            ->setParameter('trip', $trip)
            ->andWhere('b.user = :user')
            ->setParameter('user', $user)
            ->addOrderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
