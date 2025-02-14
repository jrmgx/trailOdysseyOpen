<?php

namespace App\Repository;

use App\Entity\Routing;
use App\Entity\Stage;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Routing>
 */
class RoutingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Routing::class);
    }

    /**
     * @return array<Routing>
     */
    public function findByTrip(Trip $trip): array
    {
        return $this->findBy(['trip' => $trip]);
    }

    /**
     * @return array<Routing>
     */
    public function findRelatedToStage(Stage $stage): array
    {
        return $this->findBy(['stage' => $stage]);
    }
}
