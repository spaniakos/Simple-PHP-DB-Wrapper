<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Georgios Spanos (Spaniakos) <spaniakos@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Spaniakos;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\Dotenv\Dotenv;
use PDO;

/**
 * Class Database
 * A simple PHP database wrapper class that uses PDO for database connectivity.
 */
class SimplePhpDbWrapper {
    /**
     * @var bool $debug Whether to enable debugging mode or not.
     */
    protected bool $debug;

    /**
     * @var string $host The database host name or IP address.
     */
    protected string $host;
    
    /**
     * @var string $user The database user name.
     */
    protected string $user;
    
    /**
     * @var string $db The database name.
     */
    protected string $db;
    
    /**
     * @var string $pass The database user password.
     */
    protected string $pass;
    
    /**
     * @var string $port The database port number.
     */
    protected string $port;
    
    /**
     * @var string $charset The database character set.
     */
    protected string $charset;
    
    /**
     * @var string $collation The database collation.
     */
    protected string $collation;
    
    /**
     * @var Logger $logger The logger object.
     */
    protected Logger $logger;
    
    /**
     * @var PDO $conn The PDO object for database connectivity.
     */
    protected PDO $conn;

    /**
     * The path to the log file.
     *
     * @var string
     */
    protected string $logFilePath;

    /**
     * Constructor for the database class.
     * Loads configuration from .env file and initializes a PDO connection.
     * Also initializes a Monolog logger for error logging.
     */
    public function __construct($databaseTest = false) {
        // Load configuration from .env file
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__ . '/../../.env');

        $this->debug = ($_ENV['DEBUG'] === 'true') ? true : false;
        $this->host = $_ENV['DB_HOST'];
        $this->user = $_ENV['DB_USER'];
        $this->db = $_ENV['DB_NAME'];
        $this->pass = $_ENV['DB_PASS'];
        $this->port = $_ENV['DB_PORT'];
        $this->charset = $_ENV['DB_CHARSET'];
        $this->collation = $_ENV['DB_COLLATION'];
        $this->logFilePath = $_ENV['LOG_PATH'];
        // Initialize a Monolog logger
        $this->logger = new Logger('database');
        $this->logger->pushHandler(new StreamHandler($this->logFilePath, Logger::ERROR));

        try{
            self::ensureLogFileExists();
        } catch (Exception $e) {
            $this->logger->error("Error in ensureLogFileExists: " . $e->getMessage());
        }

        if ($this->debug){
            // Add a handler for info messages
            $this->logger->pushHandler(new StreamHandler($this->logFilePath, Logger::DEBUG));
            $this->logger->debug("Logger initialized");
        }

