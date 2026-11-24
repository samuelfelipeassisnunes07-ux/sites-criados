<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Order;
use App\Services\OrderService;
use Tests\IntegrationTestCase;

final class OrderTest extends IntegrationTestCase
{
    public function testPlaceOrderRecalculatesTotalsAndCreatesSnapshot(): void
    {
        $user = $this->createUser('pedido@teste.local');
        $address = $this->createAddress((int) $user['id']);
        $product = $this->createProduct(['price' => 100.00, 'sale_price' => 80.00, 'stock_quantity' => 10]);

        $this->fillCart((int) $user['id'], [['product_id' => (int) $product['id'], 'quantity' => 2]]);

        $result = OrderService::place((int) $user['id'], (int) $address['id'], 'flat', null, null);
        $this->assertTrue($result['ok'], $result['message']);

        $order = $result['order'];
        $this->assertSame('aguardando_pagamento', $order['status']);
        $this->assertSame('pending', $order['payment_status']);
        $this->assertEquals(160.00, (float) $order['subtotal']);
        $this->assertEquals(24.90, (float) $order['shipping_total']);
        $this->assertEquals(184.90, (float) $order['total']);

        $items = Order::items((int) $order['id']);
        $this->assertCount(1, $items);
        $this->assertSame($product['name'], $items[0]['product_name']);
        $this->assertEquals(80.00, (float) $items[0]['unit_price']);
        $this->assertSame(2, (int) $items[0]['quantity']);

        $updated = \App\Models\Product::find((int) $product['id']);
        $this->assertSame(8, (int) $updated['stock_quantity']);

        $cartSummary = \App\Services\CartService::summary((int) $user['id'], false);
        $this->assertSame([], $cartSummary['items']);

        $history = Order::history((int) $order['id']);
        $this->assertCount(1, $history);
        $this->assertSame('aguardando_pagamento', $history[0]['to_status']);
    }

    public function testPlaceOrderWithCouponConsumesCoupon(): void
    {
        $user = $this->createUser('cupom@teste.local');
        $address = $this->createAddress((int) $user['id']);
        $product = $this->createProduct(['price' => 100.00, 'stock_quantity' => 5]);

        $couponId = \App\Models\Coupon::insert([
            'code' => 'TESTE10',
            'description' => '10%',
            'type' => 'percent',
            'value' => 10.00,
            'min_order_amount' => 0,
            'max_uses' => 10,
            'used_count' => 0,
            'max_uses_per_user' => 1,
            'is_active' => 1,
        ]);

        $this->fillCart((int) $user['id'], [['product_id' => (int) $product['id'], 'quantity' => 2]]);

        $result = OrderService::place((int) $user['id'], (int) $address['id'], 'flat', 'TESTE10', null);
        $this->assertTrue($result['ok'], $result['message']);

        $order = $result['order'];
        $this->assertEquals(20.00, (float) $order['discount_total']);
        $this->assertEquals(204.90, (float) $order['total'], '160 - 20 + 24.90 = 164.90? Conferir cálculo.');
        $this->assertSame('TESTE10', $order['coupon_code']);

        $coupon = \App\Models\Coupon::find($couponId);
        $this->assertSame(1, (int) $coupon['used_count']);
        $this->assertSame(1, \App\Models\CouponUsage::countForCoupon($couponId));
    }

    public function testInsufficientStockFailsWithoutSideEffects(): void
    {
        $user = $this->createUser('estoque@teste.local');
        $address = $this->createAddress((int) $user['id']);
        $product = $this->createProduct(['price' => 50.00, 'stock_quantity' => 1]);

        $this->fillCart((int) $user['id'], [['product_id' => (int) $product['id'], 'quantity' => 2]]);

        $result = OrderService::place((int) $user['id'], (int) $address['id'], 'flat', null, null);
        $this->assertFalse($result['ok']);
        $this->assertContains('Estoque insuficiente', $result['message']);
        $this->assertSame(0, Order::count('1'));

        $unchanged = \App\Models\Product::find((int) $product['id']);
        $this->assertSame(1, (int) $unchanged['stock_quantity']);

        $summary = \App\Services\CartService::summary((int) $user['id'], false);
        $this->assertCount(1, $summary['items']);
    }

    public function testFreeShippingAboveThreshold(): void
    {
        $user = $this->createUser('frete@teste.local');
        $address = $this->createAddress((int) $user['id']);
        $product = $this->createProduct(['price' => 300.00, 'stock_quantity' => 2]);

        $this->fillCart((int) $user['id'], [['product_id' => (int) $product['id'], 'quantity' => 1]]);

        $result = OrderService::place((int) $user['id'], (int) $address['id'], 'flat', null, null);
        $this->assertTrue($result['ok'], $result['message']);
        $this->assertEquals(0.00, (float) $result['order']['shipping_total']);
        $this->assertEquals(300.00, (float) $result['order']['total']);
    }

