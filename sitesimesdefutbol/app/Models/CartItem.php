<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class CartItem extends Model
{
    protected static string $table = 'cart_items';

    /** @return array<int,array<string,mixed>> */
    public static function withProducts(int $cartId): array
    {
        return self::fetchAll(
            'SELECT ci.id AS cart_item_id, ci.quantity, ci.product_id,
                    p.name, p.slug, p.sku, p.price, p.sale_price, p.stock_quantity, p.is_active,
                    c.slug AS category_slug,
                    (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id
                     ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
             FROM cart_items ci
             INNER JOIN products p ON p.id = ci.product_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE ci.cart_id = ?
             ORDER BY ci.created_at ASC, ci.id ASC',
            [$cartId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function findItem(int $cartId, int $productId): ?array
    {
        return self::fetchOne(
            'SELECT * FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1',
            [$cartId, $productId]
        );
    }

    public static function setQuantity(int $cartId, int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            self::removeProduct($cartId, $productId);
            return;
        }

        self::execute(
            'INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = NOW()',
            [$cartId, $productId, $quantity]
        );
    }

    public static function addQuantity(int $cartId, int $productId, int $quantity): void
    {
        self::execute(
            'INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), 999), updated_at = NOW()',
            [$cartId, $productId, $quantity]
        );
    }

    public static function removeProduct(int $cartId, int $productId): bool
    {
        return self::execute('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?', [$cartId, $productId]);
    }

    public static function clear(int $cartId): void
    {
        self::execute('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
    }

    public static function totalQuantity(int $cartId): int
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?'
        );
        $stmt->execute([$cartId]);

        return (int) $stmt->fetchColumn();
    }

    public static function distinctCount(int $cartId): int
    {
        return self::count('cart_id = ?', [$cartId]);
    }
}
