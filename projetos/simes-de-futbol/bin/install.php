<?php

declare(strict_types=1);

/**
 * Instalador da loja.
 *
 * Uso:
 *   php bin/install.php                 (interativo)
 *   php bin/install.php --fresh --seed  (recria banco e dados de demonstração)
 *   php bin/install.php --admin-email=admin@loja.com --admin-password="SenhaForte123"
 */

$root = dirname(__DIR__);

// Garante o .env antes de carregar a configuração.
if (!is_file($root . '/.env')) {
    if (!is_file($root . '/.env.example')) {
        fwrite(STDERR, "Arquivo .env.example não encontrado.\n");
        exit(1);
    }
    copy($root . '/.env.example', $root . '/.env');
    echo "Arquivo .env criado a partir de .env.example.\n";
}

$envContent = (string) file_get_contents($root . '/.env');
if (preg_match('/^APP_KEY=\s*$/m', $envContent)) {
    $key = bin2hex(random_bytes(32));
    $envContent = (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $envContent);
    file_put_contents($root . '/.env', $envContent);
    echo "APP_KEY gerada automaticamente.\n";
}

require $root . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Logger;
use App\Core\Str;
use App\Models\User;
use App\Support\DemoSeeder;

/** @return array<string,mixed> */
function parse_options(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $pair = explode('=', substr($arg, 2), 2);
        $options[$pair[0]] = $pair[1] ?? true;
    }

    return $options;
}

function is_interactive(): bool
{
    return function_exists('stream_isatty') && @stream_isatty(STDIN);
}

function ask(string $question, string $default = ''): string
{
    echo $question . ($default !== '' ? " [{$default}]" : '') . ': ';
    $answer = trim((string) fgets(STDIN));

    return $answer === '' ? $default : $answer;
}

$options = parse_options($argv);
$interactive = is_interactive();
$fresh = isset($options['fresh']);
$seed = isset($options['seed']);
$skipAdmin = isset($options['no-admin']);

echo "==============================================\n";
echo " Instalação - " . (string) config('app.name', 'Loja') . "\n";
echo "==============================================\n\n";

$requiredExtensions = ['pdo_mysql', 'mbstring', 'fileinfo', 'openssl', 'curl'];
$missing = array_values(array_filter($requiredExtensions, static fn (string $ext): bool => !extension_loaded($ext)));
if ($missing !== []) {
    fwrite(STDERR, "Extensões PHP ausentes: " . implode(', ', $missing) . "\n");
    exit(1);
}

$db = (array) config('database', []);
$databaseName = (string) ($db['database'] ?? '');

if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
    fwrite(STDERR, "Nome de banco inválido no .env: {$databaseName}\n");
    exit(1);
}

try {
    $raw = Database::rawConnection(
        (string) $db['host'],
        (int) $db['port'],
        (string) $db['username'],
        (string) $db['password']
    );
} catch (Throwable $e) {
    fwrite(STDERR, "Não foi possível conectar ao MySQL: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Verifique DB_HOST, DB_PORT, DB_USERNAME e DB_PASSWORD no .env.\n");
    exit(1);
}

$raw->exec(
    "CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);
echo "Banco '{$databaseName}' disponível.\n";

$existingTables = (int) $raw->query(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $raw->quote($databaseName)
)->fetchColumn();

$raw = null;

if ($existingTables > 0 && !$fresh) {
    if ($interactive) {
        $answer = strtolower(ask('Já existem tabelas neste banco. Recriar tudo (APAGA os dados)? (s/N)', 'n'));
        $fresh = $answer === 's' || $answer === 'sim';
    }

    if (!$fresh) {
        echo "Instalação cancelada. Use --fresh para recriar o banco.\n";
        exit(1);
    }
}

$schemaFile = $root . '/database/schema.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "schema.sql não encontrado.\n");
    exit(1);
}

echo "Criando estrutura do banco...\n";
$sql = (string) file_get_contents($schemaFile);
$sql = (string) preg_replace('/^--.*$/m', '', $sql);
$statements = array_filter(array_map('trim', explode(';', $sql)));

try {
    $pdo = Database::pdo();
    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Erro ao criar tabelas: " . $e->getMessage() . "\n");
    exit(1);
}
echo "Tabelas criadas com sucesso.\n\n";

// ---------------------------------------------------------------------
// Primeiro administrador
// ---------------------------------------------------------------------
$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

if ($skipAdmin) {
    echo "Criação do administrador ignorada (--no-admin).\n";
} elseif ($adminCount > 0) {
    echo "Já existe um administrador cadastrado; nenhuma conta foi criada.\n";
} else {
    $email = isset($options['admin-email']) && is_string($options['admin-email'])
        ? trim($options['admin-email'])
        : ($interactive ? ask('E-mail do administrador') : '');

    if ($email === '') {
        echo "Administrador não criado (informe --admin-email=... ou rode de forma interativa).\n";
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        fwrite(STDERR, "E-mail de administrador inválido.\n");
        exit(1);
    } else {
        $name = isset($options['admin-name']) && is_string($options['admin-name'])
            ? trim($options['admin-name'])
            : ($interactive ? ask('Nome do administrador', 'Administrador') : 'Administrador');

        $password = isset($options['admin-password']) && is_string($options['admin-password'])
            ? (string) $options['admin-password']
            : '';

        $generated = false;
        if ($password === '') {
            if ($interactive) {
                echo "Defina a senha (mínimo 10 caracteres) ou pressione Enter para gerar automaticamente.\n";
                $password = ask('Senha do administrador');
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

        User::create($name !== '' ? $name : 'Administrador', $email, $password, 'admin');
        Logger::security('Administrador inicial criado', ['email' => $email]);

        echo "Administrador criado: {$email}\n";
        if ($generated) {
            echo "SENHA GERADA (guarde agora, ela não será exibida novamente): {$password}\n";
        }
        echo "Altere a senha após o primeiro login.\n";
    }
}

echo "\n";

// ---------------------------------------------------------------------
// Dados de demonstração
// ---------------------------------------------------------------------
if (!$seed && $interactive) {
    $answer = strtolower(ask('Deseja inserir dados de demonstração? (S/n)', 's'));
    $seed = $answer !== 'n' && $answer !== 'nao' && $answer !== 'não';
}

if ($seed) {
    echo "Inserindo dados de demonstração...\n";
    try {
        DemoSeeder::run();
        echo "Dados de demonstração inseridos.\n";
        echo "  Cliente demo: cliente@demo.local / Demo@12345 (apenas ambiente local)\n";
        echo "  Cupons: BEMVINDO10, FRETE10, SIMES15\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Falha ao inserir dados de demonstração: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "\nInstalação concluída.\n";
echo "Inicie o servidor local com:\n";
echo "  php -S 127.0.0.1:8080 -t public public/router.php\n";
echo "Acesse: " . (string) config('app.url', 'http://localhost:8080') . "\n";
