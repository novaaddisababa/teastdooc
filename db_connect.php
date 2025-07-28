<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'betting_game_one';
    private $username = 'root';
    private $password = '';
    private $conn;
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true
    ];

    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                $this->options
            );
        } catch(PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("A database error occurred. Please try again later.");
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>