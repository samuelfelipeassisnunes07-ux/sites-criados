<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;

final class AdminOrderController extends Controller
{
    public function index(array $params = []): void
    {
        $filters = [
            'q' => trim((string) Request::input('q', '')),
            'status' => (string) Request::input('status', ''),
            'date_from' => (string) Request::input('date_from', ''),
            'date_to' => (string) Request::input('date_to', ''),
        ];
        $page = max(1, Request::integer('page', 1));

        $this->renderAdmin('admin/orders/index', [
            'title' => 'Pedidos',
            'orders' => Order::paginateAdmin($filters, $page, 15),
            'filters' => $filters,
            'statuses' => Order::ALL_STATUSES,
            'query' => array_filter($filters, static fn ($value): bool => $value !== ''),
        ]);
    }

    public function show(array $params = []): void
    {
        $order = Order::find((int) ($params['id'] ?? 0));
        if ($order === null) {
            abort(404, 'Pedido não encontrado.');
        }

        $allowed = OrderService::allowedTransitions()[(string) $order['status']] ?? [];

        $this->renderAdmin('admin/orders/show', [
            'title' => 'Pedido ' . $order['code'],
            'order' => $order,
            'customer' => User::find((int) $order['user_id']),
            'items' => Order::items((int) $order['id']),
            'history' => Order::history((int) $order['id']),
            'payments' => Order::payments((int) $order['id']),
            'allowedTransitions' => $allowed,
        ]);
    }

    public function updateStatus(array $params = []): void
    {
        $order = Order::find((int) ($params['id'] ?? 0));
        if ($order === null) {
            abort(404, 'Pedido não encontrado.');
        }

        $status = (string) Request::input('status', '');
        $note = trim((string) Request::input('note', ''));
        $tracking = trim((string) Request::input('tracking_code', ''));

        $result = OrderService::updateStatus(
            (int) $order['id'],
            $status,
            $note !== '' ? $note : null,
            \App\Core\Auth::id(),
            $tracking !== '' ? $tracking : null
        );

        if ($result['ok']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/admin/pedidos/' . $order['id']);
    }

    public function addTracking(array $params = []): void
    {
        $order = Order::find((int) ($params['id'] ?? 0));
        if ($order === null) {
            abort(404, 'Pedido não encontrado.');
        }

        $result = OrderService::addTracking(
            (int) $order['id'],
            (string) Request::input('tracking_code', ''),
            \App\Core\Auth::id()
        );

        $result['ok'] ? $this->success($result['message']) : $this->error($result['message']);
        $this->redirect('/admin/pedidos/' . $order['id']);
    }

    public function cancel(array $params = []): void
    {
        $order = Order::find((int) ($params['id'] ?? 0));
        if ($order === null) {
            abort(404, 'Pedido não encontrado.');
        }

        $note = trim((string) Request::input('note', ''));
        $result = OrderService::cancel((int) $order['id'], $note !== '' ? $note : null, \App\Core\Auth::id());

        $result['ok'] ? $this->success($result['message']) : $this->error($result['message']);
        $this->redirect('/admin/pedidos/' . $order['id']);
    }
}
