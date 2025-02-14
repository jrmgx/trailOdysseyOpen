<?php

namespace App\Repository;

use App\Entity\Tiles;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tiles>
 */
class TilesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tiles::class);
    }

    /**
     * @param array<int, string> $urls Skip those urls
     *
     * @return array<int, Tiles>
     */
    public function findTilesForUser(User $user, array $urls): array
    {
        $list = $this->createQueryBuilder('t')
            ->join('t.trip', 'trip')
            ->andWhere('trip.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult()
        ;

        return array_filter($list, function (Tiles $t) use (&$urls) {
            $found = \in_array($t->getUrl(), $urls, true);
            $urls[] = $t->getUrl();

            return !$found;
        });
    }
}
