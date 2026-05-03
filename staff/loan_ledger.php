<?php
include 'includes/init.php';
require_once __DIR__ . '/../db/dbcon.php';

// ─── AJAX: Fetch approved loans for dropdown ───
if (isset($_GET['fetch_approved_loans'])) {
    $group_filter  = isset($_GET['group_id']) ? trim($_GET['group_id']) : '';
    $filterBranch  = $_SESSION['branchId'] ?? '';
    $out = ['data' => []];
    $branchClause  = $filterBranch !== '' ? " AND c.Branch_ID = '" . mysqli_real_escape_string($conn, $filterBranch) . "'" : '';
    if ($group_filter !== '') {
        // Only loans whose Client_ID is in the selected group
        $safe_g = mysqli_real_escape_string($conn, $group_filter);
        $sql = "SELECT l.id, l.Loan_ID, l.Client_ID, c.Last_Name, c.First_Name,
                       l.Loan_Type, l.Loan_Amount, l.Total_Amount, l.Loan_Status
                FROM tbl_loan_info l
                LEFT JOIN tbl_client_info c ON l.Client_ID = c.Client_ID
                WHERE l.Loan_Status = 'APPROVED'
                  AND l.Client_ID IN (SELECT DISTINCT Client_ID FROM tbl_group_loan WHERE Group_ID = '$safe_g')
                  $branchClause
                ORDER BY l.id DESC";
    } else {
        $sql = "SELECT l.id, l.Loan_ID, l.Client_ID, c.Last_Name, c.First_Name,
                       l.Loan_Type, l.Loan_Amount, l.Total_Amount, l.Loan_Status
                FROM tbl_loan_info l
                LEFT JOIN tbl_client_info c ON l.Client_ID = c.Client_ID
                WHERE l.Loan_Status = 'APPROVED'
                  $branchClause
                ORDER BY l.id DESC";
    }
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) $out['data'][] = $row;
        mysqli_free_result($res);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out);
    exit;
}

// ─── AJAX: Fetch group list ───
if (isset($_GET['fetch_groups'])) {
    $out = ['data' => []];
    $sql = "SELECT DISTINCT g.Group_ID, g.Info,
                COUNT(g.Client_ID) AS member_count
            FROM tbl_group_loan g
            GROUP BY g.Group_ID, g.Info
            ORDER BY g.Group_ID ASC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) $out['data'][] = $row;
        mysqli_free_result($res);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out);
    exit;
}

// ─── AJAX: Get full loan details for preview ───
if (isset($_GET['get_loan_detail'])) {
    $lid = trim($_GET['get_loan_detail']);
    $stmt = mysqli_prepare($conn, "SELECT l.*, c.Last_Name, c.First_Name
                                    FROM tbl_loan_info l
                                    LEFT JOIN tbl_client_info c ON l.Client_ID = c.Client_ID
                                    WHERE l.Loan_ID = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $lid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    // Strip moa_pic BLOB to prevent json_encode failure on binary data
    if ($row && isset($row['moa_pic'])) {
        unset($row['moa_pic']);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => (bool)$row, 'data' => $row]);
    exit;
}

