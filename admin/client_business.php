<?php
session_start();
require_once __DIR__ . '/../db/dbcon.php';

// Handle create business
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_business'])) {
    // Generate next Business_ID in format BUS-XXX
    $last_q = mysqli_query($conn, "SELECT Business_ID FROM tbl_client_business ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/BUS-(\d+)/', $row['Business_ID'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
    }
    $business_id = sprintf('BUS-%03d', $nextNum);

    $client_id = isset($_POST['Client_ID']) ? trim($_POST['Client_ID']) : '';
    $business_type = isset($_POST['Business_Type']) ? trim($_POST['Business_Type']) : '';
    $business_name = isset($_POST['Business_Name']) ? trim($_POST['Business_Name']) : '';
    $business_address = isset($_POST['Business_Address']) ? trim($_POST['Business_Address']) : '';
    $date_established = isset($_POST['Date_Established']) ? trim($_POST['Date_Established']) : null;
    $initial_capital = isset($_POST['Initial_Capital']) && $_POST['Initial_Capital'] !== '' ? floatval($_POST['Initial_Capital']) : null;
    $weekly_sales = isset($_POST['Weekly_Sales']) && $_POST['Weekly_Sales'] !== '' ? floatval($_POST['Weekly_Sales']) : null;
    $monthly_income = isset($_POST['Monthly_Income']) && $_POST['Monthly_Income'] !== '' ? floatval($_POST['Monthly_Income']) : null;
    $total_expenses = isset($_POST['Total_Expenses']) && $_POST['Total_Expenses'] !== '' ? floatval($_POST['Total_Expenses']) : null;
    $net_income = isset($_POST['Net_Income']) && $_POST['Net_Income'] !== '' ? floatval($_POST['Net_Income']) : null;

    if ($client_id === '' || $business_name === '') {
        $error = 'Client and Business Name are required.';
    } else {
        $sql = "INSERT INTO tbl_client_business (Business_ID, Client_ID, Business_Type, Business_Name, Business_Address, Date_Established, Initial_Capital, Weekly_Sales, Monthly_Income, Total_Expenses, Net_Income) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssssddddd', $business_id, $client_id, $business_type, $business_name, $business_address, $date_established, $initial_capital, $weekly_sales, $monthly_income, $total_expenses, $net_income);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Business saved successfully.';
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

// Handle delete business
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_business'])) {
    $del_id = intval($_POST['delete_business']);
    $dstmt = mysqli_prepare($conn, "DELETE FROM tbl_client_business WHERE id = ?");
    if ($dstmt) {
        mysqli_stmt_bind_param($dstmt, 'i', $del_id);
        if (mysqli_stmt_execute($dstmt)) {
            $success = 'Business deleted successfully.';
        } else {
            $error = 'Delete failed: ' . mysqli_stmt_error($dstmt);
        }
        mysqli_stmt_close($dstmt);
    } else {
        $error = 'Delete prepare failed: ' . mysqli_error($conn);
    }
}

