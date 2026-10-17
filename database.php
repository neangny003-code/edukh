<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$host = 'localhost';
$db   = 'gameskid';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// Data Source Name
$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Connect to MySQL server (without database first, to ensure database is created if it does not exist)
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");
    
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `cls` VARCHAR(50) DEFAULT '-',
        `score` VARCHAR(50) DEFAULT '-',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

} catch (\PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection/creation failed: " . $e->getMessage()]);
    exit;
}

// Handle request methods
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method === 'GET') {
    if ($action === 'get') {
        try {
            $stmt = $pdo->query("SELECT * FROM `students` ORDER BY `id` ASC");
            $students = $stmt->fetchAll();
            echo json_encode(["status" => "success", "data" => $students]);
        } catch (\PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'add') {
        $name = isset($input['name']) ? trim($input['name']) : '';
        $cls = isset($input['cls']) ? trim($input['cls']) : '-';
        $score = isset($input['score']) ? trim($input['score']) : '-';
        
        if (empty($name)) {
            echo json_encode(["status" => "error", "message" => "Name is required"]);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO `students` (`name`, `cls`, `score`) VALUES (?, ?, ?)");
            $stmt->execute([$name, $cls, $score]);
            echo json_encode(["status" => "success", "id" => $pdo->lastInsertId()]);
        } catch (\PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } elseif ($action === 'delete') {
        $id = isset($input['id']) ? intval($input['id']) : 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM `students` WHERE `id` = ?");
            $stmt->execute([$id]);
            echo json_encode(["status" => "success"]);
        } catch (\PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } elseif ($action === 'clear') {
        try {
            $pdo->exec("TRUNCATE TABLE `students`");
            echo json_encode(["status" => "success"]);
        } catch (\PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
?>
