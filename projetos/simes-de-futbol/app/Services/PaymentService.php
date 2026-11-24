<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\DemoGateway;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PaymentGateway;
use Throwable;

final class PaymentService
{
    public static function providerName(): string
    {
        return (string) Config::get('app.payment_gateway', 'demo') === 'mercadopago'
            ? 'mercadopago'
            : 'demo';
    }

    public static function isDemo(): bool
    {
        return self::providerName() === 'demo';
    }

    public static function gateway(): PaymentGateway
    {
        return self::providerName() === 'mercadopago'
            ? new MercadoPagoGateway()
            : new DemoGateway();
    }

    /**
     * Inicia o checkout no provedor configurado.
     *
     * @param array<string,mixed> $order
     * @param array<string,mixed> $payment
     * @return array{ok:bool,message:string,redirect?:string}
     */
    public static function startCheckout(array $order, array $payment): array
    {
        $items = Order::items((int) $order['id']);
        $customer = User::find((int) $order['user_id']) ?? [];

        return self::gateway()->createCheckout($order, $items, $payment, $customer);
    }

    /**
     * Confirma um pagamento e marca o pedido como pago — idempotente.
     */
    public static function confirm(
        int $paymentId,
        ?string $providerPaymentId = null,
        ?array $payload = null,
        string $note = 'Pagamento aprovado.'
    ): bool {
        $confirmed = Database::transaction(static function () use (
            $paymentId,
            $providerPaymentId,
            $payload,
            $note
        ): bool {
            $payment = Payment::lock($paymentId);
            if ($payment === null || $payment['status'] === 'paid') {
                return false;
            }

            if (!Payment::markPaid($paymentId, $providerPaymentId, $payload)) {
                return false;
            }

            $order = Order::lockById((int) $payment['order_id']);
            if ($order === null) {
                return false;
            }

            $fromStatus = (string) $order['status'];

            if ($fromStatus === 'aguardando_pagamento') {
                Order::updateById((int) $order['id'], [
                    'status' => 'pago',
                    'payment_status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s'),
                ]);
                OrderStatusHistory::add((int) $order['id'], $fromStatus, 'pago', $note, null);
            } else {
                Order::updateById((int) $order['id'], [
                    'payment_status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return true;
        });

        if ($confirmed) {
            $payment = Payment::find($paymentId);
            if ($payment !== null) {
                $order = Order::find((int) $payment['order_id']);
                if ($order !== null) {
                    try {
                        MailService::sendOrderStatus($order);
                    } catch (Throwable $e) {
                        Logger::warning('Falha ao enviar e-mail de pagamento', ['message' => $e->getMessage()]);
                    }
                }
            }
        }

        return $confirmed;
    }

    /**
     * Aprovação simulada usada SOMENTE quando PAYMENT_GATEWAY=demo.
     *
     * @param array<string,mixed> $order
     * @return array{ok:bool,message:string}
     */
    public static function confirmDemo(array $order, int $userId): array
    {
        if (!self::isDemo()) {
            return ['ok' => false, 'message' => 'A simulação de pagamento está desativada.'];
        }

        if ((int) $order['user_id'] !== $userId) {
            return ['ok' => false, 'message' => 'Pedido não encontrado.'];
        }

        if ((string) $order['payment_status'] === 'paid') {
            return ['ok' => true, 'message' => 'Este pagamento já foi aprovado.'];
        }

        if ((string) $order['status'] === 'cancelado') {
            return ['ok' => false, 'message' => 'Este pedido está cancelado.'];
        }

        $payment = Order::payment((int) $order['id']);
        if ($payment === null) {
            return ['ok' => false, 'message' => 'Pagamento não encontrado.'];
        }

        $confirmed = self::confirm(
            (int) $payment['id'],
            null,
            ['demo' => true],
            'Pagamento aprovado em modo de demonstração (simulado).'
        );

        if (!$confirmed && (string) (Payment::find((int) $payment['id'])['status'] ?? '') === 'paid') {
            return ['ok' => true, 'message' => 'Este pagamento já foi aprovado.'];
        }

        return $confirmed
            ? ['ok' => true, 'message' => 'Pagamento de demonstração aprovado. Nenhuma cobrança real foi feita.']
            : ['ok' => false, 'message' => 'Não foi possível aprovar o pagamento.'];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $headers
     * @return array{ok:bool,message:string}
     */
    public static function handleWebhook(string $provider, array $payload, array $headers): array
    {
        if ($provider !== 'mercadopago') {
            return ['ok' => false, 'message' => 'Provedor de webhook não suportado.'];
        }

        return self::gateway()->handleWebhook($payload, $headers);
    }
}