// Handle edit/update business
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edit_business'])) {
    $eid = intval($_POST['edit_business']);
    $ecid = isset($_POST['edit_Client_ID']) ? trim($_POST['edit_Client_ID']) : null;
    $etype = isset($_POST['edit_Business_Type']) ? trim($_POST['edit_Business_Type']) : null;
    $ename = isset($_POST['edit_Business_Name']) ? trim($_POST['edit_Business_Name']) : null;
    $eaddr = isset($_POST['edit_Business_Address']) ? trim($_POST['edit_Business_Address']) : null;
    $edate = isset($_POST['edit_Date_Established']) ? trim($_POST['edit_Date_Established']) : null;
    $einit = isset($_POST['edit_Initial_Capital']) && $_POST['edit_Initial_Capital'] !== '' ? floatval($_POST['edit_Initial_Capital']) : null;
    $ews = isset($_POST['edit_Weekly_Sales']) && $_POST['edit_Weekly_Sales'] !== '' ? floatval($_POST['edit_Weekly_Sales']) : null;
    $emin = isset($_POST['edit_Monthly_Income']) && $_POST['edit_Monthly_Income'] !== '' ? floatval($_POST['edit_Monthly_Income']) : null;
    $eexp = isset($_POST['edit_Total_Expenses']) && $_POST['edit_Total_Expenses'] !== '' ? floatval($_POST['edit_Total_Expenses']) : null;
    $enet = isset($_POST['edit_Net_Income']) && $_POST['edit_Net_Income'] !== '' ? floatval($_POST['edit_Net_Income']) : null;

    $usql = "UPDATE tbl_client_business SET Client_ID=?, Business_Type=?, Business_Name=?, Business_Address=?, Date_Established=?, Initial_Capital=?, Weekly_Sales=?, Monthly_Income=?, Total_Expenses=?, Net_Income=? WHERE id=?";
    $ustmt = mysqli_prepare($conn, $usql);
    if ($ustmt) {
        mysqli_stmt_bind_param($ustmt, 'sssssdddddi', $ecid, $etype, $ename, $eaddr, $edate, $einit, $ews, $emin, $eexp, $enet, $eid);
        if (mysqli_stmt_execute($ustmt)) {
            $success = 'Business updated successfully.';
        } else {
            $error = 'Update failed: ' . mysqli_stmt_error($ustmt);
        }
        mysqli_stmt_close($ustmt);
    } else {
        $error = 'Update prepare failed: ' . mysqli_error($conn);
    }
}

