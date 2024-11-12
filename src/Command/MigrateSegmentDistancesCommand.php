<?php

namespace App\Command;

use App\Repository\SegmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:migrate:segment-distances',
    description: 'Loop over the segments and update them so they have the distance.',
)]
class MigrateSegmentDistancesCommand extends Command
{
    public function __construct(
        private readonly SegmentRepository $segmentRepository,
        private readonly EntityManagerInterface $entityManager,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Started command: ' . $this->getName() . ' at ' . (new \DateTime())->format('c'));

        $segments = $this->segmentRepository->createQueryBuilder('s')->getQuery()->toIterable();
        foreach ($segments as $segment) {
            $output->writeln('Migrating ' . $segment->getId() . '...');
            $segment->setUpdatedAt($segment->getUpdatedAt()->modify('+1 second'));
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $output->writeln('Finished command: ' . $this->getName() . ' at ' . (new \DateTime())->format('c'));

        return Command::SUCCESS;
    }
}
