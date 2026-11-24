<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

final class AdminDashboardController extends Controller
{
    public function index(array $params = []): void
    {
        $this->renderAdmin('admin/dashboard', [
            'title' => 'Dashboard',
            'stats' => Order::stats(),
            'statusCounts' => Order::statusCounts(),
            'salesByDay' => Order::salesByDay(7),
            'lowStock' => Product::lowStock(8),
            'recentOrders' => Order::recent(6),
            'latestLogs' => AdminLog::latest(6),
            'totalProducts' => Product::totalActive(),
            'totalCustomers' => User::totalCustomers(),
        ]);
    }
}
