<?php
declare(strict_types=1);

namespace App\Platform;

class PlatformException extends \RuntimeException
{
    public function __construct(string $platform, \Throwable $previous = null)
    {
        parent::__construct('Unsupported platform ' . $platform, 0, $previous);
    }
}
