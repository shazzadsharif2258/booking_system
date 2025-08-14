<?php
require_once __DIR__ . '/config.php';

class Database {
    private $conn;

    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database Connection Failed: " . $e->getMessage());
            die("Unable to connect to the database. Please try again later.");
        }
    }

    public function select($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database Select Error: " . $e->getMessage());
            return [];
        }
    }

    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Database SelectOne Error: " . $e->getMessage());
            return null;
        }
    }

    public function insert($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database Insert Error: " . $e->getMessage());
            return false;
        }
    }

    public function update($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database Update Error: " . $e->getMessage());
            return 0;
        }
    }

    public function delete($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database Delete Error: " . $e->getMessage());
            return 0;
        }
    }

    public function begin_transaction() {
        try {
            $this->conn->beginTransaction();
        } catch (PDOException $e) {
            error_log("Database Begin Transaction Error: " . $e->getMessage());
            throw new \Exception("Failed to start transaction: " . $e->getMessage());
        }
    }

    public function commit() {
        try {
            $this->conn->commit();
        } catch (PDOException $e) {
            error_log("Database Commit Error: " . $e->getMessage());
            throw new \Exception("Failed to commit transaction: " . $e->getMessage());
        }
    }

    public function rollback() {
        try {
            $this->conn->rollBack();
        } catch (PDOException $e) {
            error_log("Database Rollback Error: " . $e->getMessage());
            throw new \Exception("Failed to rollback transaction: " . $e->getMessage());
        }
    }
}

// Instantiate the database connection
$db = new Database();
?>