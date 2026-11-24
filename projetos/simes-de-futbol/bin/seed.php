<?php

declare(strict_types=1);

/**
 * Popula (ou repopula) os dados de demonstração.
 * Requer banco instalado. Uso: php bin/seed.php
 */

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

use App\Support\DemoSeeder;

try {
    DemoSeeder::run();
    echo "Dados de demonstração inseridos/atualizados.\n";
    echo "Cliente demo: cliente@demo.local / Demo@12345\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
