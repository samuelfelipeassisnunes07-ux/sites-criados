<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Setting extends Model
{
    protected static string $table = 'settings';

    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /** @return array<string,string> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = self::fetchAll('SELECT name, value FROM settings');
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['name']] = (string) ($row['value'] ?? '');
        }

        return self::$cache = $settings;
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        $all = self::all();

        return array_key_exists($name, $all) && $all[$name] !== '' ? $all[$name] : $default;
    }

    public static function set(string $name, ?string $value): void
    {
        self::execute(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, $value]
        );
        self::$cache = null;
    }

    /** @param array<string,string|null> $values */
    public static function setMany(array $values): void
    {
        foreach ($values as $name => $value) {
            self::set((string) $name, $value);
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
