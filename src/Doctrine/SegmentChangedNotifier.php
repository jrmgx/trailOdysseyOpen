<?php

namespace App\Doctrine;

use App\Entity\Segment;
use App\Helper\GeoHelper;
use App\Model\Path;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Segment::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Segment::class)]
class SegmentChangedNotifier
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function prePersist(Segment $segment, PrePersistEventArgs $event): void
    {
        $this->common($segment);
    }

    public function preUpdate(Segment $segment, PreUpdateEventArgs $event): void
    {
        $this->common($segment);
    }

    private function common(Segment $segment): void
    {
        if (\count($segment->getPoints()) < 1) {
            $this->logger->warning('Segment #' . $segment->getId() . ' must contain at least one Point');

            return;
        }
        $segment->setBoundingBox(GeoHelper::getBoundingBox(Path::fromSegment($segment)));
    }
}
