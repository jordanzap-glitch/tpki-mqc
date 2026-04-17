<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../db/dbcon.php';
$displayName = 'Jhon Doe';
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

<div class="sidebar pe-4 pb-3">
            <nav class="navbar bg-secondary navbar-dark">
                <a href="index.html" class="navbar-brand mx-4 mb-3">
                    <h3 class="text-primary"><i class="fa fa-user-edit me-2"></i>TPKI</h3>
                </a>
                <div class="d-flex align-items-center ms-4 mb-4">
                    <div class="position-relative">
                        <img class="rounded-circle" src="img/user.jpg" alt="" style="width: 40px; height: 40px;">
                        <div class="bg-success rounded-circle border border-2 border-white position-absolute end-0 bottom-0 p-1"></div>
                    </div>
                    <div class="ms-3">
                        <h6 class="mb-0"><?php echo $displayName; ?></h6>
                        <span><?php echo $roleLabel; ?></span>
                    </div>
                </div>
                <div class="navbar-nav w-100">
                    <a href="index.php" class="nav-item nav-link"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"><i class="fa fa-users me-2"></i>User Management</a>
                        <div class="dropdown-menu bg-transparent border-0">
                            <a href="user.php" class="dropdown-item">Users</a>
                            <a href="usertype.php" class="dropdown-item">User Types</a>
                        </div>
                    </div>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"><i class="fa fa-building me-2"></i>Organizations</a>
                        <div class="dropdown-menu bg-transparent border-0">
                            <a href="branch.php" class="dropdown-item">Branches</a>
                            <a href="department.php" class="dropdown-item">Departments</a>
                            <a href="position.php" class="dropdown-item">Positions</a>
                        </div>
                    </div>
                    <a href="employee.php" class="nav-item nav-link"><i class="fa fa-user-tie me-2"></i>Employees</a>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"><i class="fa fa-handshake me-2"></i>Clients</a>
                        <div class="dropdown-menu bg-transparent border-0">
                            <a href="client.php" class="dropdown-item">Client Information</a>
                            <a href="client_record.php" class="dropdown-item">Client Record</a>
                            <a href="client_asset.php" class="dropdown-item">Client Assets</a>
                            <a href="client_business.php" class="dropdown-item">Client Business</a>
                            <a href="client_dep.php" class="dropdown-item">Client Dependents</a>
                            <a href="client_inc_exp.php" class="dropdown-item">Income & Expenses</a>
                            <a href="position.php" class="dropdown-item">Positions</a>
                        </div>
                    </div>
                     <a href="comaker_info.php" class="nav-item nav-link"><i class="fa fa-user-plus me-2"></i>Comaker</a>
                     <a href="interest_rate.php" class="nav-item nav-link"><i class="fa fa-percentage me-2"></i>Interest Rate</a>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"><i class="fa fa-hand-holding-usd me-2"></i>Loans</a>
                        <div class="dropdown-menu bg-transparent border-0">
                            <a href="loan.php" class="dropdown-item">Loan Information</a>
                            <a href="loan_record.php" class="dropdown-item">Loan Record</a>
                            <a href="loan_ledger.php" class="dropdown-item">Loan Ledger</a>
                        </div>
                    </div>
                </div>
            </nav>
        </div>