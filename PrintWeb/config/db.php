<?php
/**
 * Database Configuration
 * Smart Printing Payment System
 */

define('DB_HOST', (string)(getenv('PRINTWEB_DB_HOST') ?: '127.0.0.1'));
define('DB_PORT', (string)(getenv('PRINTWEB_DB_PORT') ?: '3307'));
define('DB_USER', (string)(getenv('PRINTWEB_DB_USER') ?: 'root'));
define('DB_PASS', (string)(getenv('PRINTWEB_DB_PASS') ?: ''));
define('DB_NAME', (string)(getenv('PRINTWEB_DB_NAME') ?: 'printweb_db'));
define('DB_CHARSET', 'utf8mb4');

// Create connection using PDO
function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("DB Connection failed: " . $e->getMessage());
            die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
        }
    }
    return $pdo;
}

$pdo = getDBConnection();
