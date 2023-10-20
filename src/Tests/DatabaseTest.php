<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Georgios Spanos (Spaniakos) <spaniakos@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spaniakos\Tests;

require_once 'vendor/autoload.php';
use PHPUnit\Framework\TestCase;
use Spaniakos\SimplePhpDbWrapper;

/**
 * Class DatabaseTest
 * @package Tests
 */
class DatabaseTest extends TestCase {
    protected $db;

    /**
     * Set up the test environment before each test.
     */
    public function setUp(): void {
        // Create an instance of the Database class before each test
        $this->db = new SimplePhpDbWrapper(true);

        // Create the database and tables
        $this->createTestDatabase();
    }

    /**
     * Tear down method to drop the test database and close the database connection after each test.
     *
     * @return void
     */
    public function tearDown(): void {
        // Drop the database and close the database connection after each test
        $this->dropTestDatabase();

        // Close the PDO connection
        if ($this->db !== null) {
            $this->db = null;
        }
    }

    /**
     * Creates a test database with a users table and inserts a test user.
     * Modify the SQL statements as needed to create your database and tables.
     *
     * @return void
     */
    protected function createTestDatabase() {
        $sql = "
        CREATE DATABASE IF NOT EXISTS test_database CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
        USE test_database;
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
        INSERT INTO users (username, email) VALUES ('testuser', 'testuser@example.com');
        ";

        $this->db->Query($sql);
    }

    /**
     * Drops the test database if it exists.
     *
     * @return void
     */
    protected function dropTestDatabase() {
        // Modify the SQL statement to drop the database
        $sql = "DROP DATABASE IF EXISTS test_database;";
        $this->db->Query($sql);
    }

    /**
     * Test case for the GetColFromTable method of the Database class.
     * Retrieves a column from a table based on the specified conditions.
     * 
     * @covers SimplePhpDbWrapper::GetColFromTable
     * @return void
     */
    public function testGetColFromTable() {
        $result = $this->db->GetColFromTable('users', 'username', 'id > 0', 'username', '5');
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test case for the GetAllFromTable method of the Database class.
     * Retrieves all records from the 'users' table where the 'id' column is greater than 0,
     * orders the results by the 'id' column in ascending order, and limits the results to 5.
     * Asserts that the result is an array and is not empty.
     * 
     * @covers SimplePhpDbWrapper::GetAllFromTable
     * @return void
     */
    public function testGetAllFromTable() {
        $result = $this->db->GetAllFromTable('users', 'id > 0', 'id', 'ASC', '5');
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test to get all random records from a table.
     * 
     * @covers SimplePhpDbWrapper::GetAllRandomFromTable
     * @return void
     */
    public function testGetAllRandomFromTable() {
        $result = $this->db->GetAllRandomFromTable('users', 'id > 0');
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test case for the GetAllFromTablePrep method of the Database class.
     * 
     * @covers SimplePhpDbWrapper::GetAllFromTablePrep
     * @return void
     */
    public function testGetAllFromTablePrep() {
        $result = $this->db->GetAllFromTablePrep('users', 'id > ?', [0]);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test the Query method of the Database class.
     * It should return an array of results and should not be empty.
     *
     * @covers SimplePhpDbWrapper::DeleteFromTable
     * @return void
     */
    public function testQuery() {
        $result = $this->db->Query('SELECT * FROM users WHERE id > 0 LIMIT 5');
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test inserting data into a table.
     *
     * @covers SimplePhpDbWrapper::InsertIntoTable
     * @return void
     */
    public function testInsertIntoTable() {
        $cols = 'username, email';
        $values = "'testuser', 'testuser@example.com'";
        $result = $this->db->InsertIntoTable('users', $cols, $values);
        $this->assertTrue($result);
    }

    /**
     * Test case for the DeleteFromTable method of the Database class.
     * Deletes a row from the 'users' table where the username is 'testuser'.
     * Asserts that the method returns true.
     *
     * @covers SimplePhpDbWrapper::DeleteFromTable
     * @return void
     */
    public function testDeleteFromTable() {
        $result = $this->db->DeleteFromTable('users', "username = 'testuser'");
        $this->assertTrue($result);
    }

    /**
     * Test case for testing the UpdateTable method of the Database class.
     *
     * @covers SimplePhpDbWrapper::UpdateTable
     * @return void
     */
    public function testUpdateTable() {
        $set = "username = 'newuser'";
        $result = $this->db->UpdateTable('users', $set, "id = 1");
        $this->assertTrue($result);
    }
}

?>