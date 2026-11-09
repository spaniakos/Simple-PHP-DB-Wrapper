<?php

declare(strict_types=1);

namespace Spaniakos\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Spaniakos\SimplePhpDbWrapper;

final class MySqlIntegrationTest extends TestCase
{
    private ?SimplePhpDbWrapper $db = null;

    #[\Override]
    protected function setUp(): void
    {
        $dsn = getenv('DB_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set DB_DSN to run MySQL integration tests.');
        }

        $pdo = new PDO($dsn, (string) getenv('DB_USER'), (string) getenv('DB_PASS'));
        $pdo->exec(
            'CREATE TEMPORARY TABLE wrapper_users ('
            . 'id INT AUTO_INCREMENT PRIMARY KEY, '
            . 'name VARCHAR(100) NOT NULL, '
            . 'active TINYINT NOT NULL)'
        );
        $pdo->exec(
            'CREATE TEMPORARY TABLE wrapper_posts ('
            . 'id INT AUTO_INCREMENT PRIMARY KEY, '
            . 'user_id INT NOT NULL, '
            . 'title VARCHAR(100) NOT NULL, '
            . 'published TINYINT NOT NULL)'
        );
        $this->db = new SimplePhpDbWrapper($pdo);
    }

    public function testMySqlPreparedCrudAndGeneratedId(): void
    {
        $id = $this->db->insert('wrapper_users', [
            'name' => "O'Reilly",
            'active' => 1,
        ]);

        self::assertSame(1, $this->db->count('wrapper_users'));
        self::assertSame(
            "O'Reilly",
            $this->db->get('wrapper_users', ['name'], ['id' => (int) $id])['name']
        );
        self::assertSame(
            1,
            $this->db->update('wrapper_users', ['active' => 0], ['id' => (int) $id])
        );
    }

    public function testPreparedJoinSubqueryAndUnionQueries(): void
    {
        $aliceId = (int) $this->db->insert('wrapper_users', ['name' => 'alice', 'active' => 1]);
        $bobId = (int) $this->db->insert('wrapper_users', ['name' => 'bob', 'active' => 0]);
        $this->db->insert('wrapper_posts', [
            'user_id' => $aliceId,
            'title' => 'first post',
            'published' => 1,
        ]);
        $this->db->insert('wrapper_posts', [
            'user_id' => $aliceId,
            'title' => 'second post',
            'published' => 1,
        ]);
        $this->db->insert('wrapper_posts', [
            'user_id' => $bobId,
            'title' => 'draft post',
            'published' => 0,
        ]);

        $joined = $this->db->fetchAll(
            'SELECT u.id, p.title FROM wrapper_users u '
            . 'LEFT JOIN wrapper_posts p ON p.user_id = u.id '
            . 'WHERE u.id = :user_id AND u.active = :active '
            . 'ORDER BY p.title',
            [':user_id' => $aliceId, ':active' => 1]
        );
        self::assertSame([$aliceId, $aliceId], array_map('intval', array_column($joined, 'id')));
        self::assertSame(['first post', 'second post'], array_column($joined, 'title'));

        $subquery = $this->db->fetchAll(
            'SELECT id FROM wrapper_users WHERE id IN '
            . '(SELECT user_id FROM wrapper_posts WHERE published = :published)',
            [':published' => 1]
        );
        self::assertSame([$aliceId], array_map('intval', array_column($subquery, 'id')));

        $union = $this->db->fetchAll(
            'SELECT name AS label FROM wrapper_users WHERE active = :active '
            . 'UNION '
            . 'SELECT title AS label FROM wrapper_posts WHERE published = :published '
            . 'ORDER BY label',
            [':active' => 1, ':published' => 1]
        );
        self::assertSame(['alice', 'first post', 'second post'], array_column($union, 'label'));
    }
}
