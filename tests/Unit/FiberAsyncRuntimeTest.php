<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Async\FiberAsyncRuntime;
use CloudCastle\ConnectManager\Async\NativeStreamSelector;
use CloudCastle\ConnectManager\Exception\TimeoutException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты FiberAsyncRuntime.
 */
#[CoversClass(FiberAsyncRuntime::class)]
#[CoversClass(NativeStreamSelector::class)]
#[CoversClass(\CloudCastle\ConnectManager\ConnectManager::class)]
#[CoversClass(\CloudCastle\ConnectManager\Async\FiberAsyncRuntime::class)]
#[CoversClass(\CloudCastle\ConnectManager\Async\NativeStreamSelector::class)]
#[CoversClass(\CloudCastle\ConnectManager\Contract\ConnectionOptions::class)]
#[CoversClass(\CloudCastle\ConnectManager\Connection\LogicalConnection::class)]
#[CoversClass(\CloudCastle\ConnectManager\Connection\StreamConnection::class)]
#[CoversClass(\CloudCastle\ConnectManager\Driver\ConnectionFactory::class)]
#[CoversClass(\CloudCastle\ConnectManager\Driver\StreamOpener::class)]
#[CoversClass(\CloudCastle\ConnectManager\Driver\TransportResolver::class)]
#[CoversClass(\CloudCastle\ConnectManager\Pool\ConnectionPool::class)]
#[CoversClass(\CloudCastle\ConnectManager\Protocol\ProtocolCatalog::class)]
#[CoversClass(\CloudCastle\ConnectManager\Protocol\ProtocolDefinition::class)]
#[CoversClass(\CloudCastle\ConnectManager\Protocol\ProtocolRegistry::class)]
#[CoversClass(\CloudCastle\ConnectManager\Security\EndpointGuard::class)]
#[CoversClass(\CloudCastle\ConnectManager\Exception\ConnectManagerException::class)]
#[CoversClass(\CloudCastle\ConnectManager\Exception\ConnectionException::class)]
#[CoversClass(\CloudCastle\ConnectManager\Exception\ProtocolException::class)]
#[CoversClass(\CloudCastle\ConnectManager\Exception\SecurityException::class)]
#[CoversClass(\CloudCastle\ConnectManager\Exception\TimeoutException::class)]
/**
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.MissingImport")
 * @SuppressWarnings("PHPMD.ExcessivePublicCount")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
 */
final class FiberAsyncRuntimeTest extends TestCase
{
    /**
     * run() выполняет задачу и возвращает результат.
     */
    public function testRunReturnsValue(): void
    {
        $runtime = new FiberAsyncRuntime();
        $result = $runtime->run(static fn (): int => 42);

        self::assertSame(42, $result);
    }

    /**
     * awaitReadable без Fiber (прямой wait) на php://memory сразу готов.
     */
    public function testAwaitOutsideFiber(): void
    {
        $runtime = new FiberAsyncRuntime();
        $stream = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream);
        fwrite($stream, 'x');
        rewind($stream);
        $runtime->awaitReadable($stream, 1.0);
        $runtime->awaitWritable($stream, 1.0);
        $contents = stream_get_contents($stream, -1, 0);
        self::assertSame('x', is_string($contents) ? $contents : '');
        fclose($stream);
    }

    /**
     * Таймаут на нечитаемом pipe.
     */
    public function testTimeoutOnUnreadPipe(): void
    {
        $runtime = new FiberAsyncRuntime();
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pipes);

        $this->expectException(TimeoutException::class);

        try {
            $runtime->awaitReadable($pipes[0], 0.05);
        } finally {
            fclose($pipes[0]);
            fclose($pipes[1]);
        }
    }

    /**
     * Suspend внутри Fiber через run().
     */
    public function testFiberSuspendResume(): void
    {
        $runtime = new FiberAsyncRuntime();
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pipes);

        $result = $runtime->run(function () use ($runtime, $pipes): string {
            fwrite($pipes[1], 'ok');
            $runtime->awaitReadable($pipes[0], 1.0);
            $data = fread($pipes[0], 2);

            return is_string($data) ? $data : '';
        });

        fclose($pipes[0]);
        fclose($pipes[1]);
        self::assertSame('ok', $result);
    }
}
