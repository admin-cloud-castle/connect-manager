<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Async;

use CloudCastle\ConnectManager\Contract\AsyncRuntimeInterface;
use CloudCastle\ConnectManager\Exception\TimeoutException;
use Fiber;

/**
 * Асинхронный рантайм на Fiber + stream_select без внешних зависимостей.
 *
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 */
final class FiberAsyncRuntime implements AsyncRuntimeInterface
{
    /**
     * {@inheritdoc}
     *
     * @psalm-suppress MixedAssignment
     */
    #[\Override]
    public function run(callable $task): mixed
    {
        $fiber = new Fiber($task);
        $value = $fiber->start();

        while (!$fiber->isTerminated()) {
            if (!$this->isWaitPayload($value)) {
                $value = $fiber->resume(null);
                continue;
            }

            $this->wait($value['stream'], $value['op'], (float) $value['timeout']);
            $value = $fiber->resume(null);
        }

        return $fiber->getReturn();
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function awaitReadable(mixed $stream, float $timeoutSeconds): void
    {
        $this->suspend('read', $stream, $timeoutSeconds);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function awaitWritable(mixed $stream, float $timeoutSeconds): void
    {
        $this->suspend('write', $stream, $timeoutSeconds);
    }

    /**
     * Проверяет payload ожидания I/O.
     *
     * @param mixed $value
     *
     * @phpstan-assert-if-true array{op: string, stream: mixed, timeout: float|int} $value
     *
     * @psalm-assert-if-true array{op: string, stream: mixed, timeout: float|int} $value
     */
    private function isWaitPayload(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (
            !array_key_exists('op', $value)
            || !array_key_exists('stream', $value)
            || !array_key_exists('timeout', $value)
        ) {
            return false;
        }

        if (!is_string($value['op'])) {
            return false;
        }

        return is_int($value['timeout']) || is_float($value['timeout']);
    }

    /**
     * Приостанавливает текущий Fiber до готовности I/O.
     *
     * @param mixed $stream Stream resource
     */
    private function suspend(string $op, mixed $stream, float $timeoutSeconds): void
    {
        $current = Fiber::getCurrent();

        if ($current === null) {
            $this->wait($stream, $op, $timeoutSeconds);

            return;
        }

        Fiber::suspend([
            'op' => $op,
            'stream' => $stream,
            'timeout' => $timeoutSeconds,
        ]);
    }

    /**
     * Блокирующий select по одному stream.
     *
     * @param mixed $stream Stream resource
     *
     * @throws TimeoutException
     */
    private function wait(mixed $stream, string $op, float $timeoutSeconds): void
    {
        if (!is_resource($stream)) {
            throw new TimeoutException('Ожидался stream resource');
        }

        $meta = stream_get_meta_data($stream);
        $streamType = $meta['stream_type'];

        if ($streamType === 'MEMORY' || $streamType === 'TEMP') {
            return;
        }

        $seconds = (int) $timeoutSeconds;

        if ($seconds < 1) {
            $seconds = 1;
        }

        if ($op === 'read') {
            $read = [$stream];
            $write = null;
        } elseif ($op === 'write') {
            $read = null;
            $write = [$stream];
        } else {
            throw new TimeoutException(sprintf('Неизвестная операция ожидания: %s', $op));
        }

        $except = null;
        $result = @stream_select($read, $write, $except, $seconds);

        if ($result === false || $result === 0) {
            throw new TimeoutException(sprintf('Таймаут или ошибка ожидания %s', $op));
        }
    }
}
