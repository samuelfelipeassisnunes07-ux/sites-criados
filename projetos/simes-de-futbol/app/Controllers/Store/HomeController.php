<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;

final class HomeController extends Controller
{
    public function index(array $params = []): void
    {
        $this->render('store/home', [
            'title' => setting('store_name', 'Simês de Fútbol') . ' - ' . setting('store_slogan', 'Paixão que se veste'),
            'banners' => Banner::active('hero'),
            'categories' => array_values(array_filter(
                Category::withProductCounts(),
                static fn (array $category): bool => (int) $category['is_active'] === 1
            )),
            'featured' => Product::featured(8),
            'newArrivals' => Product::newArrivals(8),
            'promotions' => Product::promotions(8),
        ]);
    }
}