        try {
            //throw new pdo exception
            if ($databaseTest){
                $this->conn = new PDO("mysql:host={$this->host};port={$this->port};charset={$this->charset}", $this->user, $this->pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            } else {
                $this->conn = new PDO("mysql:host={$this->host};dbname={$this->db};port={$this->port};charset={$this->charset}", $this->user, $this->pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            }
        
            if ($this->debug){
                $this->logger->debug("Database connection established");
            }
        
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec('set names ' . $this->charset . ' COLLATE ' . $this->collation);
            
            // Check if the connection was successful
            if (!$this->conn){
                throw new PDOException("Connection failed");
            }
        } catch (\PDOException $e) {
            // Log the error using Monolog
            $this->logger->error("Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Closes the database connection when the object is destroyed.
     *
     * @return void
     */
    public function __destruct() {
        unset($this->conn);
    }

    /**
     * Ensures that the log file exists at the specified path. If the file does not exist, it creates an empty file and ensures that the directory exists.
     *
     * @param string $logFilePath The path to the log file.
     * @return void
     */
    function ensureLogFileExists() {
        if (!file_exists($this->logFilePath)) {
            // Ensure the directory exists
            $logDirectory = dirname($this->logFilePath);
            if (!is_dir($logDirectory)) {
                mkdir($logDirectory, 0777, true);
            }
    
            // Create an empty log file
            touch($this->logFilePath);
        }
    }

    /**
     * Get a specific column from a table with optional WHERE, ORDER BY, and LIMIT clauses.
     *
     * @param string $table The name of the table to retrieve data from.
     * @param string $col The name of the column to retrieve.
     * @param string $where Optional WHERE clause to filter the results.
     * @param string $order Optional ORDER BY clause to sort the results.
     * @param string $limit Optional LIMIT clause to limit the number of results.
     *
     * @return array An array of rows containing the specified column data.
     */
    public function GetColFromTable(string $table, string $col, string $where = '', string $order = '', string $limit = ''): array {
        try {
            if (strlen($where) > 0) {
                $where = "WHERE " . $where;
            }
            if (strlen($limit) > 0) {
                $limit = "LIMIT " . $limit;
            }
            if (strlen($order) > 0) {
                $order = $order;
            } else {
                $order = $col . " ASC";
            }
            $sql = "SELECT {$col} FROM {$table} {$where} ORDER BY {$order} {$limit}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->error("Error in GetColFromTable: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves all rows from a specified table in the database.
     *
     * @param string $table The name of the table to retrieve rows from.
     * @param string $where Optional WHERE clause to filter the rows.
     * @param string $order_col The column to order the rows by.
     * @param string $order The order direction ('ASC' or 'DESC').
     * @param string $limit Optional LIMIT clause to limit the number of rows returned.
     *
     * @return array An array of rows from the specified table.
     */
    public function GetAllFromTable(string $table, string $where = '', string $order_col = 'ID', string $order = 'ASC', string $limit = ''): array {
        try {
            if (strlen($where) > 0) {
                $where = "WHERE " . $where;
            }
            if (strlen($limit) > 0) {
                $limit = "LIMIT " . $limit;
            }
            $sql = "SELECT * FROM {$table} {$where} ORDER BY {$order_col} {$order} {$limit}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->error("Error in GetAllFromTable: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a random row from a table in the database.
     *
     * @param string $table The name of the table to select from.
     * @param string $where Optional WHERE clause to filter the results.
     * @return array An array containing the selected row.
     */
    public function GetAllRandomFromTable(string $table, string $where = ''): array {
        try {
            if (strlen($where) > 0) {
                $where = "WHERE " . $where;
            }
            $sql = "SELECT * FROM {$table} {$where} ORDER BY RAND() LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->error("Error in GetAllRandomFromTable: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves all rows from a table with optional WHERE clause using prepared statements.
     *
     * @param string $table The name of the table to retrieve data from.
     * @param string $where The WHERE clause to filter the results (optional).
     * @param array $array The array of values to bind to the prepared statement.
     * @return array An array containing all rows from the table that match the WHERE clause (if provided).
     */
    public function GetAllFromTablePrep(string $table, string $where = '', array $array): array {
        try {
            if (strlen($where) > 0) {
                $where = "WHERE " . $where;
            }
            $sql = "SELECT * FROM {$table} {$where}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($array);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->error("Error in GetAllFromTablePrep: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Executes a SQL query and returns the result set as an array.
     *
     * @param string $query The SQL query to execute.
     *
     * @return array The result set as an array.
     */
    public function Query(string $query): array {
        try {
            $sql = $query;
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->error("Error in Query: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Inserts a new row into the specified table with the given column names and values.
     *
     * @param string $table The name of the table to insert into.
     * @param string $cols A comma-separated string of column names to insert into.
     * @param string $values A comma-separated string of values to insert into the specified columns.
     * @return bool Returns true if the insert was successful, false otherwise.
     */
    public function InsertIntoTable(string $table, string $cols, string $values): bool {
        try {
            $sql = "INSERT INTO {$table} ({$cols}) VALUES ({$values})";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            $this->logger->error("Error in InsertIntoTable: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes rows from a table based on a given condition.
     *
     * @param string $table The name of the table to delete from.
     * @param string $where The condition to use for deleting rows (optional).
     *
     * @return bool True if the rows were successfully deleted, false otherwise.
     */
    public function DeleteFromTable(string $table, string $where = ''): bool {
        try {
            if (strlen($where) > 0) {
                $where = "WHERE " . $where;
            }
            $sql = "DELETE FROM {$table} {$where}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            $this->logger->error("Error in DeleteFromTable: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update a table in the database with the given set of values and optional WHERE clause.
     *
     * @param string $table The name of the table to update.
     * @param string $set The set of values to update in the table.
     * @param string $where Optional WHERE clause to filter the rows to update.
     * @return bool Returns true if the update was successful, false otherwise.
     */
    public function UpdateTable(string $table, string $set, string $where = ''): bool {
        try {
            if (strlen($where) > 0) {
                $where = "WHERE " . $where;
            }
            $sql = "UPDATE {$table} SET {$set} {$where}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            $this->logger->error("Error in UpdateTable: " . $e->getMessage());
            return false;
        }
    }
}
?>
