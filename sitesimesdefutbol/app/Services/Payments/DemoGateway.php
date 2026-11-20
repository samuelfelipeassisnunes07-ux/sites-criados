<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class DemoGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'demo';
    }

    public function createCheckout(array $order, array $items, array $payment, array $customer = []): array
    {
        return [
            'ok' => true,
            'message' => 'Pedido criado em modo de demonstração. O pagamento ainda não foi realizado.',
            'redirect' => url('/pedido/' . $order['code'] . '/confirmacao'),
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        return ['ok' => false, 'message' => 'O modo de demonstração não recebe webhooks de pagamento.'];
    }
}
