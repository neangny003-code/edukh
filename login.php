<?php
/**
 * Admin Login Page - GamesKid
 */
require_once __DIR__ . '/auth.php';

// Redirect to dashboard if already logged in
if (is_admin_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $success = 'You have been logged out successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $res = admin_login($username, $password);
    if ($res['success']) {
        header("Location: index.php");
        exit;
    } else {
        $error = $res['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | GamesKid Portal</title>
    <!-- Modern Font: Inter & Kantumruy Pro for Khmer -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-glow: rgba(79, 70, 229, 0.35);
            --accent: #06b6d4;
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.75);
            --card-border: rgba(255, 255, 255, 0.12);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: rgba(15, 23, 42, 0.6);
            --input-border: rgba(255, 255, 255, 0.15);
            --danger: #ef4444;
            --success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Kantumruy Pro', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.25) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(6, 182, 212, 0.2) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(236, 72, 153, 0.15) 0px, transparent 50%);
            color: var(--text-main);
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }

        /* Floating glowing background shapes */
        .ambient-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.6;
            pointer-events: none;
            animation: pulseShape 10s ease-in-out infinite alternate;
        }

        .shape-1 {
            width: 320px;
            height: 320px;
            background: #6366f1;
            top: 10%;
            left: 15%;
        }

        .shape-2 {
            width: 280px;
            height: 280px;
            background: #06b6d4;
            bottom: 15%;
            right: 15%;
            animation-delay: -5s;
        }

        @keyframes pulseShape {
            0% { transform: scale(1) translate(0, 0); }
            100% { transform: scale(1.15) translate(20px, 20px); }
        }

        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 40px 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px rgba(79, 70, 229, 0.15);
            transition: transform 0.3s ease;
        }

        .logo-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, #4f46e5, #06b6d4);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #fff;
            box-shadow: 0 10px 20px var(--primary-glow);
        }

        .logo-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: #64748b;
            font-size: 15px;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .form-control {
            width: 100%;
            padding: 13px 16px 13px 42px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            color: var(--text-main);
            font-size: 14.5px;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #6366f1;
            background: rgba(15, 23, 42, 0.85);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
        }

        .form-control:focus + .input-icon,
        .input-wrapper:focus-within .input-icon {
            color: #818cf8;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            font-size: 15px;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: #cbd5e1;
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            color: white;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 20px -5px var(--primary-glow);
            transition: all 0.25s ease;
            margin-top: 8px;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -5px var(--primary-glow);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Default credentials hint box */
        .credentials-box {
            margin-top: 24px;
            padding: 14px 16px;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.5);
            border: 1px dashed rgba(255, 255, 255, 0.15);
            font-size: 12.5px;
            color: #94a3b8;
            line-height: 1.6;
        }

        .credentials-box strong {
            color: #e2e8f0;
        }

        .credentials-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 4px;
        }

        .cred-badge {
            background: rgba(99, 102, 241, 0.18);
            color: #a5b4fc;
            padding: 2px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            user-select: all;
        }

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: #fff;
        }
    </style>
</head>
<body>

    <div class="ambient-shape shape-1"></div>
    <div class="ambient-shape shape-2"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="logo-header">
                <div class="logo-icon">
                    <i class="fa-solid fa-gamepad"></i>
                </div>
                <h1 class="logo-title">GamesKid Admin</h1>
                <p class="logo-subtitle">Sign in to manage games, students & scores</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="off">
                <div class="form-group">
                    <label class="form-label" for="username">Admin Username</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="text" id="username" name="username" class="form-control" placeholder="admin" value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin'); ?>" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                        <button type="button" class="toggle-password" id="btnTogglePassword" title="Show / Hide Password">
                            <i class="fa-regular fa-eye" id="pwdEyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Sign In to Dashboard</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="credentials-box">
                <div style="font-weight: 600; margin-bottom: 4px; color: #cbd5e1;">
                    <i class="fa-solid fa-key" style="color: #818cf8; margin-right: 4px;"></i> Default Admin Credentials:
                </div>
                <div class="credentials-row">
                    <span>Username:</span>
                    <span class="cred-badge" onclick="fillCred('admin', 'admin123')">admin</span>
                </div>
                <div class="credentials-row">
                    <span>Password:</span>
                    <span class="cred-badge" onclick="fillCred('admin', 'admin123')">admin123</span>
                </div>
            </div>

            <a href="../index.html" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Games Portal</span>
            </a>
        </div>
    </div>

    <script>
        const btnToggle = document.getElementById('btnTogglePassword');
        const pwdInput = document.getElementById('password');
        const pwdEyeIcon = document.getElementById('pwdEyeIcon');

        btnToggle.addEventListener('click', () => {
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                pwdEyeIcon.className = 'fa-regular fa-eye-slash';
            } else {
                pwdInput.type = 'password';
                pwdEyeIcon.className = 'fa-regular fa-eye';
            }
        });

        function fillCred(u, p) {
            document.getElementById('username').value = u;
            document.getElementById('password').value = p;
        }
    </script>
</body>
</html>
