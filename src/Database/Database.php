<?php

declare(strict_types=1);

namespace EzPhp\Database;

use EzPhp\Contracts\DatabaseInterface;
use PDO;
use Pdo\Mysql;
use PDOStatement;
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
            $options[Mysql::ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4';
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
        $this->bindValues($stmt, $bindings);
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
        $this->bindValues($stmt, $bindings);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Bind positional or named values to a prepared statement, detecting the PDO
     * param type per value.
     *
     * A bindings array with only sequential integer keys (a list) is bound
     * positionally, in order, to `?` placeholders. A bindings array with any
     * string key is bound by name to `:name` placeholders — the leading `:` is
     * optional in the array key. Mixing the two styles in one call is not
     * supported, matching PDO's own restriction on a single prepared statement.
     *
     * @param PDOStatement            $stmt
     * @param array<int|string, mixed> $bindings
     *
     * @return void
     */
    private function bindValues(PDOStatement $stmt, array $bindings): void
    {
        $named = !array_is_list($bindings);

        foreach ($bindings as $key => $value) {
            $param = $named ? (is_string($key) && str_starts_with($key, ':') ? $key : ':' . $key) : (int) $key + 1;

            if ($value === null) {
                $stmt->bindValue($param, $value, PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $stmt->bindValue($param, $value, PDO::PARAM_BOOL);
            } elseif (is_int($value)) {
                $stmt->bindValue($param, $value, PDO::PARAM_INT);
            } else {
                /** @var string|float $value */
                $stmt->bindValue($param, (string) $value, PDO::PARAM_STR);
            }
        }
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
