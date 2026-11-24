<?php

declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    public static function table(): string
    {
        return static::$table;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findBy(string $column, mixed $value): ?array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException('Coluna inválida.');
        }

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM `' . static::$table . '` WHERE `' . $column . '` = ? LIMIT 1'
        );
        $stmt->execute([$value]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $data */
    public static function insert(array $data): int
    {
        $columns = array_keys($data);
        foreach ($columns as $column) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $column)) {
                throw new \InvalidArgumentException('Coluna inválida.');
            }
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO `' . static::$table . '` (`' . implode('`, `', $columns) . '`) VALUES (' . $placeholders . ')';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function updateById(int $id, array $data): bool
    {
        if ($data === []) {
            return true;
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $column)) {
                throw new \InvalidArgumentException('Coluna inválida.');
            }
            $assignments[] = '`' . $column . '` = ?';
        }

        $sql = 'UPDATE `' . static::$table . '` SET ' . implode(', ', $assignments)
            . ' WHERE `' . static::$primaryKey . '` = ?';
        $params = array_values($data);
        $params[] = $id;
        $stmt = Database::pdo()->prepare($sql);

        return $stmt->execute($params);
    }

    public static function deleteById(int $id): bool
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = ?'
        );

        return $stmt->execute([$id]);
    }

    /** @param array<int|string,mixed> $params */
    public static function count(string $where = '1', array $params = []): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM `' . static::$table . '` WHERE ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<int|string,mixed> $params
     * @return Paginator
     */
    public static function paginate(
        string $where = '1',
        array $params = [],
        string $orderBy = 'id ASC',
        int $page = 1,
        int $perPage = 12
    ): Paginator {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $countStmt = Database::pdo()->prepare('SELECT COUNT(*) FROM `' . static::$table . '` WHERE ' . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT * FROM `' . static::$table . '` WHERE ' . $where . ' ORDER BY ' . $orderBy
            . ' LIMIT ? OFFSET ?';
        $stmt = Database::pdo()->prepare($sql);

        $index = 1;
        foreach ($params as $param) {
            $stmt->bindValue($index++, $param);
        }
        $stmt->bindValue($index++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($index, $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return new Paginator($stmt->fetchAll(), $total, $perPage, $page);
    }

    /**
     * @param array<int|string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    protected static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @param array<int|string,mixed> $params
     * @return array<string,mixed>|null
     */
    protected static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<int|string,mixed> $params */
    protected static function execute(string $sql, array $params = []): bool
    {
        $stmt = Database::pdo()->prepare($sql);

        return $stmt->execute($params);
    }
}
