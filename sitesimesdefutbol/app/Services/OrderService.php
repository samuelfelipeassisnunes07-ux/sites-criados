<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Str;
use App\Models\AdminLog;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use RuntimeException;
use Throwable;

final class OrderService
{
    public static function shippingCost(string $method, float $subtotal): float
    {
        if ($method === 'pickup') {
            return 0.0;
        }

        $threshold = (float) Config::get('app.shipping.free_threshold', 299.00);
        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }

        return round((float) Config::get('app.shipping.flat_rate', 24.90), 2);
    }

    public static function shippingLabel(string $method, float $subtotal): string
    {
        if ($method === 'pickup') {
            return 'Retirar na loja';
        }

        return self::shippingCost($method, $subtotal) <= 0
            ? 'Entrega padrão (frete grátis)'
            : 'Entrega padrão';
    }

    public static function normalizeShippingMethod(string $method): string
    {
        return $method === 'pickup' ? 'pickup' : 'flat';
    }

    /**
     * @return array<string,array{label:string,cost:float,description:string}>
     */
    public static function shippingOptions(float $subtotal): array
    {
        $standardCost = self::shippingCost('flat', $subtotal);
        $threshold = (float) Config::get('app.shipping.free_threshold', 299.00);

        $options = [
            'flat' => [
                'label' => 'Entrega padrão',
                'cost' => $standardCost,
                'description' => $standardCost > 0
                    ? 'Frete calculado para todo o Brasil.'
                    : 'Frete grátis aplicado ao seu pedido.',
            ],
        ];

        if ((bool) Config::get('app.shipping.pickup_enabled', true)) {
            $options['pickup'] = [
                'label' => 'Retirar na loja',
                'cost' => 0.0,
                'description' => (string) Config::get('app.shipping.pickup_address', 'Retirada na loja.'),
            ];
        }

        if ($standardCost > 0 && $threshold > 0) {
            $options['flat']['description'] .= ' Frete grátis acima de ' . money($threshold) . '.';
        }

        return $options;
    }

    /**
     * Cria o pedido de forma transacional: recalcula preços no servidor,
     * bloqueia o estoque, cria itens/pagamento/histórico e consome o cupom.
     *
     * @return array{ok:bool,message:string,order?:array<string,mixed>,payment?:array<string,mixed>}
     */
    public static function place(
        int $userId,
        int $addressId,
        string $shippingMethod,
        ?string $couponCode,
        ?string $customerNote = null
    ): array {
        $address = Address::findForUser($addressId, $userId);
        if ($address === null) {
            return ['ok' => false, 'message' => 'Selecione um endereço de entrega válido.'];
        }

        $cart = CartService::resolve($userId, false);
        if ($cart === null) {
            return ['ok' => false, 'message' => 'Seu carrinho está vazio.'];
        }

        $cartId = (int) $cart['id'];
        $cartItems = CartItem::withProducts($cartId);
        if ($cartItems === []) {
            return ['ok' => false, 'message' => 'Seu carrinho está vazio.'];
        }

        $shippingMethod = self::normalizeShippingMethod($shippingMethod);
        $couponCode = $couponCode !== null ? mb_strtoupper(trim($couponCode)) : null;
        $provider = PaymentService::providerName();
        $paymentMethod = $provider === 'demo' ? 'demo' : 'mercadopago';

        try {
            /** @var array<string,mixed> $result */
            $result = Database::transaction(static function () use (
                $userId,
                $address,
                $cartId,
                $cartItems,
                $shippingMethod,
                $couponCode,
                $paymentMethod,
                $customerNote,
                $provider
            ): array {
                $quantities = [];
                foreach ($cartItems as $item) {
                    $productId = (int) $item['product_id'];
                    $quantities[$productId] = ($quantities[$productId] ?? 0) + max(1, (int) $item['quantity']);
                }

                $lockedProducts = Product::lockByIds(array_keys($quantities));
                $lockedById = [];
                foreach ($lockedProducts as $product) {
                    $lockedById[(int) $product['id']] = $product;
                }

                $subtotal = 0.0;
                $lines = [];

                foreach ($quantities as $productId => $quantity) {
                    $product = $lockedById[$productId] ?? null;
                    if ($product === null || (int) $product['is_active'] !== 1) {
                        throw new RuntimeException('Um dos produtos do carrinho não está mais disponível.');
                    }

                    if ((int) $product['stock_quantity'] < $quantity) {
                        throw new RuntimeException('Estoque insuficiente para "' . $product['name'] . '".');
                    }

                    $unitPrice = product_price($product);
                    $lineTotal = round($unitPrice * $quantity, 2);
                    $subtotal += $lineTotal;

                    $image = null;
                    foreach ($cartItems as $cartItem) {
                        if ((int) $cartItem['product_id'] === $productId) {
                            $image = $cartItem['image'] ?? null;
                            break;
                        }
                    }

                    $lines[] = [
                        'product_id' => $productId,
                        'product_name' => $product['name'],
                        'product_sku' => $product['sku'],
                        'product_image' => $image,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'line_total' => $lineTotal,
                        'previous_stock' => (int) $product['stock_quantity'],
                    ];
                }

                $subtotal = round($subtotal, 2);

                $couponId = null;
                $appliedCouponCode = null;
                $discount = 0.0;

                if ($couponCode !== null && $couponCode !== '') {
                    $couponResult = CouponService::validate($couponCode, $subtotal, $userId);
                    if (!$couponResult['ok']) {
                        throw new RuntimeException($couponResult['message']);
                    }

                    $coupon = $couponResult['coupon'];
                    $couponId = (int) $coupon['id'];
                    $appliedCouponCode = (string) $coupon['code'];
                    $discount = round((float) $couponResult['discount'], 2);
                }

                $shipping = self::shippingCost($shippingMethod, $subtotal);
                $total = round(max(0, $subtotal - $discount) + $shipping, 2);
                $code = self::generateOrderCode();

                $orderId = Order::insert([
                    'code' => $code,
                    'user_id' => $userId,
                    'status' => 'aguardando_pagamento',
                    'payment_status' => 'pending',
                    'payment_method' => $paymentMethod,
                    'subtotal' => $subtotal,
                    'discount_total' => $discount,
                    'shipping_total' => $shipping,
                    'total' => $total,
                    'coupon_id' => $couponId,
                    'coupon_code' => $appliedCouponCode,
                    'shipping_method' => $shippingMethod,
                    'shipping_label' => self::shippingLabel($shippingMethod, $subtotal),
                    'recipient_name' => $address['recipient_name'],
                    'recipient_phone' => $address['phone'],
                    'shipping_cep' => $address['cep'],
                    'shipping_street' => $address['street'],
                    'shipping_number' => $address['number'],
                    'shipping_complement' => $address['complement'],
                    'shipping_district' => $address['district'],
                    'shipping_city' => $address['city'],
                    'shipping_state' => $address['state'],
                    'customer_note' => $customerNote !== null && trim($customerNote) !== ''
                        ? mb_substr(trim($customerNote), 0, 500)
                        : null,
                ]);

                OrderItem::insertMany($orderId, $lines);

                foreach ($lines as $line) {
                    $productId = (int) $line['product_id'];
                    $quantity = (int) $line['quantity'];
                    $previous = (int) $line['previous_stock'];
                    $new = $previous - $quantity;

                    $stmt = Database::pdo()->prepare(
                        'UPDATE products SET stock_quantity = stock_quantity - ?
                         WHERE id = ? AND stock_quantity >= ?'
                    );
                    $stmt->execute([$quantity, $productId, $quantity]);

                    if ($stmt->rowCount() === 0) {
                        throw new RuntimeException('O estoque de "' . $line['product_name'] . '" acabou de ser atualizado. Revise o carrinho.');
                    }

                    StockMovement::record(
                        $productId,
                        'venda',
                        $quantity,
                        $previous,
                        $new,
                        $orderId,
                        null,
                        'Venda do pedido ' . $code
                    );
                }

                OrderStatusHistory::add($orderId, null, 'aguardando_pagamento', 'Pedido criado.');
                $paymentId = Payment::create($orderId, $provider, $paymentMethod, $total, 'pending');

                if ($couponId !== null && $appliedCouponCode !== null) {
                    if (!CouponService::consume($couponId)) {
                        throw new RuntimeException('Este cupom atingiu o limite de utilizações. Remova o cupom e tente novamente.');
                    }
                    CouponUsage::record($couponId, $userId, $orderId);
                }

                CartItem::clear($cartId);
                Cart::touch($cartId);

                return [
                    'ok' => true,
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'code' => $code,
                ];
            });
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Logger::error('Falha ao criar pedido', ['message' => $e->getMessage()]);
            return ['ok' => false, 'message' => 'Não foi possível finalizar o pedido. Tente novamente em instantes.'];
        }

        $order = Order::find((int) $result['order_id']);
        $payment = Payment::find((int) $result['payment_id']);

        if ($order !== null) {
            try {
                MailService::sendOrderCreated($order, Order::items((int) $order['id']));
            } catch (Throwable $e) {
                Logger::warning('Falha ao enviar e-mail de pedido', ['message' => $e->getMessage()]);
            }
        }

        return [
            'ok' => true,
            'message' => 'Pedido criado com sucesso.',
            'order' => $order ?? [],
            'payment' => $payment ?? [],
        ];
    }

    /** @return array<string,list<string>> */
    public static function allowedTransitions(): array
    {
        return [
            'aguardando_pagamento' => ['pago', 'cancelado'],
            'pago' => ['em_preparacao', 'cancelado'],
            'em_preparacao' => ['enviado', 'cancelado'],
            'enviado' => ['entregue'],
            'entregue' => [],
            'cancelado' => [],
        ];
    }

    /** @return array{ok:bool,message:string} */
    public static function updateStatus(
        int $orderId,
        string $newStatus,
        ?string $note,
        ?int $adminId,
        ?string $trackingCode = null
    ): array {
        $order = Order::find($orderId);
        if ($order === null) {
            return ['ok' => false, 'message' => 'Pedido não encontrado.'];
        }

        if (!in_array($newStatus, Order::ALL_STATUSES, true)) {
            return ['ok' => false, 'message' => 'Status inválido.'];
        }

        if ($newStatus === 'cancelado' && $order['status'] !== 'cancelado') {
            return self::cancel($orderId, $note, $adminId);
        }

        $current = (string) $order['status'];
        if ($newStatus === $current) {
            if ($trackingCode !== null && trim($trackingCode) !== '') {
                return self::addTracking($orderId, $trackingCode, $adminId);
            }

            return ['ok' => true, 'message' => 'O pedido já está com este status.'];
        }

        $allowed = self::allowedTransitions()[$current] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            return [
                'ok' => false,
                'message' => 'Transição não permitida: ' . status_label($current) . ' → ' . status_label($newStatus) . '.',
            ];
        }

        try {
            Database::transaction(static function () use ($orderId, $newStatus, $note, $adminId, $trackingCode, $current): void {
                $locked = Order::lockById($orderId);
                if ($locked === null || (string) $locked['status'] !== $current) {
                    throw new RuntimeException('O pedido foi alterado por outro usuário. Atualize a página.');
                }

                $update = ['status' => $newStatus];
                $now = date('Y-m-d H:i:s');

                if ($newStatus === 'pago') {
                    $update['payment_status'] = 'paid';
                    $update['paid_at'] = $now;

                    $payment = Order::payment($orderId);
                    if ($payment !== null) {
                        Payment::markPaid((int) $payment['id']);
                    }
                }

                if ($newStatus === 'enviado') {
                    $update['shipped_at'] = $now;
                }

                if ($newStatus === 'entregue') {
                    $update['delivered_at'] = $now;
                }

                if ($trackingCode !== null && trim($trackingCode) !== '') {
                    $update['tracking_code'] = mb_substr(trim($trackingCode), 0, 80);
                }

                Order::updateById($orderId, $update);
                OrderStatusHistory::add($orderId, $current, $newStatus, $note, $adminId);

                AdminLog::record('order.status_updated', 'order', $orderId, [
                    'from' => $current,
                    'to' => $newStatus,
                    'note' => $note,
                ]);
            });
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Logger::error('Falha ao atualizar status do pedido', ['message' => $e->getMessage()]);
            return ['ok' => false, 'message' => 'Não foi possível atualizar o pedido.'];
        }

        $updated = Order::find($orderId);
        if ($updated !== null) {
            try {
                MailService::sendOrderStatus($updated);
            } catch (Throwable $e) {
                Logger::warning('Falha ao enviar e-mail de status', ['message' => $e->getMessage()]);
            }
        }

        return ['ok' => true, 'message' => 'Status atualizado para ' . status_label($newStatus) . '.'];
    }

    /** @return array{ok:bool,message:string} */
    public static function addTracking(int $orderId, string $trackingCode, ?int $adminId): array
    {
        $code = mb_substr(trim($trackingCode), 0, 80);
        if ($code === '') {
            return ['ok' => false, 'message' => 'Informe o código de rastreamento.'];
        }

        $order = Order::find($orderId);
        if ($order === null) {
            return ['ok' => false, 'message' => 'Pedido não encontrado.'];
        }

        Order::updateById($orderId, ['tracking_code' => $code]);
        OrderStatusHistory::add($orderId, (string) $order['status'], (string) $order['status'], 'Código de rastreamento informado: ' . $code, $adminId);
        AdminLog::record('order.tracking_updated', 'order', $orderId, ['tracking_code' => $code]);

        return ['ok' => true, 'message' => 'Código de rastreamento atualizado.'];
    }

    /** @return array{ok:bool,message:string} */
    public static function cancel(int $orderId, ?string $note, ?int $adminId): array
    {
        $order = Order::find($orderId);
        if ($order === null) {
            return ['ok' => false, 'message' => 'Pedido não encontrado.'];
        }

        if ((string) $order['status'] === 'cancelado') {
            return ['ok' => false, 'message' => 'Este pedido já foi cancelado.'];
        }

        if (in_array((string) $order['status'], ['enviado', 'entregue'], true)) {
            return [
                'ok' => false,
                'message' => 'Pedidos enviados ou entregues não podem ser cancelados. Registre uma devolução.',
            ];
        }

        try {
            Database::transaction(static function () use ($orderId, $note, $adminId, $order): void {
                $locked = Order::lockById($orderId);
                if ($locked === null || (string) $locked['status'] === 'cancelado') {
                    throw new RuntimeException('Este pedido já foi cancelado.');
                }

                $items = Order::items($orderId);

                foreach ($items as $item) {
                    $productId = $item['product_id'] !== null ? (int) $item['product_id'] : 0;
                    if ($productId <= 0) {
                        continue;
                    }

                    $product = Product::lockById($productId);
                    if ($product === null) {
                        continue;
                    }

                    $quantity = (int) $item['quantity'];
                    $previous = (int) $product['stock_quantity'];
                    $new = $previous + $quantity;

                    Product::updateById($productId, ['stock_quantity' => $new]);
                    StockMovement::record(
                        $productId,
                        'cancelamento',
                        $quantity,
                        $previous,
                        $new,
                        $orderId,
                        $adminId,
                        'Cancelamento do pedido ' . $order['code']
                    );
                }

                $payment = Order::payment($orderId);
                $wasPaid = (string) $locked['payment_status'] === 'paid';

                if ($payment !== null) {
                    if ($wasPaid) {
                        Payment::markRefunded((int) $payment['id']);
                    } else {
                        Payment::markFailed((int) $payment['id']);
                    }
                }

                Order::updateById($orderId, [
                    'status' => 'cancelado',
                    'payment_status' => $wasPaid ? 'refunded' : 'failed',
                    'canceled_at' => date('Y-m-d H:i:s'),
                ]);

                OrderStatusHistory::add(
                    $orderId,
                    (string) $locked['status'],
                    'cancelado',
                    $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : 'Pedido cancelado pelo administrador.',
                    $adminId
                );

                AdminLog::record('order.canceled', 'order', $orderId, [
                    'from' => $locked['status'],
                    'refunded' => $wasPaid,
                ]);
            });
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Logger::error('Falha ao cancelar pedido', ['message' => $e->getMessage()]);
            return ['ok' => false, 'message' => 'Não foi possível cancelar o pedido.'];
        }

        return ['ok' => true, 'message' => 'Pedido cancelado e estoque restaurado.'];
    }

    private static function generateOrderCode(): string
    {
        do {
            $code = Str::orderCode();
        } while (Order::findByCode($code) !== null);

        return $code;
    }
}
