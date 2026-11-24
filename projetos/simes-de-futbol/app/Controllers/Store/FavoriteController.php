<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Favorite;
use App\Models\Product;

final class FavoriteController extends Controller
{
    public function index(array $params = []): void
    {
        $this->render('store/favorites', [
            'title' => 'Meus favoritos',
            'products' => Favorite::withProducts((int) Auth::id()),
        ]);
    }

    public function toggle(array $params = []): void
    {
        $productId = Request::integer('product_id');
        $product = Product::findActive($productId);

        if ($product === null) {
            $this->error('Produto não encontrado.');
            $this->redirect('/favoritos');
        }

        $added = Favorite::toggle((int) Auth::id(), $productId);
        $this->success($added ? 'Produto adicionado aos favoritos.' : 'Produto removido dos favoritos.');

        \App\Core\Response::back();
    }
}
