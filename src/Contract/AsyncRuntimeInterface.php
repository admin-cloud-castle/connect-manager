<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Асинхронный рантайм на Fibers + stream_select.
 */
interface AsyncRuntimeInterface
{
    /**
     * Запускает асинхронный callable до завершения.
     *
     * @param callable(): mixed $task
     */
    public function run(callable $task): mixed;

    /**
     * Откладывает выполнение до готовности чтения ресурса.
     */
    public function awaitReadable(mixed $stream, float $timeoutSeconds): void;

    /**
     * Откладывает выполнение до готовности записи в ресурс.
     */
    public function awaitWritable(mixed $stream, float $timeoutSeconds): void;
}
