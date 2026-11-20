<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class CouponUsage extends Model
{
    protected static string $table = 'coupon_usages';

    public static function record(int $couponId, int $userId, int $orderId): int
    {
        return self::insert([
            'coupon_id' => $couponId,
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);
    }

    public static function countForUser(int $couponId, int $userId): int
    {
        return self::count('coupon_id = ? AND user_id = ?', [$couponId, $userId]);
    }

    public static function countForCoupon(int $couponId): int
    {
        return self::count('coupon_id = ?', [$couponId]);
    }
}
