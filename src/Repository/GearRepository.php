<?php

namespace App\Repository;

use App\Entity\Gear;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Gear>
 *
 * @method Gear|null find($id, $lockMode = null, $lockVersion = null)
 * @method Gear|null findOneBy(array $criteria, array $orderBy = null)
 * @method Gear[]    findAll()
 * @method Gear[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GearRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gear::class);
    }

    /**
     * @return array<int, Gear>
     */
    public function findGearsForUserAndTripWithBagInfo(User $user): array
    {
        $qb = $this->createQueryBuilder('gear')
            ->select('gear, gib, bag')
            ->leftJoin('gear.gearsInBag', 'gib')
            ->leftJoin('gib.bag', 'bag')
            ->andWhere('gear.user = :user')
            ->setParameter('user', $user)
            ->orderBy('gear.name', 'ASC')
        ;

        return $qb->getQuery()->getResult();
    }
}
