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
}
