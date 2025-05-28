<?php
declare(strict_types=1);

namespace Command;

use App\Command\UnlockCommand;
use App\Lock\Locker;
use App\Lock\LockException;
use App\Lock\ProjectUninitializedException;
use PHPUnit\Framework\Attributes\CoversClass;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\App\PhpUnit\AbstractFileSystemTestCase;
use Tests\App\PhpUnit\ProjectTestTrait;

#[CoversClass(UnlockCommand::class)]
class UnlockCommandTest extends AbstractFileSystemTestCase
{
    use ProphecyTrait;
    use ProjectTestTrait;

    public function testConfiguration(): void
    {
        $locker = $this->prophesize(Locker::class);

        $command = new UnlockCommand($locker->reveal());

        self::assertSame('unlock', $command->getName());
        self::assertSame('Clean up failed release', $command->getDescription());
        self::assertEquals([
            'target' => new InputArgument('target', InputArgument::REQUIRED, 'Target directory'),
        ], $command->getDefinition()->getArguments());
        self::assertEquals([], $command->getDefinition()->getOptions());
    }

    public function testExecute(): void
    {
        $this->setupRelease();
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/2');

        $command = new UnlockCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, $command->run($input, $output));
        $this->assertOnlyReleases([1]);
    }

    public function testExecuteProjectNotInitialized(): void
    {
        $this->setupRelease();
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willThrow(new ProjectUninitializedException());

        $command = new UnlockCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Target folder doesn\'t appear a release target, did you run init?', trim($output->fetch()));
    }

    public function testExecuteFailedToLock(): void
    {
        $this->setupRelease();
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willThrow(new LockException());

        $command = new UnlockCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Could not lock target directory, some other process might be attempting to release?', trim($output->fetch()));
    }

    public function testExecuteNoCleanup(): void
    {
        $this->setupRelease();
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/3');

        $command = new UnlockCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, $command->run($input, $output));
        self::assertSame('Cleanup not needed.', trim($output->fetch()));
        $this->assertOnlyReleases([1, 2]);
    }
}
