<?php

declare(strict_types=1);

namespace EzPhp\Database;

use EzPhp\Contracts\DatabaseInterface;
use PDO;
use Throwable;

/**
 * Class Database
 *
 * @package EzPhp\Database
 */
final class Database implements DatabaseInterface
{
    private PDO $pdo;

    /**
     * Database Constructor
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     */
    public function __construct(string $dsn, string $username, string $password)
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        // Ensure utf8mb4 charset for MySQL connections to prevent encoding
        // mismatches and multi-byte character injection vectors.
        if (str_starts_with($dsn, 'mysql:')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4';
        }

        $this->pdo = new PDO($dsn, $username, $password, $options);
    }

    /**
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param string                   $sql
     * @param array<int|string, mixed> $bindings
     *
     * @return list<array<string, mixed>>
     */
    public function query(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($sql);

        foreach (array_values($bindings) as $i => $value) {
            if ($value === null) {
                $stmt->bindValue($i + 1, $value, PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $stmt->bindValue($i + 1, $value, PDO::PARAM_BOOL);
            } elseif (is_int($value)) {
                $stmt->bindValue($i + 1, $value, PDO::PARAM_INT);
            } else {
                /** @var string|float $value */
                $stmt->bindValue($i + 1, (string) $value, PDO::PARAM_STR);
            }
        }

        $stmt->execute();

        /** @var list<array<string, mixed>> $result */
        $result = $stmt->fetchAll();

        return $result;
    }

    /**
     * @param string                   $sql
     * @param array<int|string, mixed> $bindings
     *
     * @return int
     */
    public function execute(string $sql, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($sql);

        foreach (array_values($bindings) as $i => $value) {
            if ($value === null) {
                $stmt->bindValue($i + 1, $value, PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $stmt->bindValue($i + 1, $value, PDO::PARAM_BOOL);
            } elseif (is_int($value)) {
                $stmt->bindValue($i + 1, $value, PDO::PARAM_INT);
            } else {
                /** @var string|float $value */
                $stmt->bindValue($i + 1, (string) $value, PDO::PARAM_STR);
            }
        }

        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     * @throws Throwable
     */
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $fn();
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
