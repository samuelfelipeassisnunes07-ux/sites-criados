<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class OrderItem extends Model
{
    protected static string $table = 'order_items';

    /** @param array<int,array<string,mixed>> $items */
    public static function insertMany(int $orderId, array $items): void
    {
        foreach ($items as $item) {
            self::insert([
                'order_id' => $orderId,
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $item['product_name'],
                'product_sku' => $item['product_sku'],
                'product_image' => $item['product_image'] ?? null,
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'line_total' => $item['line_total'],
            ]);
        }
    }
}
