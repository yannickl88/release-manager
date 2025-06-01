<?php
declare(strict_types=1);

namespace Command;

use App\Command\RollbackCommand;
use App\Lock\Locker;
use App\Lock\LockException;
use App\Lock\ProjectUninitializedException;
use PHPUnit\Framework\Attributes\CoversClass;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\App\PhpUnit\AbstractFileSystemTestCase;
use Tests\App\PhpUnit\ProjectTestTrait;

#[CoversClass(RollbackCommand::class)]
class RollbackCommandTest extends AbstractFileSystemTestCase
{
    use ProphecyTrait;
    use ProjectTestTrait;

    public function testConfiguration(): void
    {
        $locker = $this->prophesize(Locker::class);

        $command = new RollbackCommand($locker->reveal());

        self::assertSame('rollback', $command->getName());
        self::assertSame('Rollback a release', $command->getDescription());
        self::assertEquals([
            'target' => new InputArgument('target', InputArgument::REQUIRED, 'Target directory'),
        ], $command->getDefinition()->getArguments());
        self::assertEquals([
            'release' => new InputOption('release', 'r', InputOption::VALUE_REQUIRED, 'Release to rollback to')
        ], $command->getDefinition()->getOptions());
    }

    public function testExecute(): void
    {
        $this->setupRelease();
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->previousReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/1');
        $locker->finalizeRelease($this->testDir, $this->testDir . '/releases/1')->shouldBeCalled();

        $command = new RollbackCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, $command->run($input, $output));
    }

    public function testExecuteProjectNotInitialized(): void
    {
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->previousReleaseDir($this->testDir)->willThrow(new ProjectUninitializedException());
        $locker->finalizeRelease(Argument::cetera())->shouldNotBeCalled();

        $command = new RollbackCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Target folder doesn\'t appear a release target, did you run init?', trim($output->fetch()));
    }

    public function testExecuteFailedToLock(): void
    {
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->previousReleaseDir($this->testDir)->willThrow(new LockException());
        $locker->finalizeRelease(Argument::cetera())->shouldNotBeCalled();

        $command = new RollbackCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Could not lock target directory, some other process might be attempting to release?', trim($output->fetch()));
    }

    public function testExecuteReleaseNotFound(): void
    {
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->previousReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/2');
        $locker->finalizeRelease(Argument::cetera())->shouldNotBeCalled();

        $command = new RollbackCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Previous release could not be found, cannot rollback', trim($output->fetch()));
    }

    public function testExecuteWithRelease(): void
    {
        $this->setupRelease();
        $this->setupRelease();
        $this->setupRelease();
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->finalizeRelease($this->testDir, $this->testDir . '/releases/2')->shouldBeCalled();

        $command = new RollbackCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir, '--release' => '2']);
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, $command->run($input, $output));
    }

    public function testExecuteWithReleaseUnknownRelease(): void
    {
        $this->setupRelease();
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->finalizeRelease(Argument::cetera())->shouldNotBeCalled();

        $command = new RollbackCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir, '--release' => '10']);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Target release could not be found.', trim($output->fetch()));
    }

    public function testExecuteFailedToFinalize(): void
    {
        $this->setupRelease();
        $this->setupRelease();

        $locker = $this->prophesize(Locker::class);
        $locker->previousReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/1');
        $locker
            ->finalizeRelease($this->testDir, $this->testDir . '/releases/1')
            ->shouldBeCalled()
            ->willThrow(new LockException());

        $command = new RollbackCommand($locker->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Failed to finalize the release', trim($output->fetch()));
    }
}
