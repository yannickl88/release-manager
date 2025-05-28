<?php
declare(strict_types=1);

namespace App;

class FileUtils
{
    public static function removeDirectory(string $directory): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isLink()) {
                unlink($file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename());
            } else if ($file->isDir()) {
                rmdir($file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename());
            } else {
                unlink($file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename());
            }
        }

        rmdir($directory);
    }
}
