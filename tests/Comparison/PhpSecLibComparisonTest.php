<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Comparison;

use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Driver\Ssh\SftpClient;
use CloudCastle\ConnectManager\Driver\Ssh\SshConnection;
use CloudCastle\ConnectManager\Driver\Ssh\SshPublicKeyAuth;
use phpseclib3\Crypt\RSA;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Глубокое сравнение SSH/SFTP-слоя с phpseclib (dev-only конкурент).
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[Group('comparison')]
#[CoversNothing]
final class PhpSecLibComparisonTest extends TestCase
{
    /**
     * Оба стека предоставляют password + publickey поверхности.
     */
    public function testAuthSurfaceParity(): void
    {
        self::assertContains('login', get_class_methods(SSH2::class));
        self::assertSame('u', AuthCredentials::withPassword('u', 'p')->username());
        self::assertNotNull(AuthCredentials::withPublicKey('u', "-----BEGIN FAKE-----\nX\n-----END FAKE-----")->privateKey());
        self::assertTrue(class_exists(SshPublicKeyAuth::class));
        self::assertTrue(class_exists(RSA::class));
    }

    /**
     * SFTP операции: get/put/list ↔ get/put/nlist.
     */
    public function testSftpOperationParity(): void
    {
        $ours = get_class_methods(SftpClient::class);
        $theirs = get_class_methods(SFTP::class);
        self::assertContains('get', $ours);
        self::assertContains('put', $ours);
        self::assertContains('list', $ours);
        self::assertContains('get', $theirs);
        self::assertContains('put', $theirs);
        self::assertContains('nlist', $theirs);
    }

    /**
     * exec доступен с обеих сторон.
     */
    public function testExecParity(): void
    {
        self::assertContains('exec', get_class_methods(SSH2::class));
        self::assertContains('exec', get_class_methods(SshConnection::class));
    }

    /**
     * phpseclib не в runtime require.
     */
    public function testPhpSecLibRemainsDevDependency(): void
    {
        $raw = file_get_contents(dirname(__DIR__, 2) . '/composer.json');
        self::assertNotFalse($raw);
        $composer = json_decode($raw, true);
        self::assertIsArray($composer);
        $require = $composer['require'] ?? null;
        $requireDev = $composer['require-dev'] ?? null;
        self::assertIsArray($require);
        self::assertIsArray($requireDev);
        self::assertArrayNotHasKey('phpseclib/phpseclib', $require);
        self::assertArrayHasKey('phpseclib/phpseclib', $requireDev);
    }
}
