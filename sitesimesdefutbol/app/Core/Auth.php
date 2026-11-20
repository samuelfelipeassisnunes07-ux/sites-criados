<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    /** @var array<string,mixed>|null */
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }

        self::$loaded = true;
        $id = Session::get('auth_user_id');
        if (!$id) {
            return null;
        }

        $user = User::find((int) $id);
        if ($user === null || (int) ($user['is_active'] ?? 0) !== 1) {
            Session::remove('auth_user_id');
            return null;
        }

        self::$user = $user;

        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user !== null ? (int) $user['id'] : null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();

        return $user !== null && ($user['role'] ?? '') === 'admin';
    }

    /** @param array<string,mixed> $user */
    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('auth_user_id', (int) $user['id']);
        Csrf::rotate();
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$loaded = true;
        Session::destroy();
    }

    /** Força recarga do usuário (após alteração de perfil). */
    public static function refresh(): void
    {
        self::$user = null;
        self::$loaded = false;
    }
}
