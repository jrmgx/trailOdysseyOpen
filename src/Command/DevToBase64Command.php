<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:dev:to-base-64',
    description: 'Given a file path, return the base64 encoded version of the file.',
)]
class DevToBase64Command extends Command
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'File path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Started command: ' . $this->getName() . ' at ' . (new \DateTime())->format('c'));

        $file = $input->getArgument('file');
        $data = file_get_contents($file);
        if (false === $data) {
            throw new \Exception('Can not read file.');
        }

        $output->writeln("\n<<<\n");
        $output->writeln(base64_encode($data));
        $output->writeln("\n>>>\n");

        $output->writeln('Finished command: ' . $this->getName() . ' at ' . (new \DateTime())->format('c'));

        return Command::SUCCESS;
    }
}
