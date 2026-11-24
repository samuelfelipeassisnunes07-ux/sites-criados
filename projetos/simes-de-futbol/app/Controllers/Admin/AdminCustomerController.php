<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Address;
use App\Models\AdminLog;
use App\Models\Order;
use App\Models\User;

final class AdminCustomerController extends Controller
{
    public function index(array $params = []): void
    {
        $search = trim((string) Request::input('q', ''));
        $page = max(1, Request::integer('page', 1));

        $this->renderAdmin('admin/customers/index', [
            'title' => 'Clientes',
            'customers' => User::paginateCustomers($search, $page, 15),
            'search' => $search,
        ]);
    }

    public function show(array $params = []): void
    {
        $customer = User::find((int) ($params['id'] ?? 0));
        if ($customer === null || $customer['role'] !== 'customer') {
            abort(404, 'Cliente não encontrado.');
        }

        $page = max(1, Request::integer('page', 1));

        $this->renderAdmin('admin/customers/show', [
            'title' => 'Cliente: ' . $customer['name'],
            'customer' => $customer,
            'addresses' => Address::forUser((int) $customer['id']),
            'orders' => Order::paginateForUser((int) $customer['id'], $page, 10),
            'totalSpent' => (float) (\App\Core\Database::pdo()
                ->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id = " . (int) $customer['id'] . " AND payment_status = 'paid'")
                ->fetchColumn() ?: 0),
        ]);
    }

    public function toggleBlock(array $params = []): void
    {
        $customer = User::find((int) ($params['id'] ?? 0));
        if ($customer === null || $customer['role'] !== 'customer') {
            abort(404, 'Cliente não encontrado.');
        }

        if ((int) Auth::id() === (int) $customer['id']) {
            $this->error('Você não pode bloquear a própria conta.');
            $this->redirect('/admin/clientes');
        }

        $active = User::toggleActive((int) $customer['id']);
        AdminLog::record('customer.toggled', 'user', (int) $customer['id'], ['active' => $active]);

        $this->success($active ? 'Conta desbloqueada.' : 'Conta bloqueada. O cliente não poderá fazer login.');
        $this->redirect('/admin/clientes/' . $customer['id']);
    }
}
