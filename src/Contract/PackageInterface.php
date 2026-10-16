<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Контракт публичного API пакета.
 */
interface PackageInterface
{
    /**
     * Возвращает идентификатор пакета Composer.
     */
    public function name(): string;
}
