<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager;

use CloudCastle\ConnectManager\Contract\ConnectManagerInterface;
use CloudCastle\ConnectManager\Exception\CloudCastleException;

/**
 * Точка входа пакета Connect Manager.
 *
 * Заглушка скаффолда: замените на реальный публичный API.
 */
final class ConnectManager implements ConnectManagerInterface
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
     * Завершает работу с ошибкой пакета.
     *
     * @throws CloudCastleException
     */
    public function abort(string $message): never
    {
        throw new CloudCastleException($message);
    }
}
