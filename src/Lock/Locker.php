<?php
declare(strict_types=1);

namespace App\Lock;

use App\Platform\AbstractPlatform;
use App\Platform\ExecutionException;

class Locker
{
    public function __construct(
        private readonly AbstractPlatform $platform,
    ) {
    }

    public function nextReleaseDir(string $directory): string
    {
        $lockFile = realpath($directory) . '/.lock';

        if (!file_exists($lockFile)) {
            throw new ProjectUninitializedException();
        }

        $fp = fopen($lockFile, 'r+');

        try {
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                throw new LockException();
            }

            $current = stream_get_contents($fp);

            if (!empty($current)) {
                $release = dirname($lockFile) . '/releases/' . (intval(basename($current)) + 1);
            } else {
                $release = dirname($lockFile) . '/releases/1';
            }

            flock($fp, LOCK_UN);

            return $release;
        } finally {
            fclose($fp);
        }
    }

    public function previousReleaseDir(string $directory): string
    {
        $lockFile = realpath($directory) . '/.lock';

        if (!file_exists($lockFile)) {
            throw new ProjectUninitializedException();
        }

        $fp = fopen($lockFile, 'c+');

        try {
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                throw new LockException();
            }

            $current = stream_get_contents($fp);

            if (!empty($current)) {
                $release = dirname($lockFile) . '/releases/' . (intval(basename($current)) - 1);
            } else {
                throw new ProjectUninitializedException();
            }

            flock($fp, LOCK_UN);

            return $release;
        } finally {
            fclose($fp);
        }
    }

    public function finalizeRelease(string $directory, string $releaseDir): void {
        $symlink = realpath($directory) . '/current';

        try {
            $this->platform->symlink($releaseDir, $symlink);
        } catch (ExecutionException $e) {
            throw new LockException(previous: $e);
        }

        file_put_contents(realpath($directory) . '/.lock', $releaseDir);
    }
}
