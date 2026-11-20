<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\Str;
use App\Core\Validator;
use App\Services\CouponService;
use App\Services\OrderService;
use Tests\TestCase;

final class CoreTest extends TestCase
{
    public function testMoneyAndProductPrice(): void
    {
        $this->assertSame('R$ 1.234,56', money(1234.56));
        $this->assertSame('R$ 0,00', money(null));

        $product = ['price' => '199.90', 'sale_price' => '149.90'];
        $this->assertSame(149.9, product_price($product));
        $this->assertSame(199.9, product_old_price($product));
        $this->assertSame(25, product_discount_percent($product));

        $noPromo = ['price' => '99.90', 'sale_price' => null];
        $this->assertSame(99.9, product_price($noPromo));
        $this->assertNull(product_old_price($noPromo));
    }

    public function testCouponDiscountRules(): void
    {
        $percent = ['type' => 'percent', 'value' => '10.00'];
        $fixed = ['type' => 'fixed', 'value' => '30.00'];

        $this->assertSame(10.0, CouponService::discountFor($percent, 100.00));
        $this->assertSame(30.0, CouponService::discountFor($fixed, 100.00));
        $this->assertSame(20.0, CouponService::discountFor($fixed, 20.00));
        $this->assertSame(0.0, CouponService::discountFor($fixed, 0.0));
    }

    public function testShippingRules(): void
    {
        $this->assertSame(24.90, OrderService::shippingCost('flat', 100.00));
        $this->assertSame(0.0, OrderService::shippingCost('flat', 299.00));
        $this->assertSame(0.0, OrderService::shippingCost('pickup', 50.00));

        $options = OrderService::shippingOptions(100.00);
        $this->assertTrue(isset($options['flat']));
        $this->assertSame(24.90, $options['flat']['cost']);
    }

    public function testStatusLabelsAndBadges(): void
    {
        $this->assertSame('Aguardando pagamento', status_label('aguardando_pagamento'));
        $this->assertSame('Entregue', status_label('entregue'));
        $this->assertSame('danger', status_tone('cancelado'));
        $this->assertSame('—', payment_status_label(null));
    }

    public function testValidator(): void
    {
        $validator = Validator::make(
            ['name' => 'João', 'email' => 'invalido', 'password' => '12345678', 'password_confirmation' => 'outra'],
            [
                'name' => 'required|min:3',
                'email' => 'required|email',
                'password' => 'required|min:8|confirmed',
            ]
        );

        $this->assertTrue($validator->fails());
        $errors = $validator->errors();
        $this->assertTrue(isset($errors['email']));
        $this->assertTrue(isset($errors['password']));
        $this->assertFalse(isset($errors['name']));

        $valid = Validator::make(
            ['email' => 'cliente@teste.local', 'qty' => '3'],
            ['email' => 'required|email', 'qty' => 'required|integer|min_value:1']
        );
        $this->assertTrue($valid->passes());

        $missing = Validator::make([], ['name' => 'required']);
        $this->assertTrue($missing->fails());
    }

    public function testSlugGeneration(): void
    {
        $this->assertSame('camisa-retro-brasil-1970', Str::slug('Camisa Retrô Brasil 1970'));
        $this->assertSame('acao-e-comemoracao', Str::slug('Ação & Comemoração'));
        $this->assertSame('simes', Str::slug('Simês'));
    }

    public function testCsrfToken(): void
    {
        $_SESSION = [];
        $token = Csrf::token();
        $this->assertSame(64, strlen($token));
        $this->assertTrue(Csrf::verify($token));
        $this->assertFalse(Csrf::verify('token-invalido'));
        $this->assertFalse(Csrf::verify(null));

        Csrf::rotate();
        $this->assertFalse(Csrf::verify($token));
        $this->assertTrue(Csrf::verify(Csrf::token()));
    }

    public function testOrderStatusTransitions(): void
    {
        $transitions = OrderService::allowedTransitions();
        $this->assertTrue(in_array('pago', $transitions['aguardando_pagamento'], true));
        $this->assertFalse(in_array('entregue', $transitions['aguardando_pagamento'], true));
        $this->assertSame([], $transitions['entregue']);
    }

    public function testMoneyRounding(): void
    {
        $this->assertSame('R$ 33,33', money(33.333));
        $this->assertSame(33.33, round(33.333, 2));
    }

    public function testSafeCsv(): void
    {
        $this->assertSame("'=1+1", safe_csv('=1+1'));
        $this->assertSame('normal', safe_csv('normal'));
    }
}
