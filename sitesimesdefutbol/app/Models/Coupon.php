<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

final class Coupon extends Model
{
    protected static string $table = 'coupons';

    /** @return array<string,mixed>|null */
    public static function findByCode(string $code): ?array
    {
        return self::findBy('code', mb_strtoupper(trim($code)));
    }

    public static function incrementUsage(int $couponId): void
    {
        self::execute('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$couponId]);
    }

    public static function paginateAll(int $page, int $perPage = 15): Paginator
    {
        return self::paginate('1', [], 'created_at DESC, id DESC', $page, $perPage);
    }

    public static function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM coupons WHERE code = ?';
        $params = [mb_strtoupper(trim($code))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $stmt = \App\Core\Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
