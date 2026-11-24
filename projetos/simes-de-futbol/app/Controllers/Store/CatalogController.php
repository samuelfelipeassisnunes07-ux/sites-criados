<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Request;
use App\Models\Category;
use App\Models\Product;

final class CatalogController extends Controller
{
    public function index(array $params = []): void
    {
        $filters = [
            'q' => trim((string) Request::input('q', '')),
            'category' => trim((string) Request::input('category', '')),
            'min_price' => Request::input('min_price'),
            'max_price' => Request::input('max_price'),
            'sort' => (string) Request::input('sort', 'relevance'),
        ];

        $allowedSorts = ['relevance', 'newest', 'price_asc', 'price_desc', 'name_asc', 'name_desc'];
        if (!in_array($filters['sort'], $allowedSorts, true)) {
            $filters['sort'] = 'relevance';
        }

        foreach (['min_price', 'max_price'] as $priceKey) {
            if ($filters[$priceKey] !== null && $filters[$priceKey] !== '' && !is_numeric($filters[$priceKey])) {
                $filters[$priceKey] = null;
            }
        }

        if (
            is_numeric($filters['min_price'] ?? null)
            && is_numeric($filters['max_price'] ?? null)
            && (float) $filters['min_price'] > (float) $filters['max_price']
        ) {
            [$filters['min_price'], $filters['max_price']] = [$filters['max_price'], $filters['min_price']];
        }

        $currentCategory = null;
        if ($filters['category'] !== '') {
            $currentCategory = Category::findBySlug($filters['category']);
            if ($currentCategory === null || (int) $currentCategory['is_active'] !== 1) {
                abort(404, 'Categoria não encontrada.');
            }
        }

        $page = max(1, Request::integer('page', 1));
        $products = Product::catalog($filters, $page, 12);

        $query = array_filter([
            'q' => $filters['q'],
            'category' => $filters['category'],
            'min_price' => $filters['min_price'],
            'max_price' => $filters['max_price'],
            'sort' => $filters['sort'] !== 'relevance' ? $filters['sort'] : '',
        ], static fn ($value): bool => $value !== null && $value !== '');

        $this->render('store/catalog', [
            'title' => ($currentCategory !== null ? $currentCategory['name'] . ' - ' : '') . 'Catálogo',
            'products' => $products,
            'categories' => Category::active(),
            'currentCategory' => $currentCategory,
            'filters' => $filters,
            'query' => $query,
        ]);
    }
}