    public function testSecondOrderCannotExceedSharedStock(): void
    {
        $userA = $this->createUser('a@teste.local');
        $userB = $this->createUser('b@teste.local');
        $addressA = $this->createAddress((int) $userA['id']);
        $addressB = $this->createAddress((int) $userB['id']);
        $product = $this->createProduct(['price' => 100.00, 'stock_quantity' => 1]);

        $this->fillCart((int) $userA['id'], [['product_id' => (int) $product['id'], 'quantity' => 1]]);
        $first = OrderService::place((int) $userA['id'], (int) $addressA['id'], 'flat', null, null);
        $this->assertTrue($first['ok'], $first['message']);

        $this->fillCart((int) $userB['id'], [['product_id' => (int) $product['id'], 'quantity' => 1]]);
        $second = OrderService::place((int) $userB['id'], (int) $addressB['id'], 'flat', null, null);
        $this->assertFalse($second['ok']);
        $this->assertSame(1, Order::count('1'));

        $updated = \App\Models\Product::find((int) $product['id']);
        $this->assertSame(0, (int) $updated['stock_quantity']);
    }

    public function testCancelRestoresStockAndRefundsPayment(): void
    {
        $user = $this->createUser('cancelar@teste.local');
        $address = $this->createAddress((int) $user['id']);
        $product = $this->createProduct(['price' => 100.00, 'stock_quantity' => 5]);

        $this->fillCart((int) $user['id'], [['product_id' => (int) $product['id'], 'quantity' => 2]]);
        $placed = OrderService::place((int) $user['id'], (int) $address['id'], 'flat', null, null);
        $this->assertTrue($placed['ok'], $placed['message']);

        $order = $placed['order'];
        $payment = $placed['payment'];
        \App\Services\PaymentService::confirm((int) $payment['id']);

        $cancel = OrderService::cancel((int) $order['id'], 'Teste de cancelamento', 1);
        $this->assertTrue($cancel['ok'], $cancel['message']);

        $updatedOrder = Order::find((int) $order['id']);
        $this->assertSame('cancelado', $updatedOrder['status']);
        $this->assertSame('refunded', $updatedOrder['payment_status']);

        $updatedProduct = \App\Models\Product::find((int) $product['id']);
        $this->assertSame(5, (int) $updatedProduct['stock_quantity']);

        $updatedPayment = \App\Models\Payment::find((int) $payment['id']);
        $this->assertSame('refunded', $updatedPayment['status']);
    }

    public function testInvalidStatusTransitionIsBlocked(): void
    {
        $user = $this->createUser('transicao@teste.local');
        $address = $this->createAddress((int) $user['id']);
        $product = $this->createProduct(['stock_quantity' => 3]);
        $this->fillCart((int) $user['id'], [['product_id' => (int) $product['id'], 'quantity' => 1]]);

        $placed = OrderService::place((int) $user['id'], (int) $address['id'], 'flat', null, null);
        $this->assertTrue($placed['ok']);
        $orderId = (int) $placed['order']['id'];

        $invalid = OrderService::updateStatus($orderId, 'entregue', 'Pulo de etapa', 1);
        $this->assertFalse($invalid['ok']);

        $paid = OrderService::updateStatus($orderId, 'pago', 'Pagamento manual', 1);
        $this->assertTrue($paid['ok']);

        $preparing = OrderService::updateStatus($orderId, 'em_preparacao', null, 1);
        $this->assertTrue($preparing['ok']);

        $shipped = OrderService::updateStatus($orderId, 'enviado', null, 1, 'BR999999999BR');
        $this->assertTrue($shipped['ok']);

        $order = Order::find($orderId);
        $this->assertSame('enviado', $order['status']);
        $this->assertSame('BR999999999BR', $order['tracking_code']);

        $cancelShipped = OrderService::cancel($orderId, null, 1);
        $this->assertFalse($cancelShipped['ok'], 'Pedido enviado não pode ser cancelado.');
    }

    public function testOrderIsolationBetweenCustomers(): void
    {
        $userA = $this->createUser('iso-a@teste.local');
        $userB = $this->createUser('iso-b@teste.local');
        $addressB = $this->createAddress((int) $userB['id']);
        $product = $this->createProduct(['stock_quantity' => 5]);

        $this->fillCart((int) $userB['id'], [['product_id' => (int) $product['id'], 'quantity' => 1]]);
        $placed = OrderService::place((int) $userB['id'], (int) $addressB['id'], 'flat', null, null);
        $this->assertTrue($placed['ok']);

        $code = $placed['order']['code'];
        $this->assertNotNull(Order::findByCodeForUser($code, (int) $userB['id']));
        $this->assertNull(Order::findByCodeForUser($code, (int) $userA['id']));

        $pageA = Order::paginateForUser((int) $userA['id'], 1, 10);
        $this->assertSame(0, $pageA->total);
    }
}
