<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Str;
use App\Core\Validator;
use App\Models\AdminLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockMovement;
use App\Services\ImageUploadService;
use App\Services\StockService;

final class AdminProductController extends Controller
{
    public function index(array $params = []): void
    {
        $search = trim((string) Request::input('q', ''));
        $status = (string) Request::input('status', '');
        $categoryId = Request::integer('category');
        $page = max(1, Request::integer('page', 1));

        $this->renderAdmin('admin/products/index', [
            'title' => 'Produtos',
            'products' => Product::paginateAdmin($search, $status, $categoryId, $page, 15),
            'categories' => Category::allOrdered(),
            'search' => $search,
            'status' => $status,
            'categoryId' => $categoryId,
            'query' => array_filter([
                'q' => $search,
                'status' => $status,
                'category' => $categoryId > 0 ? $categoryId : null,
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ]);
    }

    public function create(array $params = []): void
    {
        $this->renderForm(null, [], $this->defaultProduct());
    }

    public function store(array $params = []): void
    {
        [$data, $errors] = $this->validateProduct(null);

        if ($errors !== []) {
            $this->renderForm(null, $errors, array_merge($this->defaultProduct(), $data));
            return;
        }

        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $stock = (int) $data['stock_quantity'];
        unset($data['stock_quantity']);

        $productId = Product::insert($data);
        Product::updateById($productId, ['stock_quantity' => $stock]);

        if ($stock > 0) {
            StockMovement::record(
                $productId,
                'entrada',
                $stock,
                0,
                $stock,
                null,
                Auth::id(),
                'Estoque inicial do cadastro'
            );
        }

        $this->processImageUploads($productId, $data['name']);

        AdminLog::record('product.created', 'product', $productId, ['name' => $data['name']]);
        $this->success('Produto cadastrado com sucesso.');
        $this->redirect('/admin/produtos/' . $productId . '/editar');
    }

    public function edit(array $params = []): void
    {
        $product = $this->findProduct((int) ($params['id'] ?? 0));

        $this->renderForm($product, [], $product);
    }

    public function update(array $params = []): void
    {
        $product = $this->findProduct((int) ($params['id'] ?? 0));
        $productId = (int) $product['id'];

        [$data, $errors] = $this->validateProduct($productId);

        if ($errors !== []) {
            $this->renderForm($product, $errors, array_merge($product, $data));
            return;
        }

        $newStock = (int) $data['stock_quantity'];
        unset($data['stock_quantity']);

        Product::updateById($productId, $data);

        if ($newStock !== (int) $product['stock_quantity']) {
            $result = StockService::setStock(
                $productId,
                $newStock,
                Auth::id(),
                'Ajuste manual no cadastro do produto'
            );
            if (!$result['ok']) {
                $this->error($result['message']);
            }
        }

        $this->processImageUploads($productId, $data['name']);

        AdminLog::record('product.updated', 'product', $productId, ['name' => $data['name']]);
        $this->success('Produto atualizado com sucesso.');
        $this->redirect('/admin/produtos/' . $productId . '/editar');
    }

    public function toggle(array $params = []): void
    {
        $product = $this->findProduct((int) ($params['id'] ?? 0));
        $newState = (int) $product['is_active'] === 1 ? 0 : 1;

        Product::updateById((int) $product['id'], ['is_active' => $newState]);
        AdminLog::record('product.toggled', 'product', (int) $product['id'], ['active' => $newState]);

        $this->success($newState === 1 ? 'Produto ativado.' : 'Produto desativado.');
        $this->redirect('/admin/produtos');
    }

    public function destroy(array $params = []): void
    {
        $product = $this->findProduct((int) ($params['id'] ?? 0));

        Product::updateById((int) $product['id'], [
            'is_active' => 0,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        AdminLog::record('product.deleted', 'product', (int) $product['id'], ['name' => $product['name']]);
        $this->success('Produto desativado e removido do catÃ¡logo (histÃ³rico de pedidos preservado).');
        $this->redirect('/admin/produtos');
    }

    public function uploadImages(array $params = []): void
    {
        $product = $this->findProduct((int) ($params['id'] ?? 0));
        $uploaded = $this->processImageUploads((int) $product['id'], (string) $product['name']);

        if ($uploaded > 0) {
            AdminLog::record('product.images_uploaded', 'product', (int) $product['id'], ['count' => $uploaded]);
            $this->success($uploaded . ' imagem(ns) enviada(s).');
        } else {
            $this->error('Nenhuma imagem foi enviada. Verifique o formato (JPG, PNG ou WebP) e o tamanho.');
        }

        $this->redirect('/admin/produtos/' . $product['id'] . '/editar');
    }

    public function deleteImage(array $params = []): void
    {
        $product = $this->findProduct((int) ($params['product_id'] ?? 0));
        $imageId = (int) ($params['image_id'] ?? 0);

        $filename = ProductImage::deleteForProduct($imageId, (int) $product['id']);
        if ($filename === null) {
            $this->error('Imagem nÃ£o encontrada.');
        } else {
            ImageUploadService::delete($filename);
            AdminLog::record('product.image_deleted', 'product', (int) $product['id'], ['image_id' => $imageId]);
            $this->success('Imagem removida.');
        }

        $this->redirect('/admin/produtos/' . $product['id'] . '/editar');
    }

    public function primaryImage(array $params = []): void
    {
        $product = $this->findProduct((int) ($params['product_id'] ?? 0));
        $imageId = (int) ($params['image_id'] ?? 0);

        ProductImage::setPrimary($imageId, (int) $product['id']);
        AdminLog::record('product.image_primary', 'product', (int) $product['id'], ['image_id' => $imageId]);
        $this->success('Imagem principal atualizada.');
        $this->redirect('/admin/produtos/' . $product['id'] . '/editar');
    }

    public function adjustStock(array $params = []): void
    {
        $product = $this->findProduct((int) ($params['id'] ?? 0));
        $quantity = Request::integer('stock_quantity', -1);
        $reason = trim((string) Request::input('reason', 'Ajuste manual de estoque'));

        if ($quantity < 0) {
            $this->error('Informe uma quantidade vÃ¡lida.');
            $this->redirect('/admin/produtos/' . $product['id'] . '/editar');
        }

        $result = StockService::setStock((int) $product['id'], $quantity, Auth::id(), $reason !== '' ? $reason : 'Ajuste manual');
        if ($result['ok']) {
            AdminLog::record('product.stock_adjusted', 'product', (int) $product['id'], [
                'previous' => $result['previous'] ?? null,
                'new' => $result['new'] ?? null,
                'reason' => $reason,
            ]);
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/admin/produtos/' . $product['id'] . '/editar');
    }

    /** @return array<string,mixed> */
    private function findProduct(int $id): array
    {
        $product = Product::find($id);
        if ($product === null || $product['deleted_at'] !== null) {
            abort(404, 'Produto nÃ£o encontrado.');
        }

        return $product;
    }

    /** @return array<string,mixed> */
    private function defaultProduct(): array
    {
        return [
            'name' => '',
            'category_id' => '',
            'sku' => '',
            'short_description' => '',
            'description' => '',
            'price' => '',
            'sale_price' => '',
            'cost_price' => '',
            'stock_quantity' => 0,
            'low_stock_threshold' => 5,
            'weight_grams' => 0,
            'is_active' => 1,
            'is_featured' => 0,
        ];
    }

    /**
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    private function validateProduct(?int $productId): array
    {
        $input = [
            'name' => trim((string) Request::input('name', '')),
            'category_id' => (string) Request::input('category_id', ''),
            'sku' => mb_strtoupper(trim((string) Request::input('sku', ''))),
            'short_description' => trim((string) Request::input('short_description', '')),
            'description' => trim((string) Request::input('description', '')),
            'price' => (string) Request::input('price', ''),
            'sale_price' => (string) Request::input('sale_price', ''),
            'cost_price' => (string) Request::input('cost_price', ''),
            'stock_quantity' => (string) Request::input('stock_quantity', '0'),
            'low_stock_threshold' => (string) Request::input('low_stock_threshold', '5'),
            'weight_grams' => (string) Request::input('weight_grams', '0'),
            'is_active' => Request::boolean('is_active') ? '1' : '0',
            'is_featured' => Request::boolean('is_featured') ? '1' : '0',
        ];

        $rules = [
            'name' => 'required|min:3|max:180',
            'category_id' => 'required|integer',
            'sku' => 'required|max:60|unique:products,sku' . ($productId !== null ? ',' . $productId : ''),
            'short_description' => 'max:255',
            'description' => 'required|min:10',
            'price' => 'required|numeric|min_value:0.01|max_value:999999.99',
            'sale_price' => 'numeric|min_value:0.01|max_value:999999.99',
            'cost_price' => 'numeric|min_value:0|max_value:999999.99',
            'stock_quantity' => 'required|integer|min_value:0|max_value:1000000',
            'low_stock_threshold' => 'integer|min_value:0|max_value:1000000',
            'weight_grams' => 'integer|min_value:0|max_value:1000000',
        ];

        $validator = Validator::make($input, $rules);
        $errors = $validator->errors();

        if (!is_numeric($input['category_id']) || (int) $input['category_id'] <= 0) {
            $errors['category_id'] = 'Selecione uma categoria.';
        } elseif (Category::find((int) $input['category_id']) === null) {
            $errors['category_id'] = 'Categoria invÃ¡lida.';
        }

        if ($input['sale_price'] !== '' && is_numeric($input['price']) && (float) $input['sale_price'] >= (float) $input['price']) {
            $errors['sale_price'] = 'O preÃ§o promocional deve ser menor que o preÃ§o normal.';
        }

        $data = [
            'name' => $input['name'],
            'category_id' => (int) $input['category_id'],
            'sku' => $input['sku'],
            'short_description' => $input['short_description'] !== '' ? $input['short_description'] : null,
            'description' => $input['description'],
            'price' => round((float) $input['price'], 2),
            'sale_price' => $input['sale_price'] !== '' ? round((float) $input['sale_price'], 2) : null,
            'cost_price' => $input['cost_price'] !== '' ? round((float) $input['cost_price'], 2) : null,
            'stock_quantity' => (int) $input['stock_quantity'],
            'low_stock_threshold' => (int) ($input['low_stock_threshold'] !== '' ? $input['low_stock_threshold'] : 5),
            'weight_grams' => (int) ($input['weight_grams'] !== '' ? $input['weight_grams'] : 0),
            'is_active' => (int) $input['is_active'],
            'is_featured' => (int) $input['is_featured'],
        ];

        return [$data, $errors];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Product::slugExists($slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function renderForm(?array $product, array $errors, array $old): void
    {
        $isEdit = $product !== null;

        $this->renderAdmin($isEdit ? 'admin/products/edit' : 'admin/products/create', [
            'title' => $isEdit ? 'Editar produto' : 'Novo produto',
            'product' => $old,
            'productId' => $isEdit ? (int) $product['id'] : null,
            'categories' => Category::allOrdered(),
            'images' => $isEdit ? ProductImage::forProduct((int) $product['id']) : [],
            'movements' => $isEdit ? StockMovement::forProduct((int) $product['id'], 30) : [],
            'errors' => $errors,
            'isEdit' => $isEdit,
        ]);
    }

    private function processImageUploads(int $productId, string $productName): int
    {
        $files = $_FILES['images'] ?? null;
        if (!is_array($files) || !isset($files['name'])) {
            return 0;
        }

        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

        $uploaded = 0;
        $startSort = ProductImage::countForProduct($productId);

        foreach ($names as $index => $name) {
            $file = [
                'name' => $name,
                'tmp_name' => $tmpNames[$index] ?? '',
                'error' => $errors[$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $sizes[$index] ?? 0,
            ];

            if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result = ImageUploadService::store($file, 'products');
            if (!$result['ok']) {
                $this->error($result['message']);
                continue;
            }

            ProductImage::create(
                $productId,
                (string) $result['filename'],
                $productName . ' - imagem ' . ($startSort + $uploaded + 1),
                $startSort + $uploaded + 1,
                $startSort === 0 && $uploaded === 0
            );
            $uploaded++;
        }

        return $uploaded;
    }
}

