<?php
session_start();
require_once __DIR__ . '/../db/dbcon.php';

// Handle create beneficiary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_beneficiary'])) {
    // Generate next Beneficiary_ID in format BEN-XXXX
    $last_q = mysqli_query($conn, "SELECT Beneficiary_ID FROM tbl_insurance_ben ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/BEN-(\d+)/', $row['Beneficiary_ID'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
    }
    $benef_id = sprintf('BEN-%04d', $nextNum);

    $insurance_id = isset($_POST['Insurance_ID']) ? trim($_POST['Insurance_ID']) : '';
    $last_name = isset($_POST['Last_Name']) ? trim($_POST['Last_Name']) : '';
    $first_name = isset($_POST['First_Name']) ? trim($_POST['First_Name']) : '';
    $middle_name = isset($_POST['Middle_Name']) ? trim($_POST['Middle_Name']) : '';
    $dob_raw = isset($_POST['Date_Of_Birth']) ? trim($_POST['Date_Of_Birth']) : '';
    $age = isset($_POST['Age']) ? intval($_POST['Age']) : null;

    if ($insurance_id === '' || $last_name === '' || $first_name === '') {
        $error = 'Insurance ID, Last Name and First Name are required.';
    } else {
        // Date_Of_Birth column is double in schema; store as unix timestamp (float)
        $dob_ts = $dob_raw !== '' ? floatval(strtotime($dob_raw)) : null;
        $istmt = mysqli_prepare($conn, "INSERT INTO tbl_insurance_ben (Beneficiary_ID, Insurance_ID, Last_Name, First_Name, Middle_Name, Date_Of_Birth, Age) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($istmt) {
            // bind as strings and integer for age
            mysqli_stmt_bind_param($istmt, 'ssssddi', $benef_id, $insurance_id, $last_name, $first_name, $middle_name, $dob_ts, $age);
            if (mysqli_stmt_execute($istmt)) {
                $success = 'Beneficiary saved successfully.';
            } else {
                $error = 'Insert failed: ' . mysqli_stmt_error($istmt);
            }
            mysqli_stmt_close($istmt);
        } else {
            $error = 'Insert prepare failed: ' . mysqli_error($conn);
        }
    }
}

// Handle delete beneficiary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_beneficiary'])) {
    $del_id = intval($_POST['delete_beneficiary']);
    $dstmt = mysqli_prepare($conn, "DELETE FROM tbl_insurance_ben WHERE id = ?");
    if ($dstmt) {
        mysqli_stmt_bind_param($dstmt, 'i', $del_id);
        if (mysqli_stmt_execute($dstmt)) {
            $success = 'Beneficiary deleted successfully.';
        } else {
            $error = 'Delete failed: ' . mysqli_stmt_error($dstmt);
        }
        mysqli_stmt_close($dstmt);
    } else {
        $error = 'Delete prepare failed: ' . mysqli_error($conn);
    }
}

// Handle edit/update beneficiary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edit_beneficiary'])) {
    $eid = intval($_POST['edit_beneficiary']);
    $einsurance = isset($_POST['edit_Insurance_ID']) ? trim($_POST['edit_Insurance_ID']) : null;
    $elast = isset($_POST['edit_Last_Name']) ? trim($_POST['edit_Last_Name']) : null;
    $efirst = isset($_POST['edit_First_Name']) ? trim($_POST['edit_First_Name']) : null;
    $emiddle = isset($_POST['edit_Middle_Name']) ? trim($_POST['edit_Middle_Name']) : null;
    $edob_raw = isset($_POST['edit_Date_Of_Birth']) ? trim($_POST['edit_Date_Of_Birth']) : '';
    $eage = isset($_POST['edit_Age']) ? intval($_POST['edit_Age']) : null;
    $edob_ts = $edob_raw !== '' ? floatval(strtotime($edob_raw)) : null;

    $usql = "UPDATE tbl_insurance_ben SET Insurance_ID=?, Last_Name=?, First_Name=?, Middle_Name=?, Date_Of_Birth=?, Age=? WHERE id=?";
    $ustmt = mysqli_prepare($conn, $usql);
    if ($ustmt) {
        mysqli_stmt_bind_param($ustmt, 'sssddii', $einsurance, $elast, $efirst, $emiddle, $edob_ts, $eage, $eid);
        // note: bind types adjusted below if needed
        // Execute using call with correct types
        if (mysqli_stmt_execute($ustmt)) {
            $success = 'Beneficiary updated successfully.';
        } else {
            $error = 'Update failed: ' . mysqli_stmt_error($ustmt);
        }
        mysqli_stmt_close($ustmt);
    } else {
        $error = 'Update prepare failed: ' . mysqli_error($conn);
    }
}

