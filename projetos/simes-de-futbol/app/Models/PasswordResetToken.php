<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Str;

final class PasswordResetToken extends Model
{
    protected static string $table = 'password_reset_tokens';

    /**
     * Cria um novo token de recuperação, invalidando os anteriores.
     *
     * @return array{selector:string,token:string}
     */
    public static function createForUser(int $userId): array
    {
        self::execute(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL',
            [$userId]
        );

        $selector = Str::randomToken(16);
        $token = Str::randomToken(32);

        self::insert([
            'user_id' => $userId,
            'selector' => $selector,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        return ['selector' => $selector, 'token' => $token];
    }

    /** @return array<string,mixed>|null */
    public static function findValid(string $selector, string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $row = self::fetchOne(
            'SELECT * FROM password_reset_tokens
             WHERE selector = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1',
            [$selector]
        );

        if ($row === null) {
            return null;
        }

        return hash_equals((string) $row['token_hash'], hash('sha256', $token)) ? $row : null;
    }

    public static function markUsed(int $id): void
    {
        self::updateById($id, ['used_at' => date('Y-m-d H:i:s')]);
    }

    public static function invalidateAllForUser(int $userId): void
    {
        self::execute(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL',
            [$userId]
        );
    }
}
