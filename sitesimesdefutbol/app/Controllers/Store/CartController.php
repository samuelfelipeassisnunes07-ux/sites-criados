<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Services\CartService;

final class CartController extends Controller
{
    public function index(array $params = []): void
    {
        $userId = Auth::id();
        $summary = CartService::summary($userId, false);

        $this->render('store/cart', [
            'title' => 'Carrinho',
            'summary' => $summary,
        ]);
    }

    public function add(array $params = []): void
    {
        $productId = Request::integer('product_id');
        $quantity = max(1, Request::integer('quantity', 1));

        $result = CartService::add($productId, $quantity, Auth::id());

        if ($result['ok']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/carrinho');
    }

    public function update(array $params = []): void
    {
        $quantities = Request::input('quantities', []);
        if (!is_array($quantities)) {
            $this->error('Dados de quantidade inválidos.');
            $this->redirect('/carrinho');
        }

        $messages = [];
        foreach ($quantities as $productId => $quantity) {
            if (!is_numeric($productId) || !is_numeric($quantity)) {
                continue;
            }

            $result = CartService::setQuantity((int) $productId, (int) $quantity, Auth::id());
            if ($result['ok']) {
                $messages[] = $result['message'];
            }
        }

        if ($messages !== []) {
            $this->success(count($messages) === 1 ? $messages[0] : 'Carrinho atualizado.');
        }

        $this->redirect('/carrinho');
    }

    public function remove(array $params = []): void
    {
        $productId = Request::integer('product_id');
        if ($productId > 0 && CartService::remove($productId, Auth::id())) {
            $this->success('Item removido do carrinho.');
        } else {
            $this->error('Não foi possível remover o item.');
        }

        $this->redirect('/carrinho');
    }

    public function clear(array $params = []): void
    {
        CartService::clear(Auth::id());
        $this->success('Carrinho esvaziado.');
        $this->redirect('/carrinho');
    }
}
