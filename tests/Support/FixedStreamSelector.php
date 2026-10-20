<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

use CloudCastle\ConnectManager\Async\StreamSelectorInterface;

/**
 * Селектор с фиксированным результатом для тестов.
 */
final class FixedStreamSelector implements StreamSelectorInterface
{
    /**
     * @param int|false $result Результат select
     */
    public function __construct(private readonly int|false $result)
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
        return $this->result;
    }
}
