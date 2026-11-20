<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;
use App\Core\Paginator;
use App\Core\Request;

final class AdminLog extends Model
{
    protected static string $table = 'admin_logs';

    /** @param array<string,mixed> $details */
    public static function record(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        self::insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details !== [] ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public static function paginateAll(int $page, int $perPage = 20): Paginator
    {
        $where = '1';
        return self::paginate($where, [], 'created_at DESC, id DESC', $page, $perPage);
    }

    /** @return array<int,array<string,mixed>> */
    public static function latest(int $limit = 8): array
    {
        return self::fetchAll(
            'SELECT l.*, u.name AS user_name
             FROM admin_logs l LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.created_at DESC, l.id DESC LIMIT ' . max(1, $limit)
        );
    }
}
