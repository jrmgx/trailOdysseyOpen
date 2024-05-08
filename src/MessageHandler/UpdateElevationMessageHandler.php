<?php

namespace App\MessageHandler;

namespace App\MessageHandler;

use App\Message\UpdateElevationMessage;
use App\Model\Point;
use App\Repository\TripRepository;
use App\Service\GeoElevationService;
use App\Service\RoutingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
class UpdateElevationMessageHandler
{
    public function __construct(
        private readonly TripRepository $tripRepository,
        private readonly GeoElevationService $elevationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RoutingService $routingService,
        // TODO a specific message bus with only one worker should be setup to prevent mass request on the service
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(UpdateElevationMessage $message): void
    {
        $trip = $this->tripRepository->find($message->tripId)
            ?? throw new \Exception('No Trip with id #' . $message->tripId);

        $updatedSegment = $message->updatedSegment;
        foreach ($trip->getSegments() as $segment) {
            $segmentPoints = $segment->getPoints();

            // Find points without elevations (20 max)
            $pointsWithoutElevationMax = array_filter($segmentPoints, fn (Point $point) => !$point->hasElevation());
            $pointsWithoutElevationMax20 = \array_slice($pointsWithoutElevationMax, 0, 20);

            $this->logger->info(
                'Trip #' . $trip->getId() . ' Segment #' . $segment->getId() .
                ' has ' . \count($pointsWithoutElevationMax) . ' point(s) without elevation'
            );

            if (0 === \count($pointsWithoutElevationMax20)) {
                continue; // Next segment
            }

            $this->elevationService->getElevations($pointsWithoutElevationMax20);

            $segment->setPoints($segmentPoints);
            $segment->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($segment);
            $this->entityManager->flush();

            ++$updatedSegment;

            if (20 === \count($pointsWithoutElevationMax20)) {
                sleep(3); // TODO delay stamp does not work with messenger doctrine implementation
                $this->messageBus->dispatch(new UpdateElevationMessage($trip->getId() ?? 0, $updatedSegment), [new DelayStamp(3000)]);

                return;
            }
        }

        // When we arrive here it means that we have processed all segment,
        // so we can force update all routing (to recalculate elevation and distance)
        if (0 === $updatedSegment) {
            return; // No need
        }
        $this->logger->info('Trip #' . $trip->getId() . ' recalculate elevation and distance...');
        foreach ($trip->getRoutings() as $routing) {
            $this->logger->info('Trip #' . $trip->getId() . ' recalculate Routing #' . $routing->getId());
            $routing->setPathPoints(null);
            $this->routingService->updatePathPoints($trip, $routing);
            $this->routingService->updateCalculatedValues($routing);
            $this->entityManager->flush();
        }
    }
}
