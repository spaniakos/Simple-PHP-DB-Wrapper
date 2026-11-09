<?php

declare(strict_types=1);

namespace Spaniakos\Tests;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Spaniakos\SelectOptions;
use Spaniakos\SimplePhpDbWrapper;
use Spaniakos\SortDirection;

final class DatabaseTest extends TestCase
{
    private SimplePhpDbWrapper $db;

    #[\Override]
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
            array_column(
                $this->db->select('users', ['username'], [], new SelectOptions(order: ['username' => SortDirection::Desc])),
                'username'
            )
        );
        self::assertSame('alice', $this->db->get('users', ['username'], ['id' => 1])['username']);
        self::assertSame(
            ['alice', 'bob'],
            $this->db->getColumn(
                'users',
                'username',
                [],
                new SelectOptions(order: ['id' => SortDirection::Asc])
            )
        );
    }

    public function testSelectOptionsRequireLimitWhenOffsetIsSet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SelectOptions(offset: 10);
    }

    public function testSelectOptionsApplyLimitAndOffset(): void
    {
        self::assertSame(
            ['bob'],
            array_column(
                $this->db->select(
                    'users',
                    ['username'],
                    [],
                    new SelectOptions(order: ['id' => SortDirection::Asc], limit: 1, offset: 1)
                ),
                'username'
            )
        );
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
        ], new SelectOptions(order: ['id' => SortDirection::Asc]));

        self::assertSame([1, 2], array_map('intval', array_column($rows, 'id')));
        self::assertSame(1, $this->db->count('users', ['nickname' => null]));
    }

    public function testComparisonOperatorsInConditionKeys(): void
    {
        self::assertSame(
            ['bob'],
            array_column($this->db->select('users', ['username'], ['id[>=]' => 2]), 'username')
        );
        self::assertSame(
            ['bob'],
            array_column($this->db->select('users', ['username'], ['id[>]' => 1]), 'username')
        );
        self::assertSame(
            ['alice'],
            array_column($this->db->select('users', ['username'], ['id[<=]' => 1]), 'username')
        );
        self::assertSame(
            ['alice'],
            array_column($this->db->select('users', ['username'], ['id[<]' => 2]), 'username')
        );
        self::assertSame(
            ['bob'],
            array_column($this->db->select('users', ['username'], ['id[!=]' => 1]), 'username')
        );
        self::assertSame(
            ['bob'],
            array_column($this->db->select('users', ['username'], ['id[<>]' => 1]), 'username')
        );
    }

    public function testNestedAndOrConditionsAreGrouped(): void
    {
        $rows = $this->db->select('users', ['username'], [
            'AND' => [
                ['active' => 1],
                [
                    'OR' => [
                        ['username' => 'alice'],
                        ['username' => 'bob'],
                    ],
                ],
            ],
        ]);

        self::assertSame(['alice'], array_column($rows, 'username'));
    }

    public function testPreparedJoinSubqueryAndUnionQueries(): void
    {
        $this->db->execute(
            'CREATE TABLE posts ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'user_id INTEGER NOT NULL, '
            . 'title TEXT NOT NULL, '
            . 'published INTEGER NOT NULL)'
        );
        $this->db->execute(
            'INSERT INTO posts (user_id, title, published) VALUES (:user_id, :title, :published)',
            [':user_id' => 1, ':title' => 'first post', ':published' => 1]
        );
        $this->db->execute(
            'INSERT INTO posts (user_id, title, published) VALUES (:user_id, :title, :published)',
            [':user_id' => 1, ':title' => 'second post', ':published' => 1]
        );
        $this->db->execute(
            'INSERT INTO posts (user_id, title, published) VALUES (:user_id, :title, :published)',
            [':user_id' => 2, ':title' => 'draft post', ':published' => 0]
        );

        $joined = $this->db->fetchAll(
            'SELECT u.id, p.title FROM users u '
            . 'LEFT JOIN posts p ON p.user_id = u.id '
            . 'WHERE u.id = :user_id AND u.active = :active '
            . 'ORDER BY p.title',
            [':user_id' => 1, ':active' => 1]
        );
        self::assertSame([1, 1], array_map('intval', array_column($joined, 'id')));
        self::assertSame(['first post', 'second post'], array_column($joined, 'title'));

        $subquery = $this->db->fetchAll(
            'SELECT id FROM users WHERE id IN '
            . '(SELECT user_id FROM posts WHERE published = :published)',
            [':published' => 1]
        );
        self::assertSame([1], array_map('intval', array_column($subquery, 'id')));

        $union = $this->db->fetchAll(
            'SELECT username AS label FROM users WHERE active = :active '
            . 'UNION '
            . 'SELECT title AS label FROM posts WHERE published = :published '
            . 'ORDER BY label',
            [':active' => 1, ':published' => 1]
        );
        self::assertSame(['alice', 'first post', 'second post'], array_column($union, 'label'));
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

    public function testInvalidIdentifiersAreRejected(): void
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
