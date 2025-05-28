<?php
declare(strict_types=1);

namespace Tests\App\Platform;

use App\Platform\ExecutionException;
use App\Platform\LinuxPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\App\PhpUnit\AbstractFileSystemTestCase;

#[CoversClass(LinuxPlatform::class)]
class LinuxPlatformTest extends AbstractFileSystemTestCase
{
    private LinuxPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this
            ->getMockBuilder(LinuxPlatform::class)
            ->onlyMethods(['run'])
            ->getMock();
    }

    public function testExists(): void
    {
        $this->platform
            ->expects($this->once())
            ->method('run')
            ->willReturn('');

        self::assertTrue($this->platform->exists('foo'));
    }

    public function testExistsNot(): void
    {
        $this->platform
            ->expects($this->once())
            ->method('run')
            ->willThrowException(new ExecutionException(1));

        self::assertFalse($this->platform->exists('foo'));
    }

    public function testCreateExecutableFile(): void
    {
        $this->platform->createExecutableFile('1', $this->testDir);
        $this->platform->createExecutableFile('2', $this->testDir, "foo\nbar\n\nbaz");

        self::assertStringEqualsFile($this->testDir . '/1.sh', "#!/usr/bin/env bash\n");
        self::assertSame('0744', substr(decoct(fileperms($this->testDir . '/1.sh')), -4));

        self::assertStringEqualsFile($this->testDir . '/2.sh', "#!/usr/bin/env bash\n# foo\n# bar\n#\n# baz");
        self::assertSame('0744', substr(decoct(fileperms($this->testDir . '/2.sh')), -4));
    }

    public function testRunExecutableFile(): void
    {
        $this->platform
            ->expects($this->once())
            ->method('run')
            ->with("/bin/bash '" . $this->testDir . "/1.sh'")
            ->willReturn('');

        file_put_contents($this->testDir . '/1.sh', '');

        $this->platform->runExecutableFile('1', $this->testDir, ['TEST' => 'phpunit']);
    }

    public function testRunExecutableNoFileFile(): void
    {
        $this->platform
            ->expects($this->never())
            ->method('run');

        $this->platform->runExecutableFile('1', $this->testDir);
    }

    public function testExtractTar(): void
    {
        $this->platform
            ->expects($this->once())
            ->method('run')
            ->with("tar -xzf 'foo.tar.gz' -C 'bar'")
            ->willReturn('');

        $this->platform->extractTar('foo.tar.gz', 'bar');
    }

    public function testSymlink(): void
    {
        $this->platform
            ->expects($this->once())
            ->method('run')
            ->with("ln --symbolic --force --no-dereference 'foo' 'bar'")
            ->willReturn('');

        $this->platform->symlink('foo', 'bar');
    }
}
