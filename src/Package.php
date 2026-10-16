<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager;

/**
 * Точка входа пакета Connect Manager.
 *
 * Заглушка скаффолда: замените на реальный публичный API.
 */
final class Package
{
    /**
     * Версия пакета для диагностики.
     */
    public const VERSION = '0.1.0';

    /**
     * Возвращает идентификатор пакета Composer.
     */
    public function name(): string
    {
        return 'cloudcastle/connect-manager';
    }
}
