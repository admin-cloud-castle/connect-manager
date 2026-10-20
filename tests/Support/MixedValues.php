<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

/**
 * Поставщик mixed-значений для негативных тестов без подавления анализаторов.
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.MissingImport")
 * @SuppressWarnings("PHPMD.ExcessivePublicCount")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
 */
final class MixedValues
{
    /**
     * Возвращает значение, не являющееся stream resource.
     */
    public static function notAStream(): mixed
    {
        return 'not-a-stream';
    }

    /**
     * Открывает memory-stream.
     *
     * @return resource
     */
    public static function memoryStream()
    {
        $stream = fopen('php://memory', 'r+b');

        if ($stream === false) {
            throw new \RuntimeException('Не удалось открыть php://memory');
        }

        return $stream;
    }

    /**
     * Снимает статический тип resource (для проверки is_resource после fclose).
     */
    public static function loosen(mixed $value): mixed
    {
        return $value;
    }
}
