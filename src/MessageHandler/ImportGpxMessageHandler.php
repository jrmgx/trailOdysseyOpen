<?php

namespace App\MessageHandler;

namespace App\MessageHandler;

use App\Message\ImportGpxMessage;
use App\Message\UpdateElevationMessage;
use App\Repository\TripRepository;
use App\Service\GpxService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ImportGpxMessageHandler
{
    public function __construct(
        private readonly TripRepository $tripRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GpxService $gpxService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportGpxMessage $message): void
    {
        $trip = $this->tripRepository->find($message->tripId)
            ?? throw new \Exception('No Trip with id #' . $message->tripId);

        $this->logger->info('Trip #' . $trip->getId() . ' import GPX files...');

        foreach ($message->filePaths as $filePath) {
            $gpxFile = $this->gpxService->gpxFile($filePath);

            $this->gpxService->gpxFileToSegments($gpxFile, $trip);
            $this->gpxService->gpxFileToInterests($gpxFile, $trip);
        }

        // Confirm that the message has been handled
        $trip->setIsCalculatingSegment(false);
        $this->entityManager->flush();

        $this->logger->info('Trip #' . $trip->getId() . ' import done.');

        $this->messageBus->dispatch(new UpdateElevationMessage($trip->getId() ?? 0));
    }
}
