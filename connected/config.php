<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'redwoods');
define('DB_USER', 'redwoods');
define('DB_PASS', '123');

// Application settings
define('BANK_NAME', 'Nexus Pay');

// Database connection function
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?> 