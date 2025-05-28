<?php
declare(strict_types=1);

namespace Tests\App\PhpUnit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractFileSystemTestCase extends TestCase
{
    protected readonly string $testDir;
    protected readonly Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->testDir = dirname(__DIR__) . '/out/' . uniqid();

        $this->fs->mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->testDir);
    }

    protected function getTestDir(): string
    {
        return $this->testDir;
    }
}
