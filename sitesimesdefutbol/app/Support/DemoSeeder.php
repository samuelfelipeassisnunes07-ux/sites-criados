<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Str;
use App\Models\Address;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;

/**
 * Dados fictícios para demonstração. Não usar em produção.
 */
final class DemoSeeder
{
    /** @return array<string,string> */
    private const SETTINGS = [
        'store_name' => 'Simês de Fútbol',
        'store_slogan' => 'Paixão que se veste. Futebol que se vive.',
        'contact_email' => 'contato@simesdefutbol.com.br',
        'contact_phone' => '(11) 4000-1234',
        'whatsapp' => '5511999999999',
        'address' => 'Rua das Peladas, 10 - Centro - São Paulo/SP',
        'instagram' => '@simesdefutbol',
        'about_text' => 'A Simês de Fútbol nasceu da paixão pelo futebol de rua e pelos grandes clássicos. '
            . 'Selecionamos camisas, chuteiras, bolas e acessórios para quem vive o jogo dentro e fora de campo.',
        'footer_text' => 'Simês de Fútbol — CNPJ 00.000.000/0001-00. Todos os direitos reservados.',
    ];

    /** @var array<string,array{name:string,description:string,sort:int}> */
    private const CATEGORIES = [
        'camisas' => ['name' => 'Camisas', 'description' => 'Camisas de time, retrôs e modelos de treino.', 'sort' => 1],
        'chuteiras' => ['name' => 'Chuteiras', 'description' => 'Campo, society e futsal para todos os estilos.', 'sort' => 2],
        'bolas' => ['name' => 'Bolas', 'description' => 'Bolas oficiais e de treino para não errar o passe.', 'sort' => 3],
        'acessorios' => ['name' => 'Acessórios', 'description' => 'Luvas, meiões, mochilas e bonés.', 'sort' => 4],
        'infantil' => ['name' => 'Infantil', 'description' => 'Para os craques que estão começando.', 'sort' => 5],
    ];

