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
?>