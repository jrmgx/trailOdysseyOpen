<?php

namespace App\Repository;

use App\Entity\Bag;
use App\Entity\Gear;
use App\Entity\GearInBag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GearInBag>
 */
class GearInBagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GearInBag::class);
    }

    public function findOneByGearAndBag(Gear $gear, Bag $bag): ?GearInBag
    {
        return $this->createQueryBuilder('gib')
            ->andWhere('gib.gear = :gear')
            ->andWhere('gib.bag = :bag')
            ->setParameter('gear', $gear)
            ->setParameter('bag', $bag)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
