<?php
declare(strict_types=1);

namespace Tests\App\PhpUnit;

trait ProjectTestTrait
{
    abstract protected function getTestDir(): string;

    protected function setupProject(): void
    {
        file_put_contents($this->getTestDir() . '/.lock', '');
    }

    protected function setupForceCurrent(int $version): void
    {
        file_put_contents($this->getTestDir() . '/.lock', $this->getTestDir() . '/releases/' . $version);
    }

    protected function setupRelease(bool $current = true, int|null $version = null): void
    {
        if (null !== $version) {
            $targetVersion = (string) $version;
        } else {
            $targetVersion = '1';

            if (file_exists($this->getTestDir() . '/.lock')) {
                $targetVersion = (string) (intval(basename(file_get_contents($this->getTestDir() . '/.lock'))) + 1);
            }
        }

        $releaseDir = $this->getTestDir() . '/releases/' . $targetVersion;

        mkdir($releaseDir, 0777, true);

        file_put_contents($releaseDir . '/file.txt', 'foobar');

        if ($current) {
            file_put_contents($this->getTestDir() . '/.lock', $releaseDir);
        }
    }

    /**
     * Assert that only the releases with the version numbers exists.
     *
     * @param array<int> $expected
     */
    public function assertOnlyReleases(array $expected): void
    {
        $files = array_map(
            fn (\SplFileInfo $file) => intval($file->getFilename()),
            iterator_to_array(new \FilesystemIterator($this->getTestDir() . '/releases/'))
        );

        sort($files);
        sort($expected);

        self::assertEquals($expected, array_values($files));
    }
}
