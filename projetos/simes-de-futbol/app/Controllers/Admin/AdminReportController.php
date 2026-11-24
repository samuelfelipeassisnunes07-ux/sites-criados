<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Models\Order;
use App\Services\ReportService;

final class AdminReportController extends Controller
{
    public function index(array $params = []): void
    {
        [$start, $end, $status] = $this->filters();

        $orders = Order::forReport($start . ' 00:00:00', $end . ' 23:59:59', $status);

        $this->renderAdmin('admin/reports/index', [
            'title' => 'Relatórios',
            'summary' => ReportService::summary($start, $end, $status),
            'orders' => array_slice($orders, 0, 100),
            'totalOrders' => count($orders),
            'start' => $start,
            'end' => $end,
            'status' => $status,
            'statuses' => Order::ALL_STATUSES,
        ]);
    }

    public function exportCsv(array $params = []): void
    {
        [$start, $end, $status] = $this->filters();

        ReportService::streamCsv($start, $end, $status);
    }

    /** @return array{0:string,1:string,2:string} */
    private function filters(): array
    {
        $start = (string) Request::input('date_from', date('Y-m-01'));
        $end = (string) Request::input('date_to', date('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || strtotime($start) === false) {
            $start = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || strtotime($end) === false) {
            $end = date('Y-m-d');
        }
        if (strtotime($end) < strtotime($start)) {
            [$start, $end] = [$end, $start];
        }

        $status = (string) Request::input('status', '');
        if (!in_array($status, Order::ALL_STATUSES, true)) {
            $status = '';
        }

        return [$start, $end, $status];
    }
}
