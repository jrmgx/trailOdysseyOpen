<?php

namespace App\Repository;

use App\Entity\Interest;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Interest>
 *
 * @method Interest|null find($id, $lockMode = null, $lockVersion = null)
 * @method Interest|null findOneBy(array $criteria, array $orderBy = null)
 * @method Interest[]    findAll()
 * @method Interest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InterestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interest::class);
    }

    /**
     * @return array<Interest>
     */
    public function findByTrip(Trip $trip): array
    {
        return $this->findBy(['trip' => $trip], ['arrivingAt' => 'ASC']);
    }
}
