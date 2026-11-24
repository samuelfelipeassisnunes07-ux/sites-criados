<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;

final class WebhookController extends Controller
{
    public function mercadopago(array $params = []): void
    {
        $raw = (string) file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            Response::json(['ok' => false, 'message' => 'Payload inválido.'], 400);
        }

        $headers = [
            'x-signature' => Request::header('x-signature') ?? '',
            'x-request-id' => Request::header('x-request-id') ?? '',
        ];

        $result = PaymentService::handleWebhook('mercadopago', $payload, $headers);

        Response::json(
            ['ok' => $result['ok'], 'message' => $result['message']],
            $result['ok'] ? 200 : 400
        );
    }
}
