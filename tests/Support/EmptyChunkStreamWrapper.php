<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

/**
 * Stream, который не в EOF, но fread возвращает пустую строку.
 *
 * @psalm-api
 * @psalm-suppress PossiblyUnusedMethod
 * @psalm-suppress UnusedParam
 * @psalm-suppress PossiblyUnusedProperty
 *
 * @SuppressWarnings("PHPMD.CamelCaseMethodName")
 * @SuppressWarnings("PHPMD.CamelCaseParameterName")
 * @SuppressWarnings("PHPMD.CamelCaseVariableName")
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 * @SuppressWarnings("PHPMD.StaticAccess")
 */

// phpcs:disable PSR1.Methods.CamelCapsMethodName

final class EmptyChunkStreamWrapper
{
    /**
     * Контекст PHP stream wrapper (заполняется движком PHP).
     *
     * @var resource|null
     *
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $context;

    /**
     * Регистрирует обёртку.
     */
    public static function register(): void
    {
        if (!in_array('ccempty', stream_get_wrappers(), true)) {
            stream_wrapper_register('ccempty', self::class);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        unset($path, $mode, $options, $opened_path);

        return true;
    }

    public function stream_read(int $count): string
    {
        unset($count);

        return '';
    }

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_set_option(int $option, int $arg1, mixed $arg2): bool
    {
        unset($option, $arg1, $arg2);

        return false;
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0,
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 0,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => 0,
            'blocks' => 0,
        ];
    }
}
