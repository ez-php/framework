<?php

declare(strict_types=1);

namespace Tests\Database;

use EzPhp\Database\Database;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Class DatabaseTest
 *
 * @package Tests\Database
 */
#[CoversClass(Database::class)]
final class DatabaseTest extends TestCase
{
    private Database $db;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->db = new Database('sqlite::memory:', '', '');
        $this->db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    }

    /**
     * @return void
     */
    public function test_get_pdo_returns_pdo_instance(): void
    {
        $this->assertInstanceOf(PDO::class, $this->db->getPdo());
    }

    /**
     * @return void
     */
    public function test_query_returns_inserted_rows(): void
    {
        $this->db->query('INSERT INTO users (name) VALUES (?)', ['Alice']);
        $this->db->query('INSERT INTO users (name) VALUES (?)', ['Bob']);

        $rows = $this->db->query('SELECT name FROM users ORDER BY name');

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
    }

    /**
     * @return void
     */
    public function test_query_returns_empty_array_when_no_rows(): void
    {
        $rows = $this->db->query('SELECT * FROM users');
        $this->assertSame([], $rows);
    }

    /**
     * @return void
     */
    public function test_query_supports_named_bindings(): void
    {
        $this->db->query('INSERT INTO users (name) VALUES (:name)', [':name' => 'Carol']);

        $rows = $this->db->query('SELECT name FROM users WHERE name = :name', [':name' => 'Carol']);

        $this->assertCount(1, $rows);
        $this->assertSame('Carol', $rows[0]['name']);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_transaction_commits_on_success(): void
    {
        $this->db->transaction(function (): void {
            $this->db->query('INSERT INTO users (name) VALUES (?)', ['Dave']);
        });

        $rows = $this->db->query('SELECT name FROM users');
        $this->assertCount(1, $rows);
        $this->assertSame('Dave', $rows[0]['name']);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_transaction_rolls_back_on_exception(): void
    {
        try {
            $this->db->transaction(function (): void {
                $this->db->query('INSERT INTO users (name) VALUES (?)', ['Eve']);
                throw new RuntimeException('abort');
            });
        } catch (RuntimeException) {
            // expected
        }

        $rows = $this->db->query('SELECT * FROM users');
        $this->assertSame([], $rows);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_transaction_returns_value_from_callable(): void
    {
        $result = $this->db->transaction(fn (): string => 'ok');
        $this->assertSame('ok', $result);
    }

    /**
     * When a DDL statement causes an implicit commit (MySQL behaviour), the PDO
     * transaction is silently ended. The commit() guard must not throw in that case.
     *
     * Simulated here by manually calling commit() on the PDO inside the callable,
     * which leaves `inTransaction()` returning false before `transaction()` tries
     * its own commit.
     *
     * @return void
     * @throws Throwable
     */
    public function test_transaction_survives_implicit_commit_on_success(): void
    {
        $result = $this->db->transaction(function (): string {
            // Simulate MySQL DDL implicit commit
            $this->db->getPdo()->commit();
            return 'ok';
        });

        $this->assertSame('ok', $result);
    }

    /**
     * When an implicit commit has already ended the transaction and the callable
     * also throws, the rollBack() guard must not throw a second error — only the
     * original exception must propagate.
     *
     * @return void
     */
    public function test_transaction_survives_implicit_commit_on_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('post-DDL failure');

        $this->db->transaction(function (): void {
            // Simulate MySQL DDL implicit commit, then a subsequent failure
            $this->db->getPdo()->commit();
            throw new RuntimeException('post-DDL failure');
        });
    }

    // ── type binding ──────────────────────────────────────────────────────────

    /**
     * null is bound with PDO::PARAM_NULL — stored as NULL, returned as null.
     *
     * @return void
     */
    public function test_query_binds_null(): void
    {
        $this->db->query('CREATE TABLE t (val TEXT)');
        $this->db->query('INSERT INTO t (val) VALUES (?)', [null]);

        $rows = $this->db->query('SELECT val FROM t WHERE val IS NULL');

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['val']);
    }

    /**
     * true is bound with PDO::PARAM_BOOL — SQLite stores 1.
     *
     * @return void
     */
    public function test_query_binds_bool_true(): void
    {
        $this->db->query('CREATE TABLE t (val INTEGER)');
        $this->db->query('INSERT INTO t (val) VALUES (?)', [true]);

        $rows = $this->db->query('SELECT val FROM t WHERE val = 1');

        $this->assertCount(1, $rows);
    }

    /**
     * false is bound with PDO::PARAM_BOOL — SQLite stores 0.
     *
     * @return void
     */
    public function test_query_binds_bool_false(): void
    {
        $this->db->query('CREATE TABLE t (val INTEGER)');
        $this->db->query('INSERT INTO t (val) VALUES (?)', [false]);

        $rows = $this->db->query('SELECT val FROM t WHERE val = 0');

        $this->assertCount(1, $rows);
    }

    /**
     * Negative integers are bound with PDO::PARAM_INT and round-trip correctly.
     *
     * @return void
     */
    public function test_query_binds_negative_int(): void
    {
        $this->db->query('CREATE TABLE t (val INTEGER)');
        $this->db->query('INSERT INTO t (val) VALUES (?)', [-42]);

        $rows = $this->db->query('SELECT val FROM t WHERE val = ?', [-42]);

        $this->assertCount(1, $rows);
        $this->assertSame(-42, $rows[0]['val']);
    }

    /**
     * Floats are cast to string and bound with PDO::PARAM_STR.
     *
     * @return void
     */
    public function test_query_binds_float_as_string(): void
    {
        $this->db->query('CREATE TABLE t (val TEXT)');
        $this->db->query('INSERT INTO t (val) VALUES (?)', [1.5]);

        $rows = $this->db->query('SELECT val FROM t');

        $this->assertSame('1.5', $rows[0]['val']);
    }
}
