<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Str;
use App\Core\Validator;
use App\Models\AdminLog;
use App\Models\Category;

final class AdminCategoryController extends Controller
{
    public function index(array $params = []): void
    {
        $this->renderAdmin('admin/categories/index', [
            'title' => 'Categorias',
            'categories' => Category::withProductCounts(),
        ]);
    }

    public function store(array $params = []): void
    {
        $data = [
            'name' => trim((string) Request::input('name', '')),
            'description' => trim((string) Request::input('description', '')),
            'sort_order' => Request::integer('sort_order', 0),
            'is_active' => Request::boolean('is_active') ? 1 : 0,
        ];

        $validator = Validator::make($data, [
            'name' => 'required|min:2|max:120',
            'description' => 'max:500',
        ]);

        if ($validator->fails()) {
            $this->error($validator->firstError() ?? 'Verifique os dados.');
            $this->redirect('/admin/categorias');
        }

        $slug = Str::slug($data['name']);
        $suffix = 2;
        while (Category::findBySlug($slug) !== null) {
            $slug = Str::slug($data['name']) . '-' . $suffix;
            $suffix++;
        }

        $categoryId = Category::insert([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
        ]);

        AdminLog::record('category.created', 'category', $categoryId, ['name' => $data['name']]);
        $this->success('Categoria criada com sucesso.');
        $this->redirect('/admin/categorias');
    }

    public function update(array $params = []): void
    {
        $category = Category::find((int) ($params['id'] ?? 0));
        if ($category === null) {
            abort(404, 'Categoria não encontrada.');
        }

        $data = [
            'name' => trim((string) Request::input('name', '')),
            'description' => trim((string) Request::input('description', '')),
            'sort_order' => Request::integer('sort_order', 0),
            'is_active' => Request::boolean('is_active') ? 1 : 0,
        ];

        $validator = Validator::make($data, [
            'name' => 'required|min:2|max:120',
            'description' => 'max:500',
        ]);

        if ($validator->fails()) {
            $this->error($validator->firstError() ?? 'Verifique os dados.');
            $this->redirect('/admin/categorias');
        }

        Category::updateById((int) $category['id'], [
            'name' => $data['name'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
        ]);

        AdminLog::record('category.updated', 'category', (int) $category['id'], ['name' => $data['name']]);
        $this->success('Categoria atualizada.');
        $this->redirect('/admin/categorias');
    }

    public function toggle(array $params = []): void
    {
        $category = Category::find((int) ($params['id'] ?? 0));
        if ($category === null) {
            abort(404, 'Categoria não encontrada.');
        }

        $newState = (int) $category['is_active'] === 1 ? 0 : 1;
        Category::updateById((int) $category['id'], ['is_active' => $newState]);
        AdminLog::record('category.toggled', 'category', (int) $category['id'], ['active' => $newState]);

        $this->success($newState === 1 ? 'Categoria ativada.' : 'Categoria desativada.');
        $this->redirect('/admin/categorias');
    }

    public function destroy(array $params = []): void
    {
        $category = Category::find((int) ($params['id'] ?? 0));
        if ($category === null) {
            abort(404, 'Categoria não encontrada.');
        }

        $count = Category::productCount((int) $category['id']);
        if ($count > 0) {
            $this->error('Não é possível excluir: existem ' . $count . ' produto(s) nesta categoria. Mova-os ou desative a categoria.');
            $this->redirect('/admin/categorias');
        }

        Category::deleteById((int) $category['id']);
        AdminLog::record('category.deleted', 'category', (int) $category['id'], ['name' => $category['name']]);

        $this->success('Categoria excluída.');
        $this->redirect('/admin/categorias');
    }
}
