<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Описание сетевого протокола в реестре менеджера.
 * @SuppressWarnings("PHPMD.ShortMethodName")
 */
interface ProtocolDefinitionInterface
{
    /**
     * Канонический идентификатор (например tcp, http, adsl).
     */
    public function id(): string;

    /**
     * Человекочитаемое имя.
     */
    public function name(): string;

    /**
     * Слой OSI (1–7) или 0 вне модели.
     */
    public function osiLayer(): int;

    /**
     * Базовый транспортный идентификатор (tcp/udp/raw/custom/none).
     */
    public function transport(): string;

    /**
     * Можно ли открыть соединение средствами PHP без внешнего драйвера.
     */
    public function isNativeConnectable(): bool;

    /**
     * Схема URI по умолчанию (tcp://, http://, unix:// и т.п.).
     */
    public function defaultScheme(): string;
}
