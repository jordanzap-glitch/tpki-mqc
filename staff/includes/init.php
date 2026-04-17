<?php
if (session_status() == PHP_SESSION_NONE) session_start();
// Generate a CSRF token for forms if not already present
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // Fallback: less secure but avoids fatal error on very old PHP setups
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// Helper to verify CSRF tokens
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}
// Prevent caching so browser Back button won't show protected pages after logout
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
// If there's no active session, redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}
?>
