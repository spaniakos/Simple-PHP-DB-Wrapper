# Simple PHP DB Wrapper

A small MySQL/PDO wrapper for small PHP projects. It provides prepared query execution and a compact CRUD API without an ORM or a large SQL builder.

## Requirements

- PHP 8.3 or newer
- PDO and the PDO MySQL extension

Install the stable 2.x line with Composer:

```sh
composer require spaniakos/simple-php-db-wrapper:^2.0
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

Monolog 3 is used for error logging by default. You may inject a PSR-3 logger as the second constructor argument.

## Queries

Values are passed separately and bound by PDO:

```php
$user = $db->fetchOne(
    'SELECT id, email FROM users WHERE email = :email',
    [':email' => $email]
);
```

Common CRUD operations use arrays. Select options are immutable and typed; sort direction is an enum:

```php
use Spaniakos\SelectOptions;
use Spaniakos\SortDirection;

$users = $db->select(
    'users',
    ['id', 'email'],
    ['active' => 1, 'age[>=]' => 18],
    new SelectOptions(
        order: ['email' => SortDirection::Asc],
        limit: 20,
        offset: 0
    )
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

`SelectOptions` accepts an `order` map of column names to `SortDirection::Asc` or `SortDirection::Desc`, plus optional non-negative `limit` and `offset`. An offset requires a limit.

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

Use `UNION` or `UNION ALL` directly when combining compatible result sets. Give each bound value a distinct placeholder:

```php
$items = $db->fetchAll(
    'SELECT username AS label FROM users WHERE active = :active
     UNION
     SELECT title AS label FROM posts WHERE published = :published
     ORDER BY label',
    [':active' => 1, ':published' => 1]
);
```

These are prepared raw SQL examples: the wrapper binds values while you write the SQL structure. It does not currently build joins, subqueries, or unions from arrays. Identifiers and SQL keywords cannot be bound as values.

`update()` and `delete()` require conditions. Use `updateAll()` or `deleteAll()` when a whole-table change is intentional. Database failures are logged and rethrown as PDO exceptions.

Transactions are available through a callback:

```php
$db->transaction(function (SimplePhpDbWrapper $db): void {
    $db->insert('users', ['email' => 'user@example.test', 'active' => 1]);
});
```

## Migrating from 1.x to 2.x

Version 2 requires PHP 8.3 or newer. It uses Monolog 3 and PSR Log 3, and the wrapper now expects typed select options.

Change order, limit, and offset arguments from an options array to `SelectOptions`; use the enum for sort directions:

```php
// 1.x
$users = $db->select('users', ['id', 'email'], [], [
    'order' => ['email' => 'ASC'],
    'limit' => 20,
    'offset' => 0,
]);

// 2.x
$users = $db->select(
    'users',
    ['id', 'email'],
    [],
    new SelectOptions(
        order: ['email' => SortDirection::Asc],
        limit: 20,
        offset: 0
    )
);
```

The same `SelectOptions` argument is used by `getColumn()`. An offset now requires a limit. Connection setup remains application-owned: create a PDO connection and pass it to `SimplePhpDbWrapper`; inject your PSR-3 logger as the optional second constructor argument when needed. Raw prepared SQL methods such as `fetchAll()`, `fetchOne()`, and `execute()` keep their SQL-plus-parameters form.

## Development

```sh
composer install
composer test
composer analyse
composer format:check
```

Portable tests use an in-memory SQLite connection. MySQL-specific behavior is checked separately by CI against MySQL. SQLite is a test tool; MySQL is the supported production driver.

## Support

The `main` branch is the modern line and requires PHP 8.3 or newer. The `PHP_7.4_Support` branch is the legacy 7.4 line and will receive security fixes only. The 7.4 line is available as Composer version 1.x; the modern line starts at 2.0.0.
