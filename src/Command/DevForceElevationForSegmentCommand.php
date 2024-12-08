<?php

namespace App\Command;

use App\Entity\Segment;
use App\Model\Point;
use App\Repository\SegmentRepository;
use App\Service\GeoElevationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Usage example: while [ 1 ]; do bin/console app:dev:force-elevation-for-segment 42 -vvv; sleep 3; done.
 */
#[AsCommand(
    name: 'app:dev:force-elevation-for-segment',
    description: 'Given a segment id, run a round of UpdateElevation.',
)]
class DevForceElevationForSegmentCommand extends Command
{
    public function __construct(
        private readonly SegmentRepository $segmentRepository,
        private readonly GeoElevationService $elevationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('segmentId', InputArgument::REQUIRED, 'Segment Id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Started command: ' . $this->getName() . ' at ' . (new \DateTime())->format('c'));

        $segmentId = (int) $input->getArgument('segmentId');
        $segment = $this->segmentRepository->find($segmentId) ??
            throw new \Exception('This segment does not exist.');

        $segmentPoints = $segment->getPoints();

        // Find points without elevations (20 max)
        $pointsWithoutElevationMax = array_filter($segmentPoints, fn (Point $point) => !$point->hasElevation());
        $pointsWithoutElevationMax20 = \array_slice($pointsWithoutElevationMax, 0, 20);
        $count = \count($pointsWithoutElevationMax);

        if (\count($pointsWithoutElevationMax20) > 0) {
            $output->writeln("This segment has $count point(s) without elevation.");
            $this->elevationService->getElevations($pointsWithoutElevationMax20);

            $segment->setPoints($segmentPoints);
            $segment->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($segment);
            $this->entityManager->flush();

            $output->writeln(
                '<comment>' .
                "Some point on this segment are still missing elevation, you should re-run this command.\n" .
                'This is the normal behaviour.' .
                '</comment>'
            );
        } else {
            $output->writeln('<info>All points have elevation for this segment.</info>');
        }

        $output->writeln('Finished command: ' . $this->getName() . ' at ' . (new \DateTime())->format('c'));

        return Command::SUCCESS;
    }
}
