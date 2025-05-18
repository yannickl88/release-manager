<?php
declare(strict_types=1);

namespace App\Command;

use App\Lock\Locker;
use App\Lock\LockException;
use App\Lock\ProjectUninitializedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RollbackCommand extends Command
{
    public function __construct(private readonly Locker $locker)
    {
        parent::__construct('rollback');
    }

    public function configure(): void
    {
        $this->setDescription('Rollback a release');
        $this->addOption('release', 'r', InputOption::VALUE_REQUIRED, 'Release to rollback to');
        $this->addArgument('target', InputArgument::REQUIRED, 'Target directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null !== ($release = $input->getOption('release'))) {
            $directory = realpath($input->getArgument('target')) . '/releases/' . intval($release);

            if (!file_exists($directory)) {
                $output->writeln('<error>Target release could not be found.</error>');

                return Command::FAILURE;
            }
        } else {
            try {
                $directory = $this->locker->previousReleaseDir($input->getArgument('target'));
            } catch (ProjectUninitializedException $e) {
                $output->writeln('<error>Target folder doesn\'t appear a release target, did you run init?</error>');

                return Command::FAILURE;
            } catch (LockException $e) {
                $output->writeln('<error>Could not lock target directory, some other process might be attempting to release?</error>');

                return Command::FAILURE;
            }
        }

        if (!file_exists($directory)) {
            $output->writeln('<error>Previous release could not be found, cannot rollback.</error>');

            return Command::FAILURE;
        }

        try {
            $this->locker->finalizeRelease($input->getArgument('target'), $directory);
        } catch (LockException $e) {
            $output->writeln('<error>Failed to finalize the release</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
