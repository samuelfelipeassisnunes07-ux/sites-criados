<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class OrderStatusHistory extends Model
{
    protected static string $table = 'order_status_history';

    public static function add(
        int $orderId,
        ?string $fromStatus,
        string $toStatus,
        ?string $note = null,
        ?int $changedBy = null
    ): int {
        return self::insert([
            'order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'changed_by' => $changedBy,
        ]);
    }
}
