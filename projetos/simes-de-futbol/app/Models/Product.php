<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;

final class Product extends Model
{
    protected static string $table = 'products';

    /** Expressão SQL do preço efetivamente cobrado (promocional quando válido). */
    public static function effectivePriceSql(string $alias = 'p'): string
    {
        return "(CASE WHEN {$alias}.sale_price IS NOT NULL AND {$alias}.sale_price > 0 AND {$alias}.sale_price < {$alias}.price"
            . " THEN {$alias}.sale_price ELSE {$alias}.price END)";
    }

    /**
     * Catálogo público com busca, filtros, ordenação e paginação.
     *
     * @param array{q?:string,category?:string,min_price?:string|float|null,max_price?:string|float|null,sort?:string} $filters
     */
    public static function catalog(array $filters, int $page, int $perPage = 12): Paginator
    {
        $where = ['p.is_active = 1', 'p.deleted_at IS NULL', 'c.is_active = 1'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $where[] = 'c.slug = ?';
            $params[] = $category;
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '' && $filters['min_price'] !== null) {
            $where[] = self::effectivePriceSql() . ' >= ?';
            $params[] = (float) $filters['min_price'];
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '' && $filters['max_price'] !== null) {
            $where[] = self::effectivePriceSql() . ' <= ?';
            $params[] = (float) $filters['max_price'];
        }

        $orderBy = match ((string) ($filters['sort'] ?? 'relevance')) {
            'price_asc' => self::effectivePriceSql() . ' ASC, p.name ASC',
            'price_desc' => self::effectivePriceSql() . ' DESC, p.name ASC',
            'newest' => 'p.created_at DESC, p.id DESC',
            'name_asc' => 'p.name ASC',
            'name_desc' => 'p.name DESC',
            default => 'p.is_featured DESC, p.created_at DESC, p.id DESC',
        };

        $whereSql = implode(' AND ', $where);
        $countStmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM products p INNER JOIN categories c ON c.id = p.category_id WHERE ' . $whereSql
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                    (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id
                     ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id
                WHERE ' . $whereSql . '
                ORDER BY ' . $orderBy . '
                LIMIT ? OFFSET ?';

        $stmt = Database::pdo()->prepare($sql);
        $index = 1;
        foreach ($params as $param) {
            $stmt->bindValue($index++, $param);
        }
        $stmt->bindValue($index++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($index, $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return new Paginator($stmt->fetchAll(), $total, $perPage, $page);
    }

    /** @return array<string,mixed>|null */
    public static function lockById(int $id): ?array
    {
        return self::fetchOne('SELECT * FROM products WHERE id = ? FOR UPDATE', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findActiveBySlug(string $slug): ?array
    {
        return self::fetchOne(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.slug = ? AND p.is_active = 1 AND p.deleted_at IS NULL AND c.is_active = 1
             LIMIT 1',
            [$slug]
        );
    }

    public static function findActive(int $id): ?array
    {
        return self::fetchOne(
            'SELECT * FROM products WHERE id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function featured(int $limit = 8): array
    {
        return self::fetchAll(
            'SELECT p.*, c.slug AS category_slug,
                (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id
                 ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND p.is_featured = 1 AND p.deleted_at IS NULL
             ORDER BY p.created_at DESC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function newArrivals(int $limit = 8): array
    {
        return self::fetchAll(
            'SELECT p.*, c.slug AS category_slug,
                (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id
                 ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND p.deleted_at IS NULL
             ORDER BY p.created_at DESC, p.id DESC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function promotions(int $limit = 8): array
    {
        return self::fetchAll(
            'SELECT p.*, c.slug AS category_slug,
                (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id
                 ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND p.deleted_at IS NULL
               AND p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price
             ORDER BY p.updated_at DESC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function related(int $categoryId, int $excludeId, int $limit = 4): array
    {
        return self::fetchAll(
            'SELECT p.*, c.slug AS category_slug,
                (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id
                 ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.category_id = ? AND p.id <> ? AND p.is_active = 1 AND p.deleted_at IS NULL
             ORDER BY p.is_featured DESC, p.created_at DESC LIMIT ' . max(1, $limit),
            [$categoryId, $excludeId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function images(int $productId): array
    {
        return self::fetchAll(
            'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC',
            [$productId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function lockByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM products WHERE id IN (' . $placeholders . ') ORDER BY id FOR UPDATE'
        );
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    public static function paginateAdmin(string $search, string $status, int $categoryId, int $page, int $perPage = 15): Paginator
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if (trim($search) !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
            $like = '%' . trim($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($status === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'p.is_active = 0';
        } elseif ($status === 'low_stock') {
            $where[] = 'p.stock_quantity <= p.low_stock_threshold';
        }

        if ($categoryId > 0) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = Database::pdo()->prepare('SELECT COUNT(*) FROM products p WHERE ' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT p.*, c.name AS category_name,
                    (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id
                     ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id
                WHERE ' . $whereSql . '
                ORDER BY p.created_at DESC, p.id DESC
                LIMIT ? OFFSET ?';

        $stmt = Database::pdo()->prepare($sql);
        $index = 1;
        foreach ($params as $param) {
            $stmt->bindValue($index++, $param);
        }
        $stmt->bindValue($index++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($index, $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return new Paginator($stmt->fetchAll(), $total, $perPage, $page);
    }

    /** @return array<int,array<string,mixed>> */
    public static function lowStock(int $limit = 10): array
    {
        return self::fetchAll(
            'SELECT id, name, sku, stock_quantity, low_stock_threshold
             FROM products
             WHERE is_active = 1 AND deleted_at IS NULL AND stock_quantity <= low_stock_threshold
             ORDER BY stock_quantity ASC, name ASC LIMIT ' . max(1, $limit)
        );
    }

    public static function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM products WHERE slug = ?';
        $params = [$slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function skuExists(string $sku, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM products WHERE sku = ?';
        $params = [$sku];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function totalActive(): int
    {
        return self::count('is_active = 1 AND deleted_at IS NULL');
    }
}
