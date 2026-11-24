<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Banner extends Model
{
    protected static string $table = 'banners';

    /** @return array<int,array<string,mixed>> */
    public static function active(string $position = 'hero'): array
    {
        return self::fetchAll(
            'SELECT * FROM banners
             WHERE is_active = 1 AND position = ?
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY sort_order ASC, id ASC',
            [$position]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function allOrdered(): array
    {
        return self::fetchAll('SELECT * FROM banners ORDER BY sort_order ASC, id DESC');
    }
}
