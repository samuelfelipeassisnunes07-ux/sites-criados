<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        $base = parse_url((string) Config::get('app.url', ''), PHP_URL_PATH) ?: '';
        if ($base !== '' && $base !== '/') {
            $base = rtrim($base, '/');
            if ($path === $base) {
                $path = '/';
            } elseif (str_starts_with($path, $base . '/')) {
                $path = substr($path, strlen($base));
            }
        }

        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }

        return $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public static function integer(string $key, int $default = 0): int
    {
        $value = self::input($key, $default);

        return is_scalar($value) ? (int) $value : $default;
    }

    public static function boolean(string $key): bool
    {
        return filter_var(self::input($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /** @return array<string,mixed> */
    public static function only(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = self::input($key);
        }

        return $data;
    }

    /** @return array<string,mixed>|null */
    public static function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;

        return $value !== null ? (string) $value : null;
    }

    public static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
