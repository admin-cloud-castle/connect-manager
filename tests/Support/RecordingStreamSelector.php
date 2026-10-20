<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

use CloudCastle\ConnectManager\Async\StreamSelectorInterface;

/**
 * Селектор, запоминающий вызовы и таймаут для MSI-тестов.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class RecordingStreamSelector implements StreamSelectorInterface
{
    public int $calls = 0;

    public int $lastSeconds = -1;

    public int $lastMicroseconds = -1;

    public bool $calledInsideFiber = false;

    /**
     * @param int|false $result Результат select
     */
    public function __construct(private readonly int|false $result = 1)
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function select(
        ?array &$read,
        ?array &$write,
        ?array &$except,
        int $seconds,
        int $microseconds = 0,
    ): int|false {
        ++$this->calls;
        $this->lastSeconds = $seconds;
        $this->lastMicroseconds = $microseconds;
        $this->calledInsideFiber = \Fiber::getCurrent() instanceof \Fiber;

        return $this->result;
    }
}
