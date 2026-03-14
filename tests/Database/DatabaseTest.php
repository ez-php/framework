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

}
