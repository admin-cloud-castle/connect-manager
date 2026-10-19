<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Exception\PackageException;
use CloudCastle\ConnectManager\Package;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты заглушки Package.
 */
#[CoversClass(Package::class)]
final class PackageTest extends TestCase
{
    /**
     * Проверяет, что name() возвращает composer-имя пакета.
     */
    public function testNameReturnsComposerPackageName(): void
    {
        $package = new Package();

        self::assertSame('cloud-castle/connect-manager', $package->name());
    }

    /**
     * Проверяет, что abort() бросает PackageException.
     */
    public function testAbortThrowsPackageException(): void
    {
        $package = new Package();

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('тест');

        $package->abort('тест');
    }
}
