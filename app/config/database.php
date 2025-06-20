<?php
// Database connection details
define('DB_HOST', 'localhost'); // Usually 'localhost' for local development
define('DB_USER', 'root');     // Default XAMPP user for MySQL
define('DB_PASS', '');         // Default XAMPP password (empty)
define('DB_NAME', 'recipe_planner_db'); // We'll create this database soon

function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?><?php
// app/config/database.php

class Database {
    private static $instance = null; // Holds the single instance of the Database class
    private $conn; // Holds the mysqli connection object

    // !! IMPORTANT: Update these constants with your actual database credentials !!
    private const DB_HOST = 'localhost'; // Your database host (e.g., 'localhost', '127.0.0.1')
    private const DB_NAME = 'recipe_planner_db'; // The name of your database (e.g., 'recipe_app_db')
    private const DB_USER = 'root'; // Your database username (e.g., 'root' for XAMPP/WAMP)
    private const DB_PASS = ''; // Your database password (often empty for 'root' on local setups)

    // The constructor is private to enforce the Singleton pattern
    private function __construct() {
        // Create a new database connection using mysqli
        $this->conn = new mysqli(self::DB_HOST, self::DB_USER, self::DB_PASS, self::DB_NAME);

        // Check for connection errors
        if ($this->conn->connect_error) {
            // If connection fails, stop script execution and display the error
            die("Database Connection failed: " . $this->conn->connect_error);
        }
        // Set the character set for the connection to support various characters (e.g., emojis, special characters)
        $this->conn->set_charset("utf8mb4");
    }

    /**
     * Public static method to get the single instance of the database connection.
     * Implements the Singleton design pattern.
     *
     * @return mysqli The mysqli database connection object.
     */
    public static function getInstance() {
        // If no instance exists yet, create one
        if (!self::$instance) {
            self::$instance = new Database();
        }
        // Return the existing connection object
        return self::$instance->conn;
    }
}