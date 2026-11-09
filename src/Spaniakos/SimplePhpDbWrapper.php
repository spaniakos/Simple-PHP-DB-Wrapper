<?php

declare(strict_types=1);

/*
 * This file is part of the SimplePhpDbWrapper package.
 *
 * (c) Georgios Spanos (Spaniakos) <spaniakos@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spaniakos;

use InvalidArgumentException;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Small PDO helper. The application owns configuration and supplies PDO.
 * SQL values are bound; identifiers are validated and quoted.
 */
final class SimplePhpDbWrapper
{
    private const array SUPPORTED_OPERATORS = ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

    private PDO $connection;
    private LoggerInterface $logger;

    public function __construct(PDO $connection, ?LoggerInterface $logger = null)
    {
        $this->connection = $connection;
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->logger = $logger ?? self::defaultLogger();
    }

    /** @return list<array<string, mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * @param list<string> $columns
     * @param array<string, mixed> $where Conditions use keys like 'age[>=]' or 'id[IN]'.
     * @return list<array<string, mixed>>
     */
    public function select(
        string $table,
        array $columns = ['*'],
        array $where = [],
        SelectOptions $options = new SelectOptions()
    ): array {
        if ($columns === []) {
            throw new InvalidArgumentException('Select at least one column.');
        }
        $select = [];
        foreach ($columns as $column) {
            $select[] = $column === '*' ? '*' : $this->identifier($column);
        }

        $params = [];
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . $this->identifier($table);
        $sql .= $this->where($where, $params);

        if ($options->order !== []) {
            $parts = [];
            foreach ($options->order as $column => $direction) {
                $parts[] = $this->identifier($column) . ' ' . $direction->value;
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }
        if ($options->limit !== null) {
            $sql .= ' LIMIT ' . $options->limit;
        }
        if ($options->offset !== null) {
            $sql .= ' OFFSET ' . $options->offset;
        }

        return $this->fetchAll($sql, $params);
    }

    /** @return array<string, mixed>|null */
    public function get(string $table, array $columns = ['*'], array $where = []): ?array
    {
        return $this->select($table, $columns, $where, new SelectOptions(limit: 1))[0] ?? null;
    }

    /**
     * @return list<mixed>
     */
    public function getColumn(
        string $table,
        string $column,
        array $where = [],
        SelectOptions $options = new SelectOptions()
    ): array {
        $values = [];
        foreach ($this->select($table, [$column], $where, $options) as $row) {
            $values[] = reset($row);
        }
        return $values;
    }

    /** @return string|false */
    public function insert(string $table, array $data): string|false
    {
        if ($data === []) {
            throw new InvalidArgumentException('Insert data cannot be empty.');
        }
        $columns = [];
        $marks = [];
        $params = [];
        foreach ($data as $column => $value) {
            $columns[] = $this->identifier((string) $column);
            $mark = ':p' . count($params);
            $marks[] = $mark;
            $params[$mark] = $value;
        }
        $sql = 'INSERT INTO ' . $this->identifier($table) . ' (' . implode(', ', $columns)
            . ') VALUES (' . implode(', ', $marks) . ')';
        $this->execute($sql, $params);
        return $this->connection->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        if ($where === []) {
            throw new InvalidArgumentException('Update requires conditions; use updateAll() explicitly.');
        }
        return $this->updateRows($table, $data, $where);
    }

    public function updateAll(string $table, array $data): int
    {
        return $this->updateRows($table, $data, []);
    }

    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            throw new InvalidArgumentException('Delete requires conditions; use deleteAll() explicitly.');
        }
        $params = [];
        return $this->execute(
            'DELETE FROM ' . $this->identifier($table) . $this->where($where, $params),
            $params
        );
    }

    public function deleteAll(string $table): int
    {
        return $this->execute('DELETE FROM ' . $this->identifier($table));
    }

    public function count(string $table, array $where = []): int
    {
        $params = [];
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS row_count FROM ' . $this->identifier($table) . $this->where($where, $params),
            $params
        );
        return (int) ($row['row_count'] ?? 0);
    }

    public function exists(string $table, array $where): bool
    {
        if ($where === []) {
            throw new InvalidArgumentException('Exists requires conditions.');
        }
        $params = [];
        $sql = 'SELECT 1 AS row_exists FROM ' . $this->identifier($table)
            . $this->where($where, $params) . ' LIMIT 1';
        return $this->fetchOne($sql, $params) !== null;
    }

    public function transaction(callable $callback): mixed
    {
        $this->connection->beginTransaction();
        try {
            $result = $callback($this);
            $this->connection->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private function updateRows(string $table, array $data, array $where): int
    {
        if ($data === []) {
            throw new InvalidArgumentException('Update data cannot be empty.');
        }
        $params = [];
        $sets = [];
        foreach ($data as $column => $value) {
            $mark = ':p' . count($params);
            $sets[] = $this->identifier((string) $column) . ' = ' . $mark;
            $params[$mark] = $value;
        }
        $sql = 'UPDATE ' . $this->identifier($table) . ' SET ' . implode(', ', $sets)
            . $this->where($where, $params);
        return $this->execute($sql, $params);
    }

    /** @param array<string, mixed> $params */
    private function run(string $sql, array $params): PDOStatement
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($params);
            return $statement;
        } catch (PDOException $exception) {
            $this->logger->error('Database statement failed.', ['exception' => $exception]);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $params */
    private function where(array $conditions, array &$params, string $joiner = 'AND'): string
    {
        if ($conditions === []) {
            return '';
        }
        $parts = [];
        foreach ($conditions as $key => $value) {
            if (is_int($key) && is_array($value) && $value !== []) {
                $nested = $this->where($value, $params);
                $parts[] = '(' . substr($nested, 7) . ')';
                continue;
            }
            if (is_string($key) && in_array(strtoupper($key), ['AND', 'OR'], true)) {
                if (!is_array($value) || $value === []) {
                    throw new InvalidArgumentException('Nested AND/OR groups must be non-empty arrays.');
                }
                $nested = $this->where($value, $params, strtoupper($key));
                $parts[] = '(' . substr($nested, 7) . ')';
                continue;
            }
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException('Condition keys must be identifiers.');
            }

            $column = $key;
            $operator = '=';
            if (preg_match('/^(.+)\[([A-Za-z ]+)\]$/', $key, $match)) {
                $column = $match[1];
                $operator = strtoupper(trim($match[2]));
            }
            $column = $this->identifier($column);

            if ($value === null && in_array($operator, ['=', '!=', '<>'], true)) {
                $parts[] = $column . ($operator === '=' ? ' IS NULL' : ' IS NOT NULL');
                continue;
            }
            if (in_array($operator, ['IN', 'NOT IN'], true)) {
                if (!is_array($value)) {
                    throw new InvalidArgumentException($operator . ' requires an array.');
                }
                if ($value === []) {
                    $parts[] = $operator === 'IN' ? '1 = 0' : '1 = 1';
                    continue;
                }
                $marks = [];
                foreach ($value as $item) {
                    $mark = ':p' . count($params);
                    $marks[] = $mark;
                    $params[$mark] = $item;
                }
                $parts[] = $column . ' ' . $operator . ' (' . implode(', ', $marks) . ')';
                continue;
            }
            if (!in_array($operator, self::SUPPORTED_OPERATORS, true)) {
                throw new InvalidArgumentException('Unsupported condition operator: ' . $operator);
            }
            if (is_array($value)) {
                throw new InvalidArgumentException('Use IN or NOT IN for array values.');
            }
            $mark = ':p' . count($params);
            $params[$mark] = $value;
            $parts[] = $column . ' ' . $operator . ' ' . $mark;
        }
        return ' WHERE ' . implode(' ' . $joiner . ' ', $parts);
    }

    private function identifier(string $identifier): string
    {
        $parts = explode('.', $identifier);
        foreach ($parts as $part) {
            if ($part !== '*' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
            }
        }
        return implode('.', array_map(static function (string $part): string {
            return $part === '*' ? '*' : '`' . $part . '`';
        }, $parts));
    }

    private static function defaultLogger(): LoggerInterface
    {
        $logger = new Logger('simple-php-db-wrapper');
        $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, 400));
        return $logger;
    }
}
