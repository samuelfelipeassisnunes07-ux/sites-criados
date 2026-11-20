<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Config;
use App\Core\Logger;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\PaymentService;

/**
 * Integração com o Mercado Pago Checkout Pro.
 *
 * Para ativar: PAYMENT_GATEWAY=mercadopago, MERCADOPAGO_ACCESS_TOKEN e
 * MERCADOPAGO_WEBHOOK_SECRET no .env. Teste primeiro em sandbox.
 */
final class MercadoPagoGateway implements PaymentGateway
{
    private string $accessToken;
    private string $webhookSecret;
    private bool $sandbox;

    public function __construct()
    {
        $this->accessToken = (string) Config::get('app.mercadopago.access_token', '');
        $this->webhookSecret = (string) Config::get('app.mercadopago.webhook_secret', '');
        $this->sandbox = (bool) Config::get('app.mercadopago.sandbox', true);
    }

    public function name(): string
    {
        return 'mercadopago';
    }

    public function createCheckout(array $order, array $items, array $payment, array $customer = []): array
    {
        if ($this->accessToken === '') {
            Logger::error('Mercado Pago sem access token configurado');
            return ['ok' => false, 'message' => 'Pagamento online indisponível no momento.'];
        }

        $mpItems = [];
        foreach ($items as $item) {
            $mpItems[] = [
                'id' => (string) $item['product_sku'],
                'title' => mb_substr((string) $item['product_name'], 0, 120),
                'quantity' => (int) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
                'currency_id' => 'BRL',
            ];
        }

        if ($mpItems === []) {
            return ['ok' => false, 'message' => 'Pedido sem itens para pagamento.'];
        }

        if ((float) $order['shipping_total'] > 0) {
            $mpItems[] = [
                'id' => 'frete',
                'title' => 'Frete - ' . $order['shipping_label'],
                'quantity' => 1,
                'unit_price' => (float) $order['shipping_total'],
                'currency_id' => 'BRL',
            ];
        }

        $body = [
            'items' => $mpItems,
            'external_reference' => (string) $order['code'],
            'statement_descriptor' => 'SIMES DE FUTBOL',
            'notification_url' => absolute_url('/webhooks/mercadopago'),
            'back_urls' => [
                'success' => absolute_url('/pedido/' . $order['code'] . '/confirmacao'),
                'pending' => absolute_url('/pedido/' . $order['code'] . '/confirmacao'),
                'failure' => absolute_url('/pedido/' . $order['code'] . '/confirmacao'),
            ],
        ];

        if (!empty($customer['email'])) {
            $body['payer'] = [
                'name' => (string) ($customer['name'] ?? ''),
                'email' => (string) $customer['email'],
            ];
        }

        $response = $this->request('POST', 'https://api.mercadopago.com/checkout/preferences', $body);

        if (!$response['ok']) {
            Logger::error('Falha ao criar preferência no Mercado Pago', [
                'status' => $response['status'],
                'error' => $response['error'],
            ]);
            return ['ok' => false, 'message' => 'Não foi possível iniciar o pagamento online. Tente novamente.'];
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            return ['ok' => false, 'message' => 'Resposta inválida do provedor de pagamento.'];
        }

        $redirect = $this->sandbox
            ? (string) ($data['sandbox_init_point'] ?? $data['init_point'] ?? '')
            : (string) ($data['init_point'] ?? '');

        if ($redirect === '') {
            return ['ok' => false, 'message' => 'Provedor de pagamento não retornou o link de checkout.'];
        }

        return [
            'ok' => true,
            'message' => 'Redirecionando para o Mercado Pago...',
            'redirect' => $redirect,
            'external_id' => (string) ($data['id'] ?? ''),
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        $type = (string) ($payload['type'] ?? $payload['topic'] ?? '');
        if ($type !== 'payment') {
            return ['ok' => true, 'message' => 'Evento ignorado.', 'ignored' => true];
        }

        $dataId = (string) ($payload['data']['id'] ?? ($type === 'payment' ? ($payload['id'] ?? '') : ''));
        if ($dataId === '') {
            return ['ok' => false, 'message' => 'Evento sem identificador de pagamento.'];
        }

        $eventId = (string) ($payload['id'] ?? ('payment:' . $dataId));
        if (!PaymentWebhookEvent::register('mercadopago', $eventId, $payload)) {
            return ['ok' => true, 'message' => 'Evento já processado.'];
        }

        if ($this->webhookSecret === '') {
            Logger::security('Webhook do Mercado Pago rejeitado: segredo não configurado');
            return ['ok' => false, 'message' => 'Webhook não configurado com segurança.'];
        }

        if (!$this->verifySignature($dataId, $headers)) {
            Logger::security('Assinatura de webhook do Mercado Pago inválida', ['payment_id' => $dataId]);
            return ['ok' => false, 'message' => 'Assinatura inválida.'];
        }

        $response = $this->request('GET', 'https://api.mercadopago.com/v1/payments/' . rawurlencode($dataId));
        if (!$response['ok']) {
            Logger::error('Falha ao consultar pagamento no Mercado Pago', [
                'payment_id' => $dataId,
                'status' => $response['status'],
            ]);
            return ['ok' => false, 'message' => 'Não foi possível consultar o pagamento.'];
        }

        $paymentData = json_decode($response['body'], true);
        if (!is_array($paymentData)) {
            return ['ok' => false, 'message' => 'Resposta inválida do provedor.'];
        }

        $status = (string) ($paymentData['status'] ?? '');
        $externalReference = (string) ($paymentData['external_reference'] ?? '');
        $providerPaymentId = (string) ($paymentData['id'] ?? $dataId);
        $providerAmount = (float) ($paymentData['transaction_amount'] ?? 0);

        if ($externalReference === '') {
            return ['ok' => false, 'message' => 'Pagamento sem referência de pedido.'];
        }

        $order = Order::findByCode($externalReference);
        if ($order === null) {
            Logger::warning('Webhook de pagamento para pedido inexistente', ['order_code' => $externalReference]);
            return ['ok' => false, 'message' => 'Pedido não encontrado.'];
        }

        $payment = Order::payment((int) $order['id']);
        if ($payment === null) {
            return ['ok' => false, 'message' => 'Pagamento local não encontrado.'];
        }

        if (abs($providerAmount - (float) $payment['amount']) > 0.01) {
            Logger::security('Valor de pagamento divergente', [
                'order_code' => $externalReference,
                'expected' => $payment['amount'],
                'received' => $providerAmount,
            ]);
            return ['ok' => false, 'message' => 'Valor do pagamento divergente.'];
        }

        if ($status === 'approved' || $status === 'authorized') {
            PaymentService::confirm(
                (int) $payment['id'],
                $providerPaymentId,
                $paymentData,
                'Pagamento aprovado via Mercado Pago.'
            );
        } elseif (in_array($status, ['rejected', 'cancelled', 'cancelled_by_user'], true)) {
            Payment::markFailed((int) $payment['id'], $paymentData);
        } elseif ($status === 'refunded') {
            Payment::markRefunded((int) $payment['id']);
        }

        PaymentWebhookEvent::markProcessed('mercadopago', $eventId);

        return ['ok' => true, 'message' => 'Webhook processado.'];
    }

    /** @param array<string,mixed> $headers */
    private function verifySignature(string $dataId, array $headers): bool
    {
        $signatureHeader = (string) ($headers['x-signature'] ?? '');
        $requestId = (string) ($headers['x-request-id'] ?? '');

        if ($signatureHeader === '' || $requestId === '') {
            return false;
        }

        $ts = null;
        $v1 = null;
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 'ts') {
                $ts = $value;
            } elseif ($key === 'v1') {
                $v1 = $value;
            }
        }

        if ($ts === null || $v1 === null) {
            return false;
        }

        // Rejeita notificações muito antigas (5 minutos) para reduzir replay.
        if (!ctype_digit((string) $ts) || abs(time() - (int) $ts) > 300) {
            return false;
        }

        $manifest = 'id:' . $dataId . ';request-id:' . $requestId . ';ts:' . $ts . ';';
        $expected = hash_hmac('sha256', $manifest, $this->webhookSecret);

        return hash_equals($expected, (string) $v1);
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array{ok:bool,status:int,body:string,error:string}
     */
    private function request(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'cURL indisponível'];
        }

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_string($raw) ? $raw : '',
            'error' => $error,
        ];
    }
}
