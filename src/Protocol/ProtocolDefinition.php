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
     * @var non-empty-string
     */
    private string $id;

    /**
     * @var non-empty-string
     */
    private string $name;

    private int $osiLayer;

    /**
     * @var non-empty-string
     */
    private string $transport;

    private bool $nativeConnectable;

    /**
     * @var non-empty-string
     */
    private string $defaultScheme;

    /**
     * @param non-empty-string $id
     * @param non-empty-string $name
     * @param non-empty-string $transport
     * @param non-empty-string $defaultScheme
     */
    public function __construct(
        string $id,
        string $name,
        int $osiLayer,
        string $transport,
        bool $nativeConnectable,
        string $defaultScheme,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->osiLayer = $osiLayer;
        $this->transport = $transport;
        $this->nativeConnectable = $nativeConnectable;
        $this->defaultScheme = $defaultScheme;
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
