<?php
session_start();
require_once __DIR__ . '/../db/dbcon.php';

// Handle create income/expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_inc_exp'])) {
    // Generate next Income_Expense_ID in format INC-XXX
    $last_q = mysqli_query($conn, "SELECT Income_Expense_ID FROM tbl_client_inc_exp ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/INC-(\d+)/', $row['Income_Expense_ID'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
    }
    $inc_id = sprintf('INC-%03d', $nextNum);

    $client_id = isset($_POST['Client_ID']) ? trim($_POST['Client_ID']) : '';
    $income_business = isset($_POST['Income_Business']) && $_POST['Income_Business'] !== '' ? floatval($_POST['Income_Business']) : null;
    $income_employment = isset($_POST['Income_Employment']) && $_POST['Income_Employment'] !== '' ? floatval($_POST['Income_Employment']) : null;
    $other_income = isset($_POST['Other_Income']) && $_POST['Other_Income'] !== '' ? floatval($_POST['Other_Income']) : null;
    $food_expenses = isset($_POST['Food_Expenses']) && $_POST['Food_Expenses'] !== '' ? floatval($_POST['Food_Expenses']) : null;
    $rent_expenses = isset($_POST['Rent_Expenses']) && $_POST['Rent_Expenses'] !== '' ? floatval($_POST['Rent_Expenses']) : null;
    $education_expenses = isset($_POST['Education_Expenses']) && $_POST['Education_Expenses'] !== '' ? floatval($_POST['Education_Expenses']) : null;
    $water_expenses = isset($_POST['Water_Expenses']) && $_POST['Water_Expenses'] !== '' ? floatval($_POST['Water_Expenses']) : null;
    $electric_expenses = isset($_POST['Electric_Expenses']) && $_POST['Electric_Expenses'] !== '' ? floatval($_POST['Electric_Expenses']) : null;
    $other_expenses = isset($_POST['Other_Expenses']) && $_POST['Other_Expenses'] !== '' ? floatval($_POST['Other_Expenses']) : null;
    $loan_payments = isset($_POST['Loan_Payments']) && $_POST['Loan_Payments'] !== '' ? floatval($_POST['Loan_Payments']) : null;

    if ($client_id === '') {
        $error = 'Client is required.';
    } else {
        $sql = "INSERT INTO tbl_client_inc_exp (Income_Expense_ID, Client_ID, Income_Business, Income_Employment, Other_Income, Food_Expenses, Rent_Expenses, Education_Expenses, Water_Expenses, Electric_Expenses, Other_Expenses, Loan_Payments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssdddddddddd', $inc_id, $client_id, $income_business, $income_employment, $other_income, $food_expenses, $rent_expenses, $education_expenses, $water_expenses, $electric_expenses, $other_expenses, $loan_payments);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Income/Expense record saved successfully.';
            } else {
                $error = 'Insert failed: ' . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Insert prepare failed: ' . mysqli_error($conn);
        }
    }
}

// Fetch clients for dropdown
$clients = [];
$cq = mysqli_query($conn, "SELECT Client_ID, First_Name, Last_Name FROM tbl_client_info ORDER BY Last_Name, First_Name");
if ($cq) {
    while ($c = mysqli_fetch_assoc($cq)) {
        $clients[] = $c;
    }
}

// Handle delete income/expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_inc_exp'])) {
    $del_id = intval($_POST['delete_inc_exp']);
    $dstmt = mysqli_prepare($conn, "DELETE FROM tbl_client_inc_exp WHERE id = ?");
    if ($dstmt) {
        mysqli_stmt_bind_param($dstmt, 'i', $del_id);
        if (mysqli_stmt_execute($dstmt)) {
            $success = 'Record deleted successfully.';
        } else {
            $error = 'Delete failed: ' . mysqli_stmt_error($dstmt);
        }
        mysqli_stmt_close($dstmt);
    } else {
        $error = 'Delete prepare failed: ' . mysqli_error($conn);
    }
}

