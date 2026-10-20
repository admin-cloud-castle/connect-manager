<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Exception;

/**
 * Отказ по политике безопасности (SSRF, TLS, валидация endpoint).
 */
final class SecurityException extends ConnectManagerException
{
}
