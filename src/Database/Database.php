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
     * Number of savepoints currently open through transaction(); names them uniquely.
     */
    private int $savepointDepth = 0;

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
     * Run $fn in a transaction. Nesting-aware: when a transaction is already open
     * on the connection — through an outer transaction() call or directly on the
     * PDO (e.g. a test base class) — the call runs inside a SAVEPOINT instead, so
     * a failure rolls back only the inner work and the outer owner decides the
     * final commit.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     * @throws Throwable
     */
    public function transaction(callable $fn): mixed
    {
        if ($this->inTransaction()) {
            return $this->savepoint($fn);
        }

        $this->pdo->beginTransaction();

        try {
            $result = $fn();
            if ($this->inTransaction()) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($this->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Whether the connection is inside a transaction right now. Re-checked after
     * $fn runs, because a MySQL DDL statement commits implicitly.
     *
     * @phpstan-impure
     *
     * @return bool
     */
    private function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Run $fn inside a savepoint of the already open transaction.
     *
     * The inTransaction() guards mirror transaction(): a MySQL DDL statement in
     * $fn implicitly commits and ends the transaction, which also drops the
     * savepoint.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     * @throws Throwable
     */
    private function savepoint(callable $fn): mixed
    {
        $name = 'ez_savepoint_' . (++$this->savepointDepth);

        try {
            $this->pdo->exec('SAVEPOINT ' . $name);

            try {
                $result = $fn();
                if ($this->inTransaction()) {
                    $this->pdo->exec('RELEASE SAVEPOINT ' . $name);
                }
                return $result;
            } catch (Throwable $e) {
                if ($this->inTransaction()) {
                    $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $name);
                }
                throw $e;
            }
        } finally {
            $this->savepointDepth--;
        }
    }
}
