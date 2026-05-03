<?php
require_once __DIR__ . '/../../db/dbcon.php';
$displayName = 'John Doe';
$roleLabel = 'User';
$navBranchName = '';
if (!empty($_SESSION['userId'])) {
    $uid = (int) $_SESSION['userId'];
    $stmt = mysqli_prepare($conn, "SELECT u.First_Name, u.Last_Name, u.User_Type_ID, b.Branch_Name
        FROM tbl_user u
        LEFT JOIN tbl_branch b ON u.Branch_ID = b.Branch_ID
        WHERE u.id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $displayName = htmlspecialchars(trim($row['First_Name'] . ' ' . $row['Last_Name']));
            $roleLabel = (int)$row['User_Type_ID'] === 1 ? 'Admin' : 'Staff';
            $navBranchName = htmlspecialchars($row['Branch_Name'] ?? '');
        }
    }
}
?>

<nav class="navbar navbar-expand bg-secondary navbar-dark sticky-top px-4 py-0">
                <a href="index.php" class="navbar-brand d-flex d-lg-none me-4">
                    <img src="../img/logo.png" alt="TPKI" style="height:36px;width:auto;">
                </a>
                <a href="#" class="sidebar-toggler flex-shrink-0">
                    <i class="fa fa-bars"></i>
                </a>
                <div class="navbar-nav align-items-center ms-auto">
                    <?php if ($navBranchName !== ''): ?>
                    <div class="nav-item me-2 d-none d-lg-flex">
                        <span class="badge bg-success px-2 py-1" style="font-size:.8rem">
                            <i class="fa fa-map-marker-alt me-1"></i><?php echo $navBranchName; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="nav-item me-2">
                        <button id="theme-toggle" class="btn btn-sm-square rounded-circle" title="Toggle Theme" style="background: var(--dark);">
                            <i class="fa fa-moon text-primary"></i>
                        </button>
                    </div>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <span class="d-none d-lg-inline-flex"><?php echo $displayName; ?></span>
                        </a>
                            <div class="dropdown-menu dropdown-menu-end bg-secondary border-0 rounded-0 rounded-bottom m-0">
                                <?php if ($navBranchName !== ''): ?>
                                <span class="dropdown-item-text text-muted small d-lg-none">
                                    <i class="fa fa-map-marker-alt me-1"></i><?php echo $navBranchName; ?>
                                </span>
                                <div class="dropdown-divider d-lg-none border-secondary"></div>
                                <?php endif; ?>
                                <a href="../logout.php" class="dropdown-item">Log Out</a>
                        </div>
                    </div>
                </div>
            </nav>