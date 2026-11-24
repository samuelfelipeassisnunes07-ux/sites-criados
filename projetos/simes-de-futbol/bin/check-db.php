<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

try {
    $pdo = Database::pdo();
    echo "Conexão OK: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo 'Tabelas: ' . count($tables) . PHP_EOL;
    foreach ($tables as $table) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        printf("  - %-28s %d registros\n", $table, $count);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
