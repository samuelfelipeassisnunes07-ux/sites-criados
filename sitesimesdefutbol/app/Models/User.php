<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

final class User extends Model
{
    protected static string $table = 'users';

    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return self::findBy('email', mb_strtolower(trim($email)));
    }

    public static function create(string $name, string $email, string $password, string $role = 'customer'): int
    {
        return self::insert([
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role === 'admin' ? 'admin' : 'customer',
            'is_active' => 1,
        ]);
    }

    public static function updateProfile(int $id, string $name, string $email, ?string $phone): bool
    {
        return self::updateById($id, [
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'phone' => $phone !== null && $phone !== '' ? trim($phone) : null,
        ]);
    }

    public static function setPassword(int $id, string $password): bool
    {
        return self::updateById($id, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public static function recordLogin(int $id): void
    {
        self::updateById($id, ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    public static function toggleActive(int $id): bool
    {
        $user = self::find($id);
        if ($user === null) {
            return false;
        }

        $newState = (int) $user['is_active'] === 1 ? 0 : 1;
        self::updateById($id, ['is_active' => $newState]);

        return $newState === 1;
    }

    public static function paginateCustomers(string $search, int $page, int $perPage = 15): Paginator
    {
        $where = "role = 'customer' AND deleted_at IS NULL";
        $params = [];

        if (trim($search) !== '') {
            $where .= ' AND (name LIKE ? OR email LIKE ?)';
            $like = '%' . trim($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return self::paginate($where, $params, 'created_at DESC', $page, $perPage);
    }

    public static function totalCustomers(): int
    {
        return self::count("role = 'customer' AND deleted_at IS NULL");
    }

    /** @return array<int,array<string,mixed>> */
    public static function admins(): array
    {
        return self::fetchAll("SELECT id, name, email FROM users WHERE role = 'admin' AND is_active = 1 AND deleted_at IS NULL");
    }
}
