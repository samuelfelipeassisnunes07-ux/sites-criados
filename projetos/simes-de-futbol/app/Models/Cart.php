<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Cart extends Model
{
    protected static string $table = 'carts';

    /** @return array<string,mixed>|null */
    public static function findByToken(string $token): ?array
    {
        return self::findBy('token', $token);
    }

    /** @return array<string,mixed>|null */
    public static function findForUser(int $userId): ?array
    {
        return self::fetchOne(
            'SELECT * FROM carts WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1',
            [$userId]
        );
    }

    /** @return array<string,mixed> */
    public static function getOrCreate(string $token, ?int $userId): array
    {
        $cart = self::findByToken($token);
        if ($cart !== null) {
            if ($userId !== null && $cart['user_id'] === null) {
                self::updateById((int) $cart['id'], ['user_id' => $userId]);
                $cart['user_id'] = $userId;
            }

            return $cart;
        }

        $id = self::insert(['token' => $token, 'user_id' => $userId]);

        return self::find($id) ?? [];
    }

    public static function attachUser(int $cartId, int $userId): void
    {
        self::updateById($cartId, ['user_id' => $userId]);
    }

    public static function touch(int $cartId): void
    {
        self::updateById($cartId, ['updated_at' => date('Y-m-d H:i:s')]);
    }
}
