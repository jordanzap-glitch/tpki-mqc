<?php
session_start();
include __DIR__ . '/../db/dbcon.php';

// Simple JSON endpoint for loan-side fetching
if (isset($_GET['fetch_loans'])) {
    $out = ['data' => []];
    $sql = "SELECT l.id, l.Loan_ID, l.Client_ID, c.Last_Name, c.First_Name, l.Loan_Type, l.Effective_Date, l.Maturity_Date, l.Loan_Amount, l.Total_Amount, l.Loan_Status FROM tbl_loan_info l LEFT JOIN tbl_client_info c ON l.Client_ID = c.Client_ID ORDER BY l.id DESC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $out['data'][] = $row;
        }
        mysqli_free_result($res);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out);
    exit;
}

// Fetch single loan
if (isset($_GET['get_loan'])) {
    $id = intval($_GET['get_loan']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM tbl_loan_info WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    // Convert moa_pic blob to base64 for JSON transport
    if ($row && !empty($row['moa_pic'])) {
        $blob = $row['moa_pic'];
        $mime = 'application/octet-stream';
        if (substr($blob, 0, 4) === "\x89PNG") $mime = 'image/png';
        elseif (substr($blob, 0, 2) === "\xFF\xD8") $mime = 'image/jpeg';
        elseif (substr($blob, 0, 4) === '%PDF') $mime = 'application/pdf';
        $row['moa_pic_base64'] = 'data:' . $mime . ';base64,' . base64_encode($blob);
        $row['moa_pic_mime'] = $mime;
    } else {
        $row['moa_pic_base64'] = '';
        $row['moa_pic_mime'] = '';
    }
    unset($row['moa_pic']); // Don't send raw blob in JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => (bool)$row, 'data' => $row]);
    exit;
}

// Update loan (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_loan'])) {
    $id = intval($_POST['id']);
    $Loan_ID = $_POST['Loan_ID'] ?? null;
    $Client_ID = $_POST['Client_ID'] ?? null;
    $Loan_Type = $_POST['Loan_Type'] ?? null;
    $Loan_Cycle = $_POST['Loan_Cycle'] ?? null;
    $Effective_Date = $_POST['Effective_Date'] ?? null;
    $Maturity_Date = $_POST['Maturity_Date'] ?? null;
    $Premium = $_POST['Premium'] !== '' ? floatval($_POST['Premium']) : null;
    $Benefit = $_POST['Benefit'] !== '' ? floatval($_POST['Benefit']) : null;
    $Loan_Amount = $_POST['Loan_Amount'] !== '' ? floatval($_POST['Loan_Amount']) : null;
    $No_of_Months = $_POST['No_of_Months'] !== '' ? intval($_POST['No_of_Months']) : null;
    $Payment_Mode = $_POST['Payment_Mode'] ?? null;
    $No_of_Periods = $_POST['No_of_Periods'] !== '' ? intval($_POST['No_of_Periods']) : null;
    $Interest_Rate_ID = $_POST['Interest_Rate_ID'] ?? null;
    $Total_Interest_Rate = $_POST['Total_Interest_Rate'] !== '' ? floatval($_POST['Total_Interest_Rate']) : null;
    $Total_Interest = $_POST['Total_Interest'] !== '' ? floatval($_POST['Total_Interest']) : null;
    $Total_Amount = $_POST['Total_Amount'] !== '' ? floatval($_POST['Total_Amount']) : null;
    $Fixed_Amount = $_POST['Fixed_Amount'] !== '' ? floatval($_POST['Fixed_Amount']) : null;
    $Loan_Status = $_POST['Loan_Status'] ?? null;
    $Employee_ID = $_POST['Employee_ID'] ?? null;

    $sql = "UPDATE tbl_loan_info SET Loan_ID=?, Client_ID=?, Loan_Type=?, Loan_Cycle=?, Effective_Date=?, Maturity_Date=?, Premium=?, Benefit=?, Loan_Amount=?, No_of_Months=?, Payment_Mode=?, No_of_Periods=?, Interest_Rate_ID=?, Total_Interest_Rate=?, Total_Interest=?, Total_Amount=?, Fixed_Amount=?, Loan_Status=?, Employee_ID=? WHERE id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssssdddisisddddssi', $Loan_ID, $Client_ID, $Loan_Type, $Loan_Cycle, $Effective_Date, $Maturity_Date, $Premium, $Benefit, $Loan_Amount, $No_of_Months, $Payment_Mode, $No_of_Periods, $Interest_Rate_ID, $Total_Interest_Rate, $Total_Interest, $Total_Amount, $Fixed_Amount, $Loan_Status, $Employee_ID, $id);
    $ok = mysqli_stmt_execute($stmt);
    // Handle moa_pic file upload (blob)
    if ($ok && !empty($_FILES['moa_pic_file']['tmp_name']) && $_FILES['moa_pic_file']['error'] === UPLOAD_ERR_OK) {
        $moa_data = file_get_contents($_FILES['moa_pic_file']['tmp_name']);
        $mstmt = mysqli_prepare($conn, "UPDATE tbl_loan_info SET moa_pic = ? WHERE id = ?");
        $null = null;
        mysqli_stmt_bind_param($mstmt, 'bi', $null, $id);
        mysqli_stmt_send_long_data($mstmt, 0, $moa_data);
        mysqli_stmt_execute($mstmt);
        mysqli_stmt_close($mstmt);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

// Delete loan (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_loan'])) {
    $id = intval($_POST['id']);
    $stmt = mysqli_prepare($conn, "DELETE FROM tbl_loan_info WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

// Set loan status (approve/deny)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_status'])) {
    $id = intval($_POST['id']);
    $status = strtoupper(trim($_POST['status'] ?? ''));
    if (!in_array($status, ['PENDING','APPROVED','DENIED'])) $status = 'PENDING';
    $stmt = mysqli_prepare($conn, "UPDATE tbl_loan_info SET Loan_Status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    $ok = mysqli_stmt_execute($stmt);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => (bool)$ok, 'status' => $status]);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>TPKI || Loan Records</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <?php include "includes/head.php"; ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
    .table-responsive::-webkit-scrollbar { height:12px; width:12px; }
    .table-responsive::-webkit-scrollbar-thumb { background:#000; border-radius:6px; }
    .table-responsive::-webkit-scrollbar-track { background:#333; }
    .table-responsive { scrollbar-color: #000 #333; scrollbar-width: thin; }
    .table th:first-child, .table td:first-child { width:44px; padding:0.35rem 0.5rem; text-align:center; vertical-align:middle; }
    .table tbody td { text-transform: none; }
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

        <div class="content">
           <?php include "includes/navbar.php"; ?>

           <div class="container-fluid pt-4 px-4">
               <div class="bg-secondary text-start rounded p-4">
                   <div class="d-flex align-items-center justify-content-between mb-4">
                       <h6 class="mb-0">Loan Records</h6>
                   </div>

                   <div class="table-responsive">
                       <table id="recordsTable" class="table table-striped table-bordered mb-0" style="width:100%">
                           <thead>
                               <tr>
                                   <th><input class="form-check-input" type="checkbox"></th>
                                   <th>Loan ID</th>
                                   <th>Client</th>
                                   <th>Loan Type</th>
                                   <th>Effective Date</th>
                                   <th>Maturity Date</th>
                                   <th>Loan Amount</th>
                                   <th>Total Amount</th>
                                   <th>Action</th>
                                   <th style="width:160px;">Status</th>
                               </tr>
                           </thead>
                           <tbody>
                               <tr><td colspan="10" class="text-center">No records loaded</td></tr>
                           </tbody>
                       </table>

                       <!-- Loan View Modal -->
                       <div class="modal fade" id="loanViewModal" tabindex="-1" aria-labelledby="loanViewLabel" aria-hidden="true">
                           <div class="modal-dialog modal-lg modal-dialog-centered">
                               <div class="modal-content bg-dark text-white">
                                   <div class="modal-header">
                                       <h5 class="modal-title" id="loanViewLabel">Loan Details</h5>
                                       <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                   </div>
                                   <div class="modal-body">
                                       <div class="row gy-2">
                                           <div class="col-6"><div class="text-muted small">Loan ID</div><div id="lvLoanID"></div></div>
                                           <div class="col-6"><div class="text-muted small">Client ID</div><div id="lvClient"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Loan Type</div><div id="lvType"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Loan Cycle</div><div id="lvCycle"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Effective Date</div><div id="lvEff"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Maturity Date</div><div id="lvMat"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Premium</div><div id="lvPremium"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Benefit</div><div id="lvBenefit"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Loan Amount</div><div id="lvLoanAmt"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">No. of Months</div><div id="lvNoMonths"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Payment Mode</div><div id="lvPaymentMode"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">No. of Periods</div><div id="lvNoPeriods"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Interest Rate ID</div><div id="lvInterestRateID"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Total Interest Rate</div><div id="lvTotalInterestRate"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Total Interest</div><div id="lvTotalInterest"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Total Amount</div><div id="lvTotalAmt"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Fixed Amount</div><div id="lvFixedAmt"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Loan Status</div><div id="lvStatus"></div></div>
                                           <div class="col-4 mt-2"><div class="text-muted small">Employee ID</div><div id="lvEmployeeID"></div></div>
                                       </div>
                                       <hr class="border-secondary mt-3">
                                       <div class="text-muted small">MOA / Salary Proof</div>
                                       <div id="lvMoaPic" class="mt-1"></div>
                                   </div>
                                   <div class="modal-footer">
                                       <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                   </div>
                               </div>
                           </div>
                       </div>

                       <!-- Loan Edit Modal -->
                       <div class="modal fade" id="loanEditModal" tabindex="-1" aria-labelledby="loanEditLabel" aria-hidden="true">
                           <div class="modal-dialog modal-lg modal-dialog-centered">
                               <div class="modal-content bg-dark text-white">
                                   <div class="modal-header">
                                       <h5 class="modal-title" id="loanEditLabel">Edit Loan</h5>
                                       <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                   </div>
                                   <form id="loanEditForm" enctype="multipart/form-data">
                                   <div class="modal-body">
                                       <input type="hidden" id="edit_id" name="id">
                                       <div class="row g-2">
                                           <div class="col-md-6"><label class="small text-muted">Loan ID</label><input id="edit_Loan_ID" name="Loan_ID" class="form-control form-control-sm"></div>
                                           <div class="col-md-6"><label class="small text-muted">Client ID</label><input id="edit_Client_ID" name="Client_ID" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Loan Type</label><input id="edit_Loan_Type" name="Loan_Type" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Effective Date</label><input id="edit_Effective_Date" name="Effective_Date" type="date" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Maturity Date</label><input id="edit_Maturity_Date" name="Maturity_Date" type="date" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Loan Amount</label><input id="edit_Loan_Amount" name="Loan_Amount" type="number" step="0.01" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">No. of Months</label><input id="edit_No_of_Months" name="No_of_Months" type="number" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Payment Mode</label><input id="edit_Payment_Mode" name="Payment_Mode" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">No. of Periods</label><input id="edit_No_of_Periods" name="No_of_Periods" type="number" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Interest Rate ID</label><input id="edit_Interest_Rate_ID" name="Interest_Rate_ID" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Total Interest Rate</label><input id="edit_Total_Interest_Rate" name="Total_Interest_Rate" type="number" step="0.0001" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Total Interest</label><input id="edit_Total_Interest" name="Total_Interest" type="number" step="0.01" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Total Amount</label><input id="edit_Total_Amount" name="Total_Amount" type="number" step="0.01" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Fixed Amount</label><input id="edit_Fixed_Amount" name="Fixed_Amount" type="number" step="0.01" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Loan Status</label><input id="edit_Loan_Status" name="Loan_Status" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Employee ID</label><input id="edit_Employee_ID" name="Employee_ID" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Premium</label><input id="edit_Premium" name="Premium" type="number" step="0.01" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Benefit</label><input id="edit_Benefit" name="Benefit" type="number" step="0.01" class="form-control form-control-sm"></div>
                                           <div class="col-md-4 mt-2"><label class="small text-muted">Loan Cycle</label><input id="edit_Loan_Cycle" name="Loan_Cycle" class="form-control form-control-sm"></div>
                                           <div class="col-md-6 mt-2"><label class="small text-muted">MOA / Salary Proof</label><input id="edit_moa_pic_file" name="moa_pic_file" type="file" accept=".pdf,image/png,image/jpeg" class="form-control form-control-sm"><small class="text-muted">Leave empty to keep current file</small></div>
                                           <div class="col-12 mt-2" id="edit_moa_preview"></div>
                                       </div>
                                   </div>
                                   <div class="modal-footer">
                                       <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                       <button type="submit" class="btn btn-primary">Save changes</button>
                                   </div>
                                   </form>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
           </div>

           <?php include 'includes/footer.php'; ?>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        $('#recordsTable').DataTable({
            ajax: {
                url: 'loan_record.php',
                data: { fetch_loans: 1 },
                dataSrc: 'data',
                error: function(xhr, status, error) {
                    console.error('Loan fetch error', status, error, xhr.responseText);
                }
            },
            paging: true,
            searching: true,
            info: true,
            ordering: true,
            columns: [
                { data: null, orderable: false, render: function(){ return '<input class="form-check-input" type="checkbox">'; } },
                { data: 'Loan_ID' },
                { data: null, render: function(data){ var ln = data.Last_Name || ''; var fn = data.First_Name || ''; var name = (ln + ', ' + fn).trim(); return name.toUpperCase(); } },
                { data: 'Loan_Type', render: function(data, type, row){
                        var map = { '1':'PERSONAL', '2':'SALARY', '3':'GROUP' };
                        var key = String(data);
                        return (map[key] || String(data || '').toUpperCase());
                    }
                },
                { data: 'Effective_Date' },
                { data: 'Maturity_Date' },
                { data: 'Loan_Amount', render: $.fn.dataTable.render.number( ',', '.', 2, '' ) },
                { data: 'Total_Amount', render: $.fn.dataTable.render.number( ',', '.', 2, '' ) },
                { data: null, orderable: false, render: function(data, type, row){
                        var id = row.id || '';
                        var loanid = row.Loan_ID || '';
                        var client = ((row.Last_Name||'') + ', ' + (row.First_Name||'')).toUpperCase();
                        var typev = (function(v){ var m={'1':'PERSONAL','2':'SALARY','3':'GROUP'}; return m[String(v)]||String(v||'').toUpperCase(); })(row.Loan_Type);
                        var eff = (row.Effective_Date || '');
                        var mat = (row.Maturity_Date || '');
                        var loanAmt = row.Loan_Amount || '';
                        var totalAmt = row.Total_Amount || '';
                        var viewBtn = '<button type="button" class="btn btn-sm btn-primary view-loan me-1" data-id="'+id+'" title="View">' + '<i class="bi bi-eye"></i></button>';
                        var editBtn = '<button type="button" class="btn btn-sm btn-warning edit-loan me-1" data-id="'+id+'" title="Edit">' + '<i class="bi bi-pencil"></i></button>';
                        var delBtn = '<button type="button" class="btn btn-sm btn-danger delete-loan" data-id="'+id+'" title="Delete">' + '<i class="bi bi-trash"></i></button>';
                        return '<div class="text-nowrap">' + viewBtn + editBtn + delBtn + '</div>';
                    } },
                { data: 'Loan_Status', render: function(data, type, row){
                        var s = (data||'').toString().trim().toUpperCase();
                        if (!s) s = 'PENDING';
                        var cls = 'bg-secondary';
                        if (s === 'PENDING') cls = 'bg-warning text-dark';
                        if (s === 'APPROVED') cls = 'bg-success';
                        if (s === 'DENIED') cls = 'bg-danger';
                        var badge = '<span class="badge '+cls+'">'+s+'</span>';
                        var btns = ' <button class="btn btn-sm btn-success approve-loan" data-id="'+(row.id||'')+'" title="Approve"><i class="bi bi-check2"></i></button>'
                                 + ' <button class="btn btn-sm btn-danger deny-loan" data-id="'+(row.id||'')+'" title="Deny">✖</button>';
                        return badge + btns;
                    }
                },
            ],
            initComplete: function(settings, json) {
                try { $('#spinner').removeClass('show'); } catch(e){ $('#spinner').hide(); }
            }
        });

        // View loan (AJAX)
        $(document).on('click', '.view-loan', function(){
            var id = $(this).data('id');
            $.get('loan_record.php', { get_loan: id }, function(resp){
                if (resp && resp.success) {
                    var r = resp.data || {};
                    $('#lvLoanID').text((r.Loan_ID||'').toString().toUpperCase());
                    // show client full name if available else Client_ID
                    var clientName = (r.Last_Name && r.First_Name) ? ((r.Last_Name||'') + ', ' + (r.First_Name||'')) : (r.Client_ID||'');
                    $('#lvClient').text((clientName||'').toString().toUpperCase());
                    var map = {'1':'PERSONAL','2':'SALARY','3':'GROUP'};
                    $('#lvType').text((map[String(r.Loan_Type)]||String(r.Loan_Type||'')).toUpperCase());
                    $('#lvCycle').text((r.Loan_Cycle||'').toString().toUpperCase());
                    $('#lvEff').text((r.Effective_Date||'').toString().toUpperCase());
                    $('#lvMat').text((r.Maturity_Date||'').toString().toUpperCase());
                    $('#lvPremium').text((r.Premium||'').toString());
                    $('#lvBenefit').text((r.Benefit||'').toString());
                    $('#lvLoanAmt').text((r.Loan_Amount||'').toString());
                    $('#lvNoMonths').text((r.No_of_Months||'').toString());
                    $('#lvPaymentMode').text((r.Payment_Mode||'').toString().toUpperCase());
                    $('#lvNoPeriods').text((r.No_of_Periods||'').toString());
                    $('#lvInterestRateID').text((r.Interest_Rate_ID||'').toString());
                    $('#lvTotalInterestRate').text((r.Total_Interest_Rate||'').toString());
                    $('#lvTotalInterest').text((r.Total_Interest||'').toString());
                    $('#lvTotalAmt').text((r.Total_Amount||'').toString());
                    $('#lvFixedAmt').text((r.Fixed_Amount||'').toString());
                    $('#lvStatus').html((r.Loan_Status||'PENDING').toString().toUpperCase());
                    $('#lvEmployeeID').text((r.Employee_ID||'').toString());
                    // Show MOA / Salary Proof
                    var moa = r.moa_pic_base64 || '';
                    var moaMime = r.moa_pic_mime || '';
                    if (moa) {
                        if (moaMime === 'application/pdf') {
                            $('#lvMoaPic').html('<a href="'+moa+'" target="_blank" class="btn btn-sm btn-outline-light"><i class="bi bi-file-earmark-pdf"></i> View PDF</a>');
                        } else {
                            $('#lvMoaPic').html('<img src="'+moa+'" style="max-width:100%;max-height:300px;border-radius:4px;">'); 
                        }
                    } else {
                        $('#lvMoaPic').html('<span class="text-muted">None</span>');
                    }
                    $('#loanViewModal').modal('show');
                } else {
                    Swal.fire('Error','Unable to load loan','error');
                }
            }, 'json').fail(function(){ Swal.fire('Error','Server error','error'); });
        });

        // Open edit modal and populate
        $(document).on('click', '.edit-loan', function(){
            var id = $(this).data('id');
            $.get('loan_record.php', { get_loan: id }, function(resp){
                if (resp && resp.success) {
                    var r = resp.data || {};
                    $('#edit_id').val(r.id);
                    $('#edit_Loan_ID').val(r.Loan_ID);
                    $('#edit_Client_ID').val(r.Client_ID);
                    $('#edit_Loan_Type').val(r.Loan_Type);
                    $('#edit_Loan_Cycle').val(r.Loan_Cycle);
                    $('#edit_Effective_Date').val(r.Effective_Date);
                    $('#edit_Maturity_Date').val(r.Maturity_Date);
                    $('#edit_Premium').val(r.Premium);
                    $('#edit_Benefit').val(r.Benefit);
                    $('#edit_Loan_Amount').val(r.Loan_Amount);
                    $('#edit_No_of_Months').val(r.No_of_Months);
                    $('#edit_Payment_Mode').val(r.Payment_Mode);
                    $('#edit_No_of_Periods').val(r.No_of_Periods);
                    $('#edit_Interest_Rate_ID').val(r.Interest_Rate_ID);
                    $('#edit_Total_Interest_Rate').val(r.Total_Interest_Rate);
                    $('#edit_Total_Interest').val(r.Total_Interest);
                    $('#edit_Total_Amount').val(r.Total_Amount);
                    $('#edit_Fixed_Amount').val(r.Fixed_Amount);
                    $('#edit_Loan_Status').val(r.Loan_Status);
                    $('#edit_Employee_ID').val(r.Employee_ID);
                    // Show current MOA preview in edit modal
                    var eMoa = r.moa_pic_base64 || '';
                    var eMoaMime = r.moa_pic_mime || '';
                    if (eMoa) {
                        if (eMoaMime === 'application/pdf') {
                            $('#edit_moa_preview').html('<a href="'+eMoa+'" target="_blank" class="btn btn-sm btn-outline-light"><i class="bi bi-file-earmark-pdf"></i> Current PDF</a>');
                        } else {
                            $('#edit_moa_preview').html('<img src="'+eMoa+'" style="max-width:200px;max-height:150px;border-radius:4px;">'); 
                        }
                    } else {
                        $('#edit_moa_preview').html('<span class="text-muted small">No file uploaded</span>');
                    }
                    $('#loanEditModal').modal('show');
                } else {
                    Swal.fire('Error','Unable to load loan','error');
                }
            }, 'json').fail(function(){ Swal.fire('Error','Server error','error'); });
        });

        // Save edits (FormData for file upload support)
        $('#loanEditForm').on('submit', function(e){
            e.preventDefault();
            var formData = new FormData(this);
            formData.append('update_loan', 1);
            $.ajax({
                url: 'loan_record.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(resp){
                    if (resp && resp.success) {
                        $('#loanEditModal').modal('hide');
                        $('#recordsTable').DataTable().ajax.reload(null, false);
                        Swal.fire('Saved','Loan updated','success');
                    } else {
                        Swal.fire('Error','Unable to save','error');
                    }
                },
                error: function(){ Swal.fire('Error','Server error','error'); }
            });
        });

        // Delete
        $(document).on('click', '.delete-loan', function(){
            var id = $(this).data('id');
            Swal.fire({ title: 'Delete loan?', text:'This cannot be undone', icon:'warning', showCancelButton:true }).then(function(res){
                if (res.isConfirmed) {
                    $.post('loan_record.php', { delete_loan:1, id: id }, function(resp){
                        if (resp && resp.success) {
                            $('#recordsTable').DataTable().ajax.reload(null, false);
                            Swal.fire('Deleted','Loan removed','success');
                        } else Swal.fire('Error','Unable to delete','error');
                    }, 'json').fail(function(){ Swal.fire('Error','Server error','error'); });
                }
            });
        });

        // Approve / Deny handlers
        $(document).on('click', '.approve-loan, .deny-loan', function(){
            var id = $(this).data('id');
            var isApprove = $(this).hasClass('approve-loan');
            var newStatus = isApprove ? 'APPROVED' : 'DENIED';
            $.post('loan_record.php', { set_status:1, id: id, status: newStatus }, function(resp){
                if (resp && resp.success) {
                    $('#recordsTable').DataTable().ajax.reload(null, false);
                    Swal.fire('Updated','Status set to '+(resp.status||newStatus),'success');
                } else {
                    Swal.fire('Error','Unable to update status','error');
                }
            }, 'json').fail(function(){ Swal.fire('Error','Server error','error'); });
        });
    });
    </script>
</body>

</html>