<?php
declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
    public function __construct()
    {
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

        $postInstallScript = dirname($lockFile) . "/post_install.sh";
        file_put_contents($postInstallScript, "#! /bin/bash\n# Put post release scripts here, these will be ran after the release has been\n# setup but before switching the symlink. This is great to warm any caches etc.\n\n# The following extra ENV vars are defined:\n#  - RELEASE = Release number, this will always be sequential\n#  - VERSION = Can be user specified version number, if not specified, same as RELEASE\n#  - RELEASE_DIR = Directory in which the release is done\n#  - SHARED_DIR = Directory for any shared resources");
        chmod($postInstallScript, 0744);

        return Command::SUCCESS;
    }

    private function checkEnv(): ?string
    {
        exec('ln --version', $output, $success);
        if (0 !== $success) {
            return 'ln not found';
        }

        exec('tar --version', $output, $success);
        if (0 !== $success) {
            return 'tar not found';
        }

        exec('bash --version', $output, $success);
        if (0 !== $success) {
            return 'bash not found';
        }

        return null;
    }
}
