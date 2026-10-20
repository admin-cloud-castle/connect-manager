<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Protocol;

use CloudCastle\ConnectManager\Contract\ProtocolDefinitionInterface;

/**
 * Неизменяемое описание протокола.
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.ShortMethodName")
 */
final class ProtocolDefinition implements ProtocolDefinitionInterface
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $name
     * @param non-empty-string $transport
     * @param non-empty-string $defaultScheme
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly int $osiLayer,
        private readonly string $transport,
        private readonly bool $nativeConnectable,
        private readonly string $defaultScheme,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function id(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function name(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function osiLayer(): int
    {
        return $this->osiLayer;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function transport(): string
    {
        return $this->transport;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isNativeConnectable(): bool
    {
        return $this->nativeConnectable;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function defaultScheme(): string
    {
        return $this->defaultScheme;
    }
}
