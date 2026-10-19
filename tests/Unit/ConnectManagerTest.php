<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Exception\ConnectManagerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты заглушки ConnectManager.
 */
#[CoversClass(ConnectManager::class)]
final class ConnectManagerTest extends TestCase
{
    /**
     * Проверяет, что name() возвращает composer-имя пакета.
     */
    public function testNameReturnsComposerPackageName(): void
    {
        $package = new ConnectManager();

        self::assertSame('cloud-castle/connect-manager', $package->name());
    }

    /**
     * Проверяет, что abort() бросает ConnectManagerException.
     */
    public function testAbortThrowsConnectManagerException(): void
    {
        $package = new ConnectManager();

        $this->expectException(ConnectManagerException::class);
        $this->expectExceptionMessage('тест');

        $package->abort('тест');
    }
}
