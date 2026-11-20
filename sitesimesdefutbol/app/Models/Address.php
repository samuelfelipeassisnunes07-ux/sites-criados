<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Address extends Model
{
    protected static string $table = 'addresses';

    /** @return array<int,array<string,mixed>> */
    public static function forUser(int $userId): array
    {
        return self::fetchAll(
            'SELECT * FROM addresses WHERE user_id = ? AND deleted_at IS NULL ORDER BY is_default DESC, id DESC',
            [$userId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function findForUser(int $id, int $userId): ?array
    {
        return self::fetchOne(
            'SELECT * FROM addresses WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1',
            [$id, $userId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function defaultForUser(int $userId): ?array
    {
        return self::fetchOne(
            'SELECT * FROM addresses WHERE user_id = ? AND deleted_at IS NULL ORDER BY is_default DESC, id DESC LIMIT 1',
            [$userId]
        );
    }

    /** @param array<string,mixed> $data */
    public static function createForUser(int $userId, array $data): int
    {
        $isDefault = (int) ($data['is_default'] ?? 0) === 1;
        if ($isDefault) {
            self::clearDefault($userId);
        }

        return self::insert([
            'user_id' => $userId,
            'label' => $data['label'] ?? 'Casa',
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'cep' => $data['cep'],
            'street' => $data['street'],
            'number' => $data['number'],
            'complement' => $data['complement'] ?? null,
            'district' => $data['district'],
            'city' => $data['city'],
            'state' => strtoupper((string) $data['state']),
            'is_default' => $isDefault ? 1 : 0,
        ]);
    }

    /** @param array<string,mixed> $data */
    public static function updateForUser(int $id, int $userId, array $data): bool
    {
        if (self::findForUser($id, $userId) === null) {
            return false;
        }

        $isDefault = (int) ($data['is_default'] ?? 0) === 1;
        if ($isDefault) {
            self::clearDefault($userId);
        }

        return self::updateById($id, [
            'label' => $data['label'] ?? 'Casa',
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'cep' => $data['cep'],
            'street' => $data['street'],
            'number' => $data['number'],
            'complement' => $data['complement'] ?? null,
            'district' => $data['district'],
            'city' => $data['city'],
            'state' => strtoupper((string) $data['state']),
            'is_default' => $isDefault ? 1 : 0,
        ]);
    }

    public static function softDeleteForUser(int $id, int $userId): bool
    {
        $address = self::findForUser($id, $userId);
        if ($address === null) {
            return false;
        }

        $result = self::updateById($id, ['deleted_at' => date('Y-m-d H:i:s'), 'is_default' => 0]);
        if ((int) $address['is_default'] === 1) {
            $next = self::fetchOne(
                'SELECT id FROM addresses WHERE user_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1',
                [$userId]
            );
            if ($next !== null) {
                self::updateById((int) $next['id'], ['is_default' => 1]);
            }
        }

        return $result;
    }

    public static function setDefaultForUser(int $id, int $userId): bool
    {
        if (self::findForUser($id, $userId) === null) {
            return false;
        }

        self::clearDefault($userId);

        return self::updateById($id, ['is_default' => 1]);
    }

    public static function clearDefault(int $userId): void
    {
        self::execute('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
    }
}
