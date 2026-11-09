# Simple PHP DB Wrapper

A small MySQL/PDO wrapper for small PHP projects. It provides prepared query execution and a compact CRUD API without an ORM or a large SQL builder.

## Requirements

- PHP 7.4 or newer
- PDO and the PDO MySQL extension

Install with Composer:

```sh
composer require spaniakos/simple-php-db-wrapper
```

The application owns connection configuration. Load environment variables or secrets in the application, then inject a PDO connection:

```php
<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use PDO;
use Spaniakos\SimplePhpDbWrapper;

$pdo = new PDO(
    'mysql:host=localhost;dbname=app;charset=utf8mb4',
    getenv('DB_USER'),
    getenv('DB_PASS'),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$db = new SimplePhpDbWrapper($pdo);
```

Monolog is used for error logging by default. You may inject a PSR-3 logger as the second constructor argument.

## Queries

Values are passed separately and bound by PDO:

```php
$user = $db->fetchOne(
    'SELECT id, email FROM users WHERE email = :email',
    [':email' => $email]
);
```

Common CRUD operations use arrays:

```php
$users = $db->select(
    'users',
    ['id', 'email'],
    ['active' => 1, 'age[>=]' => 18],
    ['order' => ['email' => 'ASC'], 'limit' => 20, 'offset' => 0]
);

$user = $db->get('users', ['id', 'email'], ['id' => $userId]);

$id = $db->insert('users', [
    'email' => $email,
    'active' => 1,
]);

$changed = $db->update('users', ['active' => 0], ['id' => $userId]);
$removed = $db->delete('users', ['id' => $userId]);

$total = $db->count('users', ['active' => 1]);
$hasUser = $db->exists('users', ['email' => $email]);
```

### AND and OR conditions

Condition keys support `=` by default, or an explicit operator in brackets: `age[>=]`, `name[LIKE]`, `id[IN]`, and `id[NOT IN]`. A null value compiles to `IS NULL`. Conditions at the same level are combined with `AND`; nested `AND` and `OR` groups let you control precedence:

```php
$rows = $db->select('users', ['id', 'email'], [
    'AND' => [
        ['active' => 1],
        [
            'OR' => [
                ['role' => 'admin'],
                ['AND' => [
                    ['status' => 'pending'],
                    ['age[>=]' => 18],
                ]],
            ],
        ],
    ],
]);
```

### Joins

`select()` handles a single table. For joins, write the SQL and pass values separately. Compare columns directly in the `ON` clause; bind values in filters:

```php
$rows = $db->fetchAll(
    'SELECT u.id, p.title
     FROM users u
     LEFT JOIN posts p ON p.user_id = u.id
     WHERE u.id = :user_id AND u.active = :active',
    [':user_id' => 2, ':active' => 1]
);
```

Here, `p.user_id = u.id` relates each post to its owning user. The placeholders filter the selected user and active status.

### Subqueries

Subqueries use the same prepared query methods and parameter binding:

```php
$users = $db->fetchAll(
    'SELECT id, email
     FROM users
     WHERE id IN (
         SELECT user_id FROM posts WHERE published = :published
     )',
    [':published' => 1]
);
```

### UNION

Use `UNION` or `UNION ALL` directly when combining compatible result sets. Each placeholder has a distinct name:

```php
$items = $db->fetchAll(
    'SELECT username AS label FROM users WHERE active = :active
     UNION
     SELECT title AS label FROM posts WHERE published = :published
     ORDER BY label',
    [':active' => 1, ':published' => 1]
);
```

These are prepared raw SQL examples: the wrapper binds values, while you write the SQL structure. It does not currently build joins, subqueries, or unions from arrays. Identifiers and SQL keywords cannot be bound as values.

`update()` and `delete()` require conditions. Use `updateAll()` or `deleteAll()` when a whole-table change is intentional. Database failures are logged and rethrown as PDO exceptions.

Transactions are available through a callback:

```php
$db->transaction(function (SimplePhpDbWrapper $db): void {
    $db->insert('users', ['email' => 'user@example.test', 'active' => 1]);
});
```

## Development

```sh
composer install
composer test
composer analyse
composer format:check
```

Portable tests use an in-memory SQLite connection. MySQL-specific behavior is checked separately by CI against MySQL. SQLite is a test tool; MySQL is the supported production driver.

## Support

The `PHP_7.4_Support` branch is the final PHP 7.4-compatible feature line. After its final release it will receive security fixes only. The modern development line will target PHP 8.3 and newer.
