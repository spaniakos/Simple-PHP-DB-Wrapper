<?php
class Database {
    protected $host="localhost";
    protected $user="user";
    protected $db="database";
    protected $pass="password";
    protected $port="3306";
    protected $conn;
    protected $charset="utf8mb4";
    protected $collation="utf8mb4_0900_ai_ci";

    public function __construct()
    {
        try {
            $this->conn = new PDO("mysql:host=".$this->host.";dbname=".$this->db.";port=".$this->port."charset=".$this->charset, $this->user, $this->pass);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            $this->conn->exec('set names utf8mb4 COLLATE utf8mb4_0900_ai_ci');
		} catch(PDOException $e) {
			echo $e->getMessage();
		}
	}
	
	public function __destruct() {
		$this->conn= null;
	}
  
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
	
	public function GetAllRandomFromTable($table,$where = '') {
		if (strlen($where) > 0){
			$where = "WHERE ".$where;
		}
        $sql = "SELECT * FROM ".$table." ".$where." ORDER BY RAND() LIMIT 1";
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		return $result = $stmt->fetchAll();
    }
	
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
	
	public function Query($query) {
		$sql = $query;
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		$result = $stmt->fetchAll();
		return $result;
    }
	
	public function InsertIntoTable($table,$cols,$values){
		$sql = "INSERT INTO ".$table." (".$cols.") VALUES (".$values.")";
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		return true;
	}
	
	public function DeleteFromTable($table,$where = ''){
		if (strlen($where) > 0){
			$where = "WHERE ".$where;
		}
		$sql = "DELETE FROM ".$table." ".$where;
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		return true;
	}
	
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
