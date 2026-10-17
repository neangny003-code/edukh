<?php
/**
 * GamesKid Unified Admin Dashboard
 * URL: http://localhost/gameskid/admin
 * Provides identical UI aesthetics as user portal + full game editing capabilities
 */
require_once __DIR__ . '/auth.php';

// Enforce login
require_admin_login();

$currentAdmin = get_logged_in_admin();
$db = get_db_connection();

$message = '';
$messageType = '';

// Handle POST actions for administration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action: Add Student Score
    if ($action === 'add_student') {
        $name = trim($_POST['name'] ?? '');
        $cls = trim($_POST['cls'] ?? '-');
        $score = trim($_POST['score'] ?? '-');
        $game_name = trim($_POST['game_name'] ?? 'General');

        if (!empty($name) && $db) {
            $stmt = $db->prepare("INSERT INTO `students` (`name`, `cls`, `score`, `game_name`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $cls, $score, $game_name]);
            $message = "កត់ត្រាពិន្ទុសម្រាប់សិស្ស '$name' ជោគជ័យ!";
            $messageType = 'success';
        } else {
            $message = "សូមបញ្ចូលឈ្មោះសិស្ស។";
            $messageType = 'danger';
        }
    }

    // Action: Delete Student
    if ($action === 'delete_student') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && $db) {
            $stmt = $db->prepare("DELETE FROM `students` WHERE `id` = ?");
            $stmt->execute([$id]);
            $message = "បានលុបកំណត់ត្រាលេខ #$id ជោគជ័យ។";
            $messageType = 'success';
        }
    }

    // Action: Clear All Students
    if ($action === 'clear_students') {
        if ($db) {
            $db->exec("TRUNCATE TABLE `students`");
            $message = "បានសម្អាតកំណត់ត្រាពិន្ទុសិស្សទាំងអស់រួចរាល់។";
            $messageType = 'success';
        }
    }

    // Action: Change Admin Password
    if ($action === 'change_password') {
        $old_pwd = $_POST['old_password'] ?? '';
        $new_pwd = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';

        if (empty($old_pwd) || empty($new_pwd)) {
            $message = "សូមបំពេញគ្រប់ប្រអប់ពាក្យសម្ងាត់។";
            $messageType = 'danger';
        } elseif ($new_pwd !== $confirm_pwd) {
            $message = "ពាក្យសម្ងាត់ថ្មី និងផ្ទៀងផ្ទាត់មិនត្រូវគ្នាទេ។";
            $messageType = 'danger';
        } elseif (strlen($new_pwd) < 6) {
            $message = "ពាក្យសម្ងាត់ថ្មីត្រូវមានយ៉ាងតិច ៦ តួអក្សរ។";
            $messageType = 'danger';
        } else {
            if ($db) {
                $stmt = $db->prepare("SELECT `password` FROM `admins` WHERE `id` = ?");
                $stmt->execute([$currentAdmin['id']]);
                $adminData = $stmt->fetch();

                if ($adminData && password_verify($old_pwd, $adminData['password'])) {
                    $newHash = password_hash($new_pwd, PASSWORD_DEFAULT);
                    $updateStmt = $db->prepare("UPDATE `admins` SET `password` = ? WHERE `id` = ?");
                    $updateStmt->execute([$newHash, $currentAdmin['id']]);
                    $message = "ពាក្យសម្ងាត់ត្រូវបានផ្លាស់ប្តូរដោយជោគជ័យ!";
                    $messageType = 'success';
                } else {
                    $message = "ពាក្យសម្ងាត់បច្ចុប្បន្នមិនត្រឹមត្រូវទេ។";
                    $messageType = 'danger';
                }
            } else {
                $message = "បានប្តូរពាក្យសម្ងាត់សម្រាប់ Admin Session។";
                $messageType = 'success';
            }
        }
    }

    // Action: Add New Admin
    if ($action === 'add_admin') {
        $new_user = trim($_POST['new_username'] ?? '');
        $new_pass = trim($_POST['new_password'] ?? '');
        $new_name = trim($_POST['new_fullname'] ?? 'Admin User');
        $new_email = trim($_POST['new_email'] ?? '');

        if (!empty($new_user) && !empty($new_pass)) {
            if ($db) {
                try {
                    $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO `admins` (`username`, `password`, `email`, `full_name`, `role`) VALUES (?, ?, ?, ?, 'admin')");
                    $stmt->execute([$new_user, $hash, $new_email, $new_name]);
                    $message = "គណនី Admin ថ្មី '$new_user' ត្រូវបានបង្កើតដោយជោគជ័យ!";
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = "ឈ្មោះ Admin នេះអាចមានរួចហើយក្នុងប្រព័ន្ធ។";
                    $messageType = 'danger';
                }
            } else {
                $message = "សូមបើក MySQL ក្នុង XAMPP ដើម្បីរក្សាទុកគណនី Admin។";
                $messageType = 'danger';
            }
        } else {
            $message = "សូមបញ្ចូលឈ្មោះគណនី និងពាក្យសម្ងាត់។";
            $messageType = 'danger';
        }
    }
}

// Fetch games dynamically from disk
$gamesDir = __DIR__ . '/../games';
$gamesList = [];
if (is_dir($gamesDir)) {
    $items = scandir($gamesDir);
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..' && is_dir($gamesDir . '/' . $item)) {
            $displayName = ucwords(str_replace(['_', '-'], ' ', $item));
            $hasWords = in_array($item, ['khmer_word_collection', 'spelling_bee', 'antonym', 'synonym', 'word_scramble', 'flashcards', 'khmer_consonants', 'riddles_khmer', 'proverbs_quiz', 'picture_matching']);
            $gamesList[] = [
                'folder' => $item,
                'name' => $displayName,
                'url' => "../games/{$item}/index.html",
                'has_words' => $hasWords
            ];
        }
    }
}

// Fetch Students / Scores from database
$students = [];
$totalStudents = 0;
if ($db) {
    try {
        $stmt = $db->query("SELECT * FROM `students` ORDER BY `id` DESC");
        $students = $stmt->fetchAll();
        $totalStudents = count($students);
    } catch (Exception $e) {}
}

