<?php
declare(strict_types=1);

namespace Tests\App;

use App\FileUtils;
use App\Lock\Locker;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Filesystem\Filesystem;
use Tests\App\PhpUnit\AbstractFileSystemTestCase;

#[CoversClass(FileUtils::class)]
class FileUtilsTest extends AbstractFileSystemTestCase
{
    public function testRemoveDirectory(): void
    {
        $fs = new Filesystem();
        $fs->mkdir([
            $this->testDir . '/foo/bar/baz',
        ]);
        $fs->touch([
            $this->testDir . '/1.txt',
            $this->testDir . '/foo/2.txt',
            $this->testDir . '/foo/bar/3.txt',
            $this->testDir . '/foo/bar/baz/4.txt',
        ]);
        $fs->symlink($this->testDir, $this->testDir . '/foo/link');

        FileUtils::removeDirectory($this->testDir . '/foo');

        $files = array_map(
            fn (\SplFileInfo $file) => substr($file->getPathname(), strlen($this->testDir)),
            iterator_to_array(new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->testDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            ))
        );

        self::assertEquals(['/1.txt'], array_values($files));
    }
}