// ─── AJAX: Fetch existing ledger rows for a loan ───
if (isset($_GET['fetch_ledger'])) {
    $lid = trim($_GET['fetch_ledger']);
    $out = ['data' => []];
    $stmt = mysqli_prepare($conn, "SELECT * FROM tbl_loan_ledger2 WHERE Loan_ID = ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, 's', $lid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $out['data'][] = $row;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out);
    exit;
}

// ─── AJAX POST: Generate ledger (amortization schedule) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_ledger'])) {
    header('Content-Type: application/json; charset=utf-8');

    $loan_id = trim($_POST['Loan_ID'] ?? '');
    if (!$loan_id) { echo json_encode(['success' => false, 'msg' => 'Missing Loan_ID']); exit; }

    // Check if ledger already exists for this loan
    $chk = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM tbl_loan_ledger2 WHERE Loan_ID = ?");
    mysqli_stmt_bind_param($chk, 's', $loan_id);
    mysqli_stmt_execute($chk);
    $chk_res = mysqli_stmt_get_result($chk);
    $chk_row = mysqli_fetch_assoc($chk_res);
    if ($chk_row && intval($chk_row['cnt']) > 0) {
        echo json_encode(['success' => false, 'msg' => 'Ledger already generated for this loan.']);
        exit;
    }

    // Fetch loan info
    $stmt = mysqli_prepare($conn, "SELECT * FROM tbl_loan_info WHERE Loan_ID = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $loan_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $loan = mysqli_fetch_assoc($res);
    if (!$loan) { echo json_encode(['success' => false, 'msg' => 'Loan not found']); exit; }

    $term            = intval($loan['term'] ?? 0);
    $loan_amount     = floatval($loan['Loan_Amount'] ?? 0);
    $total_interest  = floatval($loan['Total_Interest'] ?? 0);
    $total_amount    = floatval($loan['Total_Amount'] ?? 0);
    $no_of_periods   = intval($loan['No_of_Periods'] ?? 1);
    $effective_date  = $loan['Effective_Date'] ?? null;
    $monthly_rate    = floatval($loan['Total_Interest_Rate'] ?? 0);

    if ($term <= 0) { echo json_encode(['success' => false, 'msg' => 'Loan term is zero or missing.']); exit; }
    if (!$effective_date) { echo json_encode(['success' => false, 'msg' => 'Effective date missing.']); exit; }

    // Diminishing balance method
    // Fixed principal per period; interest computed on remaining principal each period
    $principal_per_period = round($loan_amount / $term, 2);
    // Convert monthly interest rate to per-period rate
    // For Salary loans (type 2), periods 6/12 count as 1 (monthly payments)
    $loan_type_val = $loan['Loan_Type'] ?? '';
    $period_divisor = $no_of_periods;
    if ($loan_type_val === '2' && in_array($no_of_periods, [6, 12])) {
        $period_divisor = 1;
    }
    $rate_per_period = ($period_divisor > 0) ? ($monthly_rate / $period_divisor) : 0;

    // Generate unique alphanumeric Payment_IDs (format: P-xxxxxxxxxx)
    function genPaymentID($conn)
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        do {
            $rand = '';
            for ($j = 0; $j < 10; $j++) {
                $rand .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $payID = 'P-' . $rand;

            $chk = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM tbl_loan_ledger2 WHERE Payment_ID = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 's', $payID);
            mysqli_stmt_execute($chk);
            $chk_res = mysqli_stmt_get_result($chk);
            $chk_row = mysqli_fetch_assoc($chk_res);
            mysqli_stmt_close($chk);
        } while ($chk_row && intval($chk_row['cnt']) > 0);

        return $payID;
    }

    // Determine payment date interval based on No_of_Periods
    // 4 = weekly (+7 days), 2 = semi-monthly (+15 days), 1 = monthly (+1 month)
    $intervalType = 'days';
    $intervalVal  = 30;
    switch ($no_of_periods) {
        case 4:  $intervalType = 'days';   $intervalVal = 7;  break;
        case 2:  $intervalType = 'days';   $intervalVal = 15; break;
        case 1:  $intervalType = 'months'; $intervalVal = 1;  break;
        case 6:  $intervalType = 'months'; $intervalVal = 1;  break;
        case 12: $intervalType = 'months'; $intervalVal = 1;  break;
        default: $intervalType = 'days';   $intervalVal = 30; break;
    }

    $ins = mysqli_prepare($conn, "INSERT INTO tbl_loan_ledger2
        (Payment_ID, Loan_ID, Payment_Date, Beginning_Balance, Principal_Payment,
         Interest_Payment, Penalty, Total_Payment, Ending_Balance, Payment_Status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $beginning_balance = $loan_amount;
    $currentDate = new DateTime($effective_date);
    $inserted = 0;
    $penalty = 0.0;
    $status = 'PENDING';

    for ($i = 0; $i < $term; $i++) {
        // Advance payment date (first payment is one interval after effective date)
        if ($intervalType === 'days') {
            $currentDate->modify("+{$intervalVal} days");
        } else {
            $currentDate->modify("+{$intervalVal} months");
        }
        $payDate = $currentDate->format('Y-m-d');
        // create a unique alphanumeric Payment_ID like P-xxxxxxxxxx
        $payID = genPaymentID($conn);

        // Interest on the remaining principal (diminishing)
        $inter = round($beginning_balance * $rate_per_period, 2);
        $princ = $principal_per_period;

        // Last period: absorb any rounding difference in principal
        if ($i === $term - 1) {
            $princ = round($beginning_balance, 2);
        }

        $total_pay = round($princ + $inter, 2);
        $ending_balance = round($beginning_balance - $princ, 2);
        if ($ending_balance < 0) $ending_balance = 0;

        mysqli_stmt_bind_param($ins, 'sssdddddds',
            $payID, $loan_id, $payDate,
            $beginning_balance, $princ, $inter,
            $penalty, $total_pay, $ending_balance, $status
        );

        if (mysqli_stmt_execute($ins)) $inserted++;

        $beginning_balance = $ending_balance;
    }

    mysqli_stmt_close($ins);
    echo json_encode(['success' => $inserted > 0, 'rows' => $inserted]);
    exit;
}

// ─── AJAX POST: Apply 10% penalty on overdue PENDING payments ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_penalties'])) {
    header('Content-Type: application/json; charset=utf-8');
    $loan_id = trim($_POST['Loan_ID'] ?? '');
    if (!$loan_id) { echo json_encode(['success' => false, 'msg' => 'Missing Loan_ID']); exit; }

    $today = date('Y-m-d');
    // Find PENDING rows where Payment_Date < today and Penalty is still 0
    $stmt = mysqli_prepare($conn,
        "SELECT id, Principal_Payment, Interest_Payment, Penalty, Total_Payment, Ending_Balance
         FROM tbl_loan_ledger2
         WHERE Loan_ID = ? AND Payment_Status = 'PENDING' AND Payment_Date < ? AND Penalty = 0
         ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, 'ss', $loan_id, $today);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $updated = 0;
    $upd = mysqli_prepare($conn,
        "UPDATE tbl_loan_ledger2 SET Penalty = ?, Total_Payment = ? WHERE id = ?");

    while ($row = mysqli_fetch_assoc($res)) {
        $base = floatval($row['Principal_Payment']) + floatval($row['Interest_Payment']);
        $pen = round($base * 0.10, 2);
        $newTotal = round($base + $pen, 2);

        mysqli_stmt_bind_param($upd, 'ddi', $pen, $newTotal, $row['id']);
        if (mysqli_stmt_execute($upd)) $updated++;
    }

    mysqli_stmt_close($upd);
    echo json_encode(['success' => true, 'updated' => $updated]);
    exit;
}

// ─── AJAX POST: Approve (pay) a single payment row ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_payment'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'msg' => 'Missing payment id']); exit; }

    $stmt = mysqli_prepare($conn,
        "UPDATE tbl_loan_ledger2 SET Payment_Status = 'PAID' WHERE id = ? AND Payment_Status = 'PENDING'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $affected > 0]);
    exit;
}

// ─── AJAX POST: Update penalty for a ledger row ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_penalty'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id      = intval($_POST['id'] ?? 0);
    $penalty = floatval($_POST['penalty'] ?? 0);
    if ($penalty < 0) $penalty = 0;
    if (!$id) { echo json_encode(['success' => false, 'msg' => 'Missing id']); exit; }

    $stmt = mysqli_prepare($conn,
        "SELECT Principal_Payment, Interest_Payment FROM tbl_loan_ledger2 WHERE id = ? AND Payment_Status = 'PENDING' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    if (!$row) { echo json_encode(['success' => false, 'msg' => 'Row not found or already paid']); exit; }

    $new_total = round(floatval($row['Principal_Payment']) + floatval($row['Interest_Payment']) + $penalty, 2);

    $upd = mysqli_prepare($conn,
        "UPDATE tbl_loan_ledger2 SET Penalty = ?, Total_Payment = ? WHERE id = ? AND Payment_Status = 'PENDING'");
    mysqli_stmt_bind_param($upd, 'ddi', $penalty, $new_total, $id);
    mysqli_stmt_execute($upd);
    $affected = mysqli_stmt_affected_rows($upd);
    mysqli_stmt_close($upd);

    echo json_encode(['success' => $affected > 0, 'new_total' => $new_total, 'penalty' => $penalty]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>TPKI || Loan Ledger</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <?php include "includes/head.php"; ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        /* ── Loan Ledger Card ── */
        .ll-card {
            background: var(--secondary);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            padding: 1.75rem 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
        }
        .ll-section-title {
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
        .ll-section-title i { font-size: .95rem; opacity: .85; }

        /* Form controls inside ll-card */
        .ll-card .form-label {
            font-size: .8rem;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            margin-bottom: .3rem;
            letter-spacing: .02em;
        }

        /* Page header */
        .ll-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
        }
        .ll-page-header h5 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }
        .ll-page-header p {
            font-size: .8rem;
            color: rgba(255,255,255,0.45);
            margin: .15rem 0 0;
        }

        /* Summary info panels */
        .ll-info-panel {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 8px;
            padding: .85rem 1rem;
            height: 100%;
        }
        .ll-info-label {
            font-size: .7rem;
            font-weight: 700;
            color: rgba(255,255,255,0.38);
            text-transform: uppercase;
            letter-spacing: .09em;
            margin-bottom: .25rem;
        }
        .ll-info-value {
            font-size: .95rem;
            font-weight: 700;
            color: #e2e5f1;
            word-break: break-word;
        }
        .ll-info-value.accent { color: var(--primary); }

        /* Buttons */
        .btn-ll-primary {
            background: var(--primary);
            color: #000;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            padding: .55rem 1.4rem;
            letter-spacing: .04em;
            transition: opacity .2s, transform .15s;
        }
        .btn-ll-primary:hover { opacity: .88; transform: translateY(-1px); color: #000; }

        .btn-ll-outline {
            background: transparent;
            color: rgba(255,255,255,0.6);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 8px;
            padding: .55rem 1.4rem;
            font-weight: 600;
            letter-spacing: .03em;
            transition: border-color .2s, color .2s, background .2s;
        }
        .btn-ll-outline:hover { border-color: var(--primary); color: var(--primary); background: rgba(61,242,118,0.06); }
        .btn-ll-outline:disabled, .btn-ll-outline[disabled] {
            opacity: .35; cursor: not-allowed; pointer-events: none;
        }

        .btn-ll-pay {
            background: var(--primary);
            color: #000;
            font-weight: 700;
            font-size: .75rem;
            border: none;
            border-radius: 6px;
            padding: .3rem .8rem;
            transition: opacity .2s;
        }
        .btn-ll-pay:hover { opacity: .82; color: #000; }

        /* Select2 dark theme */
        .select2-container--default .select2-selection--single {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            height: 42px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #e2e5f1;
            line-height: 42px;
            padding-left: 12px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; }
        .select2-container--default .select2-selection--single .select2-selection__placeholder { color: rgba(255,255,255,0.3); }
        .select2-dropdown { background: #1e2a3a; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; }
        .select2-container--default .select2-results__option { color: #e2e5f1; padding: .45rem .75rem; }
        .select2-container--default .select2-results__option--highlighted[aria-selected] { background: #1a7a3a; color: #fff; }
        .select2-search--dropdown .select2-search__field {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            color: #e2e5f1;
            border-radius: 6px;
        }

        /* DataTable overrides */
        #ledgerTable { color: #e2e5f1; border-collapse: separate; border-spacing: 0; }
        #ledgerTable thead th {
            background: rgba(61,242,118,0.08);
            color: var(--primary);
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            border-color: rgba(255,255,255,0.08) !important;
            white-space: nowrap;
            padding: .75rem;
        }
        #ledgerTable tbody td {
            font-size: .84rem;
            vertical-align: middle;
            border-color: rgba(255,255,255,0.05) !important;
            padding: .6rem .75rem;
        }
        #ledgerTable tbody tr:hover td { background: rgba(255,255,255,0.03); }

        /* Status badges */
        .ll-badge {
            display: inline-block;
            padding: .28rem .65rem;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .05em;
        }
        .ll-badge-pending  { background: rgba(255,193,7,0.15);  color: #ffc107; border: 1px solid rgba(255,193,7,0.3); }
        .ll-badge-paid     { background: rgba(40,167,69,0.15);  color: #28a745; border: 1px solid rgba(40,167,69,0.3); }
        .ll-badge-overdue  { background: rgba(220,53,69,0.15);  color: #dc3545; border: 1px solid rgba(220,53,69,0.3); }

        /* DataTable pagination/info */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary) !important;
            color: #000 !important;
            border-color: var(--primary) !important;
            border-radius: 6px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: rgba(61,242,118,0.12) !important;
            color: var(--primary) !important;
            border-color: transparent !important;
            border-radius: 6px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button { color: #e2e5f1 !important; border-radius: 6px; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: #e2e5f1;
            border-radius: 6px;
            padding: .25rem .5rem;
        }
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_info { color: rgba(255,255,255,0.45); font-size: .8rem; }

        /* Scrollbar */
        .table-responsive::-webkit-scrollbar { height: 6px; width: 6px; }
        .table-responsive::-webkit-scrollbar-thumb { background: rgba(61,242,118,0.35); border-radius: 6px; }
        .table-responsive::-webkit-scrollbar-track { background: rgba(255,255,255,0.04); }
        .table-responsive { scrollbar-color: rgba(61,242,118,0.35) rgba(255,255,255,0.04); scrollbar-width: thin; }

        /* ── Light mode overrides ── */
        [data-theme="light"] .ll-card { background: #ffffff; border-color: #e2e8f0; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        [data-theme="light"] .ll-card .form-label { color: #495057; }
        [data-theme="light"] .ll-section-title { color: #1a7a3a; border-bottom-color: rgba(26,122,58,0.2); }
        [data-theme="light"] .ll-page-header h5 { color: #1e293b; }
        [data-theme="light"] .ll-page-header p { color: #64748b; }
        [data-theme="light"] .ll-info-panel { background: #f8fafc; border-color: #e2e8f0; }
        [data-theme="light"] .ll-info-label { color: #94a3b8; }
        [data-theme="light"] .ll-info-value { color: #1e293b; }
        [data-theme="light"] .ll-info-value.accent { color: #1a7a3a; }
        [data-theme="light"] .btn-ll-outline { color: #64748b; border-color: #d1d9e0; }
        [data-theme="light"] .btn-ll-outline:hover { color: #1a7a3a; border-color: #1a7a3a; background: rgba(26,122,58,0.06); }
        [data-theme="light"] .select2-container--default .select2-selection--single { background: #f8fafc; border-color: #d1d9e0; }
        [data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__rendered { color: #212529; }
        [data-theme="light"] .select2-dropdown { background: #ffffff; border-color: #e2e8f0; }
        [data-theme="light"] .select2-container--default .select2-results__option { color: #212529; }
        [data-theme="light"] .select2-search--dropdown .select2-search__field { background: #f8fafc; border-color: #d1d9e0; color: #212529; }
        [data-theme="light"] #ledgerTable { color: #1e293b; }
        [data-theme="light"] #ledgerTable thead th { background: rgba(26,122,58,0.07); color: #1a7a3a; border-color: #e2e8f0 !important; }
        [data-theme="light"] #ledgerTable tbody td { border-color: #f0f4f8 !important; }
        [data-theme="light"] #ledgerTable tbody tr:hover td { background: #f8fafc; }
        [data-theme="light"] .ll-badge-pending  { background: rgba(255,193,7,0.12); }
        [data-theme="light"] .ll-badge-paid     { background: rgba(40,167,69,0.12); }
        [data-theme="light"] .ll-badge-overdue  { background: rgba(220,53,69,0.12); }
        [data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        [data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover { background: #1a7a3a !important; color: #fff !important; border-color: #1a7a3a !important; }
        [data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: rgba(26,122,58,0.1) !important; color: #1a7a3a !important; }
        [data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button { color: #1e293b !important; }
        [data-theme="light"] .dataTables_wrapper .dataTables_length select,
        [data-theme="light"] .dataTables_wrapper .dataTables_filter input { background: #f8fafc; border-color: #d1d9e0; color: #212529; }
        [data-theme="light"] .dataTables_wrapper .dataTables_length label,
        [data-theme="light"] .dataTables_wrapper .dataTables_filter label,
        [data-theme="light"] .dataTables_wrapper .dataTables_info { color: #64748b; }
        [data-theme="light"] .table-responsive::-webkit-scrollbar-thumb { background: rgba(26,122,58,0.3); }
        [data-theme="light"] .table-responsive { scrollbar-color: rgba(26,122,58,0.3) #f0f4f8; }
        /* Group filter switch */
        .form-check-input:checked { background-color: var(--primary); border-color: var(--primary); }
        #groupInfoPanel { transition: opacity .2s; }
        [data-theme="light"] #groupInfoPanel { background: rgba(26,122,58,0.05); border-color: rgba(26,122,58,0.2); color: #1e293b; }
        /* Group toggle button */
        .ll-group-toggle {
            display: inline-flex; align-items: center; gap: .55rem;
            padding: .45rem 1.1rem;
            border-radius: 20px;
            border: 1.5px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.04);
            color: rgba(255,255,255,0.5);
            font-size: .82rem; font-weight: 600;
            cursor: pointer;
            transition: border-color .2s, background .2s, color .2s, box-shadow .2s;
            user-select: none;
        }
        .ll-group-toggle:hover {
            border-color: rgba(61,242,118,0.45);
            background: rgba(61,242,118,0.06);
            color: rgba(255,255,255,0.8);
        }
        .ll-group-toggle.active {
            border-color: var(--primary);
            background: rgba(61,242,118,0.12);
            color: var(--primary);
            box-shadow: 0 0 0 3px rgba(61,242,118,0.1);
        }
        .ll-group-toggle .ll-toggle-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: rgba(255,255,255,0.25);
            transition: background .2s;
            flex-shrink: 0;
        }
        .ll-group-toggle.active .ll-toggle-dot { background: var(--primary); }
        [data-theme="light"] .ll-group-toggle { border-color: #d1d9e0; background: #f8fafc; color: #64748b; }
        [data-theme="light"] .ll-group-toggle:hover { border-color: rgba(26,122,58,0.4); background: rgba(26,122,58,0.04); color: #1e293b; }
        [data-theme="light"] .ll-group-toggle.active { border-color: #1a7a3a; background: rgba(26,122,58,0.08); color: #1a7a3a; box-shadow: 0 0 0 3px rgba(26,122,58,0.1); }
        [data-theme="light"] .ll-group-toggle .ll-toggle-dot { background: #cbd5e0; }
        [data-theme="light"] .ll-group-toggle.active .ll-toggle-dot { background: #1a7a3a; }
        /* Edit penalty button */
        .btn-edit-penalty {
            background: none; border: none;
            color: rgba(255,255,255,0.75);
            cursor: pointer; padding: 0 0 0 5px;
            transition: color .15s; vertical-align: middle; line-height: 1;
        }
        .btn-edit-penalty:hover { color: var(--primary); }
        .btn-edit-penalty i { font-size: .65rem; }
        [data-theme="light"] .btn-edit-penalty { color: #333; }
        [data-theme="light"] .btn-edit-penalty:hover { color: #1a7a3a; }
        /* Edit Penalty Modal */
        #editPenaltyModal .modal-content {
            background: #1a2332;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
        }
        #editPenaltyModal .modal-header { border-bottom: 1px solid rgba(255,255,255,0.08); padding: 1rem 1.25rem .75rem; }
        #editPenaltyModal .modal-footer { border-top: 1px solid rgba(255,255,255,0.08); padding: .75rem 1.25rem; }
        #editPenaltyModal .modal-body { padding: 1.25rem; }
        #editPenaltyModal .ep-mini-panel {
            background: rgba(255,255,255,0.04);
            border-radius: 8px; padding: .6rem .8rem;
        }
        #editPenaltyModal .ep-mini-label {
            font-size: .68rem; font-weight: 700;
            color: rgba(255,255,255,0.38);
            text-transform: uppercase; letter-spacing: .08em; margin-bottom: .2rem;
        }
        #editPenaltyModal .ep-mini-value { font-size: .88rem; font-weight: 700; color: #e2e5f1; }
        #editPenaltyModal .ep-mini-value.accent { color: var(--primary); }
        #editPenaltyModal .ep-total-panel {
            background: rgba(61,242,118,0.07);
            border: 1px solid rgba(61,242,118,0.2);
            border-radius: 8px; padding: .7rem 1rem;
        }
        #editPenaltyModal .ep-total-label {
            font-size: .68rem; font-weight: 700;
            color: rgba(255,255,255,0.38);
            text-transform: uppercase; letter-spacing: .08em; margin-bottom: .25rem;
        }
        #editPenaltyModal #penaltyModalTotal { font-size: 1.1rem; font-weight: 700; color: var(--primary); }
        #editPenaltyModal #penaltyModalInput {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.15);
            color: #e2e5f1; border-radius: 8px; height: 42px;
        }
        #editPenaltyModal #penaltyModalInput:focus {
            background: rgba(255,255,255,0.1);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(61,242,118,0.12);
            color: #fff;
        }
        [data-theme="light"] #editPenaltyModal .modal-content { background: #fff; border-color: #e2e8f0; }
        [data-theme="light"] #editPenaltyModal .modal-header,
        [data-theme="light"] #editPenaltyModal .modal-footer { border-color: #e2e8f0; }
        [data-theme="light"] #editPenaltyModal .ep-mini-panel { background: #f8fafc; }
        [data-theme="light"] #editPenaltyModal .ep-mini-label,
        [data-theme="light"] #editPenaltyModal .ep-total-label { color: #94a3b8; }
        [data-theme="light"] #editPenaltyModal .ep-mini-value { color: #1e293b; }
        [data-theme="light"] #editPenaltyModal .ep-mini-value.accent { color: #1a7a3a; }
        [data-theme="light"] #editPenaltyModal .ep-total-panel { background: rgba(26,122,58,0.05); border-color: rgba(26,122,58,0.2); }
        [data-theme="light"] #editPenaltyModal #penaltyModalTotal { color: #1a7a3a; }
        [data-theme="light"] #editPenaltyModal #penaltyModalInput { background: #f8fafc; border-color: #d1d9e0; color: #212529; }
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

        <?php include "includes/sidebar.php"; ?>

        <div class="content">
            <?php include "includes/navbar.php"; ?>

            <div class="container-fluid pt-4 px-4 pb-5">

                <!-- Page Header -->
                <div class="ll-page-header">
                    <div>
                        <h5><i class="fa fa-book-open me-2" style="color:var(--primary)"></i>Loan Ledger</h5>
                        <p>View and manage amortization schedules for approved loans.</p>
                    </div>
                </div>

                <!-- ── Loan Selector ── -->
                <div class="ll-card">
                    <div class="ll-section-title">
                        <i class="fa fa-search"></i> Select Approved Loan
                    </div>
                    <!-- Group filter toggle -->
                    <div class="mb-3">
                        <button type="button" id="btnToggleGroup" class="ll-group-toggle">
                            <span class="ll-toggle-dot"></span>
                            <i class="fa fa-users"></i>
                            <span id="toggleGroupLabel">Filter by Group</span>
                        </button>
                    </div>
                    <!-- Group dropdown (hidden by default) -->
                    <div id="groupFilterRow" class="row g-3 mb-3" style="display:none">
                        <div class="col-md-5">
                            <label class="form-label">Group</label>
                            <input id="groupInput" list="groupList" class="form-control" placeholder="Select a group...">
                            <input type="hidden" id="groupHidden" value="">
                            <datalist id="groupList"></datalist>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Group Info</label>
                            <div id="groupInfoPanel" class="ll-info-panel" style="display:none">
                                <div id="groupInfoText" class="ll-info-value">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-7">
                            <label class="form-label">Loan</label>
                            <input id="loanInput" list="loanList" class="form-control" placeholder="Select an approved loan...">
                            <input type="hidden" id="loanHidden" value="">
                            <datalist id="loanList"></datalist>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <button type="button" id="btnPreview" class="btn btn-ll-primary w-100">
                                <i class="fa fa-eye me-1"></i> Preview
                            </button>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <button type="button" id="btnGenerate" class="btn btn-ll-outline w-100" disabled>
                                <i class="fa fa-cogs me-1"></i> Generate Ledger
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Loan Summary ── -->
                <div id="loanSummary" class="ll-card" style="display:none">
                    <div class="ll-section-title">
                        <i class="fa fa-info-circle"></i> Loan Summary
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Loan ID</div>
                                <div class="ll-info-value accent" id="sLoanID">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Client</div>
                                <div class="ll-info-value" id="sClient">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Loan Type</div>
                                <div class="ll-info-value" id="sType">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Loan Cycle</div>
                                <div class="ll-info-value" id="sCycle">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Effective Date</div>
                                <div class="ll-info-value" id="sEff">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Maturity Date</div>
                                <div class="ll-info-value" id="sMat">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Loan Amount</div>
                                <div class="ll-info-value accent" id="sLoanAmt">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Total Interest</div>
                                <div class="ll-info-value" id="sTotalInt">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Total Amount</div>
                                <div class="ll-info-value accent" id="sTotalAmt">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Term (Payments)</div>
                                <div class="ll-info-value" id="sTerm">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="ll-info-panel">
                                <div class="ll-info-label">Payment Mode</div>
                                <div class="ll-info-value" id="sPayMode">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Amortization Schedule ── -->
                <div id="ledgerSection" class="ll-card" style="display:none">
                    <div class="ll-section-title">
                        <i class="fa fa-table"></i> Amortization Schedule
                    </div>
                    <div class="table-responsive">
                        <table id="ledgerTable" class="table table-bordered mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Payment ID</th>
                                    <th>Payment Date</th>
                                    <th>Beginning Balance</th>
                                    <th>Principal</th>
                                    <th>Interest</th>
                                    <th>Penalty</th>
                                    <th>Total Payment</th>
                                    <th>Ending Balance</th>
                                    <th>Status</th>
                                    <th style="width:90px;">Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

            </div>

            <?php include 'includes/footer.php'; ?>
        </div>

        <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up text-white"></i></a>
    </div>

    <!-- ── Edit Penalty Modal ── -->
    <div class="modal fade" id="editPenaltyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" style="color:#fff;font-weight:700;font-size:.95rem;">
                        <i class="fa fa-edit me-2" style="color:var(--primary)"></i>Edit Penalty
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="penaltyRowId">
                    <input type="hidden" id="penaltyPrincipal">
                    <input type="hidden" id="penaltyInterest">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="ep-mini-panel">
                                <div class="ep-mini-label">Principal</div>
                                <div class="ep-mini-value accent" id="penaltyModalPrincipal">—</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="ep-mini-panel">
                                <div class="ep-mini-label">Interest</div>
                                <div class="ep-mini-value" id="penaltyModalInterest">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label style="font-size:.78rem;font-weight:600;color:rgba(255,255,255,0.55);margin-bottom:.35rem;display:block;">Penalty Amount</label>
                        <input type="number" id="penaltyModalInput" class="form-control" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="ep-total-panel">
                        <div class="ep-total-label">New Total Payment</div>
                        <div id="penaltyModalTotal">—</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ll-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="btnSavePenalty" class="btn btn-ll-primary btn-sm">
                        <i class="fa fa-save me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../lib/chart/chart.min.js"></script>
    <script src="../lib/easing/easing.min.js"></script>
    <script src="../lib/waypoints/waypoints.min.js"></script>
    <script src="../lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="../lib/tempusdominus/js/moment.min.js"></script>
    <script src="../lib/tempusdominus/js/moment-timezone.min.js"></script>
    <script src="../lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(function(){
        var typeMap = {'1':'PERSONAL','2':'SALARY','3':'GROUP'};
        var selectedLoanID = '';
        var ledgerDT = null;

        // Data structures for datalists
        var groupsMap = {};
        var loansLoadedForGroup = '';
        var groupLoadTimer = null;

        // ── Load loans into dropdown (optionally filtered by group) ──
        function loadLoans(groupID) {
            var params = { fetch_approved_loans: 1 };
            if (groupID) params.group_id = groupID;
            // populate datalist for loans
            $('#loanList').empty();
            $('#loanInput').val('');
            $('#loanHidden').val('');
            selectedLoanID = '';
            $.getJSON('loan_ledger.php', params).done(function(res){
                if (res && res.data) {
                    res.data.forEach(function(l){
                        var label = l.Loan_ID + ' — ' + ((l.Last_Name||'')+', '+(l.First_Name||'')).toUpperCase()
                                  + ' — ₱' + parseFloat(l.Total_Amount||0).toLocaleString()
                                  + ' (' + l.Loan_ID + ')';
                        $('#loanList').append($('<option>').val(label));
                    });
                }
                // reset visible input and selected id
                $('#loanInput').val('');
                $('#loanHidden').val('');
                selectedLoanID = '';
                $('#spinner').removeClass('show');
            }).fail(function(){ $('#spinner').removeClass('show'); });
        }

        // Initial load — all approved loans
        loadLoans('');

        // ── Load groups into group dropdown (once only) ──
        var groupsLoaded = false;
        function loadGroups() {
            if (groupsLoaded) return;
            $.getJSON('loan_ledger.php', { fetch_groups: 1 }).done(function(res){
                if (res && res.data) {
                    $('#groupList').empty();
                    res.data.forEach(function(g){
                        var label = g.Group_ID
                            + (g.Info ? ' — ' + g.Info : '')
                            + ' (' + g.member_count + ' member' + (g.member_count != 1 ? 's' : '') + ')'
                            + ' (' + g.Group_ID + ')';
                        $('#groupList').append($('<option>').val(label));
                        groupsMap[g.Group_ID] = g;
                    });
                    groupsLoaded = true;
                }
            });
        }

        // ── Group toggle button ──
        var groupFilterOpen = false;
        $('#btnToggleGroup').on('click', function(){
            groupFilterOpen = !groupFilterOpen;
            var $btn = $(this);
            if (groupFilterOpen) {
                $btn.addClass('active');
                $('#toggleGroupLabel').text('Hide Group Filter');
                $('#groupFilterRow').slideDown(200);
                loadGroups();
            } else {
                $btn.removeClass('active');
                $('#toggleGroupLabel').text('Filter by Group');
                $('#groupFilterRow').slideUp(200);
                $('#groupInput').val('');
                $('#groupHidden').val('');
                $('#groupInfoPanel').hide();
                selectedLoanID = '';
                $('#loanSummary').hide();
                $('#ledgerSection').hide();
                $('#btnGenerate').prop('disabled', true);
                loadLoans('');
            }
        });
        // ── Group input → filter loans + show group info ──
        $('#groupInput').on('input change', function(){
            var raw = this.value || '';
            var m = raw.match(/\(([^)]+)\)$/);
            var gid = m ? m[1] : '';
            $('#groupHidden').val(gid);
            if (gid && groupsMap[gid]) {
                var g = groupsMap[gid];
                var selText = g.Group_ID + (g.Info ? ' — ' + g.Info : '') + ' (' + g.member_count + ' member' + (g.member_count != 1 ? 's' : '') + ')';
                $('#groupInfoText').text(selText);
                $('#groupInfoPanel').show();
            } else if (gid) {
                // fallback: show raw
                $('#groupInfoText').text(raw);
                $('#groupInfoPanel').show();
            } else {
                $('#groupInfoPanel').hide();
            }
            // Reset loan selection
            selectedLoanID = '';
            $('#loanSummary').hide();
            $('#ledgerSection').hide();
            $('#btnGenerate').prop('disabled', true);
            // Debounce: 'input' and 'change' both fire when picking from datalist;
            // guard prevents loadLoans from running twice with the same group.
            clearTimeout(groupLoadTimer);
            groupLoadTimer = setTimeout(function(){ loadLoans(gid); }, 50);
        });

        function fmt(n){
            return '₱ ' + parseFloat(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
        }

        // ── Preview button ──
        $('#btnPreview').on('click', function(){
            // ensure selectedLoanID is set from visible input if possible
            if (!selectedLoanID) {
                var raw = $('#loanInput').val() || '';
                var m = raw.match(/\(([^)]+)\)$/);
                selectedLoanID = m ? m[1] : '';
                $('#loanHidden').val(selectedLoanID);
            }
            if (!selectedLoanID) {
                Swal.fire({icon:'warning',title:'Select a loan',text:'Please choose a loan first.'});
                return;
            }
            $.getJSON('loan_ledger.php', { get_loan_detail: selectedLoanID }).done(function(resp){
                if (!resp || !resp.success) { Swal.fire('Error','Loan not found','error'); return; }
                var d = resp.data;
                $('#sLoanID').text(d.Loan_ID);
                $('#sClient').text(((d.Last_Name||'')+', '+(d.First_Name||'')).toUpperCase());
                $('#sType').text(typeMap[d.Loan_Type] || (d.Loan_Type||'').toUpperCase());
                $('#sCycle').text(d.Loan_Cycle || '—');
                $('#sEff').text(d.Effective_Date || '—');
                $('#sMat').text(d.Maturity_Date || '—');
                $('#sLoanAmt').text('₱ ' + parseFloat(d.Loan_Amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}));
                $('#sTotalInt').text('₱ ' + parseFloat(d.Total_Interest||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}));
                $('#sTotalAmt').text('₱ ' + parseFloat(d.Total_Amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}));
                $('#sTerm').text(d.term || '—');
                $('#sPayMode').text((d.Payment_Mode||'—').toUpperCase());
                $('#loanSummary').slideDown();
                $('#btnGenerate').prop('disabled', false);
                loadLedger(selectedLoanID);
            });
        });

        // ── Load existing ledger rows (auto-apply penalties first) ──
        function loadLedger(loanID){
            $.post('loan_ledger.php', { apply_penalties:1, Loan_ID: loanID }, function(){
                $.getJSON('loan_ledger.php', { fetch_ledger: loanID }).done(function(resp){
                    var rows = (resp && resp.data) ? resp.data : [];
                    $('#ledgerSection').show();
                    if (ledgerDT) { ledgerDT.destroy(); ledgerDT = null; }

                    if (rows.length > 0) {
                        $('#btnGenerate').prop('disabled', true);
                        ledgerDT = $('#ledgerTable').DataTable({
                            data: rows,
                            paging: true,
                            searching: false,
                            info: true,
                            ordering: false,
                            destroy: true,
                            columns: [
                                { data: null, render: function(d,t,r,meta){ return meta.row + 1; } },
                                { data: 'Payment_ID', render: function(d){ return '<span style="font-family:monospace;font-size:.78rem">'+d+'</span>'; } },
                                { data: 'Payment_Date' },
                                { data: 'Beginning_Balance', render: function(d){ return fmt(d); } },
                                { data: 'Principal_Payment',  render: function(d){ return fmt(d); } },
                                { data: 'Interest_Payment',   render: function(d){ return fmt(d); } },
                                { data: 'Penalty', render: function(d, t, row){
                                    var penVal = parseFloat(d||0);
                                    var valHtml = penVal > 0 ? '<span style="color:#dc3545;font-weight:700">'+fmt(penVal)+'</span>' : fmt(penVal);
                                    if ((row.Payment_Status||'').toUpperCase() === 'PENDING') {
                                        return valHtml;
                                    }
                                    return valHtml;
                                }},
                                { data: 'Total_Payment',      render: function(d){ return '<strong>'+fmt(d)+'</strong>'; } },
                                { data: 'Ending_Balance',     render: function(d){ return fmt(d); } },
                                { data: 'Payment_Status', render: function(d){
                                    var s = (d||'').toUpperCase();
                                    var cls = 'll-badge ';
                                    if (s === 'PENDING') cls += 'll-badge-pending';
                                    else if (s === 'PAID' || s === 'POSTED') cls += 'll-badge-paid';
                                    else cls += 'll-badge-overdue';
                                    return '<span class="'+cls+'">'+s+'</span>';
                                }},
                                { data: null, orderable: false, render: function(d){
                                    var s = (d.Payment_Status||'').toUpperCase();
                                    if (s === 'PENDING') {
                                        return '<button class="btn-ll-pay btn-approve" data-id="'+d.id+'" title="Mark as Paid"><i class="fa fa-check me-1"></i>Pay</button>';
                                    }
                                    return '<span style="color:rgba(255,255,255,0.25);font-size:.8rem">—</span>';
                                }}
                            ]
                        });
                    } else {
                        $('#btnGenerate').prop('disabled', false);
                        $('#ledgerTable tbody').html(
                            '<tr><td colspan="11" class="text-center" style="padding:2rem;color:rgba(255,255,255,0.4)"><i class="fa fa-info-circle me-2"></i>No ledger entries yet. Click <strong>Generate Ledger</strong> to create the amortization schedule.</td></tr>'
                        );
                    }
                });
            }, 'json');
        }

        // When user picks a loan from the datalist, capture the Loan_ID
        $('#loanInput').on('input change', function(){
            var raw = this.value || '';
            var m = raw.match(/\(([^)]+)\)$/);
            var lid = m ? m[1] : '';
            $('#loanHidden').val(lid);
            selectedLoanID = lid;
        });

        // ── Approve (Pay) a single payment ──
        $(document).on('click', '.btn-approve', function(){
            var btn = $(this);
            var payId = btn.data('id');
            Swal.fire({
                title: 'Approve Payment?',
                text: 'Mark this payment as PAID?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Approve',
                confirmButtonColor: '#1a7a3a'
            }).then(function(result){
                if (!result.isConfirmed) return;
                $.post('loan_ledger.php', { approve_payment:1, id: payId }, function(resp){
                    if (resp && resp.success) {
                        Swal.fire({ icon:'success', title:'Approved', text:'Payment marked as PAID.', confirmButtonColor:'#1a7a3a' });
                        loadLedger(selectedLoanID);
                    } else {
                        Swal.fire('Error', resp.msg || 'Approval failed', 'error');
                    }
                }, 'json').fail(function(){ Swal.fire('Error','Server error','error'); });
            });
        });

        // ── Edit Penalty Modal ──
        var penaltyModal = new bootstrap.Modal(document.getElementById('editPenaltyModal'));

        function calcPenaltyTotal() {
            var princ = parseFloat($('#penaltyPrincipal').val() || 0);
            var inter = parseFloat($('#penaltyInterest').val() || 0);
            var pen   = parseFloat($('#penaltyModalInput').val() || 0);
            if (isNaN(pen) || pen < 0) pen = 0;
            var total = princ + inter + pen;
            $('#penaltyModalTotal').text('₱ ' + total.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}));
        }

        $(document).on('click', '.btn-edit-penalty', function(){
            var $btn = $(this);
            var princ = parseFloat($btn.data('principal') || 0);
            var inter = parseFloat($btn.data('interest') || 0);
            var pen   = parseFloat($btn.data('penalty') || 0);
            $('#penaltyRowId').val($btn.data('id'));
            $('#penaltyPrincipal').val(princ);
            $('#penaltyInterest').val(inter);
            $('#penaltyModalPrincipal').text(fmt(princ));
            $('#penaltyModalInterest').text(fmt(inter));
            $('#penaltyModalInput').val(pen > 0 ? pen.toFixed(2) : '');
            calcPenaltyTotal();
            penaltyModal.show();
        });

        $('#penaltyModalInput').on('input', calcPenaltyTotal);

        $('#btnSavePenalty').on('click', function(){
            var id      = $('#penaltyRowId').val();
            var penalty = parseFloat($('#penaltyModalInput').val() || 0);
            if (isNaN(penalty) || penalty < 0) penalty = 0;
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Saving...');
            $.post('loan_ledger.php', { update_penalty: 1, id: id, penalty: penalty }, function(resp){
                $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i>Save');
                if (resp && resp.success) {
                    penaltyModal.hide();
                    Swal.fire({ icon: 'success', title: 'Updated', text: 'Penalty updated successfully.', confirmButtonColor: '#1a7a3a', timer: 1800, showConfirmButton: false });
                    loadLedger(selectedLoanID);
                } else {
                    Swal.fire('Error', resp.msg || 'Failed to update penalty', 'error');
                }
            }, 'json').fail(function(){
                $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i>Save');
                Swal.fire('Error', 'Server error', 'error');
            });
        });

        // ── Generate Ledger button ──
        $('#btnGenerate').on('click', function(){
            if (!selectedLoanID) return;
            Swal.fire({
                title: 'Generate Ledger?',
                text: 'This will create the full amortization schedule for ' + selectedLoanID + '. This cannot be undone.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Generate',
                confirmButtonColor: '#1a7a3a'
            }).then(function(result){
                if (!result.isConfirmed) return;
                $.post('loan_ledger.php', { generate_ledger:1, Loan_ID: selectedLoanID }, function(resp){
                    if (resp && resp.success) {
                        Swal.fire({ icon:'success', title:'Generated', text: resp.rows + ' payment rows created.', confirmButtonColor:'#1a7a3a' });
                        loadLedger(selectedLoanID);
                    } else {
                        Swal.fire('Error', resp.msg || 'Generation failed', 'error');
                    }
                }, 'json').fail(function(){ Swal.fire('Error','Server error','error'); });
            });
        });
    });
    </script>
</body>
</html>
