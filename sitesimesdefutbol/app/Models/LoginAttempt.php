<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LoginAttempt extends Model
{
    protected static string $table = 'login_attempts';

    public static function record(string $email, string $ip, bool $success): void
    {
        self::insert([
            'email' => mb_strtolower(trim($email)),
            'ip' => $ip,
            'success' => $success ? 1 : 0,
        ]);
    }

    public static function countFailuresForEmail(string $email, int $minutes = 15): int
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND success = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([mb_strtolower(trim($email)), $minutes]);

        return (int) $stmt->fetchColumn();
    }

    public static function countFailuresForIp(string $ip, int $minutes = 15): int
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip = ? AND success = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$ip, $minutes]);

        return (int) $stmt->fetchColumn();
    }

    public static function clearFailures(string $email, string $ip): void
    {
        self::execute(
            'DELETE FROM login_attempts WHERE success = 0 AND (email = ? OR ip = ?)',
            [mb_strtolower(trim($email)), $ip]
        );
    }
}