    /**
     * @var array<int,array{name:string,category:string,price:float,sale:?float,stock:int,featured:bool,short:string,description:string,weight:int}>
     */
    private const PRODUCTS = [
        [
            'name' => 'Camisa Retrô Brasil 1970', 'category' => 'camisas', 'price' => 249.90, 'sale' => 199.90,
            'stock' => 25, 'featured' => true, 'short' => 'Clássico atemporal com escudo bordado.',
            'description' => 'Camisa retrô inspirada no time de 1970, com tecido leve, escudo bordado e caimento confortável. Uma peça de coleção para quem ama futebol.',
            'weight' => 250,
        ],
        [
            'name' => 'Camisa Simês Home 2026', 'category' => 'camisas', 'price' => 189.90, 'sale' => null,
            'stock' => 40, 'featured' => true, 'short' => 'A nova camisa oficial da temporada.',
            'description' => 'Camisa oficial da coleção 2026, com tecnologia de secagem rápida, gola confortável e escudo do clube aplicado a quente.',
            'weight' => 230,
        ],
        [
            'name' => 'Camisa Simês Away 2026', 'category' => 'camisas', 'price' => 189.90, 'sale' => 159.90,
            'stock' => 30, 'featured' => false, 'short' => 'Versão away em azul marinho.',
            'description' => 'Modelo away com detalhes em dourado, tecido respirável e modelagem atlética. Ideal para jogos e para o dia a dia.',
            'weight' => 230,
        ],
        [
            'name' => 'Camisa Infantil Craque Mirim', 'category' => 'infantil', 'price' => 129.90, 'sale' => 89.90,
            'stock' => 20, 'featured' => true, 'short' => 'Para os futuros craques.',
            'description' => 'Camisa infantil com tecido macio, costuras reforçadas e estampa divertida. Tamanhos do 2 ao 14.',
            'weight' => 160,
        ],
        [
            'name' => 'Chuteira Velocidade Pro FG', 'category' => 'chuteiras', 'price' => 399.90, 'sale' => 329.90,
            'stock' => 15, 'featured' => true, 'short' => 'Leveza e tração para campo.',
            'description' => 'Chuteira para grama natural com cabedal em material sintético de alta resistência, trava mista e entressola amortecida.',
            'weight' => 320,
        ],
        [
            'name' => 'Chuteira Society Controle', 'category' => 'chuteiras', 'price' => 289.90, 'sale' => null,
            'stock' => 18, 'featured' => false, 'short' => 'Controle de bola no society.',
            'description' => 'Chuteira de society com solado em borracha antiderrapante, cabedal texturizado para melhor contato com a bola.',
            'weight' => 300,
        ],
        [
            'name' => 'Chuteira Futsal Street', 'category' => 'chuteiras', 'price' => 229.90, 'sale' => 189.90,
            'stock' => 12, 'featured' => false, 'short' => 'Estabilidade e conforto na quadra.',
            'description' => 'Chuteira de futsal com solado não marcante, forro acolchoado e ótima aderência para mudanças rápidas de direção.',
            'weight' => 280,
        ],
        [
            'name' => 'Bola Campo Oficial', 'category' => 'bolas', 'price' => 149.90, 'sale' => 119.90,
            'stock' => 35, 'featured' => true, 'short' => 'Bola oficial tamanho 5.',
            'description' => 'Bola oficial tamanho 5 com costura termofundida, câmara de ar confiável e superfície resistente para campo e society.',
            'weight' => 450,
        ],
        [
            'name' => 'Bola Futsal Pro', 'category' => 'bolas', 'price' => 129.90, 'sale' => null,
            'stock' => 28, 'featured' => false, 'short' => 'Toque macio e precisão.',
            'description' => 'Bola de futsal com baixo quique, ideal para quadra. Material de PVC resistente e ótima sensibilidade no toque.',
            'weight' => 420,
        ],
        [
            'name' => 'Luva de Goleiro Elite', 'category' => 'acessorios', 'price' => 179.90, 'sale' => 149.90,
            'stock' => 10, 'featured' => false, 'short' => 'Aderência e proteção para defesas.',
            'description' => 'Luva de goleiro com palma em látex aderente, proteção nos dedos e ajuste por velcro. Tamanhos 8 ao 11.',
            'weight' => 200,
        ],
        [
            'name' => 'Meião Simês', 'category' => 'acessorios', 'price' => 49.90, 'sale' => null,
            'stock' => 60, 'featured' => false, 'short' => 'Par de meiões com elástico confortável.',
            'description' => 'Meião em poliamida com elasticidade, reforço no calcanhar e punho confortável. Disponível em verde e branco.',
            'weight' => 90,
        ],
        [
            'name' => 'Mochila Esportiva', 'category' => 'acessorios', 'price' => 199.90, 'sale' => 169.90,
            'stock' => 14, 'featured' => false, 'short' => 'Compartimento para chuteira e bola.',
            'description' => 'Mochila esportiva com 30 litros, compartimento ventilado para chuteiras, bolso para garrafa e alças acolchoadas.',
            'weight' => 700,
        ],
        [
            'name' => 'Boné Simês', 'category' => 'acessorios', 'price' => 79.90, 'sale' => null,
            'stock' => 50, 'featured' => false, 'short' => 'Aba curva com bordado.',
            'description' => 'Boné em algodão com aba curva, fecho ajustável e bordado frontal. Proteção e estilo fora de campo.',
            'weight' => 120,
        ],
        [
            'name' => 'Camisa Treino Dry', 'category' => 'camisas', 'price' => 139.90, 'sale' => 109.90,
            'stock' => 33, 'featured' => false, 'short' => 'Tecnologia dry para treinos intensos.',
            'description' => 'Camisa de treino com tecido de secagem rápida, costas em tela respirável e modelagem confortável.',
            'weight' => 180,
        ],
    ];

