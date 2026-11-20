<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class StockMovement extends Model
{
    protected static string $table = 'stock_movements';

    public static function record(
        int $productId,
        string $type,
        int $quantity,
        int $previousQuantity,
        int $newQuantity,
        ?int $orderId = null,
        ?int $userId = null,
        ?string $reason = null
    ): int {
        return self::insert([
            'product_id' => $productId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => abs($quantity),
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'reason' => $reason,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forProduct(int $productId, int $limit = 50): array
    {
        return self::fetchAll(
            'SELECT m.*, u.name AS user_name
             FROM stock_movements m LEFT JOIN users u ON u.id = m.user_id
             WHERE m.product_id = ?
             ORDER BY m.created_at DESC, m.id DESC LIMIT ' . max(1, $limit),
            [$productId]
        );
    }
}
