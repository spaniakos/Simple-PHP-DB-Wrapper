<?php
/**
 * A simple PHP database wrapper class for MySQL using PDO.
 */
class Database {
    protected $host="localhost"; // The database host.
    protected $user="user"; // The database user.
    protected $db="database"; // The database name.
    protected $pass="password"; // The database password.
    protected $port="3306"; // The database port.
    protected $conn; // The PDO connection object.
    protected $charset="utf8mb4"; // The database character set.
    protected $collation="utf8mb4_0900_ai_ci"; // The database collation.

    /**
     * Constructor method that creates a new PDO connection object.
     */
    public function __construct()
    {
        try {
            $this->conn = new PDO("mysql:host=".$this->host.";dbname=".$this->db.";port=".$this->port."charset=".$this->charset, $this->user, $this->pass);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            $this->conn->exec('set names '.$this->charset.' COLLATE '.$this->collation);
		} catch(PDOException $e) {
			echo $e->getMessage();
		}
	}
	
	/**
	 * Destructor method that closes the PDO connection object.
	 */
	public function __destruct() {
		$this->conn= null;
	}
  
	/**
	 * Retrieves a column from a table.
	 *
	 * @param string $table The name of the table.
	 * @param string $col The name of the columns to retrieve.
	 * @param string $where The WHERE clause of the SQL query. Without the WHERE keyword.
	 * @param string $order The ORDER BY clause of the SQL query. Without The Order by keyword.
	 * @param string $limit The LIMIT clause of the SQL query. Without the Limit keyword.
	 * @return array The result set as an array.
	 */
	public function GetColFromTable($table,$col,$where = '',$order = '', $limit = '') {
		if (strlen($where) > 0){
			$where = "WHERE ".$where;
		}
		if (strlen($limit) > 0){
                        $limit = "LIMIT ".$limit;
                }
		if (strlen($order) >0){
			$order = $order;
		}else{
			$order = $col."ASC";
		}
	        $sql = "SELECT ".$col." FROM ".$table." ".$where." ORDER BY ".$order." ".$limit;
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		return $result = $stmt->fetchAll();
    }
	
	/**
	 * Retrieves all rows from a table.
	 *
	 * @param string $table The name of the table.
	 * @param string $where The WHERE clause of the SQL query. Without the WHERE keyword.
	 * @param string $order_col The column to order by.
	 * @param string $order The ORDER BY clause of the SQL query. Without The Order by keyword.
	 * @param string $limit The LIMIT clause of the SQL query. Without the LIMIT keyword.
	 * @return array The result set as an array.
	 */
	public function GetAllFromTable($table,$where = '',$order_col = 'ID',$order = 'ASC', $limit = '') {
		if (strlen($where) > 0){
			$where = "WHERE ".$where;
		}
		if (strlen($limit) > 0){
			$limit = "LIMIT ".$limit;
		}
        $sql = "SELECT * FROM ".$table." ".$where." ORDER BY ".$order_col." ".$order." ".$limit;
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		return $result = $stmt->fetchAll();
    }
	
	/**
	 * Retrieves a random row from a table.
	 *
	 * @param string $table The name of the table.
	 * @param string $where The WHERE clause of the SQL query. Without the WHERE keyword.
	 * @return array The result set as an array.
	 */
	public function GetAllRandomFromTable($table,$where = '') {
		if (strlen($where) > 0){
			$where = "WHERE ".$where;
		}
        $sql = "SELECT * FROM ".$table." ".$where." ORDER BY RAND() LIMIT 1";
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		return $result = $stmt->fetchAll();
    }
	
	/**
	 * Retrieves all rows from a table using prepared statements.
	 *
	 * @param string $table The name of the table.
	 * @param string $where The WHERE clause of the SQL query. Without the WHERE keyword.
	 * @param array $array The array of values to bind to the prepared statement.
	 * @return array The result set as an array.
	 */
	public function GetAllFromTablePrep($table,$where = '',$array) {
		if (strlen($where) > 0){
			$where = "WHERE ".$where;
		}
        $sql = "SELECT * FROM ".$table." ".$where;
		$stmt = $this->conn->prepare($sql);
		$stmt->execute($array);
		$result = $stmt->fetchAll();
		return $result;
    }
	
	/**
	 * Executes a custom SQL query.
	 *
	 * @param string $query The SQL query to execute.
	 * @return array The result set as an array.
	 */
	public function Query($query) {
		$sql = $query;
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		$result = $stmt->fetchAll();
		return $result;
    }
	
	/**
	 * Inserts a row into a table.
	 *
	 * @param string $table The name of the table.
	 * @param string $cols The columns to insert into.
	 * @param string $values The values to insert.
	 * @return bool True if the insert was successful, false otherwise.
	 */
	public function InsertIntoTable($table,$cols,$values){
		$sql = "INSERT INTO ".$table." (".$cols.") VALUES (".$values.")";
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		return true;
	}
	
	/**
	 * Deletes rows from a table.
	 *
	 * @param string $table The name of the table.
	 * @param string $where The WHERE clause of the SQL query. Without the WHERE keyword.
	 * @return bool True if the delete was successful, false otherwise.
	 */
	public function DeleteFromTable($table,$where = ''){
		if (strlen($where) > 0){
			$where = "WHERE ".$where;
		}
		$sql = "DELETE FROM ".$table." ".$where;
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		return true;
	}
	
	/**
	 * Updates rows in a table.
	 *
	 * @param string $table The name of the table.
	 * @param string $set The SET clause of the SQL query. Without the SET keyword.
	 * @param string $where The WHERE clause of the SQL query. Without the WHERE keyword.
	 * @return bool True if the update was successful, false otherwise.
	 */
	public function UpdateTable($table,$set,$where= ''){
		if (strlen($where) > 0){
			$where = "WHERE ".$where;
		}
		$sql = "UPDATE ".$table." SET ".$set." ".$where;
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		return true;
	}
}
?>
