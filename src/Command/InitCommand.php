<?php
declare(strict_types=1);

namespace App\Command;

use App\Platform\AbstractPlatform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
    public function __construct(
        private readonly AbstractPlatform $platform,
    ) {
        parent::__construct('init');
    }

    public function configure(): void
    {
        $this->setDescription('Setup the target folder for releases');
        $this->addArgument('target', InputArgument::REQUIRED, 'Target directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null !== ($error = $this->checkEnv())) {
            $output->writeln('<error>' . $error . '</error>');

            return Command::FAILURE;
        }

        $lockFile = realpath($input->getArgument('target')) . '/.lock';

        if (file_exists($lockFile)) {
            $output->writeln('<error>Target folder already contains a lock file</error>');

            return Command::FAILURE;
        }

        mkdir(dirname($lockFile) . "/releases", 0755, true);
        mkdir(dirname($lockFile) . "/shared", 0755, true);
        file_put_contents($lockFile, '');

        $this->platform->createExecutableFile(
            'post_install',
            dirname($lockFile),
            "Put post release scripts here, these will be ran after the release has been\n" .
            "setup but before switching the symlink. This is great to warm any caches etc.\n" .
            "\n" .
            "The following extra ENV vars are defined:\n" .
            "  - RELEASE = Release number, this will always be sequential\n" .
            "  - VERSION = Can be user specified version number, if not specified, same as RELEASE\n" .
            "  - RELEASE_DIR = Directory in which the release is done\n" .
            "  - SHARED_DIR = Directory for any shared resources\n"
        );

        return Command::SUCCESS;
    }

    private function checkEnv(): ?string
    {
        if (!$this->platform->exists('ln')) {
            return 'ln not found';
        }
        if (!$this->platform->exists('tar')) {
            return 'tar not found';
        }
        if (!$this->platform->exists('bash')) {
            return 'bash not found';
        }

        return null;
    }
}
