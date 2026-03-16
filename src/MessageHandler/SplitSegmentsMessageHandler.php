<?php

namespace App\MessageHandler;

use App\Entity\Segment;
use App\Message\SplitSegmentsMessage;
use App\Repository\SegmentRepository;
use App\Repository\TripRepository;
use App\Service\SegmentIntersectionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SplitSegmentsMessageHandler
{
    public function __construct(
        private readonly TripRepository $tripRepository,
        private readonly SegmentRepository $segmentRepository,
        private readonly SegmentIntersectionService $segmentIntersectionService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SplitSegmentsMessage $message): void
    {
        $trip = $this->tripRepository->find($message->tripId)
            ?? throw new \Exception('No Trip with id #' . $message->tripId);

        $segments = $this->segmentRepository->findByTrip($trip);
        $segments = array_values(array_filter($segments, static fn (Segment $s) => \count($s->getPoints()) >= 2));
        if (0 === \count($segments)) {
            return;
        }

        try {
            $this->logger->info('Trip #' . $trip->getId() . ' split segments at intersections...');
            $this->segmentIntersectionService->splitSegmentsAtIntersections($segments);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->warning('Split segments failed for trip #{tripId}: {message}', [
                'tripId' => $trip->getId(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            $this->entityManager->clear();
        }
    }
}
