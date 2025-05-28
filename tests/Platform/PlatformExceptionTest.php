<?php
declare(strict_types=1);

namespace Tests\App\Platform;

use App\Platform\PlatformException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlatformException::class)]
class PlatformExceptionTest extends TestCase
{
    public function testGeneric(): void
    {
        $previous = new \RuntimeException();

        $exception = new PlatformException('phpunit', $previous);

        self::assertSame('Unsupported platform phpunit', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }
}
