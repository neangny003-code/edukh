<?php
/**
 * Database Configuration & Connection for GamesKid
 * Works with XAMPP MySQL / MariaDB
 */

$db_host = '127.0.0.1';
$db_port = 3306;
$db_name = 'gameskid';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

function get_db_connection() {
    global $db_host, $db_port, $db_name, $db_user, $db_pass, $db_charset;
    static $pdo = null;
    static $connection_failed = false;

    if ($pdo !== null) {
        return $pdo;
    }

    if ($connection_failed) {
        return null;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 2, // Fast 2-second timeout to prevent 120s script hanging
    ];

    try {
        // Connect to MySQL server
        $dsn = "mysql:host={$db_host};port={$db_port};charset={$db_charset}";
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);

        // Auto-create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$db_name}`");

        // Auto-create admins table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `full_name` VARCHAR(100) DEFAULT 'Administrator',
            `role` VARCHAR(50) DEFAULT 'admin',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto-create students table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `cls` VARCHAR(50) DEFAULT '-',
            `score` VARCHAR(50) DEFAULT '-',
            `game_name` VARCHAR(100) DEFAULT 'General',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto-create game_logs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `game_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `game_key` VARCHAR(100) NOT NULL,
            `player_name` VARCHAR(255) DEFAULT 'Guest',
            `action` VARCHAR(100) NOT NULL,
            `details` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure default admin user exists
        $stmt = $pdo->query("SELECT COUNT(*) FROM `admins` WHERE `username` = 'admin'");
        if ($stmt->fetchColumn() == 0) {
            $defaultHash = password_hash('admin123', PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO `admins` (`username`, `password`, `email`, `full_name`, `role`) VALUES (?, ?, ?, ?, ?)");
            $ins->execute(['admin', $defaultHash, 'admin@gameskid.local', 'Super Admin', 'admin']);
        }

        return $pdo;
    } catch (Throwable $e) {
        $connection_failed = true;
        $pdo = null;
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}
