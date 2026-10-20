<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Protocol;

use CloudCastle\ConnectManager\Contract\ProtocolDefinitionInterface;
use CloudCastle\ConnectManager\Contract\ProtocolRegistryInterface;
use CloudCastle\ConnectManager\Exception\ProtocolException;

/**
 * Реестр протоколов на основе каталога OSI + пользовательские регистрации.
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class ProtocolRegistry implements ProtocolRegistryInterface
{
    /**
     * @var array<string, ProtocolDefinitionInterface>
     */
    private array $definitions = [];

    /**
     * Создаёт реестр и загружает встроенный каталог.
     */
    public function __construct()
    {
        foreach (ProtocolCatalog::definitions() as $definition) {
            $this->definitions[$definition->id()] = $definition;
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function get(string $protocolId): ProtocolDefinitionInterface
    {
        $id = $this->normalize($protocolId);

        if (!isset($this->definitions[$id])) {
            throw new ProtocolException(sprintf('Неизвестный протокол: %s', $protocolId));
        }

        return $this->definitions[$id];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function has(string $protocolId): bool
    {
        return isset($this->definitions[$this->normalize($protocolId)]);
    }

    /**
     * {@inheritdoc}
     *
     * @return \Generator<string, ProtocolDefinitionInterface>
     */
    #[\Override]
    public function all(): \Generator
    {
        foreach ($this->definitions as $id => $definition) {
            yield $id => $definition;
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function count(): int
    {
        return count($this->definitions);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function register(ProtocolDefinitionInterface $definition): void
    {
        $this->definitions[$this->normalize($definition->id())] = $definition;
    }

    /**
     * Нормализует идентификатор протокола.
     */
    private function normalize(string $protocolId): string
    {
        return strtolower(trim($protocolId));
    }
}
