<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../db/dbcon.php';
$displayName = 'John Doe';
$roleLabel = 'User';
if (!empty($_SESSION['userId'])) {
    $uid = (int) $_SESSION['userId'];
    $stmt = mysqli_prepare($conn, "SELECT First_Name, Last_Name, User_Type_ID FROM tbl_user WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $displayName = htmlspecialchars(trim($row['First_Name'] . ' ' . $row['Last_Name']));
            $roleLabel = (int)$row['User_Type_ID'] === 1 ? 'Admin' : 'Staff';
        }
    }
}
?>

<nav class="navbar navbar-expand bg-secondary navbar-dark sticky-top px-4 py-0">
                <a href="index.html" class="navbar-brand d-flex d-lg-none me-4">
                    <h2 class="text-primary mb-0"><i class="fa fa-user-edit"></i></h2>
                </a>
                <a href="#" class="sidebar-toggler flex-shrink-0">
                    <i class="fa fa-bars"></i>
                </a>
                <div class="navbar-nav align-items-center ms-auto">
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <img class="rounded-circle me-lg-2" src="img/user.jpg" alt="" style="width: 40px; height: 40px;">
                            <span class="d-none d-lg-inline-flex"><?php echo $displayName; ?></span>
                        </a>
                            <div class="dropdown-menu dropdown-menu-end bg-secondary border-0 rounded-0 rounded-bottom m-0">
                                <a href="../logout.php" class="dropdown-item">Log Out</a>
                        </div>
                    </div>
                </div>
            </nav>