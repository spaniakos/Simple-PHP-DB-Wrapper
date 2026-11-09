<?php declare(strict_types=1);

namespace Spaniakos;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Small PDO helper. The application owns configuration and supplies PDO.
 * SQL values are bound; identifiers are validated and quoted.
 */
class SimplePhpDbWrapper
{
    private PDO $connection;
    private LoggerInterface $logger;

    public function __construct(PDO $connection, ?LoggerInterface $logger = null)
    {
        $this->connection = $connection;
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->logger = $logger ?? self::defaultLogger();
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * @param array<int, string> $columns
     * @param array<string, mixed> $where Conditions use keys like 'age[>=]' or 'id[IN]'.
     * @param array{order?: array<string, string>, limit?: int, offset?: int} $options
     * @return array<int, array<string, mixed>>
     */
    public function select(string $table, array $columns = ['*'], array $where = [], array $options = []): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('Select at least one column.');
        }
        $select = [];
        foreach ($columns as $column) {
            $select[] = $column === '*' ? '*' : $this->identifier($column);
        }

        $params = [];
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . $this->identifier($table);
        $sql .= $this->where($where, $params);

        if (isset($options['order'])) {
            if (!is_array($options['order']) || $options['order'] === []) {
                throw new \InvalidArgumentException('order must be a non-empty column => ASC|DESC array.');
            }
            $parts = [];
            foreach ($options['order'] as $column => $direction) {
                $direction = strtoupper((string) $direction);
                if (!in_array($direction, ['ASC', 'DESC'], true)) {
                    throw new \InvalidArgumentException('Order direction must be ASC or DESC.');
                }
                $parts[] = $this->identifier((string) $column) . ' ' . $direction;
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }
        if (isset($options['limit'])) {
            $sql .= ' LIMIT ' . $this->nonNegativeInt($options['limit'], 'limit');
        }
        if (isset($options['offset'])) {
            if (!isset($options['limit'])) {
                $sql .= ' LIMIT 18446744073709551615';
            }
            $sql .= ' OFFSET ' . $this->nonNegativeInt($options['offset'], 'offset');
        }

        return $this->fetchAll($sql, $params);
    }

    /** @return array<string, mixed>|null */
    public function get(string $table, array $columns = ['*'], array $where = []): ?array
    {
        return $this->select($table, $columns, $where, ['limit' => 1])[0] ?? null;
    }

    /** @return array<int, mixed> */
    public function getColumn(string $table, string $column, array $where = [], array $options = []): array
    {
        $values = [];
        foreach ($this->select($table, [$column], $where, $options) as $row) {
            $values[] = reset($row);
        }
        return $values;
    }

    /** @return string|false */
    public function insert(string $table, array $data)
    {
        if ($data === []) {
            throw new \InvalidArgumentException('Insert data cannot be empty.');
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
            throw new \InvalidArgumentException('Update requires conditions; use updateAll() explicitly.');
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
            throw new \InvalidArgumentException('Delete requires conditions; use deleteAll() explicitly.');
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
            throw new \InvalidArgumentException('Exists requires conditions.');
        }
        $params = [];
        $sql = 'SELECT 1 AS row_exists FROM ' . $this->identifier($table)
            . $this->where($where, $params) . ' LIMIT 1';
        return $this->fetchOne($sql, $params) !== null;
    }

    /** @param callable(self): mixed $callback */
    public function transaction(callable $callback)
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
            throw new \InvalidArgumentException('Update data cannot be empty.');
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

    private function run(string $sql, array $params): \PDOStatement
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

    private function where(array $conditions, array &$params, string $joiner = 'AND'): string
    {
        if ($conditions === []) {
            return '';
        }
        $parts = [];
        foreach ($conditions as $key => $value) {
            if (is_string($key) && in_array(strtoupper($key), ['AND', 'OR'], true)) {
                if (!is_array($value) || $value === []) {
                    throw new \InvalidArgumentException('Nested AND/OR groups must be non-empty arrays.');
                }
                $nested = $this->where($value, $params, strtoupper($key));
                $parts[] = '(' . substr($nested, 7) . ')';
                continue;
            }
            if (!is_string($key) || $key === '') {
                throw new \InvalidArgumentException('Condition keys must be identifiers.');
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
                    throw new \InvalidArgumentException($operator . ' requires an array.');
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
            if (!in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE'], true)) {
                throw new \InvalidArgumentException('Unsupported condition operator: ' . $operator);
            }
            if (is_array($value)) {
                throw new \InvalidArgumentException('Use IN or NOT IN for array values.');
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
                throw new \InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
            }
        }
        return implode('.', array_map(static function (string $part): string {
            return $part === '*' ? '*' : '`' . $part . '`';
        }, $parts));
    }

    private function nonNegativeInt($value, string $name): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException($name . ' must be a non-negative integer.');
        }
        return $value;
    }

    private static function defaultLogger(): LoggerInterface
    {
        $logger = new Logger('simple-php-db-wrapper');
        $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, 400));
        return $logger;
    }
}
