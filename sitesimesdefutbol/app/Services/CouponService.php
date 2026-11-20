<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;

final class CouponService
{
    /**
     * Valida um cupom para o subtotal informado.
     *
     * @return array{ok:bool,message:string,discount:float,coupon:array<string,mixed>|null}
     */
    public static function validate(?string $code, float $subtotal, int $userId): array
    {
        $code = mb_strtoupper(trim((string) $code));

        if ($code === '') {
            return ['ok' => false, 'message' => 'Informe um código de cupom.', 'discount' => 0.0, 'coupon' => null];
        }

        $coupon = Coupon::findByCode($code);
        if ($coupon === null || (int) $coupon['is_active'] !== 1) {
            return ['ok' => false, 'message' => 'Cupom inválido ou inativo.', 'discount' => 0.0, 'coupon' => null];
        }

        $now = date('Y-m-d H:i:s');

        if ($coupon['starts_at'] !== null && $coupon['starts_at'] !== '' && (string) $coupon['starts_at'] > $now) {
            return ['ok' => false, 'message' => 'Este cupom ainda não está válido.', 'discount' => 0.0, 'coupon' => null];
        }

        if ($coupon['expires_at'] !== null && $coupon['expires_at'] !== '' && (string) $coupon['expires_at'] < $now) {
            return ['ok' => false, 'message' => 'Este cupom expirou.', 'discount' => 0.0, 'coupon' => null];
        }

        if ((float) $coupon['min_order_amount'] > 0 && $subtotal < (float) $coupon['min_order_amount']) {
            return [
                'ok' => false,
                'message' => 'Este cupom exige um valor mínimo de ' . money((float) $coupon['min_order_amount']) . '.',
                'discount' => 0.0,
                'coupon' => null,
            ];
        }

        if ($coupon['max_uses'] !== null && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
            return ['ok' => false, 'message' => 'Este cupom atingiu o limite de utilizações.', 'discount' => 0.0, 'coupon' => null];
        }

        $perUser = (int) $coupon['max_uses_per_user'];
        if ($perUser > 0 && CouponUsage::countForUser((int) $coupon['id'], $userId) >= $perUser) {
            return ['ok' => false, 'message' => 'Você já utilizou este cupom.', 'discount' => 0.0, 'coupon' => null];
        }

        $discount = self::discountFor($coupon, $subtotal);

        return [
            'ok' => true,
            'message' => 'Cupom aplicado: ' . $code,
            'discount' => $discount,
            'coupon' => $coupon,
        ];
    }

    /** @param array<string,mixed> $coupon */
    public static function discountFor(array $coupon, float $subtotal): float
    {
        $subtotal = max(0, $subtotal);

        if ($coupon['type'] === 'percent') {
            $percent = max(0, min(100, (float) $coupon['value']));
            $discount = $subtotal * ($percent / 100);
        } else {
            $discount = (float) $coupon['value'];
        }

        return round(max(0, min($discount, $subtotal)), 2);
    }

    /** Incremento atômico: retorna false se o cupom esgotou nesse intervalo. */
    public static function consume(int $couponId): bool
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'UPDATE coupons SET used_count = used_count + 1
             WHERE id = ? AND (max_uses IS NULL OR used_count < max_uses)'
        );
        $stmt->execute([$couponId]);

        return $stmt->rowCount() > 0;
    }
}
