<?php
declare(strict_types=1);

namespace Tests\App\Lock;

use App\Lock\Locker;
use App\Lock\LockException;
use App\Lock\ProjectUninitializedException;
use App\Platform\AbstractPlatform;
use App\Platform\ExecutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Filesystem\Filesystem;
use Tests\App\PhpUnit\AbstractFileSystemTestCase;

#[CoversClass(Locker::class)]
class LockerTest extends AbstractFileSystemTestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<AbstractPlatform>
     */
    private ObjectProphecy $platform;
    private Locker $locker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->prophesize(AbstractPlatform::class);
        $this->locker = new Locker(
            $this->platform->reveal()
        );
    }

    public function testNextReleaseDirEmptyLock(): void
    {
        file_put_contents($this->testDir . '/.lock', '');

        $next = $this->locker->nextReleaseDir($this->testDir);

        self::assertSame($this->testDir . '/releases/1', $next);
    }

    public function testNextReleaseDirExistingRelease(): void
    {
        file_put_contents($this->testDir . '/.lock', $this->testDir . '/releases/12');

        $next = $this->locker->nextReleaseDir($this->testDir);

        self::assertSame($this->testDir . '/releases/13', $next);
    }

    public function testNextReleaseDirNotInit(): void
    {
        $this->expectException(ProjectUninitializedException::class);

        $this->locker->nextReleaseDir($this->testDir);
    }

    public function testNextReleaseDirCannotLock(): void
    {
        file_put_contents($this->testDir . '/.lock', $this->testDir . '/releases/12');
        try {
            $fp = fopen($this->testDir . '/.lock', 'r+');
            flock($fp, LOCK_EX | LOCK_NB);

            $this->expectException(LockException::class);

            $this->locker->nextReleaseDir($this->testDir);
        } finally {
            fclose($fp);
        }
    }

    public function testPreviousReleaseDirExistingRelease(): void
    {
        file_put_contents($this->testDir . '/.lock', $this->testDir . '/releases/12');

        $next = $this->locker->previousReleaseDir($this->testDir);

        self::assertSame($this->testDir . '/releases/11', $next);
    }

    public function testPreviousReleaseDirEmptyLock(): void
    {
        $this->expectException(ProjectUninitializedException::class);

        file_put_contents($this->testDir . '/.lock', '');

        $this->locker->previousReleaseDir($this->testDir);
    }

    public function testPreviousReleaseDirNotInit(): void
    {
        $this->expectException(ProjectUninitializedException::class);

        $this->locker->previousReleaseDir($this->testDir);
    }

    public function testPreviousReleaseDirCannotLock(): void
    {
        file_put_contents($this->testDir . '/.lock', $this->testDir . '/releases/12');
        try {
            $fp = fopen($this->testDir . '/.lock', 'r+');
            flock($fp, LOCK_EX | LOCK_NB);

            $this->expectException(LockException::class);

            $this->locker->previousReleaseDir($this->testDir);
        } finally {
            fclose($fp);
        }
    }

    public function testFinalizeRelease(): void
    {
        $this->platform
            ->symlink($this->testDir . '/releases/12', $this->testDir . '/current')
            ->shouldBeCalled();

        $this->locker->finalizeRelease($this->testDir, $this->testDir . '/releases/12');

        self::assertSame($this->testDir . '/releases/12', file_get_contents($this->testDir . '/.lock'));
    }

    public function testFinalizeReleaseError(): void
    {
        $this->platform
            ->symlink($this->testDir . '/releases/12', $this->testDir . '/current')
            ->shouldBeCalled()
            ->willThrow(new ExecutionException(1));

        $this->expectException(LockException::class);

        $this->locker->finalizeRelease($this->testDir, $this->testDir . '/releases/12');
    }
}
