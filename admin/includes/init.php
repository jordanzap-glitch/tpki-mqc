<?php
if (session_status() == PHP_SESSION_NONE) session_start();

// Prevent caching so browser Back button won't show protected pages after logout
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// If there's no active session, redirect to workspace index.php
if (!isset($_SESSION['userId']) && !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
