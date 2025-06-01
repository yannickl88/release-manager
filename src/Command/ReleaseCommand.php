<?php
declare(strict_types=1);

namespace App\Command;

use App\Lock\Locker;
use App\Lock\LockException;
use App\Lock\ProjectUninitializedException;
use App\Platform\AbstractPlatform;
use App\Platform\ExecutionException;
use App\Platform\UntarException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReleaseCommand extends Command
{
    public function __construct(
        private readonly Locker $locker,
        private readonly AbstractPlatform $platform,
    ) {
        parent::__construct('release');
    }

    public function configure(): void
    {
        $this->setDescription('Create a release from an archive');
        $this->addOption('release', 'r', InputOption::VALUE_REQUIRED, 'Human identifiable version, this will be passed to the post install script. Defaults to release number');
        $this->addArgument('target', InputArgument::REQUIRED, 'Target directory');
        $this->addArgument('archive', InputArgument::REQUIRED, 'Archive file to release');
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

        if (file_exists($directory)) {
            $output->writeln('<error>Target directory already exists, did previous release fail? Use unlock to clean up failed releases</error>');

            return Command::FAILURE;
        }

        mkdir($directory, 0755, true);

        try {
            $this->platform->extractTar($input->getArgument('archive'), $directory);
        } catch (UntarException $e) {
            $output->writeln('<error>Failed to extract achieve</error>');

            return Command::FAILURE;
        }

        $release = basename($directory);
        $version = $input->getOption('release') ?? $release;

        try {
            $this->platform->runExecutableFile(
                'post_install',
                dirname($directory, 2),
                [
                    'RELEASE' => $release,
                    'VERSION' => $version,
                    'RELEASE_DIR' => $directory,
                    'SHARED_DIR' => dirname($directory, 2) . '/shared',
                ]
            );
        } catch (ExecutionException $e) {
            $output->writeln('<error>Failed to run post install scripts</error>');

            return Command::FAILURE;
        }

        try {
            $this->locker->finalizeRelease($input->getArgument('target'), $directory);
        } catch (LockException $e) {
            $output->writeln('<error>Failed to finalize the release</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Deployment successful to ' . $directory . '</info>');

        return Command::SUCCESS;
    }
}
