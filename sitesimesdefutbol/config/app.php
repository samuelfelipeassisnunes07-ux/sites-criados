<?php

declare(strict_types=1);

use App\Core\Env;

$baseUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8080'), '/');

return [
    'name' => (string) Env::get('APP_NAME', 'Simês de Fútbol'),
    'env' => (string) Env::get('APP_ENV', 'local'),
    'debug' => filter_var(Env::get('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
    'url' => $baseUrl,
    'key' => (string) Env::get('APP_KEY', ''),
    'timezone' => (string) Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
    'session_name' => (string) Env::get('SESSION_NAME', 'sime_store_session'),
    'upload_max_bytes' => ((int) Env::get('UPLOAD_MAX_MB', '3')) * 1024 * 1024,
    'mail_driver' => (string) Env::get('MAIL_DRIVER', 'log'),
    'mail_from' => (string) Env::get('MAIL_FROM', 'no-reply@simesdefutbol.local'),
    'mail_from_name' => (string) Env::get('MAIL_FROM_NAME', 'Simês de Fútbol'),
    'public_url' => rtrim((string) Env::get('APP_PUBLIC_URL', ''), '/'),
    'payment_gateway' => (string) Env::get('PAYMENT_GATEWAY', 'demo'),
    'shipping' => [
        'flat_rate' => (float) Env::get('SHIPPING_FLAT_RATE', '24.90'),
        'free_threshold' => (float) Env::get('FREE_SHIPPING_THRESHOLD', '299.00'),
        'pickup_enabled' => filter_var(Env::get('PICKUP_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
        'pickup_address' => (string) Env::get('PICKUP_ADDRESS', ''),
    ],
    'mercadopago' => [
        'access_token' => (string) Env::get('MERCADOPAGO_ACCESS_TOKEN', ''),
        'public_key' => (string) Env::get('MERCADOPAGO_PUBLIC_KEY', ''),
        'webhook_secret' => (string) Env::get('MERCADOPAGO_WEBHOOK_SECRET', ''),
        'sandbox' => filter_var(Env::get('MERCADOPAGO_SANDBOX', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],
];
