<?php

namespace App\Repository;

use App\Entity\GearInBag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GearInBag>
 *
 * @method GearInBag|null find($id, $lockMode = null, $lockVersion = null)
 * @method GearInBag|null findOneBy(array $criteria, array $orderBy = null)
 * @method GearInBag[]    findAll()
 * @method GearInBag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GearInBagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GearInBag::class);
    }
}
