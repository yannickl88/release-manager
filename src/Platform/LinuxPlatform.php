<?php
declare(strict_types=1);

namespace App\Platform;

class LinuxPlatform extends AbstractPlatform
{
    public function exists(string $executable): bool
    {
        try {
            $this->run('command -v ' . escapeshellarg($executable));
        } catch (ExecutionException $e) {
            return false;
        }

        return true;
    }

    public function createExecutableFile(string $fileName, string $directory, string $header = ""): void
    {
        $file = $directory . '/' . $fileName . '.sh';

        if (empty($header)) {
            $contents = "";
        } else {
            $contents = implode("\n", array_map(fn(string $line) => trim("# " . $line), explode("\n", $header)));
        }

        file_put_contents($file, "#!/usr/bin/env bash\n" . $contents);
        chmod($file, 0744);
    }

    public function runExecutableFile(string $fileName, string $directory, array $env = []): void
    {
        $file = $directory . '/' . $fileName . '.sh';

        if (file_exists($file)) {
            $this->run('/bin/bash ' . escapeshellarg($file), $env);
        }
    }

    public function extractTar(string $file, string $target): void
    {
        $this->run('tar -xzf ' . escapeshellarg($file) . ' -C ' . escapeshellarg($target));
    }

    public function symlink(string $folder, string $name): void
    {
        $this->run('ln --symbolic --force --no-dereference ' . escapeshellarg($folder) . ' ' . escapeshellarg($name));
    }
}
