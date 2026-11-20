<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Category extends Model
{
    protected static string $table = 'categories';

    /** @return array<int,array<string,mixed>> */
    public static function active(): array
    {
        return self::fetchAll(
            'SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function allOrdered(): array
    {
        return self::fetchAll('SELECT * FROM categories ORDER BY sort_order ASC, name ASC');
    }

    /** @return array<int,array<string,mixed>> */
    public static function withProductCounts(): array
    {
        return self::fetchAll(
            'SELECT c.*, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC'
        );
    }

    /** @return array<string,mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }

    public static function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categories WHERE name = ?';
        $params = [trim($name)];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $stmt = \App\Core\Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public static function options(): array
    {
        return self::fetchAll('SELECT id, name FROM categories ORDER BY name ASC');
    }

    public static function productCount(int $categoryId): int
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'SELECT COUNT(*) FROM products WHERE category_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$categoryId]);

        return (int) $stmt->fetchColumn();
    }
}
