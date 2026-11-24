<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Validator;
use App\Services\MailService;

final class PageController extends Controller
{
    public function about(array $params = []): void
    {
        $this->render('store/about', [
            'title' => 'Sobre a loja',
        ]);
    }

    public function contact(array $params = []): void
    {
        $this->render('store/contact', [
            'title' => 'Contato',
            'errors' => [],
            'old' => ['name' => '', 'email' => '', 'message' => ''],
            'sent' => false,
        ]);
    }

    public function sendContact(array $params = []): void
    {
        $data = [
            'name' => trim((string) Request::input('name', '')),
            'email' => trim((string) Request::input('email', '')),
            'message' => trim((string) Request::input('message', '')),
        ];

        $validator = Validator::make($data, [
            'name' => 'required|min:3|max:120',
            'email' => 'required|email|max:180',
            'message' => 'required|min:10|max:2000',
        ]);

        if ($validator->fails()) {
            $this->render('store/contact', [
                'title' => 'Contato',
                'errors' => $validator->errors(),
                'old' => $data,
                'sent' => false,
            ]);
            return;
        }

        $to = (string) setting('contact_email', (string) config('app.mail_from', ''));
        $html = '<p><strong>Nome:</strong> ' . e($data['name']) . '</p>'
            . '<p><strong>E-mail:</strong> ' . e($data['email']) . '</p>'
            . '<p><strong>Mensagem:</strong></p><p>' . nl2br(e($data['message'])) . '</p>';

        try {
            MailService::send($to, 'Contato pelo site - ' . $data['name'], $html, $data['message']);
        } catch (\Throwable) {
            // Não expõe falha de e-mail ao visitante.
        }

        $this->render('store/contact', [
            'title' => 'Contato',
            'errors' => [],
            'old' => ['name' => '', 'email' => '', 'message' => ''],
            'sent' => true,
        ]);
    }

    public function faq(array $params = []): void
    {
        $this->render('store/faq', ['title' => 'Perguntas frequentes']);
    }
}