    public static function run(): void
    {
        self::seedSettings();
        self::seedCategories();
        self::seedProducts();
        self::seedCoupons();
        self::seedBanners();

        $customer = self::seedDemoCustomer();
        if ($customer !== null) {
            self::seedDemoOrders($customer);
        }
    }

    private static function seedSettings(): void
    {
        $existing = Setting::all();

        foreach (self::SETTINGS as $name => $value) {
            if (!array_key_exists($name, $existing) || $existing[$name] === '') {
                Setting::set($name, $value);
            }
        }
    }

    private static function seedCategories(): void
    {
        foreach (self::CATEGORIES as $slug => $category) {
            if (Category::findBySlug($slug) !== null) {
                continue;
            }

            Category::insert([
                'name' => $category['name'],
                'slug' => $slug,
                'description' => $category['description'],
                'is_active' => 1,
                'sort_order' => $category['sort'],
            ]);
        }
    }

    private static function seedProducts(): void
    {
        if (Product::count('deleted_at IS NULL') > 0) {
            return;
        }

        $demoDir = BASE_PATH . '/public/assets/img/demo';
        if (!is_dir($demoDir)) {
            @mkdir($demoDir, 0775, true);
        }

        $index = 0;
        foreach (self::PRODUCTS as $data) {
            $index++;
            $category = Category::findBySlug($data['category']);
            if ($category === null) {
                continue;
            }

            $slug = Str::slug($data['name']);
            $createdAt = date('Y-m-d H:i:s', strtotime('-' . (30 - $index) . ' days'));

            $productId = Product::insert([
                'category_id' => (int) $category['id'],
                'name' => $data['name'],
                'slug' => $slug,
                'sku' => 'SDF-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'short_description' => $data['short'],
                'description' => $data['description'],
                'price' => $data['price'],
                'sale_price' => $data['sale'],
                'cost_price' => round($data['price'] * 0.55, 2),
                'stock_quantity' => $data['stock'],
                'low_stock_threshold' => 5,
                'weight_grams' => $data['weight'],
                'is_active' => 1,
                'is_featured' => $data['featured'] ? 1 : 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $palettes = [
                ['#0b6e4f', '#16a34a'], ['#123c69', '#2f6690'], ['#6b2737', '#b23a48'],
                ['#7c4d00', '#b7791f'], ['#2d3748', '#4a5568'], ['#0f766e', '#14b8a6'],
            ];
            $palette = $palettes[($index - 1) % count($palettes)];
            $labels = ['Frente', 'Detalhe', 'Verso'];

            for ($image = 1; $image <= 3; $image++) {
                $filename = 'assets/img/demo/' . $slug . '-' . $image . '.svg';
                $svg = self::productSvg($data['name'], $labels[$image - 1], $palette[0], $palette[1], $image);
                @file_put_contents(BASE_PATH . '/public/' . $filename, $svg);

                ProductImage::create(
                    $productId,
                    $filename,
                    $data['name'] . ' - ' . $labels[$image - 1],
                    $image,
                    $image === 1
                );
            }

            StockMovement::record(
                $productId,
                'entrada',
                $data['stock'],
                0,
                $data['stock'],
                null,
                null,
                'Estoque inicial (dados de demonstração)'
            );
        }
    }

    private static function seedCoupons(): void
    {
        $coupons = [
            [
                'code' => 'BEMVINDO10', 'description' => '10% de desconto na primeira compra',
                'type' => 'percent', 'value' => 10.00, 'min' => 99.00, 'max' => 200,
                'per_user' => 1, 'expires_days' => 90,
            ],
            [
                'code' => 'FRETE10', 'description' => 'R$ 10 de desconto no frete',
                'type' => 'fixed', 'value' => 10.00, 'min' => 150.00, 'max' => 100,
                'per_user' => 1, 'expires_days' => 60,
            ],
            [
                'code' => 'SIMES15', 'description' => '15% de desconto em compras acima de R$ 250',
                'type' => 'percent', 'value' => 15.00, 'min' => 250.00, 'max' => 50,
                'per_user' => 1, 'expires_days' => 30,
            ],
        ];

        foreach ($coupons as $coupon) {
            if (Coupon::findByCode($coupon['code']) !== null) {
                continue;
            }

            Coupon::insert([
                'code' => $coupon['code'],
                'description' => $coupon['description'],
                'type' => $coupon['type'],
                'value' => $coupon['value'],
                'min_order_amount' => $coupon['min'],
                'max_uses' => $coupon['max'],
                'used_count' => 0,
                'max_uses_per_user' => $coupon['per_user'],
                'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+' . $coupon['expires_days'] . ' days')),
                'is_active' => 1,
            ]);
        }
    }

