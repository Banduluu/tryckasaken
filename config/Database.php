<?php
class Database {
    private $host = "localhost";
    private $db_name = "tric_db";
    private $username = "root";
    private $password = "";
    private $conn = null;

    public function getConnection() {
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
            
            if ($this->conn->connect_error) {
                die("Connection failed: " . $this->conn->connect_error);
            }
            
            return $this->conn;
        } catch(Exception $e) {
            echo "Connection error: " . $e->getMessage();
            return null;
        }
    }
    
    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
