<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Единый объект сетевого соединения любого протокола.
 */
interface ConnectionInterface
{
    /**
     * Возвращает идентификатор протокола (нижний регистр).
     */
    public function protocol(): string;

    /**
     * Возвращает слой OSI (1–7) или 0 для внемодельных (socket и т.п.).
     */
    public function osiLayer(): int;

    /**
     * Проверяет, открыто ли соединение.
     */
    public function isOpen(): bool;

    /**
     * Возвращает удалённый endpoint (host:port, unix-path и т.п.) либо null.
     */
    public function remoteEndpoint(): ?string;

    /**
     * Записывает данные в соединение.
     *
     */
    public function write(string $payload): int;

    /**
     * Читает до $length байт.
     *
     */
    public function read(int $length): string;

    /**
     * Читает поток чанками (генератор для экономии памяти).
     *
     * @return \Generator<int, string>
     *
     */
    public function readChunks(int $chunkSize = 8192): \Generator;

    /**
     * Закрывает соединение и освобождает ресурс.
     */
    public function close(): void;

    /**
     * Возвращает метаданные соединения (transport, tls, peer и т.п.).
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;

    /**
     * Возвращает низкоуровневый stream-ресурс или null.
     */
    public function resource(): mixed;
}