// Handle edit/update income/expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edit_inc_exp'])) {
    $eid = intval($_POST['edit_inc_exp']);
    $ecid = isset($_POST['edit_Client_ID']) ? trim($_POST['edit_Client_ID']) : null;
    $eib = isset($_POST['edit_Income_Business']) && $_POST['edit_Income_Business'] !== '' ? floatval($_POST['edit_Income_Business']) : null;
    $eie = isset($_POST['edit_Income_Employment']) && $_POST['edit_Income_Employment'] !== '' ? floatval($_POST['edit_Income_Employment']) : null;
    $eoi = isset($_POST['edit_Other_Income']) && $_POST['edit_Other_Income'] !== '' ? floatval($_POST['edit_Other_Income']) : null;
    $ef = isset($_POST['edit_Food_Expenses']) && $_POST['edit_Food_Expenses'] !== '' ? floatval($_POST['edit_Food_Expenses']) : null;
    $er = isset($_POST['edit_Rent_Expenses']) && $_POST['edit_Rent_Expenses'] !== '' ? floatval($_POST['edit_Rent_Expenses']) : null;
    $ee = isset($_POST['edit_Education_Expenses']) && $_POST['edit_Education_Expenses'] !== '' ? floatval($_POST['edit_Education_Expenses']) : null;
    $ew = isset($_POST['edit_Water_Expenses']) && $_POST['edit_Water_Expenses'] !== '' ? floatval($_POST['edit_Water_Expenses']) : null;
    $eel = isset($_POST['edit_Electric_Expenses']) && $_POST['edit_Electric_Expenses'] !== '' ? floatval($_POST['edit_Electric_Expenses']) : null;
    $eother = isset($_POST['edit_Other_Expenses']) && $_POST['edit_Other_Expenses'] !== '' ? floatval($_POST['edit_Other_Expenses']) : null;
    $eloan = isset($_POST['edit_Loan_Payments']) && $_POST['edit_Loan_Payments'] !== '' ? floatval($_POST['edit_Loan_Payments']) : null;

    $usql = "UPDATE tbl_client_inc_exp SET Client_ID=?, Income_Business=?, Income_Employment=?, Other_Income=?, Food_Expenses=?, Rent_Expenses=?, Education_Expenses=?, Water_Expenses=?, Electric_Expenses=?, Other_Expenses=?, Loan_Payments=? WHERE id=?";
    $ustmt = mysqli_prepare($conn, $usql);
    if ($ustmt) {
        mysqli_stmt_bind_param($ustmt, 'sddddddddddi', $ecid, $eib, $eie, $eoi, $ef, $er, $ee, $ew, $eel, $eother, $eloan, $eid);
        if (mysqli_stmt_execute($ustmt)) {
            $success = 'Record updated successfully.';
        } else {
            $error = 'Update failed: ' . mysqli_stmt_error($ustmt);
        }
        mysqli_stmt_close($ustmt);
    } else {
        $error = 'Update prepare failed: ' . mysqli_error($conn);
    }
}

