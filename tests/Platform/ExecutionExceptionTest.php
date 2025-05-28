<?php
declare(strict_types=1);

namespace Tests\App\Platform;

use App\Platform\ExecutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExecutionException::class)]
class ExecutionExceptionTest extends TestCase
{
    public function testGeneric(): void
    {
        $previous = new \RuntimeException();
        $exception = new ExecutionException(42, 'foo', 'bar', $previous);

        self::assertSame(42, $exception->getCode());
        self::assertSame('Failed to execute command [42]', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame('foo', $exception->stdout);
        self::assertSame('bar', $exception->stderr);
    }
}
