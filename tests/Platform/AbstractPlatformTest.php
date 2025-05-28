<?php
declare(strict_types=1);

namespace Tests\App\Platform;

use App\Platform\AbstractPlatform;
use App\Platform\ExecutionException;
use App\Platform\LinuxPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractPlatform::class)]
class AbstractPlatformTest extends TestCase
{
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new class extends AbstractPlatform {
            public function exists(string $executable): bool
            {
                return false;
            }

            public function createExecutableFile(string $fileName, string $directory, string $header = ""): void
            {
            }

            public function runExecutableFile(string $fileName, string $directory, array $env = []): void
            {
            }

            public function extractTar(string $file, string $target): void
            {
            }

            public function symlink(string $folder, string $name): void
            {
            }

            public function run(string $cmd, array $env = []): string
            {
                return parent::run($cmd, $env);
            }
        };
    }

    public function testDetect(): void
    {
        self::assertInstanceOf(LinuxPlatform::class, AbstractPlatform::detect());
    }

    public function testRun(): void
    {
        self::assertEquals("foobar\n", $this->platform->run('echo foobar'));
    }

    public function testRunWithEnv(): void
    {
        self::assertEquals("foobar\n", $this->platform->run('echo $TEST', ['TEST' => 'foobar']));
    }

    public function testRunCreationError(): void
    {
        try {
            $this->platform->run('foobar', ['foo(!*@#&']);

            self::fail();
        } catch (ExecutionException $e) {
            self::assertSame(127, $e->getCode());
            self::assertSame('', $e->stdout);
            self::assertSame("sh: 1: foobar: not found\n", $e->stderr);
        }
    }

    public function testRunCmdError(): void
    {
        try {
            $this->platform->run('foobar');

            self::fail();
        } catch (ExecutionException $e) {
            self::assertSame(127, $e->getCode());
            self::assertSame('', $e->stdout);
            self::assertSame("sh: 1: foobar: not found\n", $e->stderr);
        }
    }
}
