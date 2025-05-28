<?php
declare(strict_types=1);

namespace Tests\App\Command;

use App\Command\InitCommand;
use App\Platform\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\App\PhpUnit\AbstractFileSystemTestCase;
use Tests\App\PhpUnit\ProjectTestTrait;

#[CoversClass(InitCommand::class)]
class InitCommandTest extends AbstractFileSystemTestCase
{
    use ProphecyTrait;
    use ProjectTestTrait;

    public function testConfiguration(): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);

        $command = new InitCommand($platform->reveal());

        self::assertSame('init', $command->getName());
        self::assertSame('Setup the target folder for releases', $command->getDescription());
        self::assertEquals([
            'target' => new InputArgument('target', InputArgument::REQUIRED, 'Target directory'),
        ], $command->getDefinition()->getArguments());
        self::assertEquals([], $command->getDefinition()->getOptions());
    }

    public function testExecute(): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->exists('ln')->willReturn(true);
        $platform->exists('tar')->willReturn(true);
        $platform->exists('bash')->willReturn(true);
        $platform
            ->createExecutableFile('post_install', $this->testDir, Argument::any())
            ->shouldBeCalled();

        $command = new InitCommand($platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, $command->run($input, $output));

        self::assertDirectoryExists($this->getTestDir() . '/releases');
        self::assertDirectoryExists($this->getTestDir() . '/shared');
        self::assertFileExists($this->getTestDir() . '/.lock');
        self::assertStringEqualsFile($this->getTestDir() . '/.lock', '');
    }

    #[DataProvider('badEnvProvider')]
    public function testExecuteBadEnv(string $expectedError, bool $existsLn, bool $existsTar, bool $existsBash): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->exists('ln')->willReturn($existsLn);
        $platform->exists('tar')->willReturn($existsTar);
        $platform->exists('bash')->willReturn($existsBash);

        $command = new InitCommand($platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame($expectedError, trim($output->fetch()));
    }

    public static function badEnvProvider(): array
    {
        return [
            'no `ln`' => ['ln not found', false, true, true],
            'no `tar`' => ['tar not found', true, false, true],
            'no `bash`' => ['bash not found', true, true, false],
        ];
    }

    public function testExecuteAlreadyInitialized(): void
    {
        $this->setupProject();

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->exists('ln')->willReturn(true);
        $platform->exists('tar')->willReturn(true);
        $platform->exists('bash')->willReturn(true);

        $command = new InitCommand($platform->reveal());

        $input = new ArrayInput(['target' => $this->testDir]);
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $command->run($input, $output));
        self::assertSame('Target folder already contains a lock file', trim($output->fetch()));
    }
}
