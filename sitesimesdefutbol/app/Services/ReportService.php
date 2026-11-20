<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Response;
use App\Models\Order;

final class ReportService
{
    /** @return array<string,mixed> */
    public static function summary(string $start, string $end, string $status = ''): array
    {
        $startDate = $start . ' 00:00:00';
        $endDate = $end . ' 23:59:59';

        $paid = Order::revenueBetween($startDate, $endDate);
        $orders = Order::countBetween($startDate, $endDate);

        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM orders WHERE status = 'cancelado' AND created_at BETWEEN ? AND ?"
        );
        $stmt->execute([$startDate, $endDate]);
        $canceled = (int) $stmt->fetchColumn();

        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM orders WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?"
        );
        $stmt->execute([$startDate, $endDate]);
        $paidOrders = (int) $stmt->fetchColumn();

        return [
            'start' => $start,
            'end' => $end,
            'status' => $status,
            'orders' => $orders,
            'paid_orders' => $paidOrders,
            'revenue' => $paid,
            'canceled' => $canceled,
            'average_ticket' => $paidOrders > 0 ? round($paid / $paidOrders, 2) : 0.0,
            'top_products' => Order::topProducts(10, $startDate, $endDate),
        ];
    }

    public static function streamCsv(string $start, string $end, string $status = ''): never
    {
        $filename = 'relatorio-pedidos-' . $start . '-a-' . $end . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            Response::abort(500, 'Não foi possível gerar o arquivo.');
        }

        // BOM para o Excel reconhecer UTF-8.
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'Código', 'Data', 'Cliente', 'E-mail', 'Status', 'Pagamento',
            'Subtotal', 'Desconto', 'Frete', 'Total', 'Rastreamento',
        ], ';');

        $orders = Order::forReport($start . ' 00:00:00', $end . ' 23:59:59', $status);

        foreach ($orders as $order) {
            fputcsv($output, [
                safe_csv($order['code']),
                safe_csv(format_datetime($order['created_at'])),
                safe_csv($order['customer_name']),
                safe_csv($order['customer_email']),
                safe_csv(status_label($order['status'])),
                safe_csv(payment_status_label($order['payment_status'])),
                safe_csv(number_format((float) $order['subtotal'], 2, ',', '')),
                safe_csv(number_format((float) $order['discount_total'], 2, ',', '')),
                safe_csv(number_format((float) $order['shipping_total'], 2, ',', '')),
                safe_csv(number_format((float) $order['total'], 2, ',', '')),
                safe_csv($order['tracking_code'] ?? ''),
            ], ';');
        }

        fclose($output);
        exit;
    }
}
