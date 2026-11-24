<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\PasswordResetToken;
use App\Services\AuthService;
use App\Services\CartService;

final class AuthController extends Controller
{
    public function showLogin(array $params = []): void
    {
        $this->render('auth/login', [
            'title' => 'Entrar',
            'old' => ['email' => ''],
            'errors' => [],
            'error' => null,
        ]);
    }

    public function login(array $params = []): void
    {
        $email = trim((string) Request::input('email', ''));
        $password = (string) Request::input('password', '');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email|max:180', 'password' => 'required|max:200']
        );

        if ($validator->fails()) {
            $this->render('auth/login', [
                'title' => 'Entrar',
                'errors' => $validator->errors(),
                'old' => ['email' => $email],
                'error' => $validator->firstError(),
            ]);
            return;
        }

        $result = AuthService::attempt($email, $password, Request::ip());
        if (!$result['ok']) {
            $this->render('auth/login', [
                'title' => 'Entrar',
                'errors' => [],
                'old' => ['email' => $email],
                'error' => $result['message'],
            ]);
            return;
        }

        $user = $result['user'] ?? null;
        if ($user !== null) {
            CartService::mergeForUser((int) $user['id']);
            $this->success('Bem-vindo(a), ' . $user['name'] . '!');
        }

        $intended = Session::pull('intended_url');
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            if (str_starts_with($intended, '/admin') && !Auth::isAdmin()) {
                $this->redirect('/minha-conta');
            }

            Response::redirect(url(ltrim($intended, '/')));
        }

        if (Auth::isAdmin()) {
            $this->redirect('/admin');
        }

        $this->redirect('/minha-conta');
    }

    public function showRegister(array $params = []): void
    {
        $this->render('auth/register', [
            'title' => 'Criar conta',
            'errors' => [],
            'old' => ['name' => '', 'email' => ''],
            'error' => null,
        ]);
    }

    public function register(array $params = []): void
    {
        $data = [
            'name' => trim((string) Request::input('name', '')),
            'email' => trim((string) Request::input('email', '')),
            'password' => (string) Request::input('password', ''),
            'password_confirmation' => (string) Request::input('password_confirmation', ''),
        ];

        $validator = Validator::make($data, [
            'name' => 'required|min:3|max:120',
            'email' => 'required|email|max:180',
            'password' => 'required|min:8|max:72|confirmed',
        ]);

        if ($validator->fails()) {
            $this->render('auth/register', [
                'title' => 'Criar conta',
                'errors' => $validator->errors(),
                'old' => ['name' => $data['name'], 'email' => $data['email']],
                'error' => $validator->firstError(),
            ]);
            return;
        }

        if (!preg_match('/[A-Za-z]/', $data['password']) || !preg_match('/\d/', $data['password'])) {
            $this->render('auth/register', [
                'title' => 'Criar conta',
                'errors' => ['password' => 'Use ao menos uma letra e um número na senha.'],
                'old' => ['name' => $data['name'], 'email' => $data['email']],
                'error' => 'Use ao menos uma letra e um número na senha.',
            ]);
            return;
        }

        $result = AuthService::register($data['name'], $data['email'], $data['password'], Request::ip());
        if (!$result['ok']) {
            $this->render('auth/register', [
                'title' => 'Criar conta',
                'errors' => ['email' => $result['message']],
                'old' => ['name' => $data['name'], 'email' => $data['email']],
                'error' => $result['message'],
            ]);
            return;
        }

        $user = $result['user'] ?? null;
        if ($user !== null) {
            CartService::mergeForUser((int) $user['id']);
        }

        $this->success('Conta criada com sucesso. Que bom ter você com a gente!');
        $this->redirect('/minha-conta');
    }

    public function logout(array $params = []): void
    {
        Auth::logout();
        Response::redirect(url('/'));
    }

    public function showForgot(array $params = []): void
    {
        $this->render('auth/forgot-password', [
            'title' => 'Esqueci minha senha',
            'errors' => [],
            'old' => ['email' => ''],
            'status' => null,
        ]);
    }

    public function sendReset(array $params = []): void
    {
        $email = trim((string) Request::input('email', ''));

        $validator = Validator::make(['email' => $email], ['email' => 'required|email|max:180']);
        if ($validator->fails()) {
            $this->render('auth/forgot-password', [
                'title' => 'Esqueci minha senha',
                'errors' => $validator->errors(),
                'old' => ['email' => $email],
                'status' => null,
            ]);
            return;
        }

        $result = AuthService::requestPasswordReset($email, Request::ip());

        $this->render('auth/forgot-password', [
            'title' => 'Esqueci minha senha',
            'errors' => [],
            'old' => ['email' => $email],
            'status' => $result['message'],
            'statusIsError' => !$result['ok'],
        ]);
    }

    public function showReset(array $params = []): void
    {
        $selector = (string) Request::input('selector', '');
        $token = (string) Request::input('token', '');
        $valid = PasswordResetToken::findValid($selector, $token) !== null;

        $this->render('auth/reset-password', [
            'title' => 'Redefinir senha',
            'selector' => $selector,
            'token' => $token,
            'valid' => $valid,
            'errors' => [],
        ]);
    }

    public function reset(array $params = []): void
    {
        $selector = (string) Request::input('selector', '');
        $token = (string) Request::input('token', '');
        $data = [
            'password' => (string) Request::input('password', ''),
            'password_confirmation' => (string) Request::input('password_confirmation', ''),
        ];

        $validator = Validator::make($data, [
            'password' => 'required|min:8|max:72|confirmed',
        ]);

        if ($validator->fails()) {
            $this->render('auth/reset-password', [
                'title' => 'Redefinir senha',
                'selector' => $selector,
                'token' => $token,
                'valid' => true,
                'errors' => $validator->errors(),
            ]);
            return;
        }

        if (!preg_match('/[A-Za-z]/', $data['password']) || !preg_match('/\d/', $data['password'])) {
            $this->render('auth/reset-password', [
                'title' => 'Redefinir senha',
                'selector' => $selector,
                'token' => $token,
                'valid' => true,
                'errors' => ['password' => 'Use ao menos uma letra e um número na senha.'],
            ]);
            return;
        }

        $result = AuthService::resetPassword($selector, $token, $data['password']);
        if (!$result['ok']) {
            $this->render('auth/reset-password', [
                'title' => 'Redefinir senha',
                'selector' => $selector,
                'token' => $token,
                'valid' => false,
                'errors' => ['password' => $result['message']],
            ]);
            return;
        }

        $this->success($result['message']);
        $this->redirect('/login');
    }
}
