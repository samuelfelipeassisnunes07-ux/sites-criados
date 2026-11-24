<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;

final class Order extends Model
{
    protected static string $table = 'orders';

    /** @var list<string> */
    public const PAID_STATUSES = ['pago', 'em_preparacao', 'enviado', 'entregue'];

    /** @var list<string> */
    public const ALL_STATUSES = [
        'aguardando_pagamento', 'pago', 'em_preparacao', 'enviado', 'entregue', 'cancelado',
    ];

    /** @return array<string,mixed>|null */
    public static function lockById(int $id): ?array
    {
        return self::fetchOne('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findByCode(string $code): ?array
    {
        return self::findBy('code', $code);
    }

    /** @return array<string,mixed>|null */
    public static function findForUser(int $id, int $userId): ?array
    {
        return self::fetchOne(
            'SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1',
            [$id, $userId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function findByCodeForUser(string $code, int $userId): ?array
    {
        return self::fetchOne(
            'SELECT * FROM orders WHERE code = ? AND user_id = ? LIMIT 1',
            [$code, $userId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $orderId): array
    {
        return self::fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC', [$orderId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function history(int $orderId): array
    {
        return self::fetchAll(
            'SELECT h.*, u.name AS changed_by_name
             FROM order_status_history h LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.order_id = ? ORDER BY h.created_at ASC, h.id ASC',
            [$orderId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function payment(int $orderId): ?array
    {
        return self::fetchOne(
            'SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1',
            [$orderId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function payments(int $orderId): array
    {
        return self::fetchAll('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$orderId]);
    }

    public static function paginateForUser(int $userId, int $page, int $perPage = 10): Paginator
    {
        return self::paginate('user_id = ?', [$userId], 'created_at DESC, id DESC', $page, $perPage);
    }

    /**
     * @param array{q?:string,status?:string,date_from?:string,date_to?:string} $filters
     */
    public static function paginateAdmin(array $filters, int $page, int $perPage = 15): Paginator
    {
        $where = ['1 = 1'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(o.code LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, self::ALL_STATUSES, true)) {
            $where[] = 'o.status = ?';
            $params[] = $status;
        }

        $dateFrom = (string) ($filters['date_from'] ?? '');
        if ($dateFrom !== '') {
            $where[] = 'o.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = (string) ($filters['date_to'] ?? '');
        if ($dateTo !== '') {
            $where[] = 'o.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM orders o INNER JOIN users u ON u.id = o.user_id WHERE ' . $whereSql
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT o.*, u.name AS customer_name, u.email AS customer_email
                FROM orders o INNER JOIN users u ON u.id = o.user_id
                WHERE ' . $whereSql . '
                ORDER BY o.created_at DESC, o.id DESC
                LIMIT ? OFFSET ?';

        $stmt = Database::pdo()->prepare($sql);
        $index = 1;
        foreach ($params as $param) {
            $stmt->bindValue($index++, $param);
        }
        $stmt->bindValue($index++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($index, $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return new Paginator($stmt->fetchAll(), $total, $perPage, $page);
    }

    /** @return array<string,mixed> */
    public static function stats(): array
    {
        $monthStart = date('Y-m-01 00:00:00');
        $todayStart = date('Y-m-d 00:00:00');

        $month = self::fetchOne(
            "SELECT COALESCE(SUM(total),0) AS revenue, COUNT(*) AS orders_count
             FROM orders WHERE payment_status = 'paid' AND created_at >= ?",
            [$monthStart]
        ) ?? ['revenue' => '0', 'orders_count' => '0'];

        $today = self::fetchOne(
            'SELECT COUNT(*) AS orders_count FROM orders WHERE created_at >= ?',
            [$todayStart]
        ) ?? ['orders_count' => '0'];

        $allTime = self::fetchOne(
            "SELECT COALESCE(SUM(total),0) AS revenue FROM orders WHERE payment_status = 'paid'"
        ) ?? ['revenue' => '0'];

        return [
            'revenue_month' => (float) $month['revenue'],
            'orders_month' => (int) $month['orders_count'],
            'orders_today' => (int) $today['orders_count'],
            'revenue_total' => (float) $allTime['revenue'],
            'pending_payment' => self::count("status = 'aguardando_pagamento'"),
            'in_progress' => self::count("status IN ('pago','em_preparacao','enviado')"),
            'delivered' => self::count("status = 'entregue'"),
            'canceled' => self::count("status = 'cancelado'"),
            'total_orders' => self::count('1'),
        ];
    }

    /** @return array<string,int> */
    public static function statusCounts(): array
    {
        $rows = self::fetchAll('SELECT status, COUNT(*) AS total FROM orders GROUP BY status');
        $counts = array_fill_keys(self::ALL_STATUSES, 0);
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 8): array
    {
        return self::fetchAll(
            'SELECT o.*, u.name AS customer_name
             FROM orders o INNER JOIN users u ON u.id = o.user_id
             ORDER BY o.created_at DESC, o.id DESC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<int,array{date:string,total:float,orders:int}> */
    public static function salesByDay(int $days = 7): array
    {
        $days = max(1, $days);
        $start = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

        $rows = self::fetchAll(
            "SELECT DATE(created_at) AS day, COALESCE(SUM(total),0) AS total, COUNT(*) AS orders
             FROM orders
             WHERE payment_status = 'paid' AND created_at >= ?
             GROUP BY DATE(created_at) ORDER BY day ASC",
            [$start]
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['day']] = [
                'total' => (float) $row['total'],
                'orders' => (int) $row['orders'],
            ];
        }

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $result[] = [
                'date' => $date,
                'total' => $indexed[$date]['total'] ?? 0.0,
                'orders' => $indexed[$date]['orders'] ?? 0,
            ];
        }

        return $result;
    }

    public static function revenueBetween(string $start, string $end): float
    {
        $row = self::fetchOne(
            "SELECT COALESCE(SUM(total),0) AS revenue FROM orders
             WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?",
            [$start, $end]
        );

        return (float) ($row['revenue'] ?? 0);
    }

    public static function countBetween(string $start, string $end): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?'
        );
        $stmt->execute([$start, $end]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public static function topProducts(int $limit, string $start, string $end): array
    {
        return self::fetchAll(
            'SELECT oi.product_name, SUM(oi.quantity) AS quantity, SUM(oi.line_total) AS total
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             WHERE o.payment_status = \'paid\' AND o.created_at BETWEEN ? AND ?
             GROUP BY oi.product_name
             ORDER BY quantity DESC, total DESC LIMIT ' . max(1, $limit),
            [$start, $end]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function forReport(string $start, string $end, string $status = ''): array
    {
        $sql = 'SELECT o.*, u.name AS customer_name, u.email AS customer_email
                FROM orders o INNER JOIN users u ON u.id = o.user_id
                WHERE o.created_at BETWEEN ? AND ?';
        $params = [$start, $end];

        if (in_array($status, self::ALL_STATUSES, true)) {
            $sql .= ' AND o.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY o.created_at ASC, o.id ASC';

        return self::fetchAll($sql, $params);
    }
}
