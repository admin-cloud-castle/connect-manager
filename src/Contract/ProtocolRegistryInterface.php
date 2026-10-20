<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Реестр протоколов (OSI + socket и пр.).
 */
interface ProtocolRegistryInterface
{
    /**
     * Возвращает определение протокола по идентификатору.
     *
     */
    public function get(string $protocolId): ProtocolDefinitionInterface;

    /**
     * Проверяет наличие протокола в реестре.
     */
    public function has(string $protocolId): bool;

    /**
     * Перечисляет все протоколы (генератор).
     *
     * @return \Generator<string, ProtocolDefinitionInterface>
     */
    public function all(): \Generator;

    /**
     * Возвращает число зарегистрированных протоколов.
     */
    public function count(): int;

    /**
     * Регистрирует или переопределяет протокол.
     */
    public function register(ProtocolDefinitionInterface $definition): void;
}
