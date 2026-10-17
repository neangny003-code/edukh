<?php
/**
 * Admin Logout
 */
require_once __DIR__ . '/auth.php';
admin_logout();
header("Location: login.php?msg=logged_out");
exit;
