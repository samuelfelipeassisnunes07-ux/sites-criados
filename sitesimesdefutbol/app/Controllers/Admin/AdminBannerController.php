<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Validator;
use App\Models\AdminLog;
use App\Models\Banner;
use App\Services\ImageUploadService;

final class AdminBannerController extends Controller
{
    public function index(array $params = []): void
    {
        $this->renderAdmin('admin/banners/index', [
            'title' => 'Banners',
            'banners' => Banner::allOrdered(),
        ]);
    }

    public function store(array $params = []): void
    {
        $data = $this->validated();
        if ($data === null) {
            $this->redirect('/admin/banners');
        }

        $image = $this->storeImage();
        $bannerId = Banner::insert($data + ['image' => $image]);

        AdminLog::record('banner.created', 'banner', $bannerId, ['title' => $data['title']]);
        $this->success('Banner criado.');
        $this->redirect('/admin/banners');
    }

    public function update(array $params = []): void
    {
        $banner = Banner::find((int) ($params['id'] ?? 0));
        if ($banner === null) {
            abort(404, 'Banner não encontrado.');
        }

        $data = $this->validated();
        if ($data === null) {
            $this->redirect('/admin/banners');
        }

        $newImage = $this->storeImage();
        if ($newImage !== null) {
            $oldImage = (string) ($banner['image'] ?? '');
            if (str_starts_with($oldImage, 'banners/')) {
                ImageUploadService::delete($oldImage);
            }
            $data['image'] = $newImage;
        }

        Banner::updateById((int) $banner['id'], $data);
        AdminLog::record('banner.updated', 'banner', (int) $banner['id'], ['title' => $data['title']]);

        $this->success('Banner atualizado.');
        $this->redirect('/admin/banners');
    }

    public function toggle(array $params = []): void
    {
        $banner = Banner::find((int) ($params['id'] ?? 0));
        if ($banner === null) {
            abort(404, 'Banner não encontrado.');
        }

        $newState = (int) $banner['is_active'] === 1 ? 0 : 1;
        Banner::updateById((int) $banner['id'], ['is_active' => $newState]);
        AdminLog::record('banner.toggled', 'banner', (int) $banner['id'], ['active' => $newState]);

        $this->success($newState === 1 ? 'Banner ativado.' : 'Banner desativado.');
        $this->redirect('/admin/banners');
    }

    public function destroy(array $params = []): void
    {
        $banner = Banner::find((int) ($params['id'] ?? 0));
        if ($banner === null) {
            abort(404, 'Banner não encontrado.');
        }

        $image = (string) ($banner['image'] ?? '');
        if (str_starts_with($image, 'banners/')) {
            ImageUploadService::delete($image);
        }

        Banner::deleteById((int) $banner['id']);
        AdminLog::record('banner.deleted', 'banner', (int) $banner['id'], ['title' => $banner['title']]);

        $this->success('Banner excluído.');
        $this->redirect('/admin/banners');
    }

    /** @return array<string,mixed>|null */
    private function validated(): ?array
    {
        $input = [
            'title' => trim((string) Request::input('title', '')),
            'subtitle' => trim((string) Request::input('subtitle', '')),
            'link' => trim((string) Request::input('link', '')),
            'position' => (string) Request::input('position', 'hero'),
            'sort_order' => Request::integer('sort_order', 0),
            'is_active' => Request::boolean('is_active') ? 1 : 0,
        ];

        $validator = Validator::make($input, [
            'title' => 'required|min:3|max:150',
            'subtitle' => 'max:255',
            'link' => 'max:255',
            'position' => 'required|in:hero,secondary',
        ]);

        if ($validator->fails()) {
            $this->error($validator->firstError() ?? 'Verifique os dados do banner.');
            return null;
        }

        $link = $input['link'];
        if ($link !== '' && !str_starts_with($link, '/') && !preg_match('#^https?://#i', $link)) {
            $link = '/' . $link;
        }

        return [
            'title' => $input['title'],
            'subtitle' => $input['subtitle'] !== '' ? $input['subtitle'] : null,
            'link' => $link !== '' ? $link : null,
            'position' => $input['position'],
            'sort_order' => $input['sort_order'],
            'is_active' => $input['is_active'],
        ];
    }

    private function storeImage(): ?string
    {
        $file = Request::file('image');
        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $result = ImageUploadService::store($file, 'banners');
        if (!$result['ok']) {
            $this->error($result['message']);
            return null;
        }

        return (string) $result['filename'];
    }
}
