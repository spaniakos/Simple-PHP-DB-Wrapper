<?php

declare(strict_types=1);

namespace Spaniakos\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Spaniakos\SimplePhpDbWrapper;

final class MySqlIntegrationTest extends TestCase
{
    private ?SimplePhpDbWrapper $db = null;

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
}
