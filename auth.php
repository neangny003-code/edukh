<?php
/**
 * Admin Authentication and Session Management
 */
require_once __DIR__ . '/database.php';

// Safe session startup
if (session_status() === PHP_SESSION_NONE) {
    // Set a reasonable session cookie lifetime
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

/**
 * Check if the user is currently logged in as admin
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && !empty($_SESSION['admin_id']);
}

/**
 * Get current logged in admin data
 */
function get_logged_in_admin() {
    if (!is_admin_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['admin_id'] ?? 1,
        'username' => $_SESSION['admin_username'] ?? 'admin',
        'full_name' => $_SESSION['admin_fullname'] ?? 'Super Admin',
        'email' => $_SESSION['admin_email'] ?? 'admin@gameskid.local',
        'role' => $_SESSION['admin_role'] ?? 'admin',
    ];
}

/**
 * Enforce admin login on protected pages
 */
function require_admin_login() {
    if (!is_admin_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Process admin login
 */
function admin_login($username, $password) {
    $username = trim($username);
    $password = trim($password);

    if (empty($username) || empty($password)) {
        return [
            'success' => false,
            'message' => 'Please enter both username and password.'
        ];
    }

    $pdo = get_db_connection();

    // If database is reachable, authenticate against DB
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `username` = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_fullname'] = $admin['full_name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];

                try {
                    $logStmt = $pdo->prepare("INSERT INTO `game_logs` (`game_key`, `player_name`, `action`, `details`) VALUES ('admin_panel', ?, 'login', 'Successful admin login')");
                    $logStmt->execute([$admin['username']]);
                } catch (Throwable $e) {}

                return ['success' => true];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid username or password. Default is: admin / admin123'
                ];
            }
        } catch (Throwable $e) {
            // Fallback check below if DB query failed
        }
    }

    // Fallback: Default admin authentication when DB is offline or table is being created
    if ($username === 'admin' && $password === 'admin123') {
        session_regenerate_id(true);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_username'] = 'admin';
        $_SESSION['admin_fullname'] = 'Super Admin';
        $_SESSION['admin_email'] = 'admin@gameskid.local';
        $_SESSION['admin_role'] = 'admin';

        return ['success' => true];
    }

    return [
        'success' => false,
        'message' => 'Invalid username or password. Default is: admin / admin123'
    ];
}

/**
 * Process admin logout
 */
function admin_logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
