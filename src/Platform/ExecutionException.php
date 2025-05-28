<?php
declare(strict_types=1);

namespace App\Platform;

class ExecutionException extends \RuntimeException
{
    public function __construct(
        int $outputCode,
        public readonly string $stdout = '',
        public readonly string $stderr = '',
        \Throwable $previous = null
    ) {
        parent::__construct("Failed to execute command [$outputCode]", $outputCode, $previous);
    }
}
