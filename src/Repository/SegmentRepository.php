<?php

namespace App\Repository;

use App\Entity\Segment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Segment>
 *
 * @method Segment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Segment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Segment[]    findAll()
 * @method Segment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SegmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Segment::class);
    }

    /**
     * @param array<int, int> $ids
     *
     * @return array<int, Segment>
     */
    public function findByIds(array $ids): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;
    }
}