// Fetch businesses for display
$businesses = [];
$bq = mysqli_query($conn, "SELECT b.*, c.First_Name, c.Last_Name FROM tbl_client_business b LEFT JOIN tbl_client_info c ON b.Client_ID = c.Client_ID ORDER BY b.id DESC");
if ($bq) {
    while ($br = mysqli_fetch_assoc($bq)) {
        $businesses[] = $br;
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
    <style>
    /* Center and size checkbox column */
    .table th:first-child, .table td:first-child {
        width: 44px;
        padding: 0.35rem 0.5rem;
        text-align: center;
        vertical-align: middle;
    }
    .table .form-check-input {
        width: 16px;
        height: 16px;
        margin: 0;
        transform: none;
    }
    /* Force table data to uppercase for visual consistency */
    .table tbody td { text-transform: uppercase; }
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


            <!-- Business Form Start -->
            <div class="container-fluid pt-4 px-4">
                <div class="row bg-secondary rounded p-4 mx-0">
                    <div class="col-md-8">
                        <h5 class="mb-3">Add Client Business</h5>
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Business ID</label>
                                <input type="text" class="form-control" value="<?php echo isset($business_id) ? htmlspecialchars($business_id) : 'BUS-001'; ?>" readonly>
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
                                    <label class="form-label">Business Type</label>
                                    <input name="Business_Type" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Business Name</label>
                                    <input name="Business_Name" class="form-control" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Business Address</label>
                                    <input name="Business_Address" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date Established</label>
                                    <input name="Date_Established" type="date" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Initial Capital</label>
                                    <input name="Initial_Capital" type="number" step="0.01" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Weekly Sales</label>
                                    <input name="Weekly_Sales" type="number" step="0.01" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Monthly Income</label>
                                    <input name="Monthly_Income" type="number" step="0.01" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Total Expenses</label>
                                    <input name="Total_Expenses" type="number" step="0.01" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Net Income</label>
                                    <input name="Net_Income" type="number" step="0.01" class="form-control">
                                </div>
                            </div>
                            <input type="hidden" name="save_business" value="1">
                            <button class="btn btn-primary">Save Business</button>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Business Form End -->

            <!-- Businesses Table Card -->
            <div class="container-fluid pt-4 px-4">
                <div class="col-12">
                    <div class="bg-secondary rounded p-4">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h6 class="mb-0">Client Businesses</h6>
                        </div>
                        <div class="table-responsive">
                            <table id="businessTable" class="table table-striped table-bordered mb-0" style="width:100%">
                                <thead>
                                                            <tr>
                                                                <th><input class="form-check-input" type="checkbox"></th>
                                                                <th>Business ID</th>
                                                                <th>Client</th>
                                                                <th>Business Type</th>
                                                                <th>Business Name</th>
                                                                <th>Business Address</th>
                                                                <th style="width:160px;">Action</th>
                                                            </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($businesses)) {
                                        foreach ($businesses as $idx => $b) {
                                            $rowId = (int)($b['id'] ?? 0);
                                            $bid = htmlspecialchars($b['Business_ID'] ?? '', ENT_QUOTES);
                                            $clientLabel = htmlspecialchars((($b['Last_Name'] ?? '') . ', ' . ($b['First_Name'] ?? '') . ' (' . ($b['Client_ID'] ?? '') . ')'));
                                            $btype = htmlspecialchars($b['Business_Type'] ?? '', ENT_QUOTES);
                                            $bname = htmlspecialchars($b['Business_Name'] ?? '', ENT_QUOTES);
                                            $baddr = htmlspecialchars($b['Business_Address'] ?? '', ENT_QUOTES);
                                            $date_e = htmlspecialchars($b['Date_Established'] ?? '', ENT_QUOTES);
                                            $init = htmlspecialchars($b['Initial_Capital'] ?? '', ENT_QUOTES);
                                            $ws = htmlspecialchars($b['Weekly_Sales'] ?? '', ENT_QUOTES);
                                            $minc = htmlspecialchars($b['Monthly_Income'] ?? '', ENT_QUOTES);
                                            $texp = htmlspecialchars($b['Total_Expenses'] ?? '', ENT_QUOTES);
                                            $net = htmlspecialchars($b['Net_Income'] ?? '', ENT_QUOTES);
                                            $data_attrs = 'data-id="' . $rowId . '" data-businessid="' . $bid . '" data-clientid="' . htmlspecialchars($b['Client_ID'] ?? '', ENT_QUOTES) . '"';
                                            $data_attrs .= ' data-btype="' . $btype . '" data-bname="' . $bname . '" data-baddr="' . $baddr . '"';
                                            $data_attrs .= ' data-date="' . $date_e . '" data-init="' . $init . '" data-ws="' . $ws . '"';
                                            $data_attrs .= ' data-minc="' . $minc . '" data-texp="' . $texp . '" data-net="' . $net . '"';
                                            echo "<tr>";
                                            echo "<td><input class=\"form-check-input\" type=\"checkbox\"></td>";
                                            echo "<td>" . $bid . "</td>";
                                            echo "<td>" . $clientLabel . "</td>";
                                            echo "<td>" . $btype . "</td>";
                                            echo "<td>" . $bname . "</td>";
                                            echo "<td>" . $baddr . "</td>";
                                            echo "<td class=\"text-nowrap\">";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-primary view-business me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#businessViewModal\" $data_attrs><i class=\"bi bi-eye\"></i></button>";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-warning edit-business-btn me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#businessEditModal\" $data_attrs><i class=\"bi bi-pencil\"></i></button>";
                                            echo "<form method=\"post\" class=\"d-inline delete-business-form\">";
                                            echo "<input type=\"hidden\" name=\"delete_business\" value=\"" . $rowId . "\">";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-danger del-business\"><i class=\"bi bi-trash\"></i></button>";
                                            echo "</form>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" class="text-center">No businesses found</td></tr>';
                                    } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Business View Modal -->
            <div class="modal fade" id="businessViewModal" tabindex="-1" aria-labelledby="businessViewLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content bg-dark text-white">
                        <div class="modal-header">
                            <h5 class="modal-title" id="businessViewLabel">Business Details</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="d-flex mb-3">
                                <div id="bizIcon" class="me-3" style="width:96px;height:96px;background:#222;display:flex;align-items:center;justify-content:center;border-radius:6px;overflow:hidden">
                                    <i class="bi bi-shop" style="font-size:32px;color:#fff;margin:auto"></i>
                                </div>
                                <div>
                                    <h5 id="bvName" class="mb-0"></h5>
                                    <div class="text-muted" id="bvBusinessID"></div>
                                    <div class="text-muted" id="bvClient"></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-5 text-muted">Business Type</dt><dd class="col-7" id="bvType"></dd>
                                        <dt class="col-5 text-muted">Date Established</dt><dd class="col-7" id="bvDate"></dd>
                                        <dt class="col-5 text-muted">Initial Capital</dt><dd class="col-7" id="bvInit"></dd>
                                        <dt class="col-5 text-muted">Weekly Sales</dt><dd class="col-7" id="bvWS"></dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-5 text-muted">Monthly Income</dt><dd class="col-7" id="bvMin"></dd>
                                        <dt class="col-5 text-muted">Total Expenses</dt><dd class="col-7" id="bvExp"></dd>
                                        <dt class="col-5 text-muted">Net Income</dt><dd class="col-7" id="bvNet"></dd>
                                        <dt class="col-5 text-muted">Address</dt><dd class="col-7" id="bvAddr"></dd>
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

            <!-- Business Edit Modal -->
            <div class="modal fade" id="businessEditModal" tabindex="-1" aria-labelledby="businessEditLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content bg-dark text-white">
                        <div class="modal-header">
                            <h5 class="modal-title" id="businessEditLabel">Edit Business</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                        <div class="modal-body">
                            <input type="hidden" name="edit_business" id="edit_business">
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
                                    <label class="form-label">Business Type</label>
                                    <input name="edit_Business_Type" id="edit_Business_Type" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Business Name</label>
                                    <input name="edit_Business_Name" id="edit_Business_Name" class="form-control" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Business Address</label>
                                    <input name="edit_Business_Address" id="edit_Business_Address" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date Established</label>
                                    <input name="edit_Date_Established" id="edit_Date_Established" type="date" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Initial Capital</label>
                                    <input name="edit_Initial_Capital" id="edit_Initial_Capital" type="number" step="0.01" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Weekly Sales</label>
                                    <input name="edit_Weekly_Sales" id="edit_Weekly_Sales" type="number" step="0.01" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Monthly Income</label>
                                    <input name="edit_Monthly_Income" id="edit_Monthly_Income" type="number" step="0.01" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Total Expenses</label>
                                    <input name="edit_Total_Expenses" id="edit_Total_Expenses" type="number" step="0.01" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Net Income</label>
                                    <input name="edit_Net_Income" id="edit_Net_Income" type="number" step="0.01" class="form-control">
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
        if ($('#businessTable').length) {
            $('#businessTable').DataTable({ paging:true, searching:true, info:true, ordering:true, columnDefs:[{orderable:false, targets:[0]}] });
        }
        // Populate view modal
        $(document).on('click', '.view-business', function() {
            var btn = $(this);
            $('#bvName').text(btn.data('bname') || '');
            $('#bvBusinessID').text(btn.data('businessid') || '');
            var clientLabel = btn.data('clientid') ? (btn.closest('tr').find('td:nth-child(3)').text()) : '';
            $('#bvClient').text(clientLabel);
            $('#bvType').text(btn.data('btype') || '');
            $('#bvDate').text(btn.data('date') || '');
            $('#bvInit').text(btn.data('init') || '');
            $('#bvWS').text(btn.data('ws') || '');
            $('#bvMin').text(btn.data('minc') || '');
            $('#bvExp').text(btn.data('texp') || '');
            $('#bvNet').text(btn.data('net') || '');
            $('#bvAddr').text(btn.data('baddr') || '');
        });

        // Populate edit modal
        $(document).on('click', '.edit-business-btn', function() {
            var btn = $(this);
            $('#edit_business').val(btn.data('id') || '');
            $('#edit_Client_ID').val(btn.data('clientid') || '').trigger('change');
            $('#edit_Business_Type').val(btn.data('btype') || '');
            $('#edit_Business_Name').val(btn.data('bname') || '');
            $('#edit_Business_Address').val(btn.data('baddr') || '');
            $('#edit_Date_Established').val(btn.data('date') || '');
            $('#edit_Initial_Capital').val(btn.data('init') || '');
            $('#edit_Weekly_Sales').val(btn.data('ws') || '');
            $('#edit_Monthly_Income').val(btn.data('minc') || '');
            $('#edit_Total_Expenses').val(btn.data('texp') || '');
            $('#edit_Net_Income').val(btn.data('net') || '');
        });

        // Delete confirmation
        $(document).on('click', '.del-business', function(e){
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({title:'Delete business?', text:'This action cannot be undone.', icon:'warning', showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#d33'}).then((res)=>{ if(res.isConfirmed) form.submit(); });
        });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php
    // Show SweetAlert feedback for server-side actions
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