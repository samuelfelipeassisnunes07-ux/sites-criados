<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;

abstract class IntegrationTestCase extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        self::resetDatabase();
        $_SESSION = [];
        Auth::logout();
        \App\Models\Setting::flush();
    }

    public static function resetDatabase(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec('TRUNCATE TABLE `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return array<string,mixed> */
    protected function createUser(string $email = 'cliente@teste.local', string $password = 'Teste@12345', string $role = 'customer'): array
    {
        $id = User::create('Usuário Teste', $email, $password, $role);
        $user = User::find($id);

        if ($user === null) {
            $this->fail('Não foi possível criar o usuário de teste.');
        }

        return $user;
    }

    /** @return array<string,mixed> */
    protected function createCategory(string $name = 'Camisas', string $slug = 'camisas'): array
    {
        $id = Category::insert([
            'name' => $name,
            'slug' => $slug,
            'description' => 'Categoria de teste',
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        return Category::find($id) ?? [];
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    protected function createProduct(array $overrides = []): array
    {
        $category = $this->createCategory('Categoria ' . bin2hex(random_bytes(2)), 'cat-' . bin2hex(random_bytes(3)));

        $defaults = [
            'category_id' => (int) $category['id'],
            'name' => 'Produto Teste',
            'slug' => 'produto-teste-' . bin2hex(random_bytes(3)),
            'sku' => 'SKU-' . bin2hex(random_bytes(3)),
            'short_description' => 'Produto de teste',
            'description' => 'Descrição completa do produto de teste.',
            'price' => 100.00,
            'sale_price' => null,
            'cost_price' => 50.00,
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'weight_grams' => 200,
            'is_active' => 1,
            'is_featured' => 0,
        ];

        $data = array_merge($defaults, $overrides);
        $id = Product::insert($data);

        return Product::find($id) ?? [];
    }

    /** @return array<string,mixed> */
    protected function createAddress(int $userId): array
    {
        $id = Address::createForUser($userId, [
            'label' => 'Casa',
            'recipient_name' => 'Usuário Teste',
            'phone' => '(11) 99999-0000',
            'cep' => '01001-000',
            'street' => 'Praça da Sé',
            'number' => '100',
            'complement' => null,
            'district' => 'Sé',
            'city' => 'São Paulo',
            'state' => 'SP',
            'is_default' => 1,
        ]);

        return Address::find($id) ?? [];
    }

    /** @param array<int,array{product_id:int,quantity:int}> $items */
    protected function fillCart(int $userId, array $items): void
    {
        $token = bin2hex(random_bytes(32));
        \App\Core\Session::set(CartService::SESSION_KEY, $token);
        $cart = Cart::getOrCreate($token, $userId);

        foreach ($items as $item) {
            CartItem::setQuantity((int) $cart['id'], (int) $item['product_id'], (int) $item['quantity']);
        }
    }
}
