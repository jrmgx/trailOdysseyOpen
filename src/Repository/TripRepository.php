<?php

namespace App\Repository;

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

    public function findOneByShareKey(string $shareKey): ?Trip
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.shareKey = :shareKey')
            ->setParameter('shareKey', $shareKey)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
