<?php
declare(strict_types=1);

namespace App\Command;

use App\FileUtils;
use App\Lock\Locker;
use App\Lock\LockException;
use App\Lock\ProjectUninitializedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UnlockCommand extends Command
{
    public function __construct(private readonly Locker $locker)
    {
        parent::__construct('unlock');
    }

    public function configure(): void
    {
        $this->setDescription('Clean up failed release');
        $this->addArgument('target', InputArgument::REQUIRED, 'Target directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $directory = $this->locker->nextReleaseDir($input->getArgument('target'));
        } catch (ProjectUninitializedException $e) {
            $output->writeln('<error>Target folder doesn\'t appear a release target, did you run init?</error>');

            return Command::FAILURE;
        } catch (LockException $e) {
            $output->writeln('<error>Could not lock target directory, some other process might be attempting to release?</error>');

            return Command::FAILURE;
        }

        if (!file_exists($directory)) {
            $output->writeln('<info>Deployment folder already unlocked</info>');

            return Command::SUCCESS;
        }

        FileUtils::removeDirectory($directory);

        $output->writeln('<info>Deployment folder successfully unlocked</info>');

        return Command::SUCCESS;
    }
}
