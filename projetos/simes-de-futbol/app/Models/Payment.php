<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Payment extends Model
{
    protected static string $table = 'payments';

    /** @return array<string,mixed>|null */
    public static function findByProviderPaymentId(string $providerPaymentId): ?array
    {
        return self::findBy('provider_payment_id', $providerPaymentId);
    }

    public static function create(
        int $orderId,
        string $provider,
        string $method,
        float $amount,
        string $status = 'pending',
        ?string $providerPaymentId = null
    ): int {
        return self::insert([
            'order_id' => $orderId,
            'provider' => $provider,
            'method' => $method,
            'provider_payment_id' => $providerPaymentId,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    /**
     * Marca o pagamento como aprovado de forma idempotente.
     * Retorna true apenas quando esta chamada realmente alterou o status.
     */
    public static function markPaid(int $paymentId, ?string $providerPaymentId = null, ?array $payload = null): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE payments
             SET status = 'paid',
                 paid_at = COALESCE(paid_at, NOW()),
                 provider_payment_id = COALESCE(provider_payment_id, ?),
                 payload = COALESCE(?, payload)
             WHERE id = ? AND status <> 'paid'"
        );

        $stmt->execute([
            $providerPaymentId,
            $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $paymentId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function markFailed(int $paymentId, ?array $payload = null): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE payments SET status = 'failed', payload = COALESCE(?, payload) WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([
            $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $paymentId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function markRefunded(int $paymentId): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE payments SET status = 'refunded' WHERE id = ? AND status = 'paid'"
        );
        $stmt->execute([$paymentId]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<string,mixed>|null */
    public static function lock(int $paymentId): ?array
    {
        return self::fetchOne('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
    }
}
