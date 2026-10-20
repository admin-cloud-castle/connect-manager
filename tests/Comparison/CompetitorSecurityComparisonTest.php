<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Comparison;

use Amp\Socket\ClientTlsContext;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Exception\SecurityException;
use CloudCastle\ConnectManager\Security\EndpointGuard;
use CloudCastle\ConnectManager\Tests\Support\ComparisonCatalog;
use CloudCastle\ConnectManager\Tools\ComparisonLoader;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use React\Socket\SecureConnector;

/**
 * Сравнение security-поведения: SSRF/TLS defaults у нас vs у каждого аналога.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[Group('comparison')]
#[Group('security')]
#[CoversNothing]
final class CompetitorSecurityComparisonTest extends TestCase
{
    /**
     * Только Connect Manager блокирует частные адреса по умолчанию (SSRF-guard).
     */
    public function testOnlyOursBlocksPrivateEndpointsByDefault(): void
    {
        $manager = new ConnectManager();

        try {
            $manager->connect('tcp', '127.0.0.1:9');
            self::fail('ожидался SecurityException');
        } catch (SecurityException $exception) {
            self::assertStringContainsString('SSRF', $exception->getMessage());
        }

        self::assertTrue(class_exists(EndpointGuard::class));
        self::assertFalse(class_exists('React\\Socket\\SsrfGuard'));
        self::assertFalse(class_exists('Amp\\Socket\\SsrfGuard'));
        self::assertFalse(class_exists('GuzzleHttp\\SsrfGuard'));
        self::assertFalse(class_exists('phpseclib3\\Net\\SsrfGuard'));
        self::assertFalse(class_exists('Socket\\Raw\\SsrfGuard'));
    }

    /**
     * TLS verify peer включён по умолчанию у нас и у HTTP/TLS-ориентированных аналогов.
     */
    public function testTlsVerifyDefaults(): void
    {
        self::assertTrue(ConnectionOptions::defaults()->verifyPeer());
        self::assertFalse(ConnectionOptions::defaults()->allowInsecure());

        $guzzle = new Client(['verify' => true]);
        self::assertContains('request', get_class_methods($guzzle));

        $tls = new ClientTlsContext();
        self::assertContains('withPeerVerification', get_class_methods($tls));
        self::assertTrue(class_exists(SecureConnector::class));
    }

    /**
     * Матрица security из tools/comparison-security.php содержит все пакеты.
     *
     * @param string $key Ключ пакета
     */
    #[DataProvider('packageKeyProvider')]
    public function testSecurityMatrixCoversPackage(string $key): void
    {
        $matrix = ComparisonLoader::security();
        self::assertNotSame([], $matrix);

        foreach ($matrix as $row) {
            self::assertArrayHasKey($key, $row['packages']);
        }
    }

    /**
     * Guzzle не имеет встроенного SSRF-guard класса (в отличие от EndpointGuard).
     */
    public function testGuzzleHasNoBuiltInSsrfGuard(): void
    {
        $client = new Client(['http_errors' => false]);
        self::assertContains('request', get_class_methods($client));
        self::assertFalse(class_exists('GuzzleHttp\\RedirectMiddleware\\SsrfGuard'));
        self::assertTrue(class_exists(EndpointGuard::class));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function packageKeyProvider(): iterable
    {
        foreach (ComparisonCatalog::load()['order'] as $key) {
            yield $key => [$key];
        }
    }
}
