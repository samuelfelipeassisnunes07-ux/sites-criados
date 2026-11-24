<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Models\Favorite;
use App\Models\Product;

final class ProductController extends Controller
{
    public function show(array $params = []): void
    {
        $product = Product::findActiveBySlug((string) ($params['slug'] ?? ''));
        if ($product === null) {
            abort(404, 'Produto não encontrado ou indisponível.');
        }

        $images = Product::images((int) $product['id']);
        $isFavorite = Auth::check() && Favorite::exists((int) Auth::id(), (int) $product['id']);

        $this->render('store/product', [
            'title' => $product['name'],
            'product' => $product,
            'images' => $images,
            'related' => Product::related((int) $product['category_id'], (int) $product['id'], 4),
            'isFavorite' => $isFavorite,
            'metaDescription' => $product['short_description'] ?? '',
        ]);
    }
}
