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

// Load the staff member's branch from tbl_user JOIN tbl_branch on every request
// so changes made by admin are reflected immediately.
require_once __DIR__ . '/../../db/dbcon.php';
$_staffUid = (int)($_SESSION['userId'] ?? $_SESSION['user_id'] ?? 0);
if ($_staffUid > 0) {
    $_bstmt = mysqli_prepare($conn,
        "SELECT u.Branch_ID, b.Branch_Name
           FROM tbl_user u
           LEFT JOIN tbl_branch b ON u.Branch_ID = b.Branch_ID
          WHERE u.id = ? LIMIT 1");
    if ($_bstmt) {
        mysqli_stmt_bind_param($_bstmt, 'i', $_staffUid);
        mysqli_stmt_execute($_bstmt);
        mysqli_stmt_bind_result($_bstmt, $_branchId, $_branchName);
        mysqli_stmt_fetch($_bstmt);
        mysqli_stmt_close($_bstmt);
        $_SESSION['branchId']   = $_branchId   ?? '';
        $_SESSION['branchName'] = $_branchName ?? '';
    }
    unset($_bstmt, $_staffUid, $_branchId, $_branchName);
}
