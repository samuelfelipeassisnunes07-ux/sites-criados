<?php

declare(strict_types=1);

/**
 * Cria uma conta de administrador (sem senha padrão).
 *
 * Uso interativo:
 *   php bin/create-admin.php
 *
 * Uso automatizado (CI):
 *   php bin/create-admin.php --email=admin@loja.com --name="Admin" --password="SenhaForte123"
 */

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

use App\Core\Logger;
use App\Core\Str;
use App\Models\User;

$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        $pair = explode('=', substr($arg, 2), 2);
        $options[$pair[0]] = $pair[1] ?? true;
    }
}

$interactive = function_exists('stream_isatty') && @stream_isatty(STDIN);

if (!is_file($root . '/.env')) {
    fwrite(STDERR, "Arquivo .env não encontrado. Rode bin/install.php primeiro.\n");
    exit(1);
}

$email = isset($options['email']) && is_string($options['email'])
    ? trim($options['email'])
    : ($interactive ? trim((string) readline('E-mail do administrador: ')) : '');

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Informe um e-mail válido com --email=...\n");
    exit(1);
}

if (User::findByEmail($email) !== null) {
    fwrite(STDERR, "Já existe um usuário com este e-mail.\n");
    exit(1);
}

$name = isset($options['name']) && is_string($options['name'])
    ? trim($options['name'])
    : ($interactive ? trim((string) readline('Nome: ')) : 'Administrador');

$password = isset($options['password']) && is_string($options['password'])
    ? (string) $options['password']
    : '';

$generated = false;
if ($password === '') {
    if ($interactive) {
        $password = (string) readline('Senha (mín. 10 caracteres, visível no terminal): ');
    }
    if ($password === '') {
        $password = Str::randomToken(9) . 'aA1!';
        $generated = true;
    }
}

if (mb_strlen($password) < 10) {
    fwrite(STDERR, "A senha deve ter pelo menos 10 caracteres.\n");
    exit(1);
}

$id = User::create($name !== '' ? $name : 'Administrador', $email, $password, 'admin');
$user = User::find($id);

if ($user === null || $user['role'] !== 'admin') {
    fwrite(STDERR, "Falha ao criar o administrador.\n");
    exit(1);
}

Logger::security('Administrador criado via CLI', ['email' => $email, 'user_id' => $id]);

echo "Administrador criado com sucesso: {$email}\n";
if ($generated) {
    echo "SENHA GERADA (exibida apenas uma vez): {$password}\n";
}
