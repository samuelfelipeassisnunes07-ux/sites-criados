<?php

declare(strict_types=1);

namespace App\Services\Payments;

interface PaymentGateway
{
    public function name(): string;

    /**
     * @param array<string,mixed> $order
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $payment
     * @param array<string,mixed> $customer
     * @return array{ok:bool,message:string,redirect?:string,external_id?:string}
     */
    public function createCheckout(array $order, array $items, array $payment, array $customer = []): array;

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $headers
     * @return array{ok:bool,message:string,ignored?:bool}
     */
    public function handleWebhook(array $payload, array $headers): array;
}
