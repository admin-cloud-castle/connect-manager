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
    private readonly StreamSelectorInterface $selector;

    /**
     * @param StreamSelectorInterface|null $selector Селектор I/O (по умолчанию нативный)
     */
    public function __construct(?StreamSelectorInterface $selector = null)
    {
        $this->selector = $selector ?? new NativeStreamSelector();
    }

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
                $value = $fiber->resume();
                continue;
            }

            $timeout = $value['timeout'];
            $this->wait($value['stream'], $value['op'], is_int($timeout) ? (float) $timeout : $timeout);
            $value = $fiber->resume();
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
     *
     * @phpstan-assert-if-true array{op: string, stream: mixed, timeout: float|int} $value
     * @psalm-assert-if-true array{op: string, stream: mixed, timeout: float|int} $value
     */
    private function isWaitPayload(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (!array_key_exists('op', $value)) {
            return false;
        }

        if (!array_key_exists('stream', $value)) {
            return false;
        }

        if (!array_key_exists('timeout', $value)) {
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

        if (!$current instanceof \Fiber) {
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

        $seconds = max(1, (int) $timeoutSeconds);
        $read = null;
        $write = null;

        if ($op === 'read') {
            $read = [$stream];
        }

        if ($op === 'write') {
            $write = [$stream];
        }

        if ($read === null && $write === null) {
            throw new TimeoutException(sprintf('Неизвестная операция ожидания: %s', $op));
        }

        $except = null;
        $result = $this->selector->select($read, $write, $except, $seconds);

        if ($result === false) {
            throw new TimeoutException(sprintf('Ошибка ожидания %s', $op));
        }

        if ($result === 0) {
            throw new TimeoutException(sprintf('Таймаут ожидания %s', $op));
        }
    }
}