// Fetch Admins
$admins = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT `id`, `username`, `email`, `full_name`, `role`, `created_at` FROM `admins` ORDER BY `id` ASC");
        $admins = $stmt->fetchAll();
    } catch (Exception $e) {}
}
if (empty($admins)) {
    $admins = [
        ['id' => 1, 'username' => 'admin', 'full_name' => 'Super Admin', 'email' => 'admin@gameskid.local', 'role' => 'admin', 'created_at' => date('Y-m-d H:i:s')]
    ];
}
?>
<!DOCTYPE html>
<html lang="km">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ផ្ទាំងគ្រប់គ្រង & កែប្រែហ្គេម | CK Education Admin Dashboard</title>
  <link rel="shortcut icon" href="../icon/icon.png" type="image/png">
  <link rel="stylesheet" href="../assets/theme.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;600;700&family=Moul&family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --sidebar-width: 260px;
      --primary-blue: #1e40af;
      --primary-gradient: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      --bg-dashboard: #f1f5f9;
      --card-bg: #ffffff;
      --card-border: rgba(226, 232, 240, 0.8);
      --text-main: #1e293b;
      --text-muted: #64748b;
      --admin-primary: #4f46e5;
      --admin-accent: #06b6d4;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Kantumruy Pro', 'Battambang', sans-serif;
      background-color: var(--bg-dashboard);
      color: var(--text-main);
      min-height: 100vh;
      overflow-x: hidden;
      transition: background-color 0.3s ease;
    }

    .font-moul {
      font-family: 'Moul', cursive;
    }

    /* Font Weight Customization */
    body.font-regular {
      font-weight: 400 !important;
    }
    body.font-regular h1,
    body.font-regular h2,
    body.font-regular h3,
    body.font-regular h4,
    body.font-regular span,
    body.font-regular p,
    body.font-regular div,
    body.font-regular button,
    body.font-regular a,
    body.font-regular input {
      font-weight: 400;
    }
    body.font-regular .font-bold {
      font-weight: 500 !important;
    }
    body.font-regular .font-moul {
      font-family: 'Moul', cursive !important;
    }

    /* App Layout */
    .app-layout {
      display: flex;
      min-height: 100vh;
      width: 100%;
      position: relative;
    }

    /* Sidebar */
    .sidebar {
      width: var(--sidebar-width);
      background: #ffffff;
      border-right: 1px solid #e2e8f0;
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0;
      bottom: 0;
      left: 0;
      z-index: 1000;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s ease;
      box-shadow: 4px 0 20px rgba(0, 0, 0, 0.03);
    }

    body.sidebar-collapsed .sidebar {
      transform: translateX(-100%);
    }

    body.sidebar-collapsed .main-content {
      margin-left: 0;
    }

    .sidebar-brand {
      padding: 14px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #f1f5f9;
    }

    .brand-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .brand-icon {
      width: 38px;
      height: 38px;
      object-fit: contain;
      filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.1));
    }

    .brand-info h2 {
      font-family: 'Moul', cursive;
      font-size: 0.9rem;
      color: #1e3a8a;
      line-height: 1.2;
    }

    .brand-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 0.65rem;
      font-weight: 700;
      color: #4338ca;
      background: #e0e7ff;
      padding: 1px 6px;
      border-radius: 999px;
      margin-top: 2px;
    }

    .brand-badge::before {
      content: '';
      width: 5px;
      height: 5px;
      background: #6366f1;
      border-radius: 50%;
      animation: pulsePurple 2s infinite;
    }

    @keyframes pulsePurple {
      0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7); }
      70% { box-shadow: 0 0 0 6px rgba(99, 102, 241, 0); }
      100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
    }

    .sidebar-toggle-close {
      background: #f1f5f9;
      border: none;
      width: 30px;
      height: 30px;
      border-radius: 8px;
      color: #64748b;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }

    .sidebar-toggle-close:hover {
      background: #e2e8f0;
      color: #1e293b;
    }

    .sidebar-menu {
      flex: 1;
      padding: 10px 10px;
      overflow-y: auto;
    }

    .menu-section-title {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: #94a3b8;
      padding: 8px 10px 4px;
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 12px;
      border-radius: 10px;
      color: #475569;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s ease;
      margin-bottom: 2px;
      cursor: pointer;
      border: 1px solid transparent;
    }

    .menu-item i {
      font-size: 1rem;
      width: 20px;
      text-align: center;
    }

    .menu-item:hover {
      background: #f8fafc;
      color: #2563eb;
      transform: translateX(2px);
    }

    .menu-item.active {
      background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
      color: #ffffff;
      box-shadow: 0 6px 12px -3px rgba(79, 70, 229, 0.4);
    }

    .menu-item.active i {
      color: #ffffff;
    }

    .menu-count {
      margin-left: auto;
      font-size: 0.7rem;
      padding: 1px 7px;
      border-radius: 999px;
      background: #f1f5f9;
      color: #475569;
      font-weight: 700;
    }

    .menu-item.active .menu-count {
      background: rgba(255, 255, 255, 0.25);
      color: #ffffff;
    }

    .sidebar-footer {
      padding: 12px 14px;
      border-top: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      gap: 10px;
      background: #fafafa;
    }

    .admin-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #4f46e5 0%, #ec4899 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 0.88rem;
      font-weight: 700;
    }

    .admin-info {
      flex: 1;
      overflow: hidden;
    }

    .admin-name {
      font-size: 0.82rem;
      font-weight: 700;
      color: #1e293b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .admin-role {
      font-size: 0.7rem;
      color: #6366f1;
      font-weight: 600;
    }

    .btn-logout-icon {
      background: none;
      border: none;
      color: #94a3b8;
      cursor: pointer;
      padding: 6px;
      font-size: 1.1rem;
      transition: color 0.2s;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .btn-logout-icon:hover {
      color: #ef4444;
      transform: scale(1.1);
    }

    /* Main Content */
    .main-content {
      margin-left: var(--sidebar-width);
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Top Sticky Navbar */
    .top-navbar {
      background: #ffffff;
      padding: 8px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      position: sticky;
      top: 0;
      z-index: 900;
      border-bottom: 1px solid #e2e8f0;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    }

    .nav-left {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
      max-width: 520px;
    }

    .sidebar-toggle-btn {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      width: 36px;
      height: 36px;
      border-radius: 9px;
      color: #334155;
      font-size: 1.05rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      flex-shrink: 0;
    }

    .sidebar-toggle-btn:hover {
      background: #4f46e5;
      color: white;
      border-color: #4f46e5;
    }

    .search-box-wrapper {
      position: relative;
      width: 100%;
      display: flex;
      align-items: center;
      background: #f8fafc;
      border: 1.5px solid #cbd5e1;
      border-radius: 12px;
      padding: 0 10px 0 14px;
      gap: 10px;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .search-box-wrapper:focus-within {
      background: #ffffff;
      border-color: #4f46e5;
      box-shadow: 0 0 0 3.5px rgba(79, 70, 229, 0.15);
    }

    .search-icon {
      color: #64748b;
      font-size: 0.95rem;
      flex-shrink: 0;
    }

    .search-box {
      width: 100%;
      flex: 1;
      min-width: 0;
      background: transparent !important;
      border: none !important;
      outline: none !important;
      padding: 9px 0 !important;
      font-size: 0.88rem;
      line-height: 1.5;
      color: #1e293b;
      font-family: 'Kantumruy Pro', 'Battambang', sans-serif;
    }

    .search-clear-btn {
      background: transparent;
      border: none;
      color: #94a3b8;
      font-size: 1rem;
      cursor: pointer;
      padding: 4px;
      display: none;
    }

    .search-clear-btn.visible {
      display: flex;
    }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .nav-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 0.8rem;
      font-weight: 600;
      border: 1px solid #e2e8f0;
      background: #ffffff;
      color: #334155;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .nav-pill:hover {
      background: #f8fafc;
      border-color: #cbd5e1;
    }

    .nav-pill-admin {
      background: #eef2ff;
      border-color: #c7d2fe;
      color: #4338ca;
      font-weight: 700;
    }

    .nav-pill-admin:hover {
      background: #e0e7ff;
      color: #3730a3;
    }

    /* Dashboard Body */
    .dashboard-body {
      padding: 16px 22px 40px;
      flex: 1;
    }

    /* Welcome Banner */
    .welcome-banner {
      background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #4f46e5 100%);
      border-radius: 16px;
      padding: 22px 28px;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      margin-bottom: 20px;
      box-shadow: 0 10px 25px -5px rgba(30, 58, 138, 0.25);
      position: relative;
      overflow: hidden;
    }

    .welcome-banner::after {
      content: '';
      position: absolute;
      right: -30px;
      top: -30px;
      width: 180px;
      height: 180px;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 50%;
      pointer-events: none;
    }

    .welcome-text h1 {
      font-size: 1.25rem;
      font-family: 'Moul', cursive;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .welcome-text p {
      font-size: 0.88rem;
      opacity: 0.9;
      max-width: 680px;
      line-height: 1.5;
    }

    .welcome-date-badge {
      background: rgba(255, 255, 255, 0.18);
      backdrop-filter: blur(8px);
      padding: 8px 16px;
      border-radius: 12px;
      font-size: 0.82rem;
      font-weight: 700;
      white-space: nowrap;
      display: flex;
      align-items: center;
      gap: 8px;
      border: 1px solid rgba(255, 255, 255, 0.25);
    }

    /* Stat Cards Grid */
    .stat-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: #ffffff;
      border-radius: 16px;
      padding: 16px 20px;
      border: 1px solid var(--card-border);
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
    }

    .stat-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10px;
    }

    .stat-title {
      font-size: 0.85rem;
      font-weight: 700;
      color: #64748b;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .stat-icon-badge {
      font-size: 1.4rem;
    }

    .stat-value {
      font-size: 1.4rem;
      font-weight: 800;
      color: #1e293b;
      margin-bottom: 6px;
    }

    .stat-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.75rem;
      font-weight: 600;
      color: #64748b;
    }

    .card-gradient-green { border-left: 4px solid #10b981; }
    .card-gradient-blue { border-left: 4px solid #3b82f6; }
    .card-gradient-purple { border-left: 4px solid #8b5cf6; }
    .card-gradient-orange { border-left: 4px solid #f97316; }
    .card-gradient-indigo { border-left: 4px solid #6366f1; }

    /* Category Filter Bar */
    .category-filter-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      overflow-x: auto;
      padding: 4px 2px 14px;
      margin-bottom: 16px;
    }

    .cat-filter-btn {
      padding: 8px 16px;
      border-radius: 999px;
      font-size: 0.84rem;
      font-weight: 700;
      border: 1px solid #cbd5e1;
      background: #ffffff;
      color: #475569;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .cat-filter-btn:hover {
      background: #f8fafc;
      border-color: #94a3b8;
      color: #1e293b;
    }

    .cat-filter-btn.active {
      background: #4f46e5;
      color: #ffffff;
      border-color: #4f46e5;
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }

    /* Admin Quick Action Banner */
    .admin-quick-bar {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 12px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }

    .admin-quick-links {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .btn-quick-admin {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 0.8rem;
      font-weight: 700;
      text-decoration: none;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-quick-admin.primary {
      background: #4f46e5;
      color: #ffffff;
    }
    .btn-quick-admin.primary:hover {
      background: #4338ca;
    }

    .btn-quick-admin.secondary {
      background: #f1f5f9;
      color: #334155;
      border-color: #e2e8f0;
    }
    .btn-quick-admin.secondary:hover {
      background: #e2e8f0;
      color: #0f172a;
    }

    /* Games Catalog & Grid */
    .category-section {
      margin-bottom: 28px;
    }

    .category-label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 1.05rem;
      font-weight: 800;
      color: #1e3a8a;
      margin-bottom: 14px;
      padding: 4px 0;
      cursor: pointer;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
    }

    /* GAME CARD - Same as User with Admin Actions Toolbar */
    .game-card {
      background: #ffffff;
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 16px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }

    .game-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
      border-color: #cbd5e1;
    }

    .game-card .icon {
      font-size: 2.2rem;
      margin-bottom: 10px;
    }

    .game-card h3 {
      font-size: 1rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 6px;
      line-height: 1.3;
    }

    .game-card p {
      font-size: 0.8rem;
      color: #64748b;
      line-height: 1.4;
      margin-bottom: 14px;
      flex-grow: 1;
    }

    /* Game Card Admin Toolbar */
    .game-card-admin-toolbar {
      display: flex;
      align-items: center;
      gap: 6px;
      padding-top: 10px;
      border-top: 1px dashed #e2e8f0;
      flex-wrap: wrap;
    }

    .btn-card-action {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      padding: 5px 10px;
      border-radius: 8px;
      font-size: 0.74rem;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
    }

    .btn-card-play {
      background: #10b981;
      color: #ffffff;
      flex: 1;
    }
    .btn-card-play:hover {
      background: #059669;
    }

    .btn-card-edit {
      background: #4f46e5;
      color: #ffffff;
      flex: 1;
    }
    .btn-card-edit:hover {
      background: #4338ca;
    }

    .btn-card-words {
      background: #f59e0b;
      color: #ffffff;
      padding: 5px 8px;
    }
    .btn-card-words:hover {
      background: #d97706;
    }

    .btn-card-media {
      background: #06b6d4;
      color: #ffffff;
      padding: 5px 8px;
    }
    .btn-card-media:hover {
      background: #0891b2;
    }

    /* Notification Banner */
    .alert-box {
      padding: 12px 18px;
      border-radius: 12px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.88rem;
      font-weight: 600;
    }

    .alert-box.success {
      background: #ecfdf5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }

    .alert-box.danger {
      background: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    /* Modals & Overlays */
    .admin-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(6px);
      z-index: 2000;
      align-items: center;
      justify-content: center;
      padding: 16px;
      animation: modalFadeIn 0.2s ease;
    }

    .admin-modal.show {
      display: flex;
    }

    @keyframes modalFadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .admin-modal-box {
      background: #ffffff;
      border-radius: 20px;
      max-width: 950px;
      width: 100%;
      max-height: 90vh;
      display: flex;
      flex-direction: column;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      border: 1px solid #e2e8f0;
      overflow: hidden;
    }

    .admin-modal-box.large {
      max-width: 1200px;
      height: 90vh;
    }

    .admin-modal-header {
      padding: 16px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #e2e8f0;
      background: #f8fafc;
    }

    .admin-modal-title {
      font-size: 1.05rem;
      font-weight: 800;
      color: #1e293b;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .admin-modal-close {
      background: #f1f5f9;
      border: none;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      font-size: 1.1rem;
      color: #64748b;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }

    .admin-modal-close:hover {
      background: #ef4444;
      color: white;
    }

    .admin-modal-body {
      padding: 20px 24px;
      overflow-y: auto;
      flex: 1;
    }

    /* Live Code Editor Styling */
    .editor-layout {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
      height: 100%;
    }

    .editor-layout.split-view {
      grid-template-columns: 1fr 1fr;
    }

    .editor-controls {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;
      gap: 10px;
      flex-wrap: wrap;
    }

    .editor-select {
      padding: 8px 14px;
      border-radius: 10px;
      border: 1.5px solid #cbd5e1;
      font-size: 0.85rem;
      font-weight: 700;
      background: #ffffff;
      color: #1e293b;
      outline: none;
    }

    .editor-textarea {
      width: 100%;
      height: 480px;
      background: #0f172a;
      color: #67e8f9;
      font-family: 'Consolas', 'Courier New', monospace;
      font-size: 13px;
      line-height: 1.5;
      padding: 16px;
      border-radius: 12px;
      border: 1px solid #334155;
      outline: none;
      resize: vertical;
      white-space: pre;
      tab-size: 4;
    }

    .preview-iframe {
      width: 100%;
      height: 480px;
      border-radius: 12px;
      border: 1px solid #cbd5e1;
      background: #ffffff;
    }

    /* Word Editor Chips */
    .grade-tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }

    .grade-tab-btn {
      padding: 6px 14px;
      border-radius: 10px;
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      color: #475569;
      font-weight: 700;
      font-size: 0.82rem;
      cursor: pointer;
      transition: all 0.2s;
    }

    .grade-tab-btn.active {
      background: #4f46e5;
      color: #ffffff;
      border-color: #4f46e5;
    }

    .words-chip-container {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      max-height: 360px;
      overflow-y: auto;
      padding: 12px;
      background: #f8fafc;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
    }

    .word-chip {
      background: #e0e7ff;
      border: 1px solid #c7d2fe;
      color: #3730a3;
      padding: 5px 10px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn-del-word {
      color: #ef4444;
      cursor: pointer;
      border: none;
      background: none;
      font-size: 0.8rem;
    }

    /* Image Gallery Grid */
    .image-gallery-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 14px;
      max-height: 420px;
      overflow-y: auto;
      padding: 4px;
    }

    .image-card-thumb {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      overflow: hidden;
      background: #ffffff;
      display: flex;
      flex-direction: column;
    }

    .image-preview-box {
      height: 100px;
      background: #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 6px;
    }

    .image-preview-box img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }

    .image-details {
      padding: 8px 10px;
      font-size: 0.75rem;
    }

    .image-details-name {
      font-weight: 700;
      color: #1e293b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Tables */
    .data-table-wrap {
      overflow-x: auto;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
    }

    table.admin-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
      text-align: left;
    }

    table.admin-table th {
      padding: 10px 14px;
      background: #f8fafc;
      color: #64748b;
      font-weight: 700;
      border-bottom: 1px solid #e2e8f0;
    }

    table.admin-table td {
      padding: 10px 14px;
      border-bottom: 1px solid #f1f5f9;
      color: #334155;
    }

    /* Dark Mode */
    body.dark-mode {
      --bg-dashboard: #0b0f19;
      --card-bg: #1e293b;
      --card-border: rgba(255, 255, 255, 0.08);
      --text-main: #f8fafc;
      --text-muted: #94a3b8;
    }

    body.dark-mode .sidebar {
      background: #0f172a;
      border-color: rgba(255, 255, 255, 0.08);
    }

    body.dark-mode .sidebar-brand,
    body.dark-mode .sidebar-footer {
      border-color: rgba(255, 255, 255, 0.08);
      background: #0f172a;
    }

    body.dark-mode .top-navbar {
      background: #0f172a;
      border-color: rgba(255, 255, 255, 0.08);
    }

    body.dark-mode .search-box-wrapper {
      background: #1e293b;
      border-color: #334155;
    }

    body.dark-mode .search-box {
      color: #f8fafc;
    }

    body.dark-mode .stat-card,
    body.dark-mode .game-card,
    body.dark-mode .admin-quick-bar,
    body.dark-mode .admin-modal-box {
      background: #1e293b;
      border-color: rgba(255, 255, 255, 0.08);
    }

    body.dark-mode .stat-value,
    body.dark-mode .game-card h3,
    body.dark-mode .admin-name,
    body.dark-mode .admin-modal-title,
    body.dark-mode .image-details-name {
      color: #f8fafc;
    }

    body.dark-mode .admin-modal-header {
      background: #0f172a;
      border-color: rgba(255, 255, 255, 0.08);
    }

    body.dark-mode .cat-filter-btn,
    body.dark-mode .nav-pill,
    body.dark-mode .btn-quick-admin.secondary {
      background: #1e293b;
      border-color: #334155;
      color: #cbd5e1;
    }

    body.dark-mode .cat-filter-btn.active {
      background: #4f46e5;
      color: #ffffff;
    }

    /* Responsive */
    @media (max-width: 900px) {
      .sidebar {
        transform: translateX(-100%);
      }
      body.sidebar-open .sidebar {
        transform: translateX(0);
      }
      .main-content {
        margin-left: 0;
      }
    }
  </style>
</head>

<body>
  <div class="app-layout">

    <!-- Left Navigation Sidebar -->
    <aside class="sidebar" id="appSidebar">
      <div class="sidebar-brand">
        <div class="brand-left">
          <img src="../icon/icon.png" alt="CK Education" class="brand-icon">
          <div class="brand-info">
            <h2>CK ADMIN</h2>
            <span class="brand-badge">ADMIN • CONTROL</span>
          </div>
        </div>
        <button class="sidebar-toggle-close" onclick="toggleSidebar()" title="បិទ/លាក់ Sidebar">
          <i class="fa-solid fa-angles-left"></i>
        </button>
      </div>

      <nav class="sidebar-menu">
        <div class="menu-section-title">ផ្ទាំងគ្រប់គ្រង / DASHBOARD</div>
        <a href="javascript:void(0)" class="menu-item active" id="menu-overview" onclick="switchMainTab('catalog', this)">
          <i class="fa-solid fa-chart-pie" style="color: #4f46e5;"></i>
          <span>បញ្ជីហ្គេម & ទូទៅ</span>
          <span class="menu-count"><?php echo count($gamesList); ?></span>
        </a>

        <div class="menu-section-title">កែប្រែហ្គេម & ខ្លឹមសារ / EDITORS</div>
        <a href="javascript:void(0)" class="menu-item" onclick="openLiveCodeEditor('khmer_word_collection')">
          <i class="fa-solid fa-code" style="color: #06b6d4;"></i>
          <span>កែកូដហ្គេម (Live Code)</span>
          <span class="menu-count">All</span>
        </a>
        <a href="javascript:void(0)" class="menu-item" onclick="openWordEditorModal('khmer_word_collection')">
          <i class="fa-solid fa-spell-check" style="color: #10b981;"></i>
          <span>កែពាក្យខ្មែរ (Vocab)</span>
          <span class="menu-count">Word</span>
        </a>
        <a href="javascript:void(0)" class="menu-item" onclick="openMediaManagerModal()">
          <i class="fa-solid fa-images" style="color: #f59e0b;"></i>
          <span>រូបភាពហ្គេម (Media)</span>
          <span class="menu-count">Photos</span>
        </a>

        <div class="menu-section-title">ទិន្នន័យ & ប្រព័ន្ធ / SYSTEM</div>
        <a href="javascript:void(0)" class="menu-item" onclick="openScoresModal()">
          <i class="fa-solid fa-trophy" style="color: #eab308;"></i>
          <span>ពិន្ទុសិស្ស (Scores)</span>
          <span class="menu-count"><?php echo $totalStudents; ?></span>
        </a>
        <a href="javascript:void(0)" class="menu-item" onclick="openAdminsModal()">
          <i class="fa-solid fa-user-shield" style="color: #ec4899;"></i>
          <span>គណនី & ពាក្យសម្ងាត់</span>
          <span class="menu-count"><?php echo count($admins); ?></span>
        </a>
        <a href="javascript:void(0)" class="menu-item" onclick="openDatabaseModal()">
          <i class="fa-solid fa-database" style="color: #8b5cf6;"></i>
          <span>Database Engine</span>
          <span class="menu-count">MySQL</span>
        </a>

        <div class="menu-section-title">ប្រភេទហ្គេម / CATEGORIES</div>
        <a href="#cat-math-sec" class="menu-item" onclick="filterCategory('math', true)">
          <i class="fa-solid fa-calculator" style="color: #ef4444;"></i>
          <span>គណិតវិទ្យា (Math)</span>
          <span class="menu-count">១៦</span>
        </a>
        <a href="#cat-khmer-sec" class="menu-item" onclick="filterCategory('khmer', true)">
          <i class="fa-solid fa-font" style="color: #10b981;"></i>
          <span>ភាសាខ្មែរ (Khmer)</span>
          <span class="menu-count">១៣</span>
        </a>
        <a href="#cat-tools-sec" class="menu-item" onclick="filterCategory('tools', true)">
          <i class="fa-solid fa-toolbox" style="color: #0ea5e9;"></i>
          <span>ឧបករណ៍គ្រូ (Tools)</span>
          <span class="menu-count">១៦</span>
        </a>
        <a href="#cat-brain-sec" class="menu-item" onclick="filterCategory('brain', true)">
          <i class="fa-solid fa-brain" style="color: #a855f7;"></i>
          <span>ហ្គេមខួរក្បាល (Brain)</span>
          <span class="menu-count">៩</span>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="admin-avatar">
          <?php echo strtoupper(substr($currentAdmin['username'], 0, 1)); ?>
        </div>
        <div class="admin-info">
          <div class="admin-name"><?php echo htmlspecialchars($currentAdmin['full_name']); ?></div>
          <div class="admin-role">@<?php echo htmlspecialchars($currentAdmin['username']); ?> • Admin</div>
        </div>
        <a href="logout.php" class="btn-logout-icon" title="ចាកចេញ (Logout)">
          <i class="fa-solid fa-right-from-bracket"></i>
        </a>
      </div>
    </aside>

    <!-- Main Content Panel -->
    <main class="main-content" id="dashboard-top">

      <!-- Top Sticky Navigation Bar -->
      <header class="top-navbar">
        <div class="nav-left">
          <button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="បើក/បិទ របារចំហៀង">
            <i class="fa-solid fa-bars"></i>
          </button>

          <div class="search-box-wrapper">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" id="globalSearchInput" class="search-box" placeholder="ស្វែងរកហ្គេមដើម្បីលេង ឬកែសម្រួលកូដ..." oninput="handleSearch(this.value)" autocomplete="off">
            <button type="button" class="search-clear-btn" id="searchClearBtn" onclick="clearSearch()" title="Clear">
              <i class="fa-solid fa-circle-xmark"></i>
            </button>
          </div>
        </div>

        <div class="nav-right">
          <!-- Student View Link -->
          <a href="../index.html" target="_blank" class="nav-pill nav-pill-admin" title="មើលផ្ទាំងសិស្ស">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
            <span>មើលជាសិស្ស (Student View)</span>
          </a>

          <!-- Telegram Contact -->
          <a href="https://t.me/mrneangny" target="_blank" class="nav-pill" title="Telegram">
            <i class="fa-brands fa-telegram" style="color: #2AABEE;"></i>
            <span>Telegram</span>
          </a>

          <!-- Font Weight Toggle -->
          <button class="nav-pill" onclick="toggleFontWeight()" title="Bold/Regular">
            <i class="fa-solid fa-bold"></i>
            <span id="fontWeightNavText">ដិត</span>
          </button>

          <!-- Theme Mode Toggle -->
          <button class="nav-pill" onclick="toggleTheme()" title="ប្តូររចនាបថ យប់/ថ្ងៃ">
            <i class="fa-solid fa-sun" id="themeIcon"></i>
          </button>

          <!-- Logout Button -->
          <a href="logout.php" class="nav-pill" style="color: #ef4444; border-color: #fecaca;" title="ចាកចេញ">
            <i class="fa-solid fa-power-off"></i>
            <span>ចាកចេញ</span>
          </a>
        </div>
      </header>

      <!-- Dashboard Body -->
      <div class="dashboard-body">

        <?php if (!empty($message)): ?>
          <div class="alert-box <?php echo $messageType; ?>">
            <i class="fa-solid fa-circle-info"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
          </div>
        <?php endif; ?>

        <div id="ajaxAlertContainer"></div>

        <!-- Welcome Banner -->
        <section class="welcome-banner">
          <div class="welcome-text">
            <h1>
              <span>🪁</span>
              <span>ផ្ទាំងគ្រប់គ្រង & កែប្រែហ្គេម — GamesKid Portal</span>
            </h1>
            <p>គ្រប់គ្រង កែសម្រួលកូដ ខ្លឹមសារពាក្យ និងរូបភាព នៃល្បែងសិក្សាទាំង ៦០+ មុខ សម្រាប់កុមារ និងលោកគ្រូអ្នកគ្រូខ្មែរ។</p>
          </div>
          <div class="welcome-date-badge">
            <i class="fa-regular fa-calendar-check text-yellow-300"></i>
            <span id="currentDateDisplay">Admin Mode • 2026</span>
          </div>
        </section>

        <!-- Admin Quick Actions Bar -->
        <div class="admin-quick-bar">
          <div style="font-weight: 700; font-size: 0.88rem; color: #1e293b; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-wand-magic-sparkles" style="color: #4f46e5;"></i>
            <span>ឧបករណ៍រហ័ស (Quick Tools):</span>
          </div>
          <div class="admin-quick-links">
            <button class="btn-quick-admin primary" onclick="openLiveCodeEditor('khmer_word_collection')">
              <i class="fa-solid fa-code"></i> Live Code Editor
            </button>
            <button class="btn-quick-admin secondary" onclick="openWordEditorModal('khmer_word_collection')">
              <i class="fa-solid fa-spell-check"></i> កែពាក្យខ្មែរ
            </button>
            <button class="btn-quick-admin secondary" onclick="openMediaManagerModal()">
              <i class="fa-solid fa-cloud-arrow-up"></i> Upload រូបភាព
            </button>
            <button class="btn-quick-admin secondary" onclick="openScoresModal()">
              <i class="fa-solid fa-trophy"></i> តារាងពិន្ទុ (<?php echo $totalStudents; ?>)
            </button>
            <button class="btn-quick-admin secondary" onclick="openAdminsModal()">
              <i class="fa-solid fa-key"></i> ប្តូរ Password
            </button>
          </div>
        </div>

        <!-- Category Stat Cards Grid -->
        <section class="stat-cards-grid">
          <!-- Math -->
          <div class="stat-card card-gradient-green" onclick="filterCategory('math', true)">
            <div class="stat-card-header">
              <span class="stat-title"><i class="fa-solid fa-calculator" style="color:#10b981;"></i> គណិតវិទ្យា</span>
              <div class="stat-icon-badge">🧮</div>
            </div>
            <div class="stat-value">១៦ ហ្គេម</div>
            <div class="stat-footer">
              <span>សរុប ១៦ មុខ</span>
              <span style="color:#10b981;"><i class="fa-solid fa-check"></i> Can Edit All</span>
            </div>
          </div>

          <!-- Khmer -->
          <div class="stat-card card-gradient-blue" onclick="filterCategory('khmer', true)">
            <div class="stat-card-header">
              <span class="stat-title"><i class="fa-solid fa-book-open" style="color:#3b82f6;"></i> ភាសាខ្មែរ</span>
              <div class="stat-icon-badge">🔤</div>
            </div>
            <div class="stat-value">១៣ ហ្គេម</div>
            <div class="stat-footer">
              <span>សរុប ១៣ មុខ</span>
              <span style="color:#3b82f6;"><i class="fa-solid fa-pen"></i> Vocab & Code</span>
            </div>
          </div>

          <!-- Tools -->
          <div class="stat-card card-gradient-purple" onclick="filterCategory('tools', true)">
            <div class="stat-card-header">
              <span class="stat-title"><i class="fa-solid fa-toolbox" style="color:#8b5cf6;"></i> ឧបករណ៍គ្រូ</span>
              <div class="stat-icon-badge">🧰</div>
            </div>
            <div class="stat-value">១៦ ឧបករណ៍</div>
            <div class="stat-footer">
              <span>សរុប ១៦ មុខ</span>
              <span style="color:#8b5cf6;"><i class="fa-solid fa-sliders"></i> Full Access</span>
            </div>
          </div>

          <!-- Brain -->
          <div class="stat-card card-gradient-orange" onclick="filterCategory('brain', true)">
            <div class="stat-card-header">
              <span class="stat-title"><i class="fa-solid fa-brain" style="color:#f97316;"></i> ហ្គេមខួរក្បាល</span>
              <div class="stat-icon-badge">🧠</div>
            </div>
            <div class="stat-value">៩ ហ្គេម</div>
            <div class="stat-footer">
              <span>សរុប ៩ មុខ</span>
              <span style="color:#f97316;"><i class="fa-solid fa-gamepad"></i> Logic & Puzzles</span>
            </div>
          </div>
        </section>

        <!-- Category Capsule Bar -->
        <nav class="category-filter-bar">
          <button class="cat-filter-btn active" data-target="all" onclick="filterCategory('all', true)">
            <span>🌟 ទាំងអស់ (<?php echo count($gamesList); ?>)</span>
          </button>
          <button class="cat-filter-btn" data-target="math" onclick="filterCategory('math', true)">
            <span>🧮 គណិតវិទ្យា (១៦)</span>
          </button>
          <button class="cat-filter-btn" data-target="khmer" onclick="filterCategory('khmer', true)">
            <span>🔤 ភាសាខ្មែរ (១៣)</span>
          </button>
          <button class="cat-filter-btn" data-target="tools" onclick="filterCategory('tools', true)">
            <span>🧰 ឧបករណ៍គ្រូ (១៦)</span>
          </button>
          <button class="cat-filter-btn" data-target="brain" onclick="filterCategory('brain', true)">
            <span>🧠 ហ្គេមខួរក្បាល (៩)</span>
          </button>
        </nav>

        <!-- ALL GAMES CATALOG (Same Cards as User with Admin Toolbar) -->
        <div id="gamesCatalogContainer">

          <!-- 1. Math Games Category -->
          <div class="category-section" data-cat="math" id="cat-math-sec">
            <span class="category-label" onclick="filterCategory('math', true)">
              <i class="cat-icon">🧮</i> <span>គណិតវិទ្យា (Mathematics)</span>
            </span>
            <div class="grid">
              <?php
              $mathGames = [
                ['folder' => 'fraction', 'icon' => '🍕', 'title' => 'ល្បែងប្រភាគ', 'desc' => 'រៀនប្រភាគតាមរយៈការលាបពណ៌រង្វង់នំខេក។'],
                ['folder' => 'fast_math', 'icon' => '⚡', 'title' => 'បូក-ដកលេខរហ័ស', 'desc' => 'គិតលេខឱ្យលឿនមុនពេលអស់ម៉ោង។'],
                ['folder' => 'multiplication', 'icon' => '✖️', 'title' => 'មេគុណ និងចែក', 'desc' => 'ហ្វឹកហាត់តារាងគុណ និងចែកសម្រាប់កុមារ។'],
                ['folder' => 'clock', 'icon' => '⏰', 'title' => 'ល្បែងទាយម៉ោង', 'desc' => 'រៀនមើលម៉ោង និងនាទីឱ្យបានត្រឹមត្រូវ។'],
                ['folder' => 'currency', 'icon' => '💵', 'title' => 'ស្គាល់លុយរៀល', 'desc' => 'រៀនរាប់ និងគិតលុយជារូបិយប័ណ្ណខ្មែរ។'],
                ['folder' => 'geometry', 'icon' => '🔺', 'title' => 'រាងធរណីមាត្រ', 'desc' => 'ស្គាល់រាងផ្សេងៗដូចជា ត្រីកោណ ចតុកោណ។'],
                ['folder' => 'even_odd', 'icon' => '🔢', 'title' => 'លេខគូ-សេស', 'desc' => 'ញែកឱ្យដាច់រវាងលេខគូ និងលេខសេស។'],
                ['folder' => 'comparison', 'icon' => '⚖️', 'title' => 'ប្រៀបធៀបលេខ', 'desc' => 'ជ្រើសរើសសញ្ញាប្រៀបធៀបត្រឹមត្រូវ តិច ស្មើ ឬ ច្រើន។'],
                ['folder' => 'missing_number', 'icon' => '❓', 'title' => 'រកលេខដែលបាត់', 'desc' => 'ស្វែងរកលេខដែលបាត់ក្នុងលំដាប់លេខ។'],
                ['folder' => 'number_ordering', 'icon' => '🔢', 'title' => 'តម្រៀបលេខ', 'desc' => 'ហ្វឹកហាត់តម្រៀបលេខពីតូចទៅធំ ឬពីធំទៅតូច។'],
                ['folder' => 'spinning_wheel', 'icon' => '🎡', 'title' => 'ល្បែងកង់លេខ', 'desc' => 'បោះគ្រាប់ឃ្លីចូលក្នុងខ្ទង់រាយ និងខ្ទង់ដប់។'],
                ['folder' => 'math_quiz_grade', 'icon' => '📐', 'title' => 'លំហាត់គណិត ថ្នាក់ទី១-៦', 'desc' => 'ធ្វើលំហាត់គណិតវិទ្យាតាមកម្រិតថ្នាក់ទី១ ដល់ទី៦។'],
                ['folder' => 'unit_conversion', 'icon' => '📏', 'title' => 'ការបម្លែងខ្នាត', 'desc' => 'រៀនបម្លែងខ្នាតប្រវែង ម៉ាស់ និងចំណុះ។'],
                ['folder' => 'roman_numbers', 'icon' => '🏛️', 'title' => 'លេខរ៉ូម៉ាំង និងលេខខ្មែរ', 'desc' => 'ស្គាល់ និងបម្លែងលេខខ្មែរ ជាមួយលេខរ៉ូម៉ាំង។'],
                ['folder' => 'area_perimeter', 'icon' => '📐', 'title' => 'ផ្ទៃក្រឡា និងបរិមាត្រ', 'desc' => 'គណនាបរិមាត្រ និងផ្ទៃក្រឡារាងផ្សេងៗ។'],
                ['folder' => 'math_word_problems', 'icon' => '📖', 'title' => 'ចំណោទគណិតវិទ្យា', 'desc' => 'ដោះស្រាយចំណោទគណិតវិទ្យាថ្នាក់បឋម។'],
              ];
              foreach ($mathGames as $g): ?>
                <div class="game-card" data-game="<?php echo $g['folder']; ?>" data-name="<?php echo strtolower($g['title'] . ' ' . $g['folder']); ?>">
                  <div>
                    <div class="icon"><?php echo $g['icon']; ?></div>
                    <h3><?php echo htmlspecialchars($g['title']); ?></h3>
                    <p><?php echo htmlspecialchars($g['desc']); ?></p>
                  </div>
                  <div class="game-card-admin-toolbar">
                    <a href="../games/<?php echo $g['folder']; ?>/index.html" target="_blank" class="btn-card-action btn-card-play" title="Play Game">
                      <i class="fa-solid fa-play"></i> លេង
                    </a>
                    <button class="btn-card-action btn-card-edit" onclick="openLiveCodeEditor('<?php echo $g['folder']; ?>')" title="កែកូដហ្គេមផ្ទាល់">
                      <i class="fa-solid fa-code"></i> កែកូដ
                    </button>
                    <button class="btn-card-action btn-card-media" onclick="openMediaManagerModal()" title="រូបភាព">
                      <i class="fa-solid fa-image"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- 2. Khmer Language Category -->
          <div class="category-section" data-cat="khmer" id="cat-khmer-sec">
            <span class="category-label" onclick="filterCategory('khmer', true)">
              <i class="cat-icon">🔤</i> <span>ភាសាខ្មែរ (Khmer Language)</span>
            </span>
            <div class="grid">
              <?php
              $khmerGames = [
                ['folder' => 'picture_matching', 'icon' => '🖼️', 'title' => 'ផ្គូផ្គងរូបភាព', 'desc' => 'រៀនពាក្យខ្មែរតាមរយៈរូបភាព។', 'has_words' => true],
                ['folder' => 'word_scramble', 'icon' => '🔠', 'title' => 'តម្រៀបពាក្យ ឬល្បះ', 'desc' => 'រៀបអក្សរឱ្យចេញជាពាក្យត្រឹមត្រូវ។', 'has_words' => true],
                ['folder' => 'word_spinner', 'icon' => '🎡', 'title' => 'កងបង្វិលចាប់ពាក្យ', 'desc' => 'បង្វិលកងដើម្បីជ្រើសរើសពាក្យដោយចៃដន្យ។', 'has_words' => true],
                ['folder' => 'hangman', 'icon' => '📝', 'title' => 'ទាយពាក្យ', 'desc' => 'ទាយអក្សរដើម្បីបំពេញពាក្យឱ្យបានត្រឹមត្រូវ។', 'has_words' => true],
                ['folder' => 'flashcards', 'icon' => '📇', 'title' => 'បណ្ណពាក្យ', 'desc' => 'កាតពាក្យ (Flashcards) សម្រាប់ទន្ទេញមេរៀន។', 'has_words' => true],
                ['folder' => 'match_pairs', 'icon' => '🔗', 'title' => 'អូសខ្សែភ្ជាប់', 'desc' => 'ស្វែងរកគូ អូសខ្សែភ្ជាប់ពាក្យទៅនឹងរូបភាព។', 'has_words' => true],
                ['folder' => 'tts', 'icon' => '🔊', 'title' => 'អានអត្ថបទ', 'desc' => 'វាយអត្ថបទ ហើយឱ្យកម្មវិធីអានជាសំឡេង។', 'has_words' => false],
                ['folder' => 'khmer_consonants', 'icon' => '🔤', 'title' => 'ព្យញ្ជនៈ និងស្រៈខ្មែរ', 'desc' => 'រៀន ស្គាល់ និងទន្ទេញព្យញ្ជនៈ ស្រៈនិស្ស័យខ្មែរ។', 'has_words' => true],
                ['folder' => 'synonym', 'icon' => '🟰', 'title' => 'ពាក្យដូច', 'desc' => 'ស្វែងរកពាក្យន័យដូច។', 'has_words' => true],
                ['folder' => 'antonym', 'icon' => '❌', 'title' => 'ពាក្យផ្ទុយ', 'desc' => 'ស្វែងរកពាក្យន័យផ្ទុយ។', 'has_words' => true],
                ['folder' => 'grammar_parts', 'icon' => '📚', 'title' => 'ថ្នាក់ពាក្យក្នុងភាសាខ្មែរ', 'desc' => 'ស្គាល់នាម កិរិយាស័ព្ទ និងគុណនាម។', 'has_words' => true],
                ['folder' => 'proverbs_quiz', 'icon' => '📜', 'title' => 'សុភាសិត និងកម្រងពាក្យ', 'desc' => 'បំពេញ និងស្វែងយល់ន័យសុភាសិតខ្មែរ។', 'has_words' => true],
                ['folder' => 'spelling_bee', 'icon' => '✍️', 'title' => 'ល្បែងអក្ខរាវិរុទ្ធខ្មែរ', 'desc' => 'ជ្រើសរើសពាក្យដែលសរសេរត្រូវតាមអក្ខរាវិរុទ្ធ។', 'has_words' => true],
              ];
              foreach ($khmerGames as $g): ?>
                <div class="game-card" data-game="<?php echo $g['folder']; ?>" data-name="<?php echo strtolower($g['title'] . ' ' . $g['folder']); ?>">
                  <div>
                    <div class="icon"><?php echo $g['icon']; ?></div>
                    <h3><?php echo htmlspecialchars($g['title']); ?></h3>
                    <p><?php echo htmlspecialchars($g['desc']); ?></p>
                  </div>
                  <div class="game-card-admin-toolbar">
                    <a href="../games/<?php echo $g['folder']; ?>/index.html" target="_blank" class="btn-card-action btn-card-play" title="Play Game">
                      <i class="fa-solid fa-play"></i> លេង
                    </a>
                    <button class="btn-card-action btn-card-edit" onclick="openLiveCodeEditor('<?php echo $g['folder']; ?>')" title="កែកូដហ្គេម">
                      <i class="fa-solid fa-code"></i> កែកូដ
                    </button>
                    <?php if (!empty($g['has_words'])): ?>
                      <button class="btn-card-action btn-card-words" onclick="openWordEditorModal('<?php echo $g['folder']; ?>')" title="កែពាក្យ">
                        <i class="fa-solid fa-spell-check"></i> ពាក្យ
                      </button>
                    <?php endif; ?>
                    <button class="btn-card-action btn-card-media" onclick="openMediaManagerModal()" title="រូបភាព">
                      <i class="fa-solid fa-image"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- 3. Teacher Tools Category -->
          <div class="category-section" data-cat="tools" id="cat-tools-sec">
            <span class="category-label" onclick="filterCategory('tools', true)">
              <i class="cat-icon">🧰</i> <span>ឧបករណ៍គ្រូ (Teacher Tools)</span>
            </span>
            <div class="grid">
              <?php
              $toolGames = [
                ['folder' => 'exercise_card', 'icon' => '🎴', 'title' => 'បង្កើតលំហាត់ស្វ័យប្រវត្តិ', 'desc' => 'បង្កើតកាតលំហាត់ទំហំកាតសិស្ស (8.56cm x 5.4cm) សម្រាប់បោះពុម្ព។'],
                ['folder' => 'flashcard_maker', 'icon' => '🏷️', 'title' => 'បង្កើតប័ណ្ណពាក្យ', 'desc' => 'បង្កើតប័ណ្ណពាក្យ Flashcards ទំហំ A4 អាចប្តូរពណ៌ ស៊ុម PDF/PNG។'],
                ['folder' => 'certificate_maker', 'icon' => '📜', 'title' => 'បង្កើតប័ណ្ណសរសើរ', 'desc' => 'បង្កើតប័ណ្ណសរសើរស្អាតៗសម្រាប់សិស្សពូកែ ទំហំ A4។'],
                ['folder' => 'lesson_plans', 'icon' => '📖', 'title' => 'បង្កើតកិច្ចតែងការបង្រៀន', 'desc' => 'បង្កើតកិច្ចតែងការបង្រៀនដោយស្វ័យប្រវត្តិ។'],
                ['folder' => 'name_spinner', 'icon' => '👤', 'title' => 'កងបង្វិលចាប់ឈ្មោះ', 'desc' => 'បង្វិលកងដើម្បីហៅឈ្មោះសិស្ស។'],
                ['folder' => 'group_maker', 'icon' => '👥', 'title' => 'រៀបសិស្សជាក្រុម', 'desc' => 'បែងចែកសិស្សជាក្រុមដោយស្វ័យប្រវត្តិ។'],
                ['folder' => 'random_number', 'icon' => '🎲', 'title' => 'ចាប់លេខចៃដន្យ', 'desc' => 'ចាប់លេខរៀងដោយចៃដន្យ (Randomizer)។'],
                ['folder' => 'timer', 'icon' => '⏱️', 'title' => 'នាឡិកាកំណត់ម៉ោង', 'desc' => 'កំណត់ពេលធ្វើលំហាត់ក្នុងថ្នាក់។'],
                ['folder' => 'scoreboard', 'icon' => '🏆', 'title' => 'តារាងពិន្ទុក្រុម', 'desc' => 'កត់ត្រា និងបូកពិន្ទុសម្រាប់ក្រុមនីមួយៗ។'],
                ['folder' => 'whiteboard', 'icon' => '✏️', 'title' => 'ក្តារខៀនឌីជីថល', 'desc' => 'គូររូប និងសរសេរពន្យល់នៅលើក្តារ។'],
                ['folder' => 'database', 'icon' => '🗄️', 'title' => 'បញ្ជីចុះឈ្មោះសិស្ស', 'desc' => 'គ្រប់គ្រងបញ្ជីឈ្មោះ និងទាញយកជា Excel។'],
                ['folder' => 'student_id_card', 'icon' => '🪪', 'title' => 'បង្កើតប័ណ្ណសិស្ស', 'desc' => 'បង្កើតប័ណ្ណសម្គាល់ខ្លួនសិស្ស និងទាញយកជា PDF។'],
                ['folder' => 'seating_chart', 'icon' => '🪑', 'title' => 'រៀបចំកន្លែងអង្គុយសិស្ស', 'desc' => 'រៀបចំ និងបែងចែកតុរៀនសិស្សក្នុងថ្នាក់។'],
                ['folder' => 'create_qr', 'icon' => '📱', 'title' => 'បង្កើត QR Code', 'desc' => 'បង្កើត QR Code ស្អាតៗសម្រាប់តំណភ្ជាប់ ឬអត្ថបទ។'],
                ['folder' => 'notebook_cover', 'icon' => '📔', 'title' => 'បង្កើតក្របសៀវភៅសរសេរ', 'desc' => 'រចនាក្របសៀវភៅសរសេរសិស្សស្អាតៗ PDF/រូបភាព។'],
                ['folder' => 'khmer_word_collection', 'icon' => '📚', 'title' => 'បណ្ដុំពាក្យភាសាខ្មែរ ទី១-ទី៦', 'desc' => 'បណ្ដុំពាក្យគន្លឹះភាសាខ្មែរថ្នាក់ទី១ ដល់ ទី៦ សម្រាប់រៀន និងទាញយក។', 'has_words' => true],
              ];
              foreach ($toolGames as $g): ?>
                <div class="game-card" data-game="<?php echo $g['folder']; ?>" data-name="<?php echo strtolower($g['title'] . ' ' . $g['folder']); ?>">
                  <div>
                    <div class="icon"><?php echo $g['icon']; ?></div>
                    <h3><?php echo htmlspecialchars($g['title']); ?></h3>
                    <p><?php echo htmlspecialchars($g['desc']); ?></p>
                  </div>
                  <div class="game-card-admin-toolbar">
                    <a href="../games/<?php echo $g['folder']; ?>/index.html" target="_blank" class="btn-card-action btn-card-play" title="Open Tool">
                      <i class="fa-solid fa-arrow-up-right-from-square"></i> បើក
                    </a>
                    <button class="btn-card-action btn-card-edit" onclick="openLiveCodeEditor('<?php echo $g['folder']; ?>')" title="កែកូដ">
                      <i class="fa-solid fa-code"></i> កែកូដ
                    </button>
                    <?php if (!empty($g['has_words'])): ?>
                      <button class="btn-card-action btn-card-words" onclick="openWordEditorModal('<?php echo $g['folder']; ?>')" title="កែពាក្យ">
                        <i class="fa-solid fa-spell-check"></i> ពាក្យ
                      </button>
                    <?php endif; ?>
                    <button class="btn-card-action btn-card-media" onclick="openMediaManagerModal()" title="រូបភាព">
                      <i class="fa-solid fa-image"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- 4. Brain Games Category -->
          <div class="category-section" data-cat="brain" id="cat-brain-sec">
            <span class="category-label" onclick="filterCategory('brain', true)">
              <i class="cat-icon">🧠</i> <span>ហ្គេមខួរក្បាល (Brain Games & Logic)</span>
            </span>
            <div class="grid">
              <?php
              $brainGames = [
                ['folder' => 'memory', 'icon' => '🧠', 'title' => 'ល្បែងចងចាំ', 'desc' => 'បើកកាតផ្គូផ្គងរូប និងពាក្យដើម្បីហ្វឹកហាត់ការចងចាំ។'],
                ['folder' => 'simon_says', 'icon' => '🌈', 'title' => 'ស្គាល់ និងចងចាំពណ៌', 'desc' => 'រៀនស្គាល់ពណ៌ និងចងចាំពណ៌តាមលំដាប់ឱ្យបានត្រឹមត្រូវ។'],
                ['folder' => 'sliding_puzzle', 'icon' => '🧩', 'title' => 'ល្បែងតម្រៀបប្រអប់លេខ', 'desc' => 'អូសតម្រៀបប្រអប់លេខពីតូចទៅធំឱ្យត្រូវតាមលំដាប់។'],
                ['folder' => 'maze', 'icon' => '🌀', 'title' => 'ល្បែងស្វែងរកច្រកចេញ', 'desc' => 'បញ្ជាតួអង្គដើរតាមផ្លូវវង្វេងដើម្បីទៅដល់គោលដៅ។'],
                ['folder' => 'sudoku', 'icon' => '🔢', 'title' => 'សុដូកូកុមារ 4x4', 'desc' => 'តម្រៀបរូបភាព ឬលេខ ៤x៤ ដោយមិនឱ្យជាន់គ្នាក្នុងជួរ។'],
                ['folder' => 'drawing', 'icon' => '💡', 'title' => 'គូររូបភាព គំនូរ', 'desc' => 'រៀនគូររូបភាពលេងកម្សាន្ត និងអាចទាញរូបភាពបាន។'],
                ['folder' => 'puzzle_image', 'icon' => '🖼', 'title' => 'ល្បែងផ្គុំរូបភាព', 'desc' => 'ផ្គុំរូបភាពឱ្យបានត្រឹមត្រូវតាមការគិត ការចងចាំ។'],
                ['folder' => 'pattern_logic', 'icon' => '🧩', 'title' => 'តក្កវិទ្យា និងលំដាប់រូបភាព', 'desc' => 'ទាយរូបភាព ឬលំដាប់បន្ទាប់តាមតក្កវិទ្យា។'],
                ['folder' => 'riddles_khmer', 'icon' => '❓', 'title' => 'ទាយប្រស្នាខ្មែរ', 'desc' => 'ដោះស្រាយប្រស្នាប្រដៅ និងពាក្យបណ្តៅខ្មែរ។', 'has_words' => true],
              ];
              foreach ($brainGames as $g): ?>
                <div class="game-card" data-game="<?php echo $g['folder']; ?>" data-name="<?php echo strtolower($g['title'] . ' ' . $g['folder']); ?>">
                  <div>
                    <div class="icon"><?php echo $g['icon']; ?></div>
                    <h3><?php echo htmlspecialchars($g['title']); ?></h3>
                    <p><?php echo htmlspecialchars($g['desc']); ?></p>
                  </div>
                  <div class="game-card-admin-toolbar">
                    <a href="../games/<?php echo $g['folder']; ?>/index.html" target="_blank" class="btn-card-action btn-card-play" title="Play Game">
                      <i class="fa-solid fa-play"></i> លេង
                    </a>
                    <button class="btn-card-action btn-card-edit" onclick="openLiveCodeEditor('<?php echo $g['folder']; ?>')" title="កែកូដហ្គេម">
                      <i class="fa-solid fa-code"></i> កែកូដ
                    </button>
                    <?php if (!empty($g['has_words'])): ?>
                      <button class="btn-card-action btn-card-words" onclick="openWordEditorModal('<?php echo $g['folder']; ?>')" title="កែពាក្យ">
                        <i class="fa-solid fa-spell-check"></i> ពាក្យ
                      </button>
                    <?php endif; ?>
                    <button class="btn-card-action btn-card-media" onclick="openMediaManagerModal()" title="រូបភាព">
                      <i class="fa-solid fa-image"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div>

      </div>

      <!-- Site Footer -->
      <footer style="padding: 16px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 0.8rem; color: #64748b; margin-top: auto;">
        <span>🎮 CK Education Admin Dashboard — គ្រប់គ្រង & កែប្រែហ្គេមទាំងអស់ដោយសេរី ❤️ រៀបរៀងដោយ៖ លោកគ្រូ នាង នី</span>
      </footer>

    </main>

  </div>

  <!-- ==================== MODAL 1: LIVE CODE & CONTENT EDITOR ==================== -->
  <div class="admin-modal" id="liveCodeModal">
    <div class="admin-modal-box large">
      <div class="admin-modal-header">
        <div class="admin-modal-title">
          <i class="fa-solid fa-code" style="color: #4f46e5;"></i>
          <span>កម្មវិធីកែសម្រួលកូដហ្គេមផ្ទាល់ (Live Game Code & Source Editor)</span>
        </div>
        <button class="admin-modal-close" onclick="closeLiveCodeModal()">×</button>
      </div>
      <div class="admin-modal-body">
        <div class="editor-controls">
          <div style="display: flex; align-items: center; gap: 8px;">
            <label style="font-size: 0.82rem; font-weight: 700; color: #64748b;">ជ្រើសរើសហ្គេម៖</label>
            <select id="codeModalGameSelect" class="editor-select" onchange="loadCodeForSelectedGame(this.value)">
              <?php foreach ($gamesList as $g): ?>
                <option value="<?php echo htmlspecialchars($g['folder']); ?>">
                  <?php echo htmlspecialchars($g['name']); ?> (<?php echo htmlspecialchars($g['folder']); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="display: flex; align-items: center; gap: 8px;">
            <button class="btn-card-action" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;" onclick="toggleEditorSplitView()" id="btnSplitView">
              <i class="fa-solid fa-columns"></i> បើក Live Preview
            </button>
            <a href="#" id="codeModalOpenExternalBtn" target="_blank" class="btn-card-action" style="background: #e0e7ff; color: #4338ca;">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> លេងហ្គេមនេះ
            </a>
            <button class="btn-card-action" style="background: #10b981; color: white; padding: 6px 14px; font-size: 0.82rem;" onclick="saveModalGameCode()">
              <i class="fa-solid fa-floppy-disk"></i> រក្សាទុក (Save Code)
            </button>
          </div>
        </div>

        <div style="font-size: 0.76rem; color: #64748b; margin-bottom: 10px; display: flex; justify-content: space-between;">
          <div>ផ្លូវឯកសារ៖ <span id="codeModalFilePath" style="font-family: monospace; color: #4f46e5; font-weight: 700;">games/khmer_word_collection/index.html</span></div>
          <div id="codeModalMetaInfo">-</div>
        </div>

        <div class="editor-layout" id="editorLayoutBox">
          <div>
            <textarea id="codeModalTextarea" class="editor-textarea" spellcheck="false" placeholder="កំពុងទាញយកកូដ..."></textarea>
          </div>
          <div id="editorPreviewCol" style="display: none;">
            <iframe id="editorPreviewIframe" class="preview-iframe" src="about:blank"></iframe>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== MODAL 2: KHMER WORD & VOCABULARY EDITOR ==================== -->
  <div class="admin-modal" id="wordEditorModal">
    <div class="admin-modal-box">
      <div class="admin-modal-header">
        <div class="admin-modal-title">
          <i class="fa-solid fa-spell-check" style="color: #10b981;"></i>
          <span id="wordEditorModalTitle">កែសម្រួលពាក្យខ្មែរ (Khmer Vocabulary Editor)</span>
        </div>
        <button class="admin-modal-close" onclick="closeWordEditorModal()">×</button>
      </div>
      <div class="admin-modal-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
          <!-- Grade tabs -->
          <div class="grade-tabs" style="margin-bottom: 0;">
            <button class="grade-tab-btn active" onclick="selectModalWordGrade(1, this)">ថ្នាក់ទី១ (<span id="mcount-g1">0</span>)</button>
            <button class="grade-tab-btn" onclick="selectModalWordGrade(2, this)">ថ្នាក់ទី២ (<span id="mcount-g2">0</span>)</button>
            <button class="grade-tab-btn" onclick="selectModalWordGrade(3, this)">ថ្នាក់ទី៣ (<span id="mcount-g3">0</span>)</button>
            <button class="grade-tab-btn" onclick="selectModalWordGrade(4, this)">ថ្នាក់ទី៤ (<span id="mcount-g4">0</span>)</button>
            <button class="grade-tab-btn" onclick="selectModalWordGrade(5, this)">ថ្នាក់ទី៥ (<span id="mcount-g5">0</span>)</button>
            <button class="grade-tab-btn" onclick="selectModalWordGrade(6, this)">ថ្នាក់ទី៦ (<span id="mcount-g6">0</span>)</button>
          </div>

          <button class="btn-card-action" style="background: #10b981; color: white; padding: 7px 16px;" onclick="saveModalWordsData()">
            <i class="fa-solid fa-floppy-disk"></i> រក្សាទុកចូលហ្គេម (Save)
          </button>
        </div>

        <!-- Add tools -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
          <div style="background: #f8fafc; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">
              <i class="fa-solid fa-plus-circle" style="color: #10b981;"></i> បន្ថែមពាក្យមួយៗ
            </label>
            <div style="display: flex; gap: 6px;">
              <input type="text" id="modalInputSingleWord" placeholder="បញ្ចូលពាក្យ..." style="flex:1; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem;" onkeydown="if(event.key==='Enter') addModalSingleWord()">
              <button class="btn-card-action" style="background: #4f46e5; color: white;" onclick="addModalSingleWord()">+ បន្ថែម</button>
            </div>
          </div>

          <div style="background: #f8fafc; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">
              <i class="fa-solid fa-layer-group" style="color: #6366f1;"></i> បន្ថែមពាក្យជាដុំ (Bulk Add)
            </label>
            <div style="display: flex; gap: 6px;">
              <input type="text" id="modalInputBulkWords" placeholder="ពាក្យ១, ពាក្យ២, ពាក្យ៣..." style="flex:1; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem;" onkeydown="if(event.key==='Enter') addModalBulkWords()">
              <button class="btn-card-action" style="background: #334155; color: white;" onclick="addModalBulkWords()">បញ្ចូលដុំ</button>
            </div>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.8rem; color: #64748b;">
          <div>ពាក្យក្នុង <strong id="modalActiveGradeLabel" style="color: #1e293b;">ថ្នាក់ទី១</strong>៖ <span id="modalActiveGradeTotal" style="font-weight: 700; color: #4f46e5;">0</span> ពាក្យ</div>
          <input type="text" id="modalFilterWordsInput" placeholder="ស្វែងរកក្នុងថ្នាក់នេះ..." onkeyup="filterModalWordsDisplay()" style="padding: 4px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.78rem;">
        </div>

        <div class="words-chip-container" id="modalWordsContainer">
          <div style="color: #64748b; padding: 20px; text-align: center; width: 100%;">កំពុងផ្ទុក...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== MODAL 3: MEDIA & IMAGE MANAGER ==================== -->
  <div class="admin-modal" id="mediaManagerModal">
    <div class="admin-modal-box">
      <div class="admin-modal-header">
        <div class="admin-modal-title">
          <i class="fa-solid fa-images" style="color: #f59e0b;"></i>
          <span>គ្រប់គ្រង និងបន្ថែមរូបភាព (Game Image Assets Manager)</span>
        </div>
        <button class="admin-modal-close" onclick="closeMediaManagerModal()">×</button>
      </div>
      <div class="admin-modal-body">
        <!-- Upload Form -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
          <form id="modalUploadImageForm" onsubmit="handleModalImageUpload(event)">
            <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: flex-end;">
              <div>
                <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">ជ្រើសរើសរូបភាព (PNG, JPG, SVG, WEBP)</label>
                <input type="file" id="modalImgFileInput" name="image_file" accept="image/*" required style="font-size: 0.8rem; width: 100%;">
              </div>
              <div>
                <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">ថតគោលដៅ (Folder)</label>
                <select name="target_folder" class="editor-select" style="width: 100%; padding: 6px;">
                  <option value="photo">/photo/ (រូបភាព)</option>
                  <option value="icon">/icon/ (Icons)</option>
                  <option value="assets">/assets/ (Assets)</option>
                </select>
              </div>
              <button type="submit" class="btn-card-action" id="btnModalUploadImg" style="background: #4f46e5; color: white; padding: 8px 16px; height: 36px;">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload
              </button>
            </div>
          </form>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">រូបភាពទាំងអស់ក្នុងប្រព័ន្ធ</div>
          <button class="btn-card-action" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;" onclick="loadModalImagesList()">
            <i class="fa-solid fa-rotate"></i> Refresh
          </button>
        </div>

        <div class="image-gallery-grid" id="modalImagesGrid">
          <div style="color: #64748b; padding: 20px; text-align: center; width: 100%;">កំពុងផ្ទុក...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== MODAL 4: STUDENT SCORES LEADERBOARD ==================== -->
  <div class="admin-modal" id="scoresModal">
    <div class="admin-modal-box">
      <div class="admin-modal-header">
        <div class="admin-modal-title">
          <i class="fa-solid fa-trophy" style="color: #eab308;"></i>
          <span>កំណត់ត្រា & ពិន្ទុសិស្ស (Student Scores Leaderboard)</span>
        </div>
        <button class="admin-modal-close" onclick="closeScoresModal()">×</button>
      </div>
      <div class="admin-modal-body">
        <!-- Add Score Form -->
        <form method="POST" action="index.php" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
          <input type="hidden" name="action" value="add_student">
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)) auto; gap: 8px; align-items: flex-end;">
            <div>
              <label style="font-size: 0.75rem; font-weight: 700; color: #475569;">ឈ្មោះសិស្ស *</label>
              <input type="text" name="name" required placeholder="ឧ. សុខា" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.8rem;">
            </div>
            <div>
              <label style="font-size: 0.75rem; font-weight: 700; color: #475569;">ថ្នាក់</label>
              <input type="text" name="cls" placeholder="ឧ. 3A" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.8rem;">
            </div>
            <div>
              <label style="font-size: 0.75rem; font-weight: 700; color: #475569;">ពិន្ទុ</label>
              <input type="text" name="score" placeholder="ឧ. 95" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.8rem;">
            </div>
            <div>
              <label style="font-size: 0.75rem; font-weight: 700; color: #475569;">ហ្គេម</label>
              <select name="game_name" class="editor-select" style="width: 100%; padding: 6px; font-size: 0.78rem;">
                <option value="General">General / All</option>
                <?php foreach ($gamesList as $g): ?>
                  <option value="<?php echo htmlspecialchars($g['folder']); ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn-card-action" style="background: #10b981; color: white; height: 34px; padding: 0 14px;">
              <i class="fa-solid fa-plus"></i> Add
            </button>
          </div>
        </form>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">កំណត់ត្រាសរុប៖ <?php echo count($students); ?> នាក់</div>
          <form method="POST" action="index.php" onsubmit="return confirm('លុបកំណត់ត្រាទាំងអស់?');">
            <input type="hidden" name="action" value="clear_students">
            <button type="submit" class="btn-card-action" style="background: #fef2f2; color: #ef4444; border: 1px solid #fecaca;">
              <i class="fa-solid fa-trash"></i> Clear All
            </button>
          </form>
        </div>

        <div class="data-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>ឈ្មោះសិស្ស</th>
                <th>ថ្នាក់</th>
                <th>ហ្គេម</th>
                <th>ពិន្ទុ</th>
                <th>កាលបរិច្ឆេទ</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($students)): ?>
                <tr><td colspan="7" style="text-align: center; color: #64748b; padding: 24px;">ពុំទាន់មានកំណត់ត្រាពិន្ទុសិស្សក្នុង Database ទេ។</td></tr>
              <?php else: ?>
                <?php foreach ($students as $s): ?>
                  <tr>
                    <td>#<?php echo $s['id']; ?></td>
                    <td style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><span style="background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;"><?php echo htmlspecialchars($s['cls']); ?></span></td>
                    <td><?php echo htmlspecialchars($s['game_name']); ?></td>
                    <td style="font-weight: 800; color: #d97706;"><?php echo htmlspecialchars($s['score']); ?></td>
                    <td style="font-size: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($s['created_at']); ?></td>
                    <td>
                      <form method="POST" action="index.php" onsubmit="return confirm('លុបកំណត់ត្រានេះ?');" style="display:inline;">
                        <input type="hidden" name="action" value="delete_student">
                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                        <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer;" title="លុប"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== MODAL 5: ADMIN ACCOUNTS & SECURITY ==================== -->
  <div class="admin-modal" id="adminsModal">
    <div class="admin-modal-box">
      <div class="admin-modal-header">
        <div class="admin-modal-title">
          <i class="fa-solid fa-user-shield" style="color: #ec4899;"></i>
          <span>គណនី Admin & សុវត្ថិភាព (Admin Accounts & Password)</span>
        </div>
        <button class="admin-modal-close" onclick="closeAdminsModal()">×</button>
      </div>
      <div class="admin-modal-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
          <!-- Change Password -->
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px;">
            <div style="font-weight: 700; font-size: 0.85rem; color: #1e293b; margin-bottom: 10px;">
              <i class="fa-solid fa-key" style="color: #4f46e5;"></i> ប្តូរពាក្យសម្ងាត់ផ្ទាល់ខ្លួន
            </div>
            <form method="POST" action="index.php">
              <input type="hidden" name="action" value="change_password">
              <div style="margin-bottom: 8px;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #64748b;">ពាក្យសម្ងាត់បច្ចុប្បន្ន *</label>
                <input type="password" name="old_password" required style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.8rem;">
              </div>
              <div style="margin-bottom: 8px;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #64748b;">ពាក្យសម្ងាត់ថ្មី (យ៉ាងតិច ៦ តួ) *</label>
                <input type="password" name="new_password" required style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.8rem;">
              </div>
              <div style="margin-bottom: 12px;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #64748b;">ផ្ទៀងផ្ទាត់ពាក្យសម្ងាត់ថ្មី *</label>
                <input type="password" name="confirm_password" required style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.8rem;">
              </div>
              <button type="submit" class="btn-card-action" style="background: #4f46e5; color: white; width: 100%; padding: 8px;">
                <i class="fa-solid fa-check"></i> រក្សាទុកពាក្យសម្ងាត់ថ្មី
              </button>
            </form>
          </div>

          <!-- Create Admin -->
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px;">
            <div style="font-weight: 700; font-size: 0.85rem; color: #1e293b; margin-bottom: 10px;">
              <i class="fa-solid fa-user-plus" style="color: #10b981;"></i> បង្កើតគណនី Admin បន្ថែម
            </div>
            <form method="POST" action="index.php">
              <input type="hidden" name="action" value="add_admin">
              <div style="margin-bottom: 8px;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #64748b;">Username *</label>
                <input type="text" name="new_username" required style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.8rem;">
              </div>
              <div style="margin-bottom: 8px;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #64748b;">Password *</label>
                <input type="password" name="new_password" required style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.8rem;">
              </div>
              <div style="margin-bottom: 12px;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #64748b;">Full Name</label>
                <input type="text" name="new_fullname" placeholder="ឧ. លោកគ្រូ នាង នី" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.8rem;">
              </div>
              <button type="submit" class="btn-card-action" style="background: #10b981; color: white; width: 100%; padding: 8px;">
                <i class="fa-solid fa-plus"></i> បង្កើតគណនីថ្មី
              </button>
            </form>
          </div>
        </div>

        <div style="font-weight: 700; font-size: 0.85rem; color: #1e293b; margin-bottom: 8px;">គណនី Admin ក្នុងប្រព័ន្ធ</div>
        <div class="data-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Role</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admins as $adm): ?>
                <tr>
                  <td>#<?php echo $adm['id']; ?></td>
                  <td style="font-weight: 700; color: #4f46e5;">@<?php echo htmlspecialchars($adm['username']); ?></td>
                  <td><?php echo htmlspecialchars($adm['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($adm['email'] ?? '-'); ?></td>
                  <td><span style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;"><?php echo htmlspecialchars($adm['role']); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== MODAL 6: DATABASE STATUS ==================== -->
  <div class="admin-modal" id="databaseModal">
    <div class="admin-modal-box">
      <div class="admin-modal-header">
        <div class="admin-modal-title">
          <i class="fa-solid fa-database" style="color: #8b5cf6;"></i>
          <span>ស្ថានភាព Database Engine</span>
        </div>
        <button class="admin-modal-close" onclick="closeDatabaseModal()">×</button>
      </div>
      <div class="admin-modal-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px;">
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px;">
            <div style="font-size: 0.75rem; color: #64748b;">Database Host</div>
            <div style="font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-top: 4px;">127.0.0.1:3306 (MySQL)</div>
          </div>
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px;">
            <div style="font-size: 0.75rem; color: #64748b;">Active Database</div>
            <div style="font-size: 0.95rem; font-weight: 700; color: #4f46e5; margin-top: 4px;">gameskid</div>
          </div>
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px;">
            <div style="font-size: 0.75rem; color: #64748b;">Database Status</div>
            <div style="font-size: 0.95rem; font-weight: 700; color: <?php echo $db ? '#10b981' : '#f59e0b'; ?>; margin-top: 4px;">
              <i class="fa-solid <?php echo $db ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
              <?php echo $db ? 'Connected (MySQL XAMPP)' : 'Standby Mode'; ?>
            </div>
          </div>
        </div>

        <div style="background: #f1f5f9; padding: 14px; border-radius: 10px; font-size: 0.82rem; color: #475569; line-height: 1.6;">
          <strong>SQL Files:</strong>
          <ul style="padding-left: 20px; margin-top: 6px;">
            <li><code>admin/database.sql</code> (Tables: admins, students, game_logs)</li>
            <li><code>admin/database.php</code> (Auto database initializer & connection pool)</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- JAVASCRIPT LOGIC -->
  <script>
    // State
    let activeEditorGame = 'khmer_word_collection';
    let isSplitViewActive = false;
    let modalWordsData = { 1: [], 2: [], 3: [], 4: [], 5: [], 6: [] };
    let modalActiveGrade = 1;
    let activeWordGame = 'khmer_word_collection';

    // Sidebar In/Out Toggle
    function toggleSidebar() {
      document.body.classList.toggle('sidebar-collapsed');
      document.body.classList.toggle('sidebar-open');
    }

    // Theme Mode Toggle
    function toggleTheme() {
      document.body.classList.toggle('dark-mode');
      const isDark = document.body.classList.contains('dark-mode');
      localStorage.setItem('ck_theme_mode', isDark ? 'dark' : 'light');
      const icon = document.getElementById('themeIcon');
      if (icon) {
        icon.className = isDark ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
      }
    }

    // Font Weight Toggle
    function toggleFontWeight() {
      document.body.classList.toggle('font-regular');
      const isRegular = document.body.classList.contains('font-regular');
      localStorage.setItem('ck_font_weight', isRegular ? 'regular' : 'bold');
      const text = document.getElementById('fontWeightNavText');
      if (text) text.innerText = isRegular ? 'ធម្មតា' : 'ដិត';
    }

    // Initialize Preferences
    (function initPreferences() {
      if (localStorage.getItem('ck_theme_mode') === 'dark') {
        document.body.classList.add('dark-mode');
        const icon = document.getElementById('themeIcon');
        if (icon) icon.className = 'fa-solid fa-moon';
      }
      if (localStorage.getItem('ck_font_weight') === 'regular') {
        document.body.classList.add('font-regular');
        const text = document.getElementById('fontWeightNavText');
        if (text) text.innerText = 'ធម្មតា';
      }
    })();

    // Search Filter
    function handleSearch(query) {
      const q = (query || '').trim().toLowerCase();
      const clearBtn = document.getElementById('searchClearBtn');
      if (clearBtn) {
        clearBtn.classList.toggle('visible', q.length > 0);
      }

      const cards = document.querySelectorAll('.game-card');
      cards.forEach(c => {
        const title = c.getAttribute('data-name') || '';
        const match = title.includes(q);
        c.style.display = match ? 'flex' : 'none';
      });
    }

    function clearSearch() {
      const input = document.getElementById('globalSearchInput');
      if (input) {
        input.value = '';
        handleSearch('');
      }
    }

    // Category Filter
    function filterCategory(cat, scroll = false) {
      document.querySelectorAll('.cat-filter-btn').forEach(b => {
        b.classList.toggle('active', b.getAttribute('data-target') === cat);
      });

      const sections = document.querySelectorAll('.category-section');
      sections.forEach(s => {
        if (cat === 'all' || s.getAttribute('data-cat') === cat) {
          s.style.display = 'block';
        } else {
          s.style.display = 'none';
        }
      });

      if (scroll && cat !== 'all') {
        const targetSec = document.querySelector(`.category-section[data-cat="${cat}"]`);
        if (targetSec) targetSec.scrollIntoView({ behavior: 'smooth' });
      }
    }

    // Alert helper
    function showAjaxAlert(msg, type = 'success') {
      const c = document.getElementById('ajaxAlertContainer');
      c.innerHTML = `
        <div class="alert-box ${type}">
          <i class="fa-solid fa-${type === 'success' ? 'circle-check' : 'circle-exclamation'}"></i>
          <span>${msg}</span>
        </div>
      `;
      setTimeout(() => { c.innerHTML = ''; }, 5000);
    }

    // ==================== 1. LIVE CODE EDITOR MODAL ====================
    function openLiveCodeEditor(gameFolder) {
      activeEditorGame = gameFolder || 'khmer_word_collection';
      const modal = document.getElementById('liveCodeModal');
      const select = document.getElementById('codeModalGameSelect');
      if (select) select.value = activeEditorGame;

      modal.classList.add('show');
      loadCodeForSelectedGame(activeEditorGame);
    }

    function closeLiveCodeModal() {
      document.getElementById('liveCodeModal').classList.remove('show');
    }

    function loadCodeForSelectedGame(gameFolder) {
      activeEditorGame = gameFolder;
      const textarea = document.getElementById('codeModalTextarea');
      const pathLabel = document.getElementById('codeModalFilePath');
      const metaLabel = document.getElementById('codeModalMetaInfo');
      const extBtn = document.getElementById('codeModalOpenExternalBtn');

      pathLabel.innerText = `games/${gameFolder}/index.html`;
      extBtn.href = `../games/${gameFolder}/index.html`;
      textarea.value = 'កំពុងទាញយកកូដ...';

      fetch(`api.php?action=get_game_code&game=${gameFolder}`)
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            textarea.value = data.code;
            const kb = (data.size / 1024).toFixed(1);
            metaLabel.innerText = `ទំហំ៖ ${kb} KB | កែប្រែចុងក្រោយ៖ ${data.last_modified}`;
            if (isSplitViewActive) {
              updatePreviewIframe();
            }
          } else {
            textarea.value = data.message || 'ពុំអាចទាញយកកូដបាន';
          }
        })
        .catch(err => {
          textarea.value = 'Error: ' + err.message;
        });
    }

    function toggleEditorSplitView() {
      isSplitViewActive = !isSplitViewActive;
      const layout = document.getElementById('editorLayoutBox');
      const previewCol = document.getElementById('editorPreviewCol');
      const btn = document.getElementById('btnSplitView');

      if (isSplitViewActive) {
        layout.classList.add('split-view');
        previewCol.style.display = 'block';
        btn.style.background = '#4f46e5';
        btn.style.color = '#ffffff';
        updatePreviewIframe();
      } else {
        layout.classList.remove('split-view');
        previewCol.style.display = 'none';
        btn.style.background = '#f1f5f9';
        btn.style.color = '#334155';
      }
    }

    function updatePreviewIframe() {
      const iframe = document.getElementById('editorPreviewIframe');
      iframe.src = `../games/${activeEditorGame}/index.html?t=` + Date.now();
    }

    function saveModalGameCode() {
      const code = document.getElementById('codeModalTextarea').value;
      showAjaxAlert(`កំពុងរក្សាទុកកូដហ្គេម "${activeEditorGame}"...`, 'success');

      fetch('api.php?action=save_game_code', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ game: activeEditorGame, code: code })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showAjaxAlert(data.message, 'success');
          if (isSplitViewActive) updatePreviewIframe();
        } else {
          showAjaxAlert(data.message, 'danger');
        }
      })
      .catch(err => {
        showAjaxAlert('Error saving code: ' + err.message, 'danger');
      });
    }

    // ==================== 2. KHMER WORD EDITOR MODAL ====================
    function openWordEditorModal(gameName) {
      activeWordGame = gameName || 'khmer_word_collection';
      document.getElementById('wordEditorModalTitle').innerText = `កែសម្រួលពាក្យក្នុងហ្គេម: ${activeWordGame}`;
      document.getElementById('wordEditorModal').classList.add('show');
      loadModalGameWords(activeWordGame);
    }

    function closeWordEditorModal() {
      document.getElementById('wordEditorModal').classList.remove('show');
    }

    function loadModalGameWords(gameName) {
      const container = document.getElementById('modalWordsContainer');
      container.innerHTML = '<div style="color: #64748b; padding: 20px; text-align: center; width: 100%;"><i class="fa-solid fa-spinner fa-spin mr-2"></i> កំពុងទាញយកពាក្យ...</div>';

      fetch(`api.php?action=get_game_words&game=${gameName}`)
        .then(res => res.json())
        .then(data => {
          if (data.success && data.data) {
            modalWordsData = data.data;
            updateModalGradeCounters();
            renderModalWordsForActiveGrade();
          } else {
            container.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center; width: 100%;">${data.message || 'ពុំមានទិន្នន័យពាក្យក្នុងទម្រង់រៀបរយទេ។ សូមប្រើប្រាស់ Live Code Editor!'}</div>`;
          }
        })
        .catch(err => {
          container.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center; width: 100%;">Error: ${err.message}</div>`;
        });
    }

    function updateModalGradeCounters() {
      for (let g = 1; g <= 6; g++) {
        const count = (modalWordsData[g] || []).length;
        const el = document.getElementById(`mcount-g${g}`);
        if (el) el.innerText = count;
      }
    }

    function selectModalWordGrade(grade, btn) {
      modalActiveGrade = grade;
      document.querySelectorAll('#wordEditorModal .grade-tab-btn').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');
      document.getElementById('modalActiveGradeLabel').innerText = `ថ្នាក់ទី${grade}`;
      renderModalWordsForActiveGrade();
    }

    function renderModalWordsForActiveGrade() {
      const container = document.getElementById('modalWordsContainer');
      const words = modalWordsData[modalActiveGrade] || [];
      const query = (document.getElementById('modalFilterWordsInput').value || '').trim().toLowerCase();
      document.getElementById('modalActiveGradeTotal').innerText = words.length;

      let filtered = words;
      if (query) {
        filtered = words.filter(w => (typeof w === 'string' ? w : JSON.stringify(w)).toLowerCase().includes(query));
      }

      if (filtered.length === 0) {
        container.innerHTML = '<div style="color: #64748b; padding: 20px; text-align: center; width: 100%;">ពុំមានពាក្យនៅក្នុងថ្នាក់នេះទេ។ សូមបន្ថែមពាក្យខាងលើ!</div>';
        return;
      }

      container.innerHTML = filtered.map(word => {
        const displayWord = typeof word === 'string' ? word : (word.word || word.khmer || JSON.stringify(word));
        const escaped = displayWord.replace(/"/g, '&quot;');
        return `
          <div class="word-chip">
            <span>${escaped}</span>
            <button class="btn-del-word" onclick="deleteModalSingleWord('${escaped}')" title="លុប">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
        `;
      }).join('');
    }

    function filterModalWordsDisplay() {
      renderModalWordsForActiveGrade();
    }

    function addModalSingleWord() {
      const input = document.getElementById('modalInputSingleWord');
      const word = input.value.trim();
      if (!word) return;

      if (!modalWordsData[modalActiveGrade]) modalWordsData[modalActiveGrade] = [];
      if (modalWordsData[modalActiveGrade].includes(word)) {
        alert(`ពាក្យ "${word}" មានរួចហើយ!`);
        return;
      }

      modalWordsData[modalActiveGrade].unshift(word);
      input.value = '';
      updateModalGradeCounters();
      renderModalWordsForActiveGrade();
      showAjaxAlert(`បានបន្ថែមពាក្យ "${word}" ទៅថ្នាក់ទី${modalActiveGrade}! (សូមចុច Save)`, 'success');
    }

    function addModalBulkWords() {
      const input = document.getElementById('modalInputBulkWords');
      const raw = input.value.trim();
      if (!raw) return;

      const wordsToAdd = raw.split(/[\n,;]+/).map(w => w.trim()).filter(w => w.length > 0);
      if (!modalWordsData[modalActiveGrade]) modalWordsData[modalActiveGrade] = [];

      let added = 0;
      wordsToAdd.forEach(w => {
        if (!modalWordsData[modalActiveGrade].includes(w)) {
          modalWordsData[modalActiveGrade].push(w);
          added++;
        }
      });

      input.value = '';
      updateModalGradeCounters();
      renderModalWordsForActiveGrade();
      showAjaxAlert(`បានបន្ថែម ${added} ពាក្យដោយជោគជ័យ!`, 'success');
    }

    function deleteModalSingleWord(word) {
      if (!confirm(`តើអ្នកពិតជាចង់លុប "${word}" មែនទេ?`)) return;
      if (modalWordsData[modalActiveGrade]) {
        modalWordsData[modalActiveGrade] = modalWordsData[modalActiveGrade].filter(w => (typeof w === 'string' ? w : (w.word || w.khmer)) !== word);
        updateModalGradeCounters();
        renderModalWordsForActiveGrade();
      }
    }

    function saveModalWordsData() {
      showAjaxAlert('កំពុងរក្សាទុកពាក្យចូលហ្គេម...', 'success');
      fetch('api.php?action=save_game_words', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ game: activeWordGame, wordsData: modalWordsData })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showAjaxAlert(data.message, 'success');
        } else {
          showAjaxAlert(data.message, 'danger');
        }
      })
      .catch(err => {
        showAjaxAlert('Error saving words: ' + err.message, 'danger');
      });
    }

    // ==================== 3. MEDIA MANAGER MODAL ====================
    function openMediaManagerModal() {
      document.getElementById('mediaManagerModal').classList.add('show');
      loadModalImagesList();
    }

    function closeMediaManagerModal() {
      document.getElementById('mediaManagerModal').classList.remove('show');
    }

    function loadModalImagesList() {
      const grid = document.getElementById('modalImagesGrid');
      grid.innerHTML = '<div style="color:#64748b; padding:20px; text-align:center; width:100%;"><i class="fa-solid fa-spinner fa-spin mr-2"></i> កំពុងទាញយករូបភាព...</div>';

      fetch('api.php?action=list_images')
        .then(res => res.json())
        .then(data => {
          if (data.success && data.images) {
            if (data.images.length === 0) {
              grid.innerHTML = '<div style="color:#64748b; padding:20px; text-align:center; width:100%;">ពុំមានរូបភាពនៅក្នុងថតទេ។</div>';
              return;
            }

            grid.innerHTML = data.images.map(img => {
              const kb = (img.size / 1024).toFixed(1);
              return `
                <div class="image-card-thumb">
                  <div class="image-preview-box">
                    <img src="${img.url}" alt="${img.filename}" loading="lazy">
                  </div>
                  <div class="image-details">
                    <div class="image-details-name" title="${img.filename}">${img.filename}</div>
                    <div style="font-size: 0.68rem; color: #64748b; margin: 2px 0 6px;">${img.folder}/ • ${kb} KB</div>
                    <div style="display: flex; gap: 4px;">
                      <button class="btn-card-action" style="background:#f1f5f9; color:#334155; padding:3px 6px; font-size:0.68rem;" onclick="copyModalImagePath('${img.path}')" title="Copy">
                        <i class="fa-regular fa-copy"></i>
                      </button>
                      <button class="btn-card-action" style="background:#fef2f2; color:#ef4444; padding:3px 6px; font-size:0.68rem;" onclick="deleteModalGameImage('${img.path}')" title="Delete">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  </div>
                </div>
              `;
            }).join('');
          }
        });
    }

    function handleModalImageUpload(e) {
      e.preventDefault();
      const form = document.getElementById('modalUploadImageForm');
      const formData = new FormData(form);
      const btn = document.getElementById('btnModalUploadImg');
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';

      fetch('api.php?action=upload_image', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Upload';
        if (data.success) {
          showAjaxAlert(data.message, 'success');
          form.reset();
          loadModalImagesList();
        } else {
          showAjaxAlert(data.message, 'danger');
        }
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Upload';
        showAjaxAlert('Upload Error: ' + err.message, 'danger');
      });
    }

    function copyModalImagePath(path) {
      navigator.clipboard.writeText(path).then(() => {
        showAjaxAlert(`បានចម្លងផ្លូវរូបភាព៖ "${path}"`, 'success');
      });
    }

    function deleteModalGameImage(path) {
      if (!confirm(`តើអ្នកពិតជាចង់លុបរូបភាព "${path}" មែនទេ?`)) return;
      fetch('api.php?action=delete_image', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ path: path })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showAjaxAlert(data.message, 'success');
          loadModalImagesList();
        } else {
          showAjaxAlert(data.message, 'danger');
        }
      });
    }

    // ==================== 4. SCORES & SYSTEM MODALS ====================
    function openScoresModal() { document.getElementById('scoresModal').classList.add('show'); }
    function closeScoresModal() { document.getElementById('scoresModal').classList.remove('show'); }

    function openAdminsModal() { document.getElementById('adminsModal').classList.add('show'); }
    function closeAdminsModal() { document.getElementById('adminsModal').classList.remove('show'); }

    function openDatabaseModal() { document.getElementById('databaseModal').classList.add('show'); }
    function closeDatabaseModal() { document.getElementById('databaseModal').classList.remove('show'); }

    // Close on backdrop click
    document.querySelectorAll('.admin-modal').forEach(m => {
      m.addEventListener('click', (e) => {
        if (e.target === m) m.classList.remove('show');
      });
    });
  </script>
</body>
</html>
