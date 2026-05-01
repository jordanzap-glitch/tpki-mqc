<?php
include 'includes/init.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once __DIR__ . '/../db/dbcon.php';

$success = '';
$error = '';

// Load interest rates map (Interest_Rate_ID => Interest_Rate_Code)
$ir_map = array();
$ir_q = mysqli_query($conn, "SELECT Interest_Rate_ID, Interest_Rate_Code FROM tbl_interest_rate");
if ($ir_q) {
    while ($r = mysqli_fetch_assoc($ir_q)) {
        $ir_map[$r['Interest_Rate_ID']] = $r['Interest_Rate_Code'];
    }
}

// Load co-makers list (Comaker_ID => display name)
$comakers_map = array();
$cm_q = mysqli_query($conn, "SELECT Comaker_ID, Last_Name, First_Name FROM tbl_comaker_info");
if ($cm_q) {
    while ($r = mysqli_fetch_assoc($cm_q)) {
        $comakers_map[$r['Comaker_ID']] = trim(($r['Last_Name'] ?: '') . ', ' . ($r['First_Name'] ?: ''));
    }
}

// Load existing groups for group loan join option
$groups_data = array();
$grp_q = mysqli_query($conn, "SELECT tg.Group_ID, tg.Client_ID, tg.Info,
    CONCAT(IFNULL(c.Last_Name,''), ', ', IFNULL(c.First_Name,'')) AS Client_Name
    FROM tbl_group_loan tg
    LEFT JOIN tbl_client_info c ON tg.Client_ID = c.Client_ID
    ORDER BY tg.Group_ID, tg.id");
if ($grp_q) {
    while ($r = mysqli_fetch_assoc($grp_q)) {
        $gid = $r['Group_ID'];
        if (!isset($groups_data[$gid])) {
            $groups_data[$gid] = array('Group_ID' => $gid, 'Info' => $r['Info'] ?: '', 'members' => array());
        }
        if (!empty($r['Client_ID'])) {
            $name = trim($r['Client_Name'], ', ');
            $groups_data[$gid]['members'][] = array(
                'id'   => $r['Client_ID'],
                'name' => $name !== '' ? $name : $r['Client_ID']
            );
        }
    }
    mysqli_free_result($grp_q);
}
$groups_list = array_values($groups_data);

// Handle save loan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_loan'])) {
    // Generate next Loan_ID in format L-0001
    $last_q = mysqli_query($conn, "SELECT Loan_ID FROM tbl_loan_info ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/L-(\d+)/', $row['Loan_ID'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
    }
    $loan_id = sprintf('L-%04d', $nextNum);

    // Collect inputs
    $client_id = isset($_POST['Client_ID']) ? trim($_POST['Client_ID']) : null;
    $loan_type = isset($_POST['Loan_Type']) ? trim($_POST['Loan_Type']) : null;
    $loan_cycle = null; // will be computed server-side based on existing loans for the client
    $effective_date = isset($_POST['Effective_Date']) && $_POST['Effective_Date'] !== '' ? $_POST['Effective_Date'] : null;
    $maturity_date = isset($_POST['Maturity_Date']) && $_POST['Maturity_Date'] !== '' ? $_POST['Maturity_Date'] : null;
    $premium = isset($_POST['Premium']) && $_POST['Premium'] !== '' ? floatval($_POST['Premium']) : null;
    $benefit = isset($_POST['Benefit']) && $_POST['Benefit'] !== '' ? floatval($_POST['Benefit']) : null;
    $loan_amount = isset($_POST['Loan_Amount']) && $_POST['Loan_Amount'] !== '' ? floatval($_POST['Loan_Amount']) : null;
    $no_of_months = isset($_POST['No_of_Months']) && $_POST['No_of_Months'] !== '' ? intval($_POST['No_of_Months']) : null;
    $payment_mode = isset($_POST['Payment_Mode']) ? trim($_POST['Payment_Mode']) : null;
    $no_of_periods = isset($_POST['No_of_Periods']) && $_POST['No_of_Periods'] !== '' ? intval($_POST['No_of_Periods']) : null;
    $interest_rate_id = isset($_POST['Interest_Rate_ID']) ? trim($_POST['Interest_Rate_ID']) : null;
    $total_interest_rate = isset($_POST['Total_Interest_Rate']) && $_POST['Total_Interest_Rate'] !== '' ? floatval($_POST['Total_Interest_Rate']) : null;
    $total_interest = isset($_POST['Total_Interest']) && $_POST['Total_Interest'] !== '' ? floatval($_POST['Total_Interest']) : null;
    $total_amount = isset($_POST['Total_Amount']) && $_POST['Total_Amount'] !== '' ? floatval($_POST['Total_Amount']) : null;
    $fixed_amount = isset($_POST['Fixed_Amount']) && $_POST['Fixed_Amount'] !== '' ? floatval($_POST['Fixed_Amount']) : null;
    // Handle salary proof upload (only relevant for Salary loans)
    $salary_proof_path = null;
    $moa_pic = null; // blob to store in tbl_loan_info.moa_pic
    if ($loan_type === '2' && !empty($_FILES['Salary_Proof']) && $_FILES['Salary_Proof']['error'] === UPLOAD_ERR_OK) {
        $uploadsDir = __DIR__ . '/../uploads/loan_docs';
        if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0755, true);
        $origName = basename($_FILES['Salary_Proof']['name']);
        $ext = pathinfo($origName, PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($origName, PATHINFO_FILENAME));
        $filename = $loan_id . '_' . time() . '_' . $safeName . '.' . $ext;
        $dest = $uploadsDir . '/' . $filename;
        // Save a copy on disk
        if (move_uploaded_file($_FILES['Salary_Proof']['tmp_name'], $dest)) {
            $salary_proof_path = 'uploads/loan_docs/' . $filename;
            // Read file contents for DB blob
            $moa_pic = file_get_contents($dest);
        } else {
            // fallback: try reading directly from tmp_name
            $tmp = $_FILES['Salary_Proof']['tmp_name'];
            if (is_readable($tmp)) {
                $moa_pic = file_get_contents($tmp);
            }
        }
    }
    // Force loan status to PENDING by default
    $loan_status = 'PENDING';

    // Employee ID from session if available
    $employee_id = isset($_SESSION['User_ID']) ? $_SESSION['User_ID'] : (isset($_SESSION['UserID']) ? $_SESSION['UserID'] : null);

    if (empty($client_id)) {
        $error = 'Client is required.';
    } else {
        // Compute loan cycle as (existing loans for client) + 1
        $loan_cycle = 1;
        $safe_client = mysqli_real_escape_string($conn, $client_id);
        $cnt_q = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM tbl_loan_info WHERE Client_ID = '$safe_client'");
        if ($cnt_q) {
            $cnt_row = mysqli_fetch_assoc($cnt_q);
            if ($cnt_row && isset($cnt_row['cnt'])) {
                $loan_cycle = intval($cnt_row['cnt']) + 1;
            }
            mysqli_free_result($cnt_q);
        }
        // compute term = No_of_Periods * No_of_Months
        // For Salary loans (type 2), periods 6 and 12 count as 1 (monthly payments)
        $period_mult = intval($no_of_periods);
        if ($loan_type === '2' && in_array($period_mult, [6, 12])) {
            $period_mult = 1;
        }
        $term = $period_mult * intval($no_of_months);

        $sql = "INSERT INTO tbl_loan_info (Loan_ID, Client_ID, Loan_Type, Loan_Cycle, Effective_Date, Maturity_Date, Premium, Benefit, Loan_Amount, No_of_Months, Payment_Mode, No_of_Periods, Term, Interest_Rate_ID, Total_Interest_Rate, Total_Interest, Total_Amount, Fixed_Amount, moa_pic, Loan_Status, Employee_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            // types: s=string, d=double, i=int, b=blob
            $types = 'ssssssdddisiisddddbss';
            mysqli_stmt_bind_param($stmt, $types,
                $loan_id, $client_id, $loan_type, $loan_cycle, $effective_date, $maturity_date,
                $premium, $benefit, $loan_amount, $no_of_months, $payment_mode, $no_of_periods, $term,
                $interest_rate_id, $total_interest_rate, $total_interest, $total_amount, $fixed_amount,
                $moa_pic, $loan_status, $employee_id
            );
            // If blob present, send it via send_long_data (param index is zero-based)
            if ($moa_pic !== null) {
                $blob_param_index = 18; // zero-based index of moa_pic in bind list
                mysqli_stmt_send_long_data($stmt, $blob_param_index, $moa_pic);
            }
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Loan saved successfully.';
                if ($salary_proof_path) {
                    $success .= ' Salary proof uploaded.';
                }
                    // Handle group configuration (create new or join existing)
                    if ($loan_type === '3') {
                        $group_action = isset($_POST['group_action']) ? trim($_POST['group_action']) : '';
                        // Check if client is already a member of any group
                        $chk_grp_stmt = mysqli_prepare($conn, "SELECT Group_ID FROM tbl_group_loan WHERE Client_ID = ? LIMIT 1");
                        $client_existing_group = '';
                        if ($chk_grp_stmt) {
                            mysqli_stmt_bind_param($chk_grp_stmt, 's', $client_id);
                            mysqli_stmt_execute($chk_grp_stmt);
                            mysqli_stmt_store_result($chk_grp_stmt);
                            if (mysqli_stmt_num_rows($chk_grp_stmt) > 0) {
                                mysqli_stmt_bind_result($chk_grp_stmt, $client_existing_group);
                                mysqli_stmt_fetch($chk_grp_stmt);
                            }
                            mysqli_stmt_close($chk_grp_stmt);
                        }
                        if ($group_action === 'create') {
                            if (!empty($client_existing_group)) {
                                $success .= ' Warning: Client is already in group ' . htmlspecialchars($client_existing_group) . '. Group not created.';
                            } else {
                                // Auto-generate Group_ID
                                $last_grp_q = mysqli_query($conn, "SELECT Group_ID FROM tbl_group_loan ORDER BY id DESC LIMIT 1");
                                $nextGrpNum = 1;
                                if ($last_grp_q && mysqli_num_rows($last_grp_q) > 0) {
                                    $grp_row = mysqli_fetch_assoc($last_grp_q);
                                    if ($grp_row && preg_match('/GRP-(\d+)/', $grp_row['Group_ID'], $gm)) {
                                        $nextGrpNum = intval($gm[1]) + 1;
                                    }
                                    mysqli_free_result($last_grp_q);
                                }
                                $new_group_id = sprintf('GRP-%04d', $nextGrpNum);
                                $group_info = isset($_POST['Group_Info']) ? trim($_POST['Group_Info']) : '';
                                $ins_grp = mysqli_prepare($conn, "INSERT INTO tbl_group_loan (Group_ID, Client_ID, Info) VALUES (?, ?, ?)");
                                if ($ins_grp) {
                                    mysqli_stmt_bind_param($ins_grp, 'sss', $new_group_id, $client_id, $group_info);
                                    mysqli_stmt_execute($ins_grp);
                                    mysqli_stmt_close($ins_grp);
                                    $success .= ' Group ' . $new_group_id . ' created.';
                                }
                            }
                        } elseif ($group_action === 'join') {
                            $join_group_id = isset($_POST['Join_Group_ID']) ? trim($_POST['Join_Group_ID']) : '';
                            if (!empty($join_group_id)) {
                                if (!empty($client_existing_group)) {
                                    $success .= ' Warning: Client is already in group ' . htmlspecialchars($client_existing_group) . '. Join skipped.';
                                } else {
                                    $safe_jgid = mysqli_real_escape_string($conn, $join_group_id);
                                    $grp_info_r = mysqli_query($conn, "SELECT Info FROM tbl_group_loan WHERE Group_ID = '$safe_jgid' LIMIT 1");
                                    $join_info = '';
                                    if ($grp_info_r && mysqli_num_rows($grp_info_r) > 0) {
                                        $gi_row = mysqli_fetch_assoc($grp_info_r);
                                        $join_info = $gi_row['Info'] ?: '';
                                        mysqli_free_result($grp_info_r);
                                    }
                                    $ins_grp = mysqli_prepare($conn, "INSERT INTO tbl_group_loan (Group_ID, Client_ID, Info) VALUES (?, ?, ?)");
                                    if ($ins_grp) {
                                        mysqli_stmt_bind_param($ins_grp, 'sss', $join_group_id, $client_id, $join_info);
                                        mysqli_stmt_execute($ins_grp);
                                        mysqli_stmt_close($ins_grp);
                                        $success .= ' Joined group ' . $join_group_id . '.';
                                    }
                                }
                            }
                        }
                    }
            } else {
                $error = 'Insert failed: ' . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Insert prepare failed: ' . mysqli_error($conn);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>TPKI || Admin Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">

    <?php include "includes/head.php"; ?>
    <style>
    /* ── Loan Form Cards ── */
    .lf-card {
        background: var(--secondary);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 12px;
        padding: 1.75rem 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 12px rgba(0,0,0,0.25);
    }
    .lf-section-title {
        display: flex;
        align-items: center;
        gap: .6rem;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--primary);
        border-bottom: 1px solid rgba(61,242,118,0.2);
        padding-bottom: .6rem;
        margin-bottom: 1.25rem;
    }
    .lf-step-badge {
        width: 28px; height: 28px;
        border-radius: 50%;
        background: rgba(61,242,118,0.15);
        border: 1.5px solid var(--primary);
        color: var(--primary);
        font-size: .75rem; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .lf-card .form-control,
    .lf-card .form-select {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        color: #e2e5f1;
        border-radius: 8px;
        transition: border-color .2s, box-shadow .2s;
    }
    .lf-card .form-control:focus,
    .lf-card .form-select:focus {
        background: rgba(255,255,255,0.08);
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(61,242,118,0.12);
        color: #fff;
    }
    .lf-card .form-control[readonly] {
        background: rgba(255,255,255,0.03);
        color: rgba(255,255,255,0.5);
        cursor: default;
    }
    .lf-card .form-control::placeholder { color: rgba(255,255,255,0.3); }
    .lf-card .form-label {
        font-size: .8rem; font-weight: 600;
        color: rgba(255,255,255,0.6);
        margin-bottom: .3rem; letter-spacing: .02em;
    }
    /* Client detail card */
    .lf-client-detail {
        background: rgba(61,242,118,0.04);
        border: 1px solid rgba(61,242,118,0.18);
        border-radius: 10px;
        padding: 1.1rem 1.4rem;
    }
    .lf-client-detail .lf-detail-row {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px,1fr));
        gap: .55rem .75rem;
        margin-top: .75rem;
    }
    .lf-client-detail .lf-detail-item label {
        display: block; font-size: .72rem; font-weight: 600;
        color: rgba(255,255,255,0.45); text-transform: uppercase; letter-spacing: .06em;
        margin-bottom: .1rem;
    }
    .lf-client-detail .lf-detail-item span {
        font-size: .88rem; color: #e2e5f1; font-weight: 500;
    }
    /* Computed fields highlight */
    .lf-computed {
        background: rgba(61,242,118,0.07) !important;
        border-color: rgba(61,242,118,0.25) !important;
        color: var(--primary) !important;
        font-weight: 600;
    }
    /* Page header */
    .lf-page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.75rem; }
    .lf-page-header h5 { font-size:1.15rem; font-weight:700; color:#fff; margin:0; }
    .lf-page-header p { font-size:.8rem; color:rgba(255,255,255,0.45); margin:.15rem 0 0; }
    /* Action bar */
    .lf-action-bar {
        display:flex; align-items:center; justify-content:flex-end; gap:.75rem;
        padding-top:1rem; border-top:1px solid rgba(255,255,255,0.07); margin-top:.5rem;
    }
    .btn-lf-primary {
        background: var(--primary); color:#000; font-weight:700;
        border:none; border-radius:8px; padding:.55rem 1.6rem;
        letter-spacing:.04em; transition:opacity .2s, transform .15s;
    }
    .btn-lf-primary:hover { opacity:.88; transform:translateY(-1px); color:#000; }
    .btn-lf-outline {
        background:transparent; color:rgba(255,255,255,0.55);
        border:1px solid rgba(255,255,255,0.15); border-radius:8px; padding:.55rem 1.2rem;
        transition:border-color .2s, color .2s;
    }
    .btn-lf-outline:hover { border-color:rgba(255,255,255,0.4); color:#fff; }
    /* Verify button */
    .btn-lf-verify {
        background: rgba(61,242,118,0.15);
        border: 1px solid rgba(61,242,118,0.4);
        color: var(--primary); font-weight:600; border-radius:8px;
        padding:.5rem 1.25rem; transition:background .2s, color .2s;
        white-space:nowrap;
    }
    .btn-lf-verify:hover { background:var(--primary); color:#000; }

    /* ── Light mode ── */
    [data-theme="light"] .lf-card {
        background: #fff; border-color: #e2e8f0;
        box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    }
    [data-theme="light"] .lf-card .form-control,
    [data-theme="light"] .lf-card .form-select {
        background: #f8fafc; border-color: #d1d9e0; color: #212529;
    }
    [data-theme="light"] .lf-card .form-control:focus,
    [data-theme="light"] .lf-card .form-select:focus {
        background:#fff; border-color:#1a7a3a;
        box-shadow:0 0 0 3px rgba(26,122,58,0.1); color:#212529;
    }
    [data-theme="light"] .lf-card .form-control[readonly] {
        background:#f1f5f9; color:#6c757d;
    }
    [data-theme="light"] .lf-card .form-control::placeholder { color:#adb5bd; }
    [data-theme="light"] .lf-card .form-label { color:#495057; }
    [data-theme="light"] .lf-section-title { color:#1a7a3a; border-bottom-color:rgba(26,122,58,0.2); }
    [data-theme="light"] .lf-step-badge { background:rgba(26,122,58,0.1); border-color:#1a7a3a; color:#1a7a3a; }
    [data-theme="light"] .lf-client-detail { background:rgba(26,122,58,0.04); border-color:rgba(26,122,58,0.18); }
    [data-theme="light"] .lf-client-detail .lf-detail-item label { color:#64748b; }
    [data-theme="light"] .lf-client-detail .lf-detail-item span { color:#1e293b; }
    [data-theme="light"] .lf-computed { background:rgba(26,122,58,0.07) !important; border-color:rgba(26,122,58,0.3) !important; color:#1a7a3a !important; }
    [data-theme="light"] .lf-page-header h5 { color:#1e293b; }
    [data-theme="light"] .lf-page-header p { color:#64748b; }
    [data-theme="light"] .lf-action-bar { border-top-color:#e2e8f0; }
    [data-theme="light"] .btn-lf-outline { color:#64748b; border-color:#d1d9e0; }
    [data-theme="light"] .btn-lf-outline:hover { color:#1e293b; border-color:#94a3b8; }
    [data-theme="light"] .btn-lf-verify { background:rgba(26,122,58,0.08); border-color:rgba(26,122,58,0.35); color:#1a7a3a; }
    [data-theme="light"] #step4Note { color:#64748b; }
    </style>
</head>

<body>
    <div class="container-fluid position-relative d-flex p-0">
        <!-- Spinner Start -->
        <div id="spinner" class="show bg-dark position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->


        <!-- Sidebar Start -->
        <?php include "includes/sidebar.php"; ?>
        <!-- Sidebar End -->


        <!-- Content Start -->
        <div class="content">
            <!-- Navbar Start -->
           <?php include "includes/navbar.php"; ?>
            <!-- Navbar End -->


            <!-- Loan Form -->
            <div class="container-fluid pt-4 px-4 pb-5">

                <!-- Page Header -->
                <div class="lf-page-header">
                    <div>
                        <h5><i class="fa fa-hand-holding-usd me-2" style="color:var(--primary)"></i>New Loan Application</h5>
                        <p>Complete all steps to submit a loan application.</p>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php elseif (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form id="loanForm" method="post" enctype="multipart/form-data">

                    <!-- ── Step 1: Client Verification ── -->
                    <div class="lf-card">
                        <div class="lf-section-title">
                            <span class="lf-step-badge">1</span>
                            <i class="fa fa-user-check"></i> Client Verification
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-9">
                                <label class="form-label">Select Client <span class="text-danger">*</span></label>
                                <select id="loan_client" name="Client_ID" class="form-select" style="width:100%"></select>
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="verifyClient" class="btn btn-lf-verify w-100">
                                    <i class="fa fa-search-plus me-1"></i> Verify Client
                                </button>
                            </div>
                        </div>

                        <!-- Client detail panel -->
                        <div id="clientDetails" style="display:none" class="lf-client-detail mt-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa fa-id-badge" style="color:var(--primary);font-size:1.1rem;"></i>
                                <strong id="dName" style="font-size:.95rem;"></strong>
                                <span class="ms-2 text-muted" style="font-size:.8rem;" id="dClientID"></span>
                            </div>
                            <div class="lf-detail-row">
                                <div class="lf-detail-item"><label>Date of Birth</label><span id="dDOB">—</span></div>
                                <div class="lf-detail-item"><label>Age</label><span id="dAge">—</span></div>
                                <div class="lf-detail-item"><label>Civil Status</label><span id="dCivilStatus">—</span></div>
                                <div class="lf-detail-item"><label>City / Municipality</label><span id="dCity">—</span></div>
                                <div class="lf-detail-item"><label>Province</label><span id="dProvince">—</span></div>
                                <div class="lf-detail-item"><label>Mobile No.</label><span id="dMobile">—</span></div>
                                <div class="lf-detail-item"><label>Email</label><span id="dEmail">—</span></div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label class="form-label">Loan Type <span class="text-danger">*</span></label>
                                <select name="Loan_Type" class="form-select">
                                    <option value="">— Select Type —</option>
                                    <option value="1">Personal</option>
                                    <option value="2">Salary</option>
                                    <option value="3">Group</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fixed Amount</label>
                                <input id="Fixed_Amount" type="number" step="0.01" name="Fixed_Amount" class="form-control lf-computed" readonly placeholder="Auto-populated">
                            </div>
                        </div>
                    </div>

                    <!-- ── Group Configuration Card ── -->
                    <div id="groupConfigCard" class="lf-card" style="display:none">
                        <div class="lf-section-title">
                            <span class="lf-step-badge"><i class="fa fa-users" style="font-size:.65rem"></i></span>
                            <i class="fa fa-layer-group"></i> Group Configuration
                        </div>

                        <!-- Action choice -->
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label class="form-label">Group Action <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3 mt-2 flex-wrap">
                                    <label for="groupActionCreate" class="lf-group-action-opt" id="groupOptCreate">
                                        <input type="radio" name="group_action" id="groupActionCreate" value="create" checked style="display:none">
                                        <div class="lf-group-opt-icon"><i class="fa fa-plus-circle"></i></div>
                                        <div>
                                            <div class="lf-group-opt-title">Create New Group</div>
                                            <div class="lf-group-opt-desc">Start a fresh group and set its name &amp; description. A Group ID will be auto-generated.</div>
                                        </div>
                                    </label>
                                    <label for="groupActionJoin" class="lf-group-action-opt" id="groupOptJoin">
                                        <input type="radio" name="group_action" id="groupActionJoin" value="join" style="display:none">
                                        <div class="lf-group-opt-icon"><i class="fa fa-sign-in-alt"></i></div>
                                        <div>
                                            <div class="lf-group-opt-title">Join Existing Group</div>
                                            <div class="lf-group-opt-desc">Add this client to an already existing group and view its current members.</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <style>
                        .lf-group-action-opt {
                            display:flex; align-items:flex-start; gap:1rem;
                            padding:1rem 1.25rem; border-radius:10px; cursor:pointer;
                            border:1.5px solid rgba(255,255,255,0.1);
                            background:rgba(255,255,255,0.03);
                            flex:1; min-width:220px; max-width:340px;
                            transition:border-color .2s, background .2s;
                            user-select:none;
                        }
                        .lf-group-action-opt:hover { border-color:rgba(61,242,118,0.3); background:rgba(61,242,118,0.04); }
                        .lf-group-action-opt.active { border-color:var(--primary); background:rgba(61,242,118,0.08); }
                        .lf-group-opt-icon {
                            font-size:1.5rem; color:var(--primary);
                            min-width:36px; text-align:center; padding-top:2px;
                        }
                        .lf-group-opt-title { font-size:.95rem; font-weight:700; color:#e2e5f1; margin-bottom:.2rem; }
                        .lf-group-opt-desc { font-size:.78rem; color:rgba(255,255,255,0.45); line-height:1.4; }
                        [data-theme="light"] .lf-group-action-opt { border-color:#d1d9e0; background:#f8fafc; }
                        [data-theme="light"] .lf-group-action-opt:hover { border-color:rgba(26,122,58,0.35); background:rgba(26,122,58,0.04); }
                        [data-theme="light"] .lf-group-action-opt.active { border-color:#1a7a3a; background:rgba(26,122,58,0.07); }
                        [data-theme="light"] .lf-group-opt-icon { color:#1a7a3a; }
                        [data-theme="light"] .lf-group-opt-title { color:#1e293b; }
                        [data-theme="light"] .lf-group-opt-desc { color:#64748b; }
                        /* Member list styles */
                        .lf-members-list {
                            border-radius:10px;
                            border:1px solid rgba(255,255,255,0.1);
                            background:rgba(255,255,255,0.02);
                            overflow:hidden;
                            min-height:52px;
                        }
                        .lf-members-empty {
                            padding:.85rem 1rem;
                            color:rgba(255,255,255,0.35);
                            font-size:.82rem;
                        }
                        .lf-member-item {
                            display:flex; align-items:center; gap:.85rem;
                            padding:.6rem 1rem;
                            border-bottom:1px solid rgba(255,255,255,0.05);
                            transition:background .15s;
                        }
                        .lf-member-item:last-child { border-bottom:none; }
                        .lf-member-item:hover { background:rgba(61,242,118,0.04); }
                        .lf-member-avatar {
                            width:34px; height:34px; border-radius:50%;
                            background:rgba(61,242,118,0.12);
                            border:1.5px solid rgba(61,242,118,0.3);
                            display:flex; align-items:center; justify-content:center;
                            flex-shrink:0;
                            color:var(--primary); font-size:.85rem;
                        }
                        .lf-member-info { line-height:1.25; min-width:0; }
                        .lf-member-name { font-size:.88rem; font-weight:600; color:#e2e5f1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                        .lf-member-id { font-size:.74rem; color:rgba(255,255,255,0.4); margin-top:.1rem; }
                        .lf-member-num {
                            margin-left:auto; flex-shrink:0;
                            width:22px; height:22px; border-radius:50%;
                            background:rgba(255,255,255,0.06);
                            font-size:.7rem; font-weight:700;
                            color:rgba(255,255,255,0.4);
                            display:flex; align-items:center; justify-content:center;
                        }
                        [data-theme="light"] .lf-members-list { border-color:#e2e8f0; background:#f8fafc; }
                        [data-theme="light"] .lf-members-empty { color:#94a3b8; }
                        [data-theme="light"] .lf-member-item { border-bottom-color:#f1f5f9; }
                        [data-theme="light"] .lf-member-item:hover { background:rgba(26,122,58,0.04); }
                        [data-theme="light"] .lf-member-avatar { background:rgba(26,122,58,0.1); border-color:rgba(26,122,58,0.3); color:#1a7a3a; }
                        [data-theme="light"] .lf-member-name { color:#1e293b; }
                        [data-theme="light"] .lf-member-id { color:#94a3b8; }
                        [data-theme="light"] .lf-member-num { background:#f1f5f9; color:#94a3b8; }
                        </style>

                        <!-- Create New Group panel -->
                        <div id="createGroupPanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Group ID</label>
                                    <input type="text" class="form-control lf-computed" readonly id="newGroupIDDisplay" placeholder="Auto-generated on save">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Client ID</label>
                                    <input type="text" class="form-control lf-computed" readonly id="groupClientIDDisplay" placeholder="From selected client">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Group Info / Description</label>
                                    <textarea name="Group_Info" id="Group_Info" class="form-control" rows="3" placeholder="Enter group name, purpose, or description..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Join Existing Group panel -->
                        <div id="joinGroupPanel" style="display:none">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">Select Group <span class="text-danger">*</span></label>
                                    <select name="Join_Group_ID" id="joinGroupSelect" class="form-select">
                                        <option value="">— Select a Group —</option>
                                        <?php foreach ($groups_list as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g['Group_ID']); ?>">
                                            <?php echo htmlspecialchars($g['Group_ID']); ?><?php if (!empty($g['Info'])): ?> — <?php echo htmlspecialchars($g['Info']); ?><?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Current Members</label>
                                    <div id="groupMembersDisplay" class="lf-members-list">
                                        <div class="lf-members-empty"><i class="fa fa-users me-2"></i>Select a group to see its members.</div>
                                    </div>
                                </div>
                            </div>
                            <div id="groupJoinWarning" class="mt-2 px-1" style="display:none">
                                <div style="background:rgba(255,193,7,0.1);border:1px solid rgba(255,193,7,0.35);border-radius:8px;padding:.6rem 1rem;font-size:.82rem;color:#ffc107;display:flex;align-items:center;gap:.6rem;">
                                    <i class="fa fa-exclamation-triangle"></i>
                                    <span id="groupJoinWarningText"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Step 2: Loan Details ── -->
                    <div class="lf-card">
                        <div class="lf-section-title">
                            <span class="lf-step-badge">2</span>
                            <i class="fa fa-file-invoice-dollar"></i> Loan Details
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Payment Mode</label>
                                <input name="Payment_Mode" class="form-control" placeholder="e.g. Weekly / Monthly">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">No. of Periods</label>
                                <select id="No_of_Periods" name="No_of_Periods" class="form-select">
                                    <option value="">— Select Period —</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Effective Date</label>
                                <input id="Effective_Date" type="date" name="Effective_Date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Maturity Date</label>
                                <input id="Maturity_Date" type="date" name="Maturity_Date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">No. of Months</label>
                                <input id="No_of_Months" type="number" name="No_of_Months" class="form-control lf-computed" readonly placeholder="Auto-calculated">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Loan Amount <span class="text-danger">*</span></label>
                                <input id="Loan_Amount" type="number" step="0.01" name="Loan_Amount" class="form-control" placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Premium</label>
                                <input type="number" step="0.01" name="Premium" class="form-control" placeholder="0.00">
                            </div>
                            <input type="hidden" name="Benefit" value="">
                            <div class="col-md-6" id="salaryProofContainer" style="display:none">
                                <label class="form-label"><i class="fa fa-paperclip me-1"></i>Salary Proof <small class="text-muted">(PDF / PNG / JPG)</small></label>
                                <input id="Salary_Proof" name="Salary_Proof" type="file" accept=".pdf,image/png,image/jpeg" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- ── Step 3: Interest & Totals ── -->
                    <div class="lf-card">
                        <div class="lf-section-title">
                            <span class="lf-step-badge">3</span>
                            <i class="fa fa-percentage"></i> Interest &amp; Totals
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Interest Rate ID</label>
                                <input id="Interest_Rate_ID" name="Interest_Rate_ID" class="form-control lf-computed" readonly placeholder="Auto-set by loan type">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Interest Rate</label>
                                <input id="Total_Interest_Rate" type="number" step="0.0001" name="Total_Interest_Rate" class="form-control lf-computed" readonly placeholder="0.0000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Interest</label>
                                <input id="Total_Interest" type="number" step="0.01" name="Total_Interest" class="form-control lf-computed" readonly placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Amount</label>
                                <input id="Total_Amount" type="number" step="0.01" name="Total_Amount" class="form-control lf-computed" readonly placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="lf-card" style="padding:1rem 2rem;">
                        <input type="hidden" name="save_loan" value="1">
                        <div class="lf-action-bar" style="border-top:none;padding-top:0;margin-top:0;">
                            <button type="reset" class="btn btn-lf-outline"><i class="fa fa-times me-1"></i> Clear</button>
                            <button type="submit" class="btn btn-lf-primary"><i class="fa fa-save me-1"></i> Save Loan</button>
                        </div>
                    </div>

                </form>
            </div>


            <!-- Footer Start -->
            <?php include 'includes/footer.php'; ?>
            <!-- Footer End -->
        </div>
        <!-- Content End -->


        <!-- Back to Top -->
        <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up text-white"></i></a>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../lib/chart/chart.min.js"></script>
    <script src="../lib/easing/easing.min.js"></script>
    <script src="../lib/waypoints/waypoints.min.js"></script>
    <script src="../lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="../lib/tempusdominus/js/moment.min.js"></script>
    <script src="../lib/tempusdominus/js/moment-timezone.min.js"></script>
    <script src="../lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>

    <!-- Select2 & SweetAlert for verify UI -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Template Javascript -->
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>"></script>
    <script>
    $(function(){
        // Previous civil status from PHP session (if any)
        var prevCivilStatus = <?php echo json_encode(isset($_SESSION['Civil_Status']) ? $_SESSION['Civil_Status'] : ''); ?> || '';

        // Map civil status codes to human-readable labels
        var civilStatusMap = {
            'M': 'Married',
            'S': 'Single',
            'SP': 'Single Parent',
            'MO': 'Married w/o Child'
        };

        var clientsMap = {};
        // initialize empty select2
        $('#loan_client').select2({
            placeholder: '-- Select client --',
            allowClear: true,
            width: '100%'
        });

        // load clients via existing endpoint
        $.getJSON('client_record.php?fetch_clients=1').done(function(res){
            if (res && res.data) {
                res.data.forEach(function(c){
                    var id = c.Client_ID || '';
                    var text = (c.Last_Name||'') + ', ' + (c.First_Name||'') + ' — ' + id;
                    clientsMap[id] = c;
                    var option = new Option(text, id, false, false);
                    $('#loan_client').append(option);
                });
                $('#loan_client').trigger('change');
            }
        }).fail(function(){
            console.warn('Failed to load clients.');
        });

        // Verify button handler
        $('#verifyClient').on('click', function(){
            var sel = $('#loan_client').val();
            if (!sel) {
                Swal.fire({icon:'warning', title:'Select a client', text:'Please choose a client first.'});
                return;
            }
            var c = clientsMap[sel];
            if (!c) {
                Swal.fire({icon:'error', title:'Not found', text:'Client data not found.'});
                return;
            }
            // Populate details
            $('#dClientID').text(c.Client_ID || '');
            var name = (c.Last_Name||'') + ', ' + (c.First_Name||'');
            $('#dName').text(name);
            $('#dDOB').text(c.Date_Of_Birth || '');
            $('#dAge').text(c.Age || '');
            // Prefer session-stored civil status if available, otherwise use client value
            var civCode = prevCivilStatus || (c.Civil_Status || '');
            var civLabel = civilStatusMap[civCode] || civCode || '';
            $('#dCivilStatus').text(civLabel);
            // Determine Fixed Amount based on civil status code and age ranges
            var ageNum = parseInt(c.Age, 10);
            var fixedVal = '';

            // mapping of fixed amounts by civil status and age ranges
            var fixedMap = {
                'M': [ {min:18,max:65,val:'481.80'}, {min:66,max:70,val:'258.53'} ],
                'S': [ {min:18,max:65,val:'300.80'}, {min:66,max:70,val:'193.20'}, {min:71,max:75,val:'633.60'} ],
                'SP': [ {min:18,max:65,val:'318.05'}, {min:66,max:70,val:'210.56'} ],
                'MO': [ {min:18,max:65,val:'464.55'}, {min:66,max:70,val:'241.28'}, {min:71,max:75,val:'802.56'} ]
            };

            // normalize civCode: if civCode is a human label, map back to code
            var civKey = civCode;
            if (!fixedMap[civKey]) {
                for (var k in civilStatusMap) {
                    if (civilStatusMap[k] === civCode) { civKey = k; break; }
                }
            }

            if (!isNaN(ageNum) && fixedMap[civKey]) {
                for (var i = 0; i < fixedMap[civKey].length; i++) {
                    var r = fixedMap[civKey][i];
                    if (ageNum >= r.min && ageNum <= r.max) {
                        fixedVal = r.val;
                        break;
                    }
                }
            }

            $('#Fixed_Amount').val(fixedVal);
            $('#dCity').text(c.City_Municipality || '');
            $('#dProvince').text(c.Province || '');
            $('#dEmail').text(c.Email_Address || '');
            $('#dMobile').text(c.Mobile_No || '');
            $('#clientDetails').show();
            // scroll to details
            $('html,body').animate({scrollTop: $('#clientDetails').offset().top - 80}, 300);
        });

        // Map loan type to interest rate ID (fallback to these IDs)
        var loanTypeToIR = {
            '1': 'IN-001', // Personal
            '2': 'IN-002', // Salary
            '3': 'IN-003'  // Group
        };

        // Interest rates map loaded from server: Interest_Rate_ID => Interest_Rate_Code
        var interestRates = <?php echo json_encode($ir_map, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?> || {};

        function setInterestRateFieldsByType(typeVal) {
            var irid = loanTypeToIR[typeVal] || '';
            $('#Interest_Rate_ID').val(irid);
            var code = '';
            if (irid && interestRates.hasOwnProperty(irid)) {
                code = interestRates[irid];
            }
            $('#Total_Interest_Rate').val(code);
            // recompute total interest when rate is set
            computeTotalInterest();
        }

        // When loan type changes, set the Interest_Rate_ID and total rate
        $('select[name="Loan_Type"]').on('change', function(){
            var t = $(this).val();
            setInterestRateFieldsByType(t);
            populatePeriodsByType(t);
            // show/hide salary proof upload for Salary loan (type '2')
            if (t === '2') {
                $('#salaryProofContainer').show();
            } else {
                $('#salaryProofContainer').hide();
                $('#Salary_Proof').val('');
            }
            // show/hide group config card
            if (t === '3') {
                $('#groupConfigCard').show();
                // prefill client ID display
                var selClient = $('#loan_client').val() || '';
                $('#groupClientIDDisplay').val(selClient);
            } else {
                $('#groupConfigCard').hide();
            }
        });

        // Populate No_of_Periods options depending on loan type
        function populatePeriodsByType(typeVal) {
            var map = {
                // Personal: Weekly=4, Monthly=1
                '1': [ {val:4, text:'Weekly'}, {val:1, text:'Monthly'} ],
                // Salary: Semi-month (2), 6 months (6), 12 months (12)
                '2': [ {val:2, text:'Semi-Month'}, {val:6, text:'6 Months'}, {val:12, text:'12 Months'} ],
                // Group: Weekly only
                '3': [ {val:4, text:'Weekly'} ]
            };
            var opts = map[typeVal] || [ {val:1, text:'Monthly'} ];
            var $sel = $('#No_of_Periods');
            $sel.empty();
            $sel.append(new Option('-- Select Period --', ''));
            opts.forEach(function(o){
                var opt = new Option(o.text, o.val, false, false);
                $sel.append(opt);
            });
        }

        // If Interest_Rate_ID is manually changed (unlikely), update total rate too
        $('#Interest_Rate_ID').on('change input', function(){
            var irid = $(this).val() || '';
            var code = interestRates[irid] || '';
            $('#Total_Interest_Rate').val(code);
            computeTotalInterest();
        });

        // Groups data from server
        var groupsData = <?php echo json_encode(array_values($groups_data), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?> || [];
        var groupsMap = {};
        groupsData.forEach(function(g){ groupsMap[g.Group_ID] = g; });

        // Returns all Group_IDs that contain the given clientId
        function getClientGroups(clientId) {
            var result = [];
            groupsData.forEach(function(g) {
                if (g.members && g.members.some(function(m){ return m.id === clientId; })) {
                    result.push(g.Group_ID);
                }
            });
            return result;
        }

        // Show/hide duplicate-member warning in the join panel
        function refreshJoinWarning() {
            var gid = $('#joinGroupSelect').val();
            var selClient = $('#loan_client').val() || '';
            var $warn = $('#groupJoinWarning');
            if (!gid || !selClient) { $warn.hide(); return; }
            var inThisGroup = groupsMap[gid] && groupsMap[gid].members &&
                groupsMap[gid].members.some(function(m){ return m.id === selClient; });
            if (inThisGroup) {
                $('#groupJoinWarningText').text('This client is already a member of this group and cannot be added again.');
                $warn.show(); return;
            }
            var otherGroups = getClientGroups(selClient).filter(function(g){ return g !== gid; });
            if (otherGroups.length > 0) {
                $('#groupJoinWarningText').text('This client is already a member of group ' + otherGroups[0] + ' and cannot join another group.');
                $warn.show(); return;
            }
            $warn.hide();
        }

        // Group action radio toggle
        function updateGroupOptActive() {
            var val = $('input[name="group_action"]:checked').val();
            $('#groupOptCreate').toggleClass('active', val === 'create');
            $('#groupOptJoin').toggleClass('active', val === 'join');
        }
        // Set initial active state
        updateGroupOptActive();
        $('input[name="group_action"]').on('change', function(){
            updateGroupOptActive();
            if ($(this).val() === 'create') {
                $('#createGroupPanel').show();
                $('#joinGroupPanel').hide();
            } else {
                $('#createGroupPanel').hide();
                $('#joinGroupPanel').show();
            }
        });

        // When join group selected, show members
        $('#joinGroupSelect').on('change', function(){
            var gid = $(this).val();
            var $disp = $('#groupMembersDisplay');
            if (!gid || !groupsMap[gid]) {
                $disp.html('<div class="lf-members-empty"><i class="fa fa-users me-2"></i>Select a group to see its members.</div>');
                $('#groupJoinWarning').hide();
                return;
            }
            var members = groupsMap[gid].members || [];
            if (members.length === 0) {
                $disp.html('<div class="lf-members-empty"><i class="fa fa-user-slash me-2"></i>No members in this group yet.</div>');
            } else {
                var html = members.map(function(m, idx){
                    var label = m.name || m.id;
                    var initials = label.replace(/,.*/, '').trim().charAt(0).toUpperCase();
                    return '<div class="lf-member-item">'
                        + '<div class="lf-member-avatar">' + initials + '</div>'
                        + '<div class="lf-member-info">'
                        + '<div class="lf-member-name">' + label + '</div>'
                        + '<div class="lf-member-id"><i class="fa fa-id-card me-1" style="font-size:.65rem"></i>' + m.id + '</div>'
                        + '</div>'
                        + '<div class="lf-member-num">' + (idx + 1) + '</div>'
                        + '</div>';
                }).join('');
                $disp.html(html);
            }
            refreshJoinWarning();
        });

        // Keep groupClientIDDisplay in sync with client selection
        $('#loan_client').on('change', function(){
            $('#groupClientIDDisplay').val($(this).val() || '');
            if ($('#joinGroupPanel').is(':visible')) {
                refreshJoinWarning();
            }
        });

        // Form submit: validate group membership uniqueness
        $('#loanForm').on('submit', function(e){
            var loanType = $('select[name="Loan_Type"]').val();
            if (loanType !== '3') return true;
            var action = $('input[name="group_action"]:checked').val();
            var selClient = $('#loan_client').val() || '';
            if (!selClient) return true;
            var clientGroups = getClientGroups(selClient);
            if (action === 'join') {
                var jgid = $('#joinGroupSelect').val();
                if (jgid && groupsMap[jgid]) {
                    var inThisGroup = groupsMap[jgid].members &&
                        groupsMap[jgid].members.some(function(m){ return m.id === selClient; });
                    if (inThisGroup) {
                        e.preventDefault();
                        Swal.fire({icon:'error', title:'Already a Member',
                            text:'This client is already a member of the selected group and cannot be added again.'});
                        return false;
                    }
                }
                if (clientGroups.length > 0) {
                    e.preventDefault();
                    Swal.fire({icon:'error', title:'Already in a Group',
                        text:'This client is already a member of group ' + clientGroups[0] + ' and cannot join another group.'});
                    return false;
                }
            } else if (action === 'create') {
                if (clientGroups.length > 0) {
                    e.preventDefault();
                    Swal.fire({icon:'error', title:'Already in a Group',
                        text:'This client is already a member of group ' + clientGroups[0] + ' and cannot be assigned to a new group.'});
                    return false;
                }
            }
            return true;
        });

        // Initialize interest rate id and total rate if a loan type is preselected
        var initialType = $('select[name="Loan_Type"]').val();
        if (initialType) {
            setInterestRateFieldsByType(initialType);
            populatePeriodsByType(initialType);
        }

        // --- Auto-calc Effective / Maturity and No_of_Months ---
        function formatInputDate(d) {
            var yyyy = d.getFullYear();
            var mm = String(d.getMonth()+1).padStart(2,'0');
            var dd = String(d.getDate()).padStart(2,'0');
            return yyyy+'-'+mm+'-'+dd;
        }

        function addMonthsToDateStr(dateStr, months) {
            var d = new Date(dateStr);
            var day = d.getDate();
            d.setMonth(d.getMonth() + months);
            // If month overflow changed day (e.g., Feb), adjust to last day of prev month
            if (d.getDate() < day) {
                d.setDate(0);
            }
            return formatInputDate(d);
        }

        function calcMonthsBetween(effStr, matStr) {
            var d1 = new Date(effStr);
            var d2 = new Date(matStr);
            if (isNaN(d1) || isNaN(d2) || d2 < d1) return '';
            var months = (d2.getFullYear() - d1.getFullYear()) * 12 + (d2.getMonth() - d1.getMonth());
            // adjust if day-of-month in mat is earlier than eff
            if (d2.getDate() < d1.getDate()) months -= 1;
            return months;
        }

        // set Effective_Date default to today if empty
        var $eff = $('#Effective_Date');
        var todayStr = formatInputDate(new Date());
        if (!$eff.val()) $eff.val(todayStr);

        // No_of_Periods is driven only by Loan_Type (select). Do NOT connect it to maturity/months.
        $('#Effective_Date').on('change input', function(){
            // if maturity present, recalc months
            var eff = $(this).val();
            var mat = $('#Maturity_Date').val();
            if (eff && mat) {
                $('#No_of_Months').val(calcMonthsBetween(eff, mat));
                computeTotalAmount();
            }
        });

        // --- Total Interest calculation ---
        function computeTotalInterest() {
            var amt = parseFloat($('#Loan_Amount').val());
            var rate = parseFloat($('#Total_Interest_Rate').val());
            if (isNaN(amt) || isNaN(rate)) {
                $('#Total_Interest').val('');
                return;
            }
            var total = amt * rate;
            // Round to 2 decimals
            $('#Total_Interest').val(total.toFixed(2));
            computeTotalAmount();
        }

        // --- Total Amount calculation (diminishing balance) ---
        // term = No_of_Months × No_of_Periods
        // Each period: interest on remaining principal, fixed principal repayment
        function computeTotalAmount() {
            var months = parseFloat($('#No_of_Months').val());
            var periods = parseFloat($('#No_of_Periods').val());
            var loanAmt = parseFloat($('#Loan_Amount').val());
            var monthlyRate = parseFloat($('#Total_Interest_Rate').val());
            if (isNaN(months) || isNaN(periods) || isNaN(loanAmt) || isNaN(monthlyRate) || loanAmt === 0 || periods === 0) {
                $('#Total_Amount').val('');
                return;
            }
            // For Salary loans, periods 6/12 count as 1 (monthly payments)
            var loanType = $('select[name="Loan_Type"]').val();
            var periodMult = periods;
            if (loanType === '2' && (periods === 6 || periods === 12)) {
                periodMult = 1;
            }
            var term = months * periodMult;
            var ratePerPeriod = monthlyRate / periodMult;
            var principalPerPeriod = loanAmt / term;
            var remaining = loanAmt;
            var totalAmount = 0;
            var totalInterestSum = 0;
            for (var i = 0; i < term; i++) {
                var intThisPeriod = remaining * ratePerPeriod;
                totalInterestSum += intThisPeriod;
                totalAmount += principalPerPeriod + intThisPeriod;
                remaining -= principalPerPeriod;
                if (remaining < 0) remaining = 0;
            }
            $('#Total_Interest').val(totalInterestSum.toFixed(2));
            $('#Total_Amount').val(totalAmount.toFixed(2));
        }

        // compute when amount or rate inputs change
        $('#Loan_Amount').on('input change', function(){ computeTotalInterest(); computeTotalAmount(); });
        $('#Total_Interest_Rate').on('input change', function(){ computeTotalInterest(); computeTotalAmount(); });
        $('#No_of_Periods').on('change', computeTotalAmount);
        $('#No_of_Months').on('input change', computeTotalAmount);
        $('#Total_Interest').on('input change', computeTotalAmount);

        // when Maturity changed manually, compute months
        $('#Maturity_Date').on('change input', function(){
            var eff = $('#Effective_Date').val();
            var mat = $(this).val();
            if (eff && mat) {
                $('#No_of_Months').val(calcMonthsBetween(eff, mat));
                computeTotalAmount();
            }
        });

        // ensure Verify triggers Effective default and updates maturity/months
        $('#verifyClient').on('click', function(){
            if (!$('#Effective_Date').val()) $('#Effective_Date').val(todayStr);
            // if maturity present, recalc months
            var eff = $('#Effective_Date').val();
            var mat = $('#Maturity_Date').val();
            if (eff && mat) {
                $('#No_of_Months').val(calcMonthsBetween(eff, mat));
                computeTotalAmount();
            }
            // recompute total interest and total amount based on populated fields
            computeTotalInterest();
            computeTotalAmount();
        });

        // Sections displayed statically (no next/prev navigation)
    });
    </script>
    <?php
    if (!empty($success)) {
        $msg = addslashes($success);
        echo "<script>Swal.fire({icon: 'success', title: 'Success', text: '{$msg}'});</script>";
    } elseif (!empty($error)) {
        $emsg = addslashes($error);
        echo "<script>Swal.fire({icon: 'error', title: 'Error', text: '{$emsg}'});</script>";
    }
    ?>
</body>

</html>