// Fetch income/expense records for display (join client name)
$records = [];
$rq = mysqli_query($conn, "SELECT ie.*, c.First_Name, c.Last_Name, c.Client_ID as client_code FROM tbl_client_inc_exp ie LEFT JOIN tbl_client_info c ON ie.Client_ID = c.Client_ID ORDER BY ie.id DESC");
if ($rq) {
    while ($rr = mysqli_fetch_assoc($rq)) {
        $records[] = $rr;
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
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


            <!-- Blank Content Start: keep layout but remove inner content -->
            <div class="container-fluid pt-4 px-4">
                <div class="container-fluid pt-4 px-4">
                    <div class="row bg-secondary rounded p-4 mx-0">
                        <div class="col-md-8">
                            <h5 class="mb-3">Add Client Income / Expense</h5>
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                            <?php elseif (!empty($error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Record ID</label>
                                    <input type="text" class="form-control" value="<?php echo isset($inc_id) ? htmlspecialchars($inc_id) : 'INC-001'; ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Client</label>
                                    <select name="Client_ID" class="form-select select2" style="width:100%">
                                        <option value="">-- Select client --</option>
                                        <?php foreach ($clients as $c):
                                            $label = htmlspecialchars($c['Last_Name'] . ', ' . $c['First_Name'] . ' (' . $c['Client_ID'] . ')');
                                            $val = htmlspecialchars($c['Client_ID']);
                                            echo "<option value=\"$val\">$label</option>";
                                        endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Income (Business)</label>
                                        <input name="Income_Business" type="number" step="0.01" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Income (Employment)</label>
                                        <input name="Income_Employment" type="number" step="0.01" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Other Income</label>
                                        <input name="Other_Income" type="number" step="0.01" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Food Expenses</label>
                                        <input name="Food_Expenses" type="number" step="0.01" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Rent Expenses</label>
                                        <input name="Rent_Expenses" type="number" step="0.01" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Education Expenses</label>
                                        <input name="Education_Expenses" type="number" step="0.01" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Water Expenses</label>
                                        <input name="Water_Expenses" type="number" step="0.01" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Electric Expenses</label>
                                        <input name="Electric_Expenses" type="number" step="0.01" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Other Expenses</label>
                                        <input name="Other_Expenses" type="number" step="0.01" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Loan Payments</label>
                                        <input name="Loan_Payments" type="number" step="0.01" class="form-control">
                                    </div>
                                </div>
                                <input type="hidden" name="save_inc_exp" value="1">
                                <button class="btn btn-primary">Save Record</button>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Records Table Card -->
                <div class="container-fluid pt-4 px-4">
                    <div class="col-12">
                        <div class="bg-secondary rounded p-4">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h6 class="mb-0">Income & Expense Records</h6>
                            </div>
                            <div class="table-responsive">
                                <table id="incExpTable" class="table table-striped table-bordered mb-0" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th><input class="form-check-input" type="checkbox"></th>
                                            <th>Record ID</th>
                                            <th>Client</th>
                                            <th>Income (Business)</th>
                                            <th>Income (Employment)</th>
                                            <th>Other Income</th>
                                            <th style="width:140px">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($records)) {
                                            foreach ($records as $r) {
                                                $rowId = (int)($r['id'] ?? 0);
                                                $rid = htmlspecialchars($r['Income_Expense_ID'] ?? '', ENT_QUOTES);
                                                $clientLabel = htmlspecialchars((($r['Last_Name'] ?? '') . ', ' . ($r['First_Name'] ?? '') . ' (' . ($r['client_code'] ?? '') . ')'));
                                                $ib = htmlspecialchars($r['Income_Business'] ?? '', ENT_QUOTES);
                                                $ie = htmlspecialchars($r['Income_Employment'] ?? '', ENT_QUOTES);
                                                $oi = htmlspecialchars($r['Other_Income'] ?? '', ENT_QUOTES);
                                                $data_attrs = 'data-id="' . $rowId . '" data-recordid="' . $rid . '" data-clientid="' . htmlspecialchars($r['Client_ID'] ?? '', ENT_QUOTES) . '"';
                                                $data_attrs .= ' data-ib="' . $ib . '" data-ie="' . $ie . '" data-oi="' . $oi . '"';
                                                // include full dataset as well for modals
                                                $data_attrs .= ' data-f="' . htmlspecialchars($r['Food_Expenses'] ?? '', ENT_QUOTES) . '"';
                                                $data_attrs .= ' data-re="' . htmlspecialchars($r['Rent_Expenses'] ?? '', ENT_QUOTES) . '"';
                                                $data_attrs .= ' data-ee="' . htmlspecialchars($r['Education_Expenses'] ?? '', ENT_QUOTES) . '"';
                                                $data_attrs .= ' data-we="' . htmlspecialchars($r['Water_Expenses'] ?? '', ENT_QUOTES) . '"';
                                                $data_attrs .= ' data-el="' . htmlspecialchars($r['Electric_Expenses'] ?? '', ENT_QUOTES) . '"';
                                                $data_attrs .= ' data-oe="' . htmlspecialchars($r['Other_Expenses'] ?? '', ENT_QUOTES) . '"';
                                                $data_attrs .= ' data-loan="' . htmlspecialchars($r['Loan_Payments'] ?? '', ENT_QUOTES) . '"';
                                                echo '<tr>';
                                                echo '<td><input class="form-check-input" type="checkbox"></td>';
                                                echo '<td>' . $rid . '</td>';
                                                echo '<td>' . $clientLabel . '</td>';
                                                echo '<td>' . $ib . '</td>';
                                                echo '<td>' . $ie . '</td>';
                                                echo '<td>' . $oi . '</td>';
                                                echo '<td class="text-nowrap">';
                                                echo '<button type="button" class="btn btn-sm btn-primary view-record me-1" data-bs-toggle="modal" data-bs-target="#incExpViewModal" ' . $data_attrs . '><i class="bi bi-eye"></i></button>';
                                                echo '<button type="button" class="btn btn-sm btn-warning edit-record-btn me-1" data-bs-toggle="modal" data-bs-target="#incExpEditModal" ' . $data_attrs . '><i class="bi bi-pencil"></i></button>';
                                                echo '<form method="post" class="d-inline delete-record-form">';
                                                echo '<input type="hidden" name="delete_inc_exp" value="' . $rowId . '">';
                                                echo '<button type="button" class="btn btn-sm btn-danger del-record"><i class="bi bi-trash"></i></button>';
                                                echo '</form>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="7" class="text-center">No records found</td></tr>';
                                        } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                    <!-- Blank Content End -->

                    <!-- Income/Expense View Modal -->
                    <div class="modal fade" id="incExpViewModal" tabindex="-1" aria-labelledby="incExpViewLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content bg-dark text-white">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="incExpViewLabel">Income & Expense Details</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="d-flex mb-3">
                                        <div id="ivIcon" class="me-3" style="width:96px;height:96px;background:#222;display:flex;align-items:center;justify-content:center;border-radius:6px;overflow:hidden">
                                            <i class="bi bi-receipt" style="font-size:32px;color:#fff;margin:auto"></i>
                                        </div>
                                        <div>
                                            <h5 id="ivRecordID" class="mb-0"></h5>
                                            <div class="text-muted" id="ivClient"></div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <dl class="row mb-0">
                                                <dt class="col-5 text-muted">Income (Business)</dt><dd class="col-7" id="ivIB"></dd>
                                                <dt class="col-5 text-muted">Income (Employment)</dt><dd class="col-7" id="ivIE"></dd>
                                                <dt class="col-5 text-muted">Other Income</dt><dd class="col-7" id="ivOI"></dd>
                                                <dt class="col-5 text-muted">Food Expenses</dt><dd class="col-7" id="ivFood"></dd>
                                                <dt class="col-5 text-muted">Rent Expenses</dt><dd class="col-7" id="ivRent"></dd>
                                            </dl>
                                        </div>
                                        <div class="col-md-6">
                                            <dl class="row mb-0">
                                                <dt class="col-5 text-muted">Education Expenses</dt><dd class="col-7" id="ivEdu"></dd>
                                                <dt class="col-5 text-muted">Water Expenses</dt><dd class="col-7" id="ivWater"></dd>
                                                <dt class="col-5 text-muted">Electric Expenses</dt><dd class="col-7" id="ivElectric"></dd>
                                                <dt class="col-5 text-muted">Other Expenses</dt><dd class="col-7" id="ivOther"></dd>
                                                <dt class="col-5 text-muted">Loan Payments</dt><dd class="col-7" id="ivLoan"></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Income/Expense Edit Modal -->
                    <div class="modal fade" id="incExpEditModal" tabindex="-1" aria-labelledby="incExpEditLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content bg-dark text-white">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="incExpEditLabel">Edit Income & Expense</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="edit_inc_exp" id="edit_inc_exp">
                                    <div class="row g-2">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Client</label>
                                            <select name="edit_Client_ID" id="edit_Client_ID" class="form-select select2" style="width:100%">
                                                <option value="">-- Select client --</option>
                                                <?php foreach ($clients as $c):
                                                    $label = htmlspecialchars($c['Last_Name'] . ', ' . $c['First_Name'] . ' (' . $c['Client_ID'] . ')');
                                                    $val = htmlspecialchars($c['Client_ID']);
                                                    echo "<option value=\"$val\">$label</option>";
                                                endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Income (Business)</label>
                                            <input name="edit_Income_Business" id="edit_Income_Business" type="number" step="0.01" class="form-control">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Income (Employment)</label>
                                            <input name="edit_Income_Employment" id="edit_Income_Employment" type="number" step="0.01" class="form-control">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Other Income</label>
                                            <input name="edit_Other_Income" id="edit_Other_Income" type="number" step="0.01" class="form-control">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Food Expenses</label>
                                            <input name="edit_Food_Expenses" id="edit_Food_Expenses" type="number" step="0.01" class="form-control">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Rent Expenses</label>
                                            <input name="edit_Rent_Expenses" id="edit_Rent_Expenses" type="number" step="0.01" class="form-control">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Education Expenses</label>
                                            <input name="edit_Education_Expenses" id="edit_Education_Expenses" type="number" step="0.01" class="form-control">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Water Expenses</label>
                                            <input name="edit_Water_Expenses" id="edit_Water_Expenses" type="number" step="0.01" class="form-control">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Electric Expenses</label>
                                            <input name="edit_Electric_Expenses" id="edit_Electric_Expenses" type="number" step="0.01" class="form-control">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Other Expenses</label>
                                            <input name="edit_Other_Expenses" id="edit_Other_Expenses" type="number" step="0.01" class="form-control">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Loan Payments</label>
                                            <input name="edit_Loan_Payments" id="edit_Loan_Payments" type="number" step="0.01" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save changes</button>
                                </div>
                                </form>
                            </div>
                        </div>
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

    <!-- Template Javascript -->
    <script src="../js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
    $(function(){
        if ($('.select2').length) {
            $('.select2').select2({ width: '100%' });
        }
    });
    $(document).ready(function(){
        if ($('#incExpTable').length) {
            $('#incExpTable').DataTable({ paging:true, searching:true, info:true, ordering:true, columnDefs:[{orderable:false, targets:[0,6]}] });
        }

        // Populate view modal
        $(document).on('click', '.view-record', function(){
            var btn = $(this);
            $('#ivRecordID').text(btn.data('recordid') || '');
            var clientLabel = btn.data('clientid') ? btn.closest('tr').find('td:nth-child(3)').text() : '';
            $('#ivClient').text(clientLabel);
            $('#ivIB').text(btn.data('ib') || '');
            $('#ivIE').text(btn.data('ie') || '');
            $('#ivOI').text(btn.data('oi') || '');
            $('#ivFood').text(btn.data('f') || '');
            $('#ivRent').text(btn.data('re') || '');
            $('#ivEdu').text(btn.data('ee') || '');
            $('#ivWater').text(btn.data('we') || '');
            $('#ivElectric').text(btn.data('el') || '');
            $('#ivOther').text(btn.data('oe') || '');
            $('#ivLoan').text(btn.data('loan') || '');
        });

        // Populate edit modal
        $(document).on('click', '.edit-record-btn', function(){
            var btn = $(this);
            $('#edit_inc_exp').val(btn.data('id') || '');
            $('#edit_Client_ID').val(btn.data('clientid') || '').trigger('change');
            $('#edit_Income_Business').val(btn.data('ib') || '');
            $('#edit_Income_Employment').val(btn.data('ie') || '');
            $('#edit_Other_Income').val(btn.data('oi') || '');
            $('#edit_Food_Expenses').val(btn.data('f') || '');
            $('#edit_Rent_Expenses').val(btn.data('re') || '');
            $('#edit_Education_Expenses').val(btn.data('ee') || '');
            $('#edit_Water_Expenses').val(btn.data('we') || '');
            $('#edit_Electric_Expenses').val(btn.data('el') || '');
            $('#edit_Other_Expenses').val(btn.data('oe') || '');
            $('#edit_Loan_Payments').val(btn.data('loan') || '');
        });

        // Delete confirmation
        $(document).on('click', '.del-record', function(e){
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({title:'Delete record?', text:'This action cannot be undone.', icon:'warning', showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#d33'}).then((res)=>{ if(res.isConfirmed) form.submit(); });
        });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php
    if (!empty($success)) {
        $msg = addslashes($success);
        echo "<script>Swal.fire({icon:'success', title:'Success', text:'{$msg}'});</script>";
    } elseif (!empty($error)) {
        $emsg = addslashes($error);
        echo "<script>Swal.fire({icon:'error', title:'Error', text:'{$emsg}'});</script>";
    }
    ?>
</body>

</html>