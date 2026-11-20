<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ProductImage extends Model
{
    protected static string $table = 'product_images';

    /** @return array<int,array<string,mixed>> */
    public static function forProduct(int $productId): array
    {
        return self::fetchAll(
            'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC',
            [$productId]
        );
    }

    public static function create(int $productId, string $filename, ?string $alt = null, int $sortOrder = 0, bool $primary = false): int
    {
        return self::insert([
            'product_id' => $productId,
            'filename' => $filename,
            'alt' => $alt,
            'sort_order' => $sortOrder,
            'is_primary' => $primary ? 1 : 0,
        ]);
    }

    public static function setPrimary(int $imageId, int $productId): void
    {
        self::execute('UPDATE product_images SET is_primary = 0 WHERE product_id = ?', [$productId]);
        self::execute(
            'UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?',
            [$imageId, $productId]
        );
    }

    public static function deleteForProduct(int $imageId, int $productId): ?string
    {
        $image = self::fetchOne(
            'SELECT * FROM product_images WHERE id = ? AND product_id = ? LIMIT 1',
            [$imageId, $productId]
        );

        if ($image === null) {
            return null;
        }

        self::deleteById($imageId);
        $remaining = self::forProduct($productId);
        if ($remaining !== [] && (int) $remaining[0]['is_primary'] === 0) {
            self::setPrimary((int) $remaining[0]['id'], $productId);
        }

        return (string) $image['filename'];
    }

    public static function countForProduct(int $productId): int
    {
        return self::count('product_id = ?', [$productId]);
    }
}
