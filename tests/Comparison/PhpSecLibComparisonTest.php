<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Comparison;

use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Driver\Ssh\SshConnection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

/**
 * Сравнительные проверки с phpseclib (только require-dev, не runtime пакета).
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
#[Group('comparison')]
#[CoversNothing]
final class PhpSecLibComparisonTest extends TestCase
{
    /**
     * phpseclib доступен в dev и является конкурентом по SSH/SFTP API.
     */
    public function testPhpSecLibIsDevCompetitorNotRuntimeDependency(): void
    {
        $raw = file_get_contents(dirname(__DIR__, 2) . '/composer.json');
        self::assertNotFalse($raw);
        $composer = json_decode($raw, true);
        self::assertIsArray($composer);
        /** @var array<string, mixed> $require */
        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        /** @var array<string, mixed> $requireDev */
        $requireDev = is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];

        self::assertArrayNotHasKey('phpseclib/phpseclib', $require);
        self::assertArrayHasKey('phpseclib/phpseclib', $requireDev);
        self::assertTrue(class_exists(SSH2::class));
        self::assertTrue(class_exists(SFTP::class));
        self::assertTrue(class_exists(SshConnection::class));
        self::assertTrue(class_exists(ConnectManager::class));
        self::assertTrue(class_exists(AuthCredentials::class));
    }

    /**
     * Поверхности API сопоставимы: connect/auth/exec у конкурента существуют.
     */
    public function testCompetitorApiSurface(): void
    {
        $sshMethods = get_class_methods(SSH2::class);
        $sftpMethods = get_class_methods(SFTP::class);
        self::assertContains('login', $sshMethods);
        self::assertContains('exec', $sshMethods);
        self::assertContains('get', $sftpMethods);
        self::assertContains('put', $sftpMethods);
        self::assertContains('nlist', $sftpMethods);
    }
}
