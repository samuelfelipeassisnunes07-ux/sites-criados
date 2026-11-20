<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Validator;
use App\Models\AdminLog;
use App\Models\Setting;
use App\Services\ImageUploadService;

final class AdminSettingController extends Controller
{
    /** @return array<string,string> */
    private function defaults(): array
    {
        return [
            'store_name' => (string) config('app.name', 'Simês de Fútbol'),
            'store_slogan' => 'Paixão que se veste. Futebol que se vive.',
            'contact_email' => '',
            'contact_phone' => '',
            'whatsapp' => '',
            'address' => '',
            'instagram' => '',
            'about_text' => '',
            'footer_text' => '',
            'logo_image' => '',
        ];
    }

    public function edit(array $params = []): void
    {
        $settings = array_merge($this->defaults(), Setting::all());

        $this->renderAdmin('admin/settings/edit', [
            'title' => 'Configurações da loja',
            'settings' => $settings,
            'errors' => [],
        ]);
    }

    public function update(array $params = []): void
    {
        $input = [
            'store_name' => trim((string) Request::input('store_name', '')),
            'store_slogan' => trim((string) Request::input('store_slogan', '')),
            'contact_email' => trim((string) Request::input('contact_email', '')),
            'contact_phone' => trim((string) Request::input('contact_phone', '')),
            'whatsapp' => trim((string) Request::input('whatsapp', '')),
            'address' => trim((string) Request::input('address', '')),
            'instagram' => trim((string) Request::input('instagram', '')),
            'about_text' => trim((string) Request::input('about_text', '')),
            'footer_text' => trim((string) Request::input('footer_text', '')),
        ];

        $validator = Validator::make($input, [
            'store_name' => 'required|min:2|max:120',
            'store_slogan' => 'max:180',
            'contact_email' => 'email|max:180',
            'contact_phone' => 'max:30',
            'whatsapp' => 'max:30',
            'address' => 'max:255',
            'instagram' => 'max:120',
            'about_text' => 'max:4000',
            'footer_text' => 'max:500',
        ]);

        if ($validator->fails()) {
            $settings = array_merge($this->defaults(), Setting::all(), $input);

            $this->renderAdmin('admin/settings/edit', [
                'title' => 'Configurações da loja',
                'settings' => $settings,
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $logo = Request::file('logo');
        if ($logo !== null && (int) ($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $result = ImageUploadService::store($logo, 'branding');
            if ($result['ok']) {
                $oldLogo = (string) Setting::get('logo_image', '');
                if (str_starts_with($oldLogo, 'branding/')) {
                    ImageUploadService::delete($oldLogo);
                }
                $input['logo_image'] = (string) $result['filename'];
            } else {
                $this->error($result['message']);
            }
        }

        Setting::setMany($input);
        AdminLog::record('settings.updated', 'settings', null, ['store_name' => $input['store_name']]);

        $this->success('Configurações atualizadas.');
        $this->redirect('/admin/configuracoes');
    }
}
