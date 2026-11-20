<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));

$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

require BASE_PATH . '/app/Support/helpers.php';

date_default_timezone_set((string) Config::get('app.timezone', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', Config::get('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-' . date('Y-m-d') . '.log');

if (!is_dir(BASE_PATH . '/storage/logs')) {
    @mkdir(BASE_PATH . '/storage/logs', 0775, true);
}

if (PHP_SAPI !== 'cli') {
    \App\Core\Session::start();
}
