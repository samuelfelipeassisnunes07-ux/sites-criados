<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Address;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\PaymentService;

final class CheckoutController extends Controller
{
    public function index(array $params = []): void
    {
        $summary = CartService::summary(Auth::id(), false);

        if ($summary['items'] === []) {
            $this->error('Seu carrinho está vazio. Adicione produtos antes de finalizar.');
            $this->redirect('/carrinho');
        }

        $this->renderCheckout([]);
    }

    public function store(array $params = []): void
    {
        $summary = CartService::summary(Auth::id(), false);
        if ($summary['items'] === []) {
            $this->error('Seu carrinho está vazio.');
            $this->redirect('/carrinho');
        }

        $data = [
            'address_id' => (string) Request::input('address_id', ''),
            'shipping_method' => (string) Request::input('shipping_method', 'flat'),
            'coupon_code' => trim((string) Request::input('coupon_code', '')),
            'customer_note' => trim((string) Request::input('customer_note', '')),
        ];

        $validator = Validator::make($data, [
            'address_id' => 'required|integer',
            'shipping_method' => 'required|in:flat,pickup',
            'coupon_code' => 'max:60',
            'customer_note' => 'max:500',
        ]);

        $errors = $validator->errors();

        $address = Address::findForUser((int) $data['address_id'], (int) Auth::id());
        if ($address === null) {
            $errors['address_id'] = 'Selecione um endereço válido.';
        }

        if ($data['shipping_method'] === 'pickup' && !\App\Core\Config::get('app.shipping.pickup_enabled', true)) {
            $errors['shipping_method'] = 'A retirada na loja não está disponível.';
        }

        if ($errors !== []) {
            $this->renderCheckout($errors, $data);
            return;
        }

        $result = OrderService::place(
            (int) Auth::id(),
            (int) $data['address_id'],
            $data['shipping_method'],
            $data['coupon_code'] !== '' ? $data['coupon_code'] : null,
            $data['customer_note'] !== '' ? $data['customer_note'] : null
        );

        if (!$result['ok']) {
            $this->renderCheckout(['checkout' => $result['message']], $data);
            return;
        }

        $order = $result['order'] ?? [];
        $payment = $result['payment'] ?? [];

        $this->success('Pedido ' . $order['code'] . ' criado com sucesso.');

        if ($payment !== []) {
            $checkout = PaymentService::startCheckout($order, $payment);
            if ($checkout['ok'] && isset($checkout['redirect'])) {
                Response::redirect($checkout['redirect']);
            }
        }

        $this->redirect('/pedido/' . $order['code'] . '/confirmacao');
    }

    /** @param array<string,string> $errors @param array<string,mixed> $old */
    private function renderCheckout(array $errors = [], array $old = []): void
    {
        $userId = (int) Auth::id();
        $summary = CartService::summary($userId, false);
        $addresses = Address::forUser($userId);
        $selectedAddressId = (int) ($old['address_id'] ?? ($addresses[0]['id'] ?? 0));

        $couponCode = trim((string) ($old['coupon_code'] ?? Request::input('cupom', '')));
        $couponResult = null;
        if ($couponCode !== '') {
            $couponResult = CouponService::validate($couponCode, $summary['subtotal'], $userId);
        }

        $shippingOptions = OrderService::shippingOptions($summary['subtotal']);
        $selectedShipping = (string) ($old['shipping_method'] ?? 'flat');
        if (!isset($shippingOptions[$selectedShipping])) {
            $selectedShipping = 'flat';
        }

        $shippingCost = $shippingOptions[$selectedShipping]['cost'] ?? 0.0;
        $discount = $couponResult !== null && $couponResult['ok'] ? (float) $couponResult['discount'] : 0.0;
        $total = round(max(0, $summary['subtotal'] - $discount) + $shippingCost, 2);

        $this->render('store/checkout', [
            'title' => 'Finalizar compra',
            'summary' => $summary,
            'addresses' => $addresses,
            'selectedAddressId' => $selectedAddressId,
            'shippingOptions' => $shippingOptions,
            'selectedShipping' => $selectedShipping,
            'couponCode' => $couponCode,
            'couponResult' => $couponResult,
            'discount' => $discount,
            'shippingCost' => $shippingCost,
            'total' => $total,
            'errors' => $errors,
            'old' => $old,
            'isDemo' => PaymentService::isDemo(),
        ]);
    }
}
