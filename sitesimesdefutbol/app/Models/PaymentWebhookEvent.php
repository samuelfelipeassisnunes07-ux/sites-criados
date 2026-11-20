<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PaymentWebhookEvent extends Model
{
    protected static string $table = 'payment_webhook_events';

    /**
     * Registra o evento de webhook. Retorna false se ele já foi processado
     * (proteção contra processamento duplicado).
     */
    public static function register(string $provider, string $eventId, array $payload): bool
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'INSERT IGNORE INTO payment_webhook_events (provider, event_id, payload) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $provider,
            $eventId,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function markProcessed(string $provider, string $eventId): void
    {
        self::execute(
            'UPDATE payment_webhook_events SET processed_at = NOW() WHERE provider = ? AND event_id = ?',
            [$provider, $eventId]
        );
    }
}
