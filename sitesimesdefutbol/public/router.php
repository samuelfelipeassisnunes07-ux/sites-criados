<?php

/**
 * Roteador para o servidor embutido do PHP:
 *   php -S 127.0.0.1:8080 -t public public/router.php
 *
 * Arquivos estáticos são servidos diretamente; o restante vai para o front controller.
 */

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
