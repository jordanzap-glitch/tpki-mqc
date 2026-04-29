<?php
session_start();
// Clear session data
$_SESSION = array();
// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
// Destroy the session
session_destroy();
// Prevent caching so the back button won't show authenticated pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
// Redirect to login page
header('Location: index.php');
exit;
?>