// Fetch beneficiaries for display
$beneficiaries = [];
$bq = mysqli_query($conn, "SELECT * FROM tbl_insurance_ben ORDER BY id DESC");
if ($bq) {
    while ($b = mysqli_fetch_assoc($bq)) {
        $beneficiaries[] = $b;
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
    .table-responsive::-webkit-scrollbar { height:12px; width:12px; }
    .table-responsive::-webkit-scrollbar-thumb { background:#000; border-radius:6px; }
    .table-responsive::-webkit-scrollbar-track { background:#333; }
    .table-responsive { scrollbar-color: #000 #333; scrollbar-width: thin; }
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


            <!-- Beneficiary Form and Table -->
            <div class="container-fluid pt-4 px-4">
                <div class="row bg-secondary rounded p-4 mx-0">
                    <div class="col-md-8">
                        <h5 class="mb-3">Add Insurance Beneficiary</h5>
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Beneficiary ID</label>
                                <?php
                                    // show next id preview
                                    $preview_id = 'BEN-0001';
                                    $last_q = mysqli_query($conn, "SELECT Beneficiary_ID FROM tbl_insurance_ben ORDER BY id DESC LIMIT 1");
                                    if ($last_q && mysqli_num_rows($last_q) > 0) {
                                        $lr = mysqli_fetch_assoc($last_q);
                                        if (preg_match('/BEN-(\d+)/', $lr['Beneficiary_ID'], $m)) $preview_id = sprintf('BEN-%04d', intval($m[1]) + 1);
                                    }
                                ?>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($preview_id); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Insurance ID</label>
                                <input name="Insurance_ID" class="form-control" required>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input name="Last_Name" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input name="First_Name" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Middle Name</label>
                                    <input name="Middle_Name" class="form-control">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="Date_Of_Birth" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Age</label>
                                    <input type="number" name="Age" class="form-control" min="0">
                                </div>
                            </div>
                            <input type="hidden" name="save_beneficiary" value="1">
                            <button class="btn btn-primary">Save Beneficiary</button>
                        </form>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="bg-secondary rounded p-4">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h6 class="mb-0">Insurance Beneficiaries</h6>
                            </div>
                            <div class="table-responsive">
                                <table id="benTable" class="table table-striped table-bordered mb-0" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th><input class="form-check-input" type="checkbox"></th>
                                            <th>Beneficiary ID</th>
                                            <th>Insurance ID</th>
                                            <th>Last Name</th>
                                            <th>First Name</th>
                                            <th>Middle Name</th>
                                            <th>DOB</th>
                                            <th>Age</th>
                                            <th style="width:160px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($beneficiaries)) {
                                        foreach ($beneficiaries as $row) {
                                            $bid = (int)($row['id'] ?? 0);
                                            $benid = htmlspecialchars($row['Beneficiary_ID'] ?? '');
                                            $insid = htmlspecialchars($row['Insurance_ID'] ?? '');
                                            $ln = htmlspecialchars($row['Last_Name'] ?? '');
                                            $fn = htmlspecialchars($row['First_Name'] ?? '');
                                            $mn = htmlspecialchars($row['Middle_Name'] ?? '');
                                            $dob_val = '';
                                            if (!empty($row['Date_Of_Birth']) && is_numeric($row['Date_Of_Birth'])) $dob_val = date('Y-m-d', intval($row['Date_Of_Birth']));
                                            $agev = htmlspecialchars($row['Age'] ?? '');
                                            $data_attrs = 'data-id="' . $bid . '" data-benid="' . $benid . '" data-insid="' . $insid . '"';
                                            $data_attrs .= ' data-ln="' . $ln . '" data-fn="' . $fn . '" data-mn="' . $mn . '" data-dob="' . $dob_val . '" data-age="' . $agev . '"';
                                            echo "<tr>";
                                            echo "<td><input class=\"form-check-input\" type=\"checkbox\"></td>";
                                            echo "<td>" . $benid . "</td>";
                                            echo "<td>" . $insid . "</td>";
                                            echo "<td>" . $ln . "</td>";
                                            echo "<td>" . $fn . "</td>";
                                            echo "<td>" . $mn . "</td>";
                                            echo "<td>" . $dob_val . "</td>";
                                            echo "<td>" . $agev . "</td>";
                                            echo "<td class=\"text-nowrap\">";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-primary view-ben me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#benViewModal\" $data_attrs><i class=\"bi bi-eye\"></i></button>";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-warning edit-ben me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#benEditModal\" $data_attrs><i class=\"bi bi-pencil\"></i></button>";
                                            echo "<form method=\"post\" class=\"d-inline delete-ben-form\">";
                                            echo "<input type=\"hidden\" name=\"delete_beneficiary\" value=\"" . $bid . "\">";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-danger del-ben\"><i class=\"bi bi-trash\"></i></button>";
                                            echo "</form>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan=9 class=\"text-center\">No beneficiaries found</td></tr>";
                                    } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Beneficiary View Modal -->
                <div class="modal fade" id="benViewModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-md modal-dialog-centered">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title">Beneficiary Details</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <dl class="row mb-0">
                                    <dt class="col-4 text-muted">Beneficiary ID</dt><dd class="col-8" id="bvBenID"></dd>
                                    <dt class="col-4 text-muted">Insurance ID</dt><dd class="col-8" id="bvInsID"></dd>
                                    <dt class="col-4 text-muted">Name</dt><dd class="col-8" id="bvName"></dd>
                                    <dt class="col-4 text-muted">DOB</dt><dd class="col-8" id="bvDOB"></dd>
                                    <dt class="col-4 text-muted">Age</dt><dd class="col-8" id="bvAge"></dd>
                                </dl>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Beneficiary Edit Modal -->
                <div class="modal fade" id="benEditModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-md modal-dialog-centered">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Beneficiary</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="edit_beneficiary" id="edit_beneficiary">
                                <div class="mb-3">
                                    <label class="form-label">Insurance ID</label>
                                    <input name="edit_Insurance_ID" id="edit_Insurance_ID" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input name="edit_Last_Name" id="edit_Last_Name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input name="edit_First_Name" id="edit_First_Name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Middle Name</label>
                                    <input name="edit_Middle_Name" id="edit_Middle_Name" class="form-control">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" name="edit_Date_Of_Birth" id="edit_Date_Of_Birth" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Age</label>
                                        <input type="number" name="edit_Age" id="edit_Age" class="form-control" min="0">
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        if ($('.select2').length) $('.select2').select2({ width: '100%' });
        if ($('#benTable').length) $('#benTable').DataTable({ paging:true, searching:true, info:true, ordering:true, columnDefs:[{orderable:false, targets:[0,8]}] });

        // View modal populate
        $(document).on('click', '.view-ben', function(){
            var btn = $(this);
            $('#bvBenID').text(btn.data('benid')||'');
            $('#bvInsID').text(btn.data('insid')||'');
            var name = (btn.data('ln')||'') + ', ' + (btn.data('fn')||'');
            $('#bvName').text(name);
            $('#bvDOB').text(btn.data('dob')||'');
            $('#bvAge').text(btn.data('age')||'');
        });

        // Edit modal populate
        $(document).on('click', '.edit-ben', function(){
            var btn = $(this);
            $('#edit_beneficiary').val(btn.data('id')||'');
            $('#edit_Insurance_ID').val(btn.data('insid')||'');
            $('#edit_Last_Name').val(btn.data('ln')||'');
            $('#edit_First_Name').val(btn.data('fn')||'');
            $('#edit_Middle_Name').val(btn.data('mn')||'');
            $('#edit_Date_Of_Birth').val(btn.data('dob')||'');
            $('#edit_Age').val(btn.data('age')||'');
        });

        // Delete confirmation
        $(document).on('click', '.del-ben', function(e){
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({title:'Delete beneficiary?', text:'This action cannot be undone.', icon:'warning', showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#d33'}).then((res)=>{ if(res.isConfirmed) form.submit(); });
        });
    });
    </script>

    <?php
    // Show SweetAlert feedback for server-side actions
    if (!empty($success)) {
        $msg = addslashes($success);
        echo "<script>Swal.fire({icon:'success', title:'Success', text:'{$msg}'});</script>\n";
    } elseif (!empty($error)) {
        $emsg = addslashes($error);
        echo "<script>Swal.fire({icon:'error', title:'Error', text:'{$emsg}'});</script>\n";
    }
    ?>
</body>

</html>