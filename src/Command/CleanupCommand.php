<?php
declare(strict_types=1);

namespace App\Command;

use App\FileUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupCommand extends Command
{
    public function __construct()
    {
        parent::__construct('cleanup');
    }

    public function configure(): void
    {
        $this->setDescription('Cleanup old releases');
        $this->addArgument('target', InputArgument::REQUIRED, 'Target directory');
        $this->addOption('keep', 'k', InputOption::VALUE_REQUIRED, 'Number of releases to keep', 3);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lockFile = realpath($input->getArgument('target')) . '/.lock';

        if (!file_exists($lockFile)) {
            $output->writeln('<error>Target folder doesn\'t appear a release target, did you run init?</error>');

            return Command::FAILURE;
        }
        $current = trim(file_get_contents($lockFile));
        $releases = glob(dirname($lockFile) . '/releases/*', GLOB_ONLYDIR);

        usort($releases, 'strnatcmp');

        $releasesToKeep = intval($input->getOption('keep'));
        $currentKey = array_search($current, $releases);

        if (false === $currentKey) {
            $output->writeln('<error>Cannot determine the current release</error>');

            return Command::FAILURE;
        }

        for ($i = 0; $i < $currentKey - ($releasesToKeep - 1); $i++) {
            $directory = $releases[$i];
            $output->writeln('<info>Removing ' . $directory . '</info>');

            FileUtils::removeDirectory($directory);
        }

        return Command::SUCCESS;
    }
}
