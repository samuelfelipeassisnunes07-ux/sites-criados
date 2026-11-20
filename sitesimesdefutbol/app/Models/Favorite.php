<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Favorite extends Model
{
    protected static string $table = 'favorites';

    public static function exists(int $userId, int $productId): bool
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'SELECT COUNT(*) FROM favorites WHERE user_id = ? AND product_id = ?'
        );
        $stmt->execute([$userId, $productId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Alterna o favorito e retorna o novo estado. */
    public static function toggle(int $userId, int $productId): bool
    {
        if (self::exists($userId, $productId)) {
            self::execute('DELETE FROM favorites WHERE user_id = ? AND product_id = ?', [$userId, $productId]);
            return false;
        }

        self::insert(['user_id' => $userId, 'product_id' => $productId]);

        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public static function withProducts(int $userId): array
    {
        return self::fetchAll(
            'SELECT f.created_at AS favorited_at, p.*, c.slug AS category_slug,
                (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id
                 ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
             FROM favorites f
             INNER JOIN products p ON p.id = f.product_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE f.user_id = ? AND p.is_active = 1 AND p.deleted_at IS NULL
             ORDER BY f.created_at DESC',
            [$userId]
        );
    }

    /** @return array<int,int> */
    public static function productIds(int $userId): array
    {
        $rows = self::fetchAll('SELECT product_id FROM favorites WHERE user_id = ?', [$userId]);

        return array_map(static fn (array $row): int => (int) $row['product_id'], $rows);
    }

    public static function countForUser(int $userId): int
    {
        return self::count('user_id = ?', [$userId]);
    }
}
