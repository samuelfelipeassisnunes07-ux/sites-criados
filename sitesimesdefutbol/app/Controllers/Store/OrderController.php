<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Response;
use App\Models\Order;
use App\Services\PaymentService;

final class OrderController extends Controller
{
    public function confirmation(array $params = []): void
    {
        $order = $this->findOrder((string) ($params['code'] ?? ''));

        $this->render('store/order-confirmation', [
            'title' => 'Pedido ' . $order['code'],
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'payment' => Order::payment((int) $order['id']),
            'isDemo' => PaymentService::isDemo(),
        ]);
    }

    public function simulatePayment(array $params = []): void
    {
        $order = $this->findOrder((string) ($params['code'] ?? ''));

        $result = PaymentService::confirmDemo($order, (int) Auth::id());
        if ($result['ok']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/pedido/' . $order['code'] . '/confirmacao');
    }

    public function retryPayment(array $params = []): void
    {
        $order = $this->findOrder((string) ($params['code'] ?? ''));

        $payment = Order::payment((int) $order['id']);
        if ($payment === null) {
            $this->error('Pagamento não encontrado.');
            $this->redirect('/pedido/' . $order['code'] . '/confirmacao');
        }

        if ((string) $payment['status'] === 'paid') {
            $this->success('Este pedido já está pago.');
            $this->redirect('/pedido/' . $order['code'] . '/confirmacao');
        }

        $checkout = PaymentService::startCheckout($order, $payment);
        if ($checkout['ok'] && isset($checkout['redirect'])) {
            Response::redirect($checkout['redirect']);
        }

        $this->error($checkout['message'] ?? 'Não foi possível iniciar o pagamento.');
        $this->redirect('/pedido/' . $order['code'] . '/confirmacao');
    }

    /** @return array<string,mixed> */
    private function findOrder(string $code): array
    {
        $order = Order::findByCodeForUser($code, (int) Auth::id());
        if ($order === null) {
            abort(404, 'Pedido não encontrado.');
        }

        return $order;
    }
}
