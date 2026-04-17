<?php
session_start();
require_once __DIR__ . '/../db/dbcon.php';

// ─── AJAX: Fetch approved loans for dropdown ───
if (isset($_GET['fetch_approved_loans'])) {
    $out = ['data' => []];
    $sql = "SELECT l.id, l.Loan_ID, l.Client_ID, c.Last_Name, c.First_Name,
                   l.Loan_Type, l.Loan_Amount, l.Total_Amount, l.Loan_Status
            FROM tbl_loan_info l
            LEFT JOIN tbl_client_info c ON l.Client_ID = c.Client_ID
            WHERE l.Loan_Status = 'APPROVED'
            ORDER BY l.id DESC";
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

    if ($term <= 0) { echo json_encode(['success' => false, 'msg' => 'Loan term is zero or missing.']); exit; }
    if (!$effective_date) { echo json_encode(['success' => false, 'msg' => 'Effective date missing.']); exit; }

    // Compute per-period amounts
    // Total_Amount already includes interest, so payment per period = Total_Amount / term
    $payment_per_period   = round($total_amount / $term, 2);
    $principal_per_period = round($loan_amount / $term, 2);
    $interest_per_period  = round($payment_per_period - $principal_per_period, 2);

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

    $beginning_balance = $total_amount;
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

        $princ = $principal_per_period;
        $inter = $interest_per_period;
        $total_pay = $payment_per_period;

        // Last period: absorb any rounding difference
        if ($i === $term - 1) {
            $total_pay = round($beginning_balance, 2);
            $princ = round($total_pay - $inter, 2);
        }

        $ending_balance = round($beginning_balance - $total_pay, 2);
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
    .table-responsive::-webkit-scrollbar { height:12px; width:12px; }
    .table-responsive::-webkit-scrollbar-thumb { background:#000; border-radius:6px; }
    .table-responsive::-webkit-scrollbar-track { background:#333; }
    .table-responsive { scrollbar-color: #000 #333; scrollbar-width: thin; }
    .summary-label { font-size:.85rem; color:#adb5bd; }
    .summary-value { font-size:1rem; font-weight:600; }
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

            <div class="container-fluid pt-4 px-4">
                <!-- Loan Selector -->
                <div class="bg-secondary rounded p-4 mb-4">
                    <h6 class="mb-3">Loan Ledger</h6>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Select Approved Loan</label>
                            <select id="loanSelect" class="form-select" style="width:100%">
                                <option value="">-- Select Loan --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="button" id="btnPreview" class="btn btn-primary w-100">Preview</button>
                        </div>
                        <div class="col-md-3">
                            <button type="button" id="btnGenerate" class="btn btn-success w-100" disabled>Generate Ledger</button>
                        </div>
                    </div>
                </div>

                <!-- Loan Summary Card -->
                <div id="loanSummary" class="bg-secondary rounded p-4 mb-4" style="display:none">
                    <h6 class="mb-3">Loan Summary</h6>
                    <div class="row g-2">
                        <div class="col-md-3"><div class="summary-label">Loan ID</div><div class="summary-value" id="sLoanID"></div></div>
                        <div class="col-md-3"><div class="summary-label">Client</div><div class="summary-value" id="sClient"></div></div>
                        <div class="col-md-3"><div class="summary-label">Loan Type</div><div class="summary-value" id="sType"></div></div>
                        <div class="col-md-3"><div class="summary-label">Loan Cycle</div><div class="summary-value" id="sCycle"></div></div>
                        <div class="col-md-3 mt-2"><div class="summary-label">Effective Date</div><div class="summary-value" id="sEff"></div></div>
                        <div class="col-md-3 mt-2"><div class="summary-label">Maturity Date</div><div class="summary-value" id="sMat"></div></div>
                        <div class="col-md-3 mt-2"><div class="summary-label">Loan Amount</div><div class="summary-value" id="sLoanAmt"></div></div>
                        <div class="col-md-3 mt-2"><div class="summary-label">Total Interest</div><div class="summary-value" id="sTotalInt"></div></div>
                        <div class="col-md-3 mt-2"><div class="summary-label">Total Amount</div><div class="summary-value" id="sTotalAmt"></div></div>
                        <div class="col-md-3 mt-2"><div class="summary-label">Term (Payments)</div><div class="summary-value" id="sTerm"></div></div>
                        <div class="col-md-3 mt-2"><div class="summary-label">No. of Periods</div><div class="summary-value" id="sPeriods"></div></div>
                        <div class="col-md-3 mt-2"><div class="summary-label">Payment Mode</div><div class="summary-value" id="sPayMode"></div></div>
                    </div>
                </div>

                <!-- Ledger Table -->
                <div id="ledgerSection" class="bg-secondary rounded p-4" style="display:none">
                    <h6 class="mb-3">Amortization Schedule</h6>
                    <div class="table-responsive">
                        <table id="ledgerTable" class="table table-striped table-bordered mb-0" style="width:100%">
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
                                    <th style="width:100px;">Action</th>
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

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../lib/chart/chart.min.js"></script>
    <script src="../lib/easing/easing.min.js"></script>
    <script src="../lib/waypoints/waypoints.min.js"></script>
    <script src="../lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="../lib/tempusdominus/js/moment.min.js"></script>
    <script src="../lib/tempusdominus/js/moment-timezone.min.js"></script>
    <script src="../lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>
    <script src="../js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(function(){
        var typeMap = {'1':'PERSONAL','2':'SALARY','3':'GROUP'};
        var selectedLoanID = '';
        var ledgerDT = null;

        // Init select2
        $('#loanSelect').select2({ placeholder:'-- Select Loan --', allowClear:true, width:'100%' });

        // Load approved loans into dropdown
        $.getJSON('loan_ledger.php', { fetch_approved_loans:1 }).done(function(res){
            if (res && res.data) {
                res.data.forEach(function(l){
                    var label = l.Loan_ID + ' — ' + ((l.Last_Name||'')+', '+(l.First_Name||'')).toUpperCase()
                              + ' — ₱' + parseFloat(l.Total_Amount||0).toLocaleString();
                    $('#loanSelect').append(new Option(label, l.Loan_ID, false, false));
                });
            }
            $('#spinner').removeClass('show');
        }).fail(function(){ $('#spinner').removeClass('show'); });

        function fmt(n){
            return parseFloat(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
        }

        // ── Preview button ──
        $('#btnPreview').on('click', function(){
            selectedLoanID = $('#loanSelect').val();
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
                $('#sCycle').text(d.Loan_Cycle || '');
                $('#sEff').text(d.Effective_Date || '');
                $('#sMat').text(d.Maturity_Date || '');
                $('#sLoanAmt').text('₱ ' + fmt(d.Loan_Amount));
                $('#sTotalInt').text('₱ ' + fmt(d.Total_Interest));
                $('#sTotalAmt').text('₱ ' + fmt(d.Total_Amount));
                $('#sTerm').text(d.term || '');
                $('#sPeriods').text(d.No_of_Periods || '');
                $('#sPayMode').text((d.Payment_Mode||'').toUpperCase());
                // Hide the No. of Periods field in the preview
                $('#sPeriods').closest('.col-md-3').hide();
                $('#loanSummary').slideDown();
                $('#btnGenerate').prop('disabled', false);
                loadLedger(selectedLoanID);
            });
        });

        // ── Load existing ledger rows (auto-apply penalties first) ──
        function loadLedger(loanID){
            // First, apply penalties on overdue PENDING rows
            $.post('loan_ledger.php', { apply_penalties:1, Loan_ID: loanID }, function(){
                // Then fetch the updated ledger
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
                                { data: 'Payment_ID' },
                                { data: 'Payment_Date' },
                                { data: 'Beginning_Balance', render: function(d){ return fmt(d); } },
                                { data: 'Principal_Payment', render: function(d){ return fmt(d); } },
                                { data: 'Interest_Payment', render: function(d){ return fmt(d); } },
                                { data: 'Penalty', render: function(d){ return fmt(d); } },
                                { data: 'Total_Payment', render: function(d){ return fmt(d); } },
                                { data: 'Ending_Balance', render: function(d){ return fmt(d); } },
                                { data: 'Payment_Status', render: function(d){
                                    var s = (d||'').toUpperCase();
                                    var cls = 'bg-secondary';
                                    if (s === 'PENDING') cls = 'bg-warning text-dark';
                                    if (s === 'POSTED') cls = 'bg-success';
                                    if (s === 'PAID') cls = 'bg-success';
                                    return '<span class="badge '+cls+'">'+s+'</span>';
                                }},
                                { data: null, orderable: false, render: function(d){
                                    var s = (d.Payment_Status||'').toUpperCase();
                                    if (s === 'PENDING') {
                                        return '<button class="btn btn-sm btn-success btn-approve" data-id="'+d.id+'" title="Approve Payment">✓ Pay</button>';
                                    }
                                    return '<span class="text-muted">—</span>';
                                }}
                            ]
                        });
                    } else {
                        $('#btnGenerate').prop('disabled', false);
                        $('#ledgerTable tbody').html(
                            '<tr><td colspan="11" class="text-center">No ledger entries yet. Click "Generate Ledger" to create the amortization schedule.</td></tr>'
                        );
                    }
                });
            }, 'json');
        }

        // ── Approve (Pay) a single payment ──
        $(document).on('click', '.btn-approve', function(){
            var btn = $(this);
            var payId = btn.data('id');
            Swal.fire({
                title: 'Approve Payment?',
                text: 'Mark this payment as PAID?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Approve'
            }).then(function(result){
                if (!result.isConfirmed) return;
                $.post('loan_ledger.php', { approve_payment:1, id: payId }, function(resp){
                    if (resp && resp.success) {
                        Swal.fire('Approved', 'Payment marked as PAID.', 'success');
                        loadLedger(selectedLoanID);
                    } else {
                        Swal.fire('Error', resp.msg || 'Approval failed', 'error');
                    }
                }, 'json').fail(function(){ Swal.fire('Error','Server error','error'); });
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
                confirmButtonText: 'Generate'
            }).then(function(result){
                if (!result.isConfirmed) return;
                $.post('loan_ledger.php', { generate_ledger:1, Loan_ID: selectedLoanID }, function(resp){
                    if (resp && resp.success) {
                        Swal.fire('Generated', resp.rows + ' payment rows created.', 'success');
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