    private static function seedBanners(): void
    {
        if (Banner::count('1') > 0) {
            return;
        }

        $demoDir = BASE_PATH . '/public/assets/img/demo';
        if (!is_dir($demoDir)) {
            @mkdir($demoDir, 0775, true);
        }

        $banners = [
            [
                'title' => 'Nova coleção 2026',
                'subtitle' => 'Camisas oficiais com frete grátis acima de R$ 299.',
                'link' => '/catalogo?category=camisas',
                'palette' => ['#064e3b', '#16a34a'],
            ],
            [
                'title' => 'Chuteiras com até 20% OFF',
                'subtitle' => 'Velocidade e conforto para dominar o campo.',
                'link' => '/catalogo?category=chuteiras',
                'palette' => ['#1e3a8a', '#3b82f6'],
            ],
            [
                'title' => 'Para os craques mirins',
                'subtitle' => 'Linha infantil completa a partir de R$ 89,90.',
                'link' => '/catalogo?category=infantil',
                'palette' => ['#7c2d12', '#f97316'],
            ],
        ];

        foreach ($banners as $index => $banner) {
            $filename = 'assets/img/demo/banner-' . ($index + 1) . '.svg';
            @file_put_contents(
                BASE_PATH . '/public/' . $filename,
                self::bannerSvg($banner['palette'][0], $banner['palette'][1], $index + 1)
            );

            Banner::insert([
                'title' => $banner['title'],
                'subtitle' => $banner['subtitle'],
                'image' => $filename,
                'link' => $banner['link'],
                'position' => 'hero',
                'is_active' => 1,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /** @return array<string,mixed>|null */
    private static function seedDemoCustomer(): ?array
    {
        $email = 'cliente@demo.local';
        $user = User::findByEmail($email);

        if ($user === null) {
            $userId = User::create('Cliente Demo', $email, 'Demo@12345', 'customer');
            $user = User::find($userId);
        }

        if ($user === null) {
            return null;
        }

        if (Address::forUser((int) $user['id']) === []) {
            Address::createForUser((int) $user['id'], [
                'label' => 'Casa',
                'recipient_name' => 'Cliente Demo',
                'phone' => '(11) 98888-7777',
                'cep' => '01001-000',
                'street' => 'Praça da Sé',
                'number' => '100',
                'complement' => 'Apto 12',
                'district' => 'Sé',
                'city' => 'São Paulo',
                'state' => 'SP',
                'is_default' => 1,
            ]);
        }

        return $user;
    }

    /** @param array<string,mixed> $customer */
    private static function seedDemoOrders(array $customer): void
    {
        $userId = (int) $customer['id'];

        if (Order::count('user_id = ?', [$userId]) > 0) {
            return;
        }

        $address = Address::defaultForUser($userId);
        if ($address === null) {
            return;
        }

        self::createDemoOrder($userId, $address, 'aguardando_pagamento', 'pending', [
            ['sku' => 'SDF-0009', 'quantity' => 1],
        ], 1, null);

        self::createDemoOrder($userId, $address, 'pago', 'paid', [
            ['sku' => 'SDF-0001', 'quantity' => 1],
            ['sku' => 'SDF-0011', 'quantity' => 2],
        ], 3, null);

        self::createDemoOrder($userId, $address, 'enviado', 'paid', [
            ['sku' => 'SDF-0005', 'quantity' => 1],
        ], 6, 'BR123456789BR');

        self::createDemoOrder($userId, $address, 'entregue', 'paid', [
            ['sku' => 'SDF-0008', 'quantity' => 1],
            ['sku' => 'SDF-0013', 'quantity' => 1],
        ], 12, null);
    }

    /** @param array<string,mixed> $address */
    private static function createDemoOrder(
        int $userId,
        array $address,
        string $status,
        string $paymentStatus,
        array $items,
        int $daysAgo,
        ?string $tracking
    ): void {
        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $product = Product::findBy('sku', $item['sku']);
            if ($product === null) {
                continue;
            }

            $unitPrice = product_price($product);
            $quantity = (int) $item['quantity'];
            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;

            $images = Product::images((int) $product['id']);
            $lines[] = [
                'product_id' => (int) $product['id'],
                'product_name' => (string) $product['name'],
                'product_sku' => (string) $product['sku'],
                'product_image' => $images[0]['filename'] ?? null,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        if ($lines === []) {
            return;
        }

        $subtotal = round($subtotal, 2);
        $shipping = $subtotal >= 299.00 ? 0.0 : 24.90;
        $total = round($subtotal + $shipping, 2);
        $createdAt = date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days'));

        $orderId = Order::insert([
            'code' => 'DEMO-' . strtoupper(Str::randomToken(3)),
            'user_id' => $userId,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_method' => 'demo',
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'shipping_total' => $shipping,
            'total' => $total,
            'shipping_method' => 'flat',
            'shipping_label' => $shipping > 0 ? 'Entrega padrão' : 'Entrega padrão (frete grátis)',
            'recipient_name' => $address['recipient_name'],
            'recipient_phone' => $address['phone'],
            'shipping_cep' => $address['cep'],
            'shipping_street' => $address['street'],
            'shipping_number' => $address['number'],
            'shipping_complement' => $address['complement'],
            'shipping_district' => $address['district'],
            'shipping_city' => $address['city'],
            'shipping_state' => $address['state'],
            'tracking_code' => $tracking,
            'paid_at' => $paymentStatus === 'paid' ? $createdAt : null,
            'shipped_at' => $status === 'enviado' || $status === 'entregue' ? $createdAt : null,
            'delivered_at' => $status === 'entregue' ? $createdAt : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        OrderItem::insertMany($orderId, $lines);

        $paymentId = Payment::insert([
            'order_id' => $orderId,
            'provider' => 'demo',
            'method' => 'demo',
            'amount' => $total,
            'status' => $paymentStatus,
            'paid_at' => $paymentStatus === 'paid' ? $createdAt : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        unset($paymentId);

        OrderStatusHistory::insert([
            'order_id' => $orderId,
            'from_status' => null,
            'to_status' => 'aguardando_pagamento',
            'note' => 'Pedido criado (demonstração).',
            'created_at' => $createdAt,
        ]);

        $steps = [];
        if (in_array($status, ['pago', 'em_preparacao', 'enviado', 'entregue'], true)) {
            $steps[] = 'pago';
        }
        if (in_array($status, ['em_preparacao', 'enviado', 'entregue'], true)) {
            $steps[] = 'em_preparacao';
        }
        if (in_array($status, ['enviado', 'entregue'], true)) {
            $steps[] = 'enviado';
        }
        if ($status === 'entregue') {
            $steps[] = 'entregue';
        }

        $previous = 'aguardando_pagamento';
        foreach ($steps as $index => $step) {
            OrderStatusHistory::insert([
                'order_id' => $orderId,
                'from_status' => $previous,
                'to_status' => $step,
                'note' => 'Atualização de demonstração.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days +' . ($index + 1) . ' hours')),
            ]);
            $previous = $step;
        }

        foreach ($lines as $line) {
            $product = Product::find((int) $line['product_id']);
            if ($product === null) {
                continue;
            }

            $previousStock = (int) $product['stock_quantity'];
            $quantity = (int) $line['quantity'];
            $newStock = max(0, $previousStock - $quantity);

            Product::updateById((int) $product['id'], ['stock_quantity' => $newStock]);
            StockMovement::record(
                (int) $product['id'],
                'venda',
                $quantity,
                $previousStock,
                $newStock,
                $orderId,
                null,
                'Pedido de demonstração'
            );
        }
    }

    private static function productSvg(string $name, string $label, string $color1, string $color2, int $variant): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $words = explode(' ', $name);
        $line1 = implode(' ', array_slice($words, 0, 2));
        $line2 = implode(' ', array_slice($words, 2));
        $line1 = htmlspecialchars($line1, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $line2 = htmlspecialchars($line2, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $offset = 40 * $variant;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800" role="img" aria-label="{$safeName}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$color1}"/>
      <stop offset="1" stop-color="{$color2}"/>
    </linearGradient>
    <pattern id="dots" width="56" height="56" patternUnits="userSpaceOnUse">
      <circle cx="28" cy="28" r="3" fill="#ffffff" opacity="0.12"/>
    </pattern>
  </defs>
  <rect width="800" height="800" fill="url(#bg)"/>
  <rect width="800" height="800" fill="url(#dots)"/>
  <circle cx="400" cy="320" r="190" fill="#ffffff" opacity="0.10"/>
  <circle cx="400" cy="320" r="150" fill="#ffffff" opacity="0.14"/>
  <text x="400" y="390" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="150" opacity="0.9">⚽</text>
  <text x="400" y="610" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="40" font-weight="bold" fill="#ffffff">{$line1}</text>
  <text x="400" y="660" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="40" font-weight="bold" fill="#ffffff">{$line2}</text>
  <text x="400" y="730" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="24" letter-spacing="6" fill="#ffffff" opacity="0.8">{$safeLabel}</text>
  <text x="40" y="70" font-family="Arial, Helvetica, sans-serif" font-size="22" fill="#ffffff" opacity="0.7">SIMÊS DE FÚTBOL</text>
  <text x="760" y="760" text-anchor="end" font-family="Arial, Helvetica, sans-serif" font-size="18" fill="#ffffff" opacity="0.55">imagem ilustrativa</text>
</svg>
SVG;
    }

    private static function bannerSvg(string $color1, string $color2, int $variant): string
    {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="640" viewBox="0 0 1600 640" role="img" aria-label="Banner promocional">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$color1}"/>
      <stop offset="1" stop-color="{$color2}"/>
    </linearGradient>
  </defs>
  <rect width="1600" height="640" fill="url(#bg)"/>
  <g opacity="0.12" fill="#ffffff">
    <circle cx="1280" cy="120" r="240"/>
    <circle cx="1450" cy="520" r="180"/>
    <circle cx="1050" cy="540" r="120"/>
  </g>
  <g opacity="0.2" stroke="#ffffff" stroke-width="2" fill="none">
    <path d="M0 560 Q 400 380 800 520 T 1600 420"/>
    <path d="M0 620 Q 400 440 800 580 T 1600 480"/>
  </g>
  <text x="80" y="300" font-family="Arial, Helvetica, sans-serif" font-size="150" font-weight="bold" fill="#ffffff" opacity="0.95">⚽</text>
  <text x="80" y="420" font-family="Arial, Helvetica, sans-serif" font-size="30" fill="#ffffff" opacity="0.7">COLEÇÃO {$variant} · SIMÊS DE FÚTBOL</text>
</svg>
SVG;
    }
}
