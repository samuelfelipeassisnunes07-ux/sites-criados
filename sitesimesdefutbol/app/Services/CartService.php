<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;

final class CartService
{
    public const SESSION_KEY = 'cart_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /** @return array<string,mixed>|null */
    public static function resolve(?int $userId, bool $create = true): ?array
    {
        $token = self::token();
        $cart = Cart::findByToken($token);

        if ($cart === null && $create) {
            $cart = Cart::getOrCreate($token, $userId);
        }

        if ($cart !== null && $userId !== null && $cart['user_id'] === null) {
            Cart::attachUser((int) $cart['id'], $userId);
            $cart['user_id'] = $userId;
        }

        return $cart;
    }

    /**
     * Itens do carrinho com preços recalculados a partir do banco.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function items(int $cartId): array
    {
        $rows = CartItem::withProducts($cartId);
        $items = [];

        foreach ($rows as $row) {
            if ((int) $row['is_active'] !== 1) {
                continue;
            }

            $quantity = max(1, (int) $row['quantity']);
            $unitPrice = product_price($row);
            $stock = (int) $row['stock_quantity'];

            $items[] = array_merge($row, [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $quantity, 2),
                'stock_quantity' => $stock,
                'available' => $stock >= $quantity,
                'max_quantity' => max(1, min($stock, 99)),
            ]);
        }

        return $items;
    }

    /** @return array{cart:array<string,mixed>|null,items:array<int,array<string,mixed>>,subtotal:float,quantity:int,distinct:int} */
    public static function summary(?int $userId, bool $create = false): array
    {
        $cart = self::resolve($userId, $create);
        $items = $cart !== null ? self::items((int) $cart['id']) : [];
        $subtotal = 0.0;
        $quantity = 0;

        foreach ($items as $item) {
            $subtotal += (float) $item['line_total'];
            $quantity += (int) $item['quantity'];
        }

        return [
            'cart' => $cart,
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'quantity' => $quantity,
            'distinct' => count($items),
        ];
    }

    /** @return array{ok:bool,message:string} */
    public static function add(int $productId, int $quantity, ?int $userId): array
    {
        $quantity = max(1, min(99, $quantity));
        $product = Product::findActive($productId);

        if ($product === null) {
            return ['ok' => false, 'message' => 'Produto não encontrado ou indisponível.'];
        }

        $stock = (int) $product['stock_quantity'];
        if ($stock < 1) {
            return ['ok' => false, 'message' => 'Produto sem estoque no momento.'];
        }

        $cart = self::resolve($userId, true);
        if ($cart === null) {
            return ['ok' => false, 'message' => 'Não foi possível iniciar o carrinho.'];
        }

        $cartId = (int) $cart['id'];
        $existing = CartItem::findItem($cartId, $productId);
        $current = $existing !== null ? (int) $existing['quantity'] : 0;
        $newQuantity = $current + $quantity;

        if ($newQuantity > $stock) {
            $newQuantity = $stock;
            if ($current >= $stock) {
                return ['ok' => false, 'message' => 'Você já tem o máximo disponível deste produto no carrinho.'];
            }
        }

        CartItem::setQuantity($cartId, $productId, $newQuantity);
        Cart::touch($cartId);

        $message = $newQuantity < $current + $quantity
            ? 'Quantidade ajustada ao estoque disponível.'
            : 'Produto adicionado ao carrinho.';

        return ['ok' => true, 'message' => $message];
    }

    /** @return array{ok:bool,message:string} */
    public static function setQuantity(int $productId, int $quantity, ?int $userId): array
    {
        $cart = self::resolve($userId, false);
        if ($cart === null) {
            return ['ok' => false, 'message' => 'Carrinho não encontrado.'];
        }

        $cartId = (int) $cart['id'];
        $item = CartItem::findItem($cartId, $productId);
        if ($item === null) {
            return ['ok' => false, 'message' => 'Item não encontrado no carrinho.'];
        }

        if ($quantity <= 0) {
            CartItem::removeProduct($cartId, $productId);
            Cart::touch($cartId);

            return ['ok' => true, 'message' => 'Item removido do carrinho.'];
        }

        $product = Product::findActive($productId);
        if ($product === null) {
            return ['ok' => false, 'message' => 'Produto indisponível.'];
        }

        $stock = (int) $product['stock_quantity'];
        $adjusted = min($quantity, $stock, 99);
        if ($adjusted < 1) {
            return ['ok' => false, 'message' => 'Produto sem estoque no momento.'];
        }

        CartItem::setQuantity($cartId, $productId, $adjusted);
        Cart::touch($cartId);

        return [
            'ok' => true,
            'message' => $adjusted < $quantity ? 'Quantidade ajustada ao estoque disponível.' : 'Quantidade atualizada.',
        ];
    }

    public static function remove(int $productId, ?int $userId): bool
    {
        $cart = self::resolve($userId, false);
        if ($cart === null) {
            return false;
        }

        Cart::touch((int) $cart['id']);

        return CartItem::removeProduct((int) $cart['id'], $productId);
    }

    public static function clear(?int $userId): void
    {
        $cart = self::resolve($userId, false);
        if ($cart !== null) {
            CartItem::clear((int) $cart['id']);
        }
    }

    /** Quantidade total para o badge do cabeçalho (não cria carrinho). */
    public static function countForHeader(?int $userId): int
    {
        $cart = self::resolve($userId, false);
        if ($cart !== null) {
            return CartItem::totalQuantity((int) $cart['id']);
        }

        return 0;
    }

    /** Mescla o carrinho de visitante com o carrinho do usuário após o login. */
    public static function mergeForUser(int $userId): void
    {
        $token = self::token();
        $guestCart = Cart::findByToken($token);
        $userCart = Cart::findForUser($userId);

        if ($guestCart === null) {
            if ($userCart !== null) {
                Session::set(self::SESSION_KEY, (string) $userCart['token']);
            }
            return;
        }

        if ($userCart !== null && (int) $userCart['id'] !== (int) $guestCart['id']) {
            $guestItems = self::items((int) $guestCart['id']);
            foreach ($guestItems as $item) {
                $existing = CartItem::findItem((int) $userCart['id'], (int) $item['product_id']);
                $current = $existing !== null ? (int) $existing['quantity'] : 0;
                $quantity = min($current + (int) $item['quantity'], (int) $item['stock_quantity'], 99);
                if ($quantity > 0) {
                    CartItem::setQuantity((int) $userCart['id'], (int) $item['product_id'], $quantity);
                }
            }

            Cart::deleteById((int) $guestCart['id']);
            Session::set(self::SESSION_KEY, (string) $userCart['token']);
            Cart::touch((int) $userCart['id']);

            return;
        }

        Cart::attachUser((int) $guestCart['id'], $userId);
    }
}
