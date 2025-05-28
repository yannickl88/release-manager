<?php
declare(strict_types=1);

namespace App\Platform;

abstract class AbstractPlatform
{
    abstract public function exists(string $executable): bool;

    abstract public function createExecutableFile(string $fileName, string $directory, string $header = ""): void;

    /**
     * @throws ExecutionException When script returns a non zero exit code.
     */
    abstract public function runExecutableFile(string $fileName, string $directory, array $env = []): void;

    /**
     * @throws UntarException When could not extract tar file.
     */
    abstract public function extractTar(string $file, string $target): void;

    /**
     * @throws ExecutionException When symlink could not have been created.
     */
    abstract public function symlink(string $folder, string $name): void;

    public static function detect(): self
    {
        $uname = php_uname('s');

        if (stripos($uname, 'linux') === 0) {
            return new LinuxPlatform();
        }

        throw new PlatformException($uname);
    }

    protected function run(string $cmd, array $env = []): string
    {
        $process = proc_open(
            $cmd,
            [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"],
            ],
            $pipes,
            null,
            $env
        );

        if (!is_resource($process)) {
            throw new ExecutionException(-1);
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $return_value = proc_close($process);

        if (0 !== $return_value) {
            throw new ExecutionException($return_value, $stdout, $stderr);
        }

        return $stdout;
    }
}
