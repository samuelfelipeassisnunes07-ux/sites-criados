<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;
    private static int $transactionLevel = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = (array) Config::get('database', []);
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'] ?? '127.0.0.1',
            (int) ($cfg['port'] ?? 3306),
            $cfg['database'] ?? '',
            $cfg['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO($dsn, (string) ($cfg['username'] ?? ''), (string) ($cfg['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $e) {
            Logger::error('Falha ao conectar ao banco de dados', ['message' => $e->getMessage()]);
            throw new RuntimeException(
                'Não foi possível conectar ao banco de dados. Verifique o arquivo .env e se o MySQL está iniciado.',
                0,
                $e
            );
        }

        return self::$pdo;
    }

    /** Usado pelo instalador/testes para abrir conexão sem database selecionado. */
    public static function rawConnection(string $host, int $port, string $username, string $password): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public static function begin(): void
    {
        if (self::$transactionLevel === 0) {
            self::pdo()->beginTransaction();
        } else {
            self::pdo()->exec('SAVEPOINT trans' . self::$transactionLevel);
        }
        self::$transactionLevel++;
    }

    public static function commit(): void
    {
        if (self::$transactionLevel <= 0) {
            throw new RuntimeException('Nenhuma transação ativa para commit.');
        }

        self::$transactionLevel--;
        if (self::$transactionLevel === 0) {
            self::pdo()->commit();
        } else {
            self::pdo()->exec('RELEASE SAVEPOINT trans' . self::$transactionLevel);
        }
    }

    public static function rollBack(): void
    {
        if (self::$transactionLevel <= 0) {
            return;
        }

        self::$transactionLevel--;
        if (self::$transactionLevel === 0) {
            if (self::pdo()->inTransaction()) {
                self::pdo()->rollBack();
            }
        } else {
            self::pdo()->exec('ROLLBACK TO SAVEPOINT trans' . self::$transactionLevel);
        }
    }

    /**
     * Executa $callback dentro de uma transação, revertendo em caso de exceção.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        self::begin();

        try {
            $result = $callback();
            self::commit();

            return $result;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function inTransaction(): bool
    {
        return self::$transactionLevel > 0;
    }
}
