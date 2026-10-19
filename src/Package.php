<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager;

use CloudCastle\ConnectManager\Contract\PackageInterface;
use CloudCastle\ConnectManager\Exception\PackageException;

/**
 * Точка входа пакета Connect Manager.
 *
 * Заглушка скаффолда: замените на реальный публичный API.
 */
final class Package implements PackageInterface
{
    /**
     * Версия пакета для диагностики.
     */
    public const VERSION = '0.1.0';

    /**
     * Возвращает идентификатор пакета Composer.
     */
    #[\Override]
    public function name(): string
    {
        return 'cloud-castle/connect-manager';
    }

    /**
     * Завершает выполнение с ошибкой пакета.
     *
     * @throws PackageException
     */
    public function abort(string $message): never
    {
        throw new PackageException($message);
    }
}
