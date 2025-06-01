<?php
declare(strict_types=1);

namespace Command;

use App\Command\ReleaseCommand;
use App\Lock\Locker;
use App\Lock\LockException;
use App\Lock\ProjectUninitializedException;
use App\Platform\AbstractPlatform;
use App\Platform\ExecutionException;
use App\Platform\UntarException;
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

#[CoversClass(ReleaseCommand::class)]
class ReleaseCommandTest extends AbstractFileSystemTestCase
{
    use ProphecyTrait;
    use ProjectTestTrait;

    public function testConfiguration(): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);
        $locker = $this->prophesize(Locker::class);

        $command = new ReleaseCommand($locker->reveal(), $platform->reveal());

        self::assertSame('release', $command->getName());
        self::assertSame('Create a release from an archive', $command->getDescription());
        self::assertEquals([
            'target' => new InputArgument('target', InputArgument::REQUIRED, 'Target directory'),
            'archive' => new InputArgument('archive', InputArgument::REQUIRED, 'Archive file to release'),
        ], $command->getDefinition()->getArguments());
        self::assertEquals([
            'release' => new InputOption('release', 'r', InputOption::VALUE_REQUIRED, 'Human identifiable version, this will be passed to the post install script. Defaults to release number')
        ], $command->getDefinition()->getOptions());
    }

    public function testExecute(): void
    {
        $this->setupProject();

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->extractTar('release.tar.gz', $this->testDir . '/releases/1')->shouldBeCalled();
        $platform->runExecutableFile(
            'post_install',
            $this->testDir,
            [
                'RELEASE' => '1',
                'VERSION' => '1',
                'RELEASE_DIR' => $this->testDir . '/releases/1',
                'SHARED_DIR' => $this->testDir . '/shared',
            ]
        )->shouldBeCalled();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/1');
        $locker->finalizeRelease($this->testDir, $this->testDir . '/releases/1')->shouldBeCalled();

        $command = new ReleaseCommand($locker->reveal(), $platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir, 'archive' => 'release.tar.gz']);
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, $command->run($input, $output));
    }

    public function testExecuteWithReleaseNumber(): void
    {
        $this->setupProject();

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->extractTar('release.tar.gz', $this->testDir . '/releases/1')->shouldBeCalled();
        $platform->runExecutableFile(
            'post_install',
            $this->testDir,
            [
                'RELEASE' => '1',
                'VERSION' => '1.2.3',
                'RELEASE_DIR' => $this->testDir . '/releases/1',
                'SHARED_DIR' => $this->testDir . '/shared',
            ]
        )->shouldBeCalled();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/1');
        $locker->finalizeRelease($this->testDir, $this->testDir . '/releases/1')->shouldBeCalled();

        $command = new ReleaseCommand($locker->reveal(), $platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir, 'archive' => 'release.tar.gz', '--release' => '1.2.3']);
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, $command->run($input, $output));
    }

    public function testExecuteUninitializedProject(): void
    {
        $this->setupProject();

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->extractTar(Argument::cetera())->shouldNotBeCalled();
        $platform->runExecutableFile(Argument::cetera())->shouldNotBeCalled();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willThrow(new ProjectUninitializedException());
        $locker->finalizeRelease(Argument::cetera())->shouldNotBeCalled();

        $command = new ReleaseCommand($locker->reveal(), $platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir, 'archive' => 'release.tar.gz']);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Target folder doesn\'t appear a release target, did you run init?', trim($output->fetch()));
    }

    public function testExecuteCannotLock(): void
    {
        $this->setupProject();

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->extractTar(Argument::cetera())->shouldNotBeCalled();
        $platform->runExecutableFile(Argument::cetera())->shouldNotBeCalled();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willThrow(new LockException());
        $locker->finalizeRelease(Argument::cetera())->shouldNotBeCalled();

        $command = new ReleaseCommand($locker->reveal(), $platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir, 'archive' => 'release.tar.gz']);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Could not lock target directory, some other process might be attempting to release?', trim($output->fetch()));
    }

    public function testExecuteReleaseAlreadyExists(): void
    {
        $this->setupRelease();

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->extractTar(Argument::cetera())->shouldNotBeCalled();
        $platform->runExecutableFile(Argument::cetera())->shouldNotBeCalled();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/1');
        $locker->finalizeRelease($this->testDir, $this->testDir . '/releases/1')->shouldNotBeCalled();

        $command = new ReleaseCommand($locker->reveal(), $platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir, 'archive' => 'release.tar.gz']);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Target directory already exists, did previous release fail? Use unlock to clean up failed releases', trim($output->fetch()));
    }

    public function testExecuteFailedToExtract(): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);
        $platform
            ->extractTar('release.tar.gz', $this->testDir . '/releases/1')
            ->shouldBeCalled()
            ->willThrow(new UntarException());
        $platform->runExecutableFile(Argument::cetera())->shouldNotBeCalled();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/1');
        $locker->finalizeRelease($this->testDir, $this->testDir . '/releases/1')->shouldNotBeCalled();

        $command = new ReleaseCommand($locker->reveal(), $platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir, 'archive' => 'release.tar.gz']);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Failed to extract achieve', trim($output->fetch()));
    }

    public function testExecuteFailedPostInstallScript(): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->extractTar('release.tar.gz', $this->testDir . '/releases/1')->shouldBeCalled();
        $platform->runExecutableFile(
            'post_install',
            $this->testDir,
            [
                'RELEASE' => '1',
                'VERSION' => '1',
                'RELEASE_DIR' => $this->testDir . '/releases/1',
                'SHARED_DIR' => $this->testDir . '/shared',
            ]
        )->shouldBeCalled()->willThrow(new ExecutionException(1));

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/1');
        $locker->finalizeRelease($this->testDir, $this->testDir . '/releases/1')->shouldNotBeCalled();

        $command = new ReleaseCommand($locker->reveal(), $platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir, 'archive' => 'release.tar.gz']);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Failed to run post install scripts', trim($output->fetch()));
    }

    public function testExecuteFailedToFinalize(): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->extractTar('release.tar.gz', $this->testDir . '/releases/1')->shouldBeCalled();
        $platform->runExecutableFile(
            'post_install',
            $this->testDir,
            [
                'RELEASE' => '1',
                'VERSION' => '1',
                'RELEASE_DIR' => $this->testDir . '/releases/1',
                'SHARED_DIR' => $this->testDir . '/shared',
            ]
        )->shouldBeCalled();

        $locker = $this->prophesize(Locker::class);
        $locker->nextReleaseDir($this->testDir)->willReturn($this->testDir . '/releases/1');
        $locker
            ->finalizeRelease($this->testDir, $this->testDir . '/releases/1')
            ->shouldBeCalled()
            ->willThrow(new LockException());

        $command = new ReleaseCommand($locker->reveal(), $platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir, 'archive' => 'release.tar.gz']);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Failed to finalize the release', trim($output->fetch()));
    }
}
