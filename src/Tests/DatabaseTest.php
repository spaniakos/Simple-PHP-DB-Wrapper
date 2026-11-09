<?php declare(strict_types=1);

namespace Spaniakos\Tests;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Spaniakos\SimplePhpDbWrapper;

final class DatabaseTest extends TestCase
{
    private SimplePhpDbWrapper $db;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL, email TEXT NOT NULL, active INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO users (username, email, active) VALUES ('alice', 'alice@example.test', 1), ('bob', 'bob@example.test', 0)");
        $this->db = new SimplePhpDbWrapper($pdo);
    }

    public function testPreparedFetchAndInjectionSafeValues(): void
    {
        $rows = $this->db->fetchAll('SELECT * FROM users WHERE username = :username', [
            ':username' => "alice' OR 1=1 --",
        ]);

        self::assertSame([], $rows);
        self::assertSame('alice', $this->db->fetchOne('SELECT username FROM users WHERE id = :id', [':id' => 1])['username']);
    }

    public function testSelectGetColumnAndOrdering(): void
    {
        self::assertSame(
            ['bob', 'alice'],
            array_column($this->db->select('users', ['username'], [], ['order' => ['username' => 'DESC']]), 'username')
        );
        self::assertSame('alice', $this->db->get('users', ['username'], ['id' => 1])['username']);
        self::assertSame(['alice', 'bob'], $this->db->getColumn('users', 'username', [], ['order' => ['id' => 'ASC']]));
    }

    public function testConditionsSupportOperatorsGroupsAndNull(): void
    {
        $this->db->execute('ALTER TABLE users ADD COLUMN nickname TEXT NULL');
        $this->db->execute('UPDATE users SET nickname = :nickname WHERE id = :id', [':nickname' => 'ally', ':id' => 1]);

        $rows = $this->db->select('users', ['id'], [
            'OR' => [
                ['username' => 'alice'],
                ['active[IN]' => [9, 0]],
            ],
        ], ['order' => ['id' => 'ASC']]);

        self::assertSame([1, 2], array_column($rows, 'id'));
        self::assertSame(1, $this->db->count('users', ['nickname' => null]));
    }

    public function testInsertUpdateDeleteAndCounts(): void
    {
        $id = $this->db->insert('users', [
            'username' => 'charlie',
            'email' => 'charlie@example.test',
            'active' => 1,
        ]);

        self::assertSame(3, $this->db->count('users'));
        self::assertTrue($this->db->exists('users', ['id' => (int) $id]));

        self::assertSame(1, $this->db->update('users', ['active' => 0], ['id' => (int) $id]));
        self::assertSame(1, $this->db->delete('users', ['id' => (int) $id]));
        self::assertFalse($this->db->exists('users', ['id' => (int) $id]));
    }

    public function testWholeTableMutationsMustBeExplicit(): void
    {
        try {
            $this->db->update('users', ['active' => 0], []);
            self::fail('Expected update without conditions to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('requires conditions', $exception->getMessage());
        }

        try {
            $this->db->delete('users', []);
            self::fail('Expected delete without conditions to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('requires conditions', $exception->getMessage());
        }

        self::assertSame(2, $this->db->updateAll('users', ['active' => 1]));
        self::assertSame(2, $this->db->deleteAll('users'));
        self::assertSame(0, $this->db->count('users'));
    }

    public function testInvalidIdentifiersAndOrderDirectionsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->db->select('users; DROP TABLE users');
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->db->transaction(function (SimplePhpDbWrapper $db): void {
                $db->insert('users', [
                    'username' => 'transactional',
                    'email' => 'transactional@example.test',
                    'active' => 1,
                ]);
                throw new \RuntimeException('rollback');
            });
            self::fail('Expected the callback exception to bubble up.');
        } catch (\RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }

        self::assertSame(2, $this->db->count('users'));
    }
}
