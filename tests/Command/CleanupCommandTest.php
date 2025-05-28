<?php
declare(strict_types=1);

namespace Tests\App\Command;

use App\Command\CleanupCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\App\PhpUnit\ProjectTestTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\App\PhpUnit\AbstractFileSystemTestCase;

#[CoversClass(CleanupCommand::class)]
class CleanupCommandTest extends AbstractFileSystemTestCase
{
    use ProjectTestTrait;

    public function testConfiguration(): void
    {
        $command = new CleanupCommand();

        self::assertSame('cleanup', $command->getName());
        self::assertSame('Cleanup old releases', $command->getDescription());
        self::assertEquals([
            'target' => new InputArgument('target', InputArgument::REQUIRED, 'Target directory'),
        ], $command->getDefinition()->getArguments());
        self::assertEquals([
            'keep' => new InputOption('keep', 'k', InputOption::VALUE_REQUIRED, 'Number of releases to keep', 3),
        ], $command->getDefinition()->getOptions());
    }

    public function testExecute(): void
    {
        // Make 5 releasees
        $this->setupRelease(version: 1);
        $this->setupRelease(version: 2);
        $this->setupRelease(version: 3);
        $this->setupRelease(version: 4);
        $this->setupRelease(version: 5); // Current release

        $command = new CleanupCommand();

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, $command->run($input, $output));

        $this->assertOnlyReleases([3, 4, 5]);
    }

    public function testExecuteWithOlderCurrentRelease(): void
    {
        // Make 5 releasees
        $this->setupRelease(version: 1);
        $this->setupRelease(version: 2);
        $this->setupRelease(version: 3);
        $this->setupRelease(version: 4); // Current release
        $this->setupRelease(current: false, version: 5);

        $command = new CleanupCommand();

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, $command->run($input, $output));

        $this->assertOnlyReleases([2, 3, 4, 5]);
    }

    public function testExecuteNotInitialized(): void
    {
        $command = new CleanupCommand();

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Target folder doesn\'t appear a release target, did you run init?', trim($output->fetch()));
    }

    public function testExecuteCannotFindCurrent(): void
    {
        $this->setupRelease(version: 1);
        $this->setupRelease(version: 2);
        $this->setupRelease(version: 3);
        $this->setupForceCurrent(version: 4);

        $command = new CleanupCommand();

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Cannot determine the current release.', trim($output->fetch()));
    }
}
