<?php
session_start();
require_once __DIR__ . '/../db/dbcon.php';

// Handle create dependent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_dependent'])) {
    // Generate next Dependent_ID in format DEP-XXXX
    $last_q = mysqli_query($conn, "SELECT Dependent_ID FROM tbl_insurance_dep ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/DEP-(\d+)/', $row['Dependent_ID'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
    }
    $dep_id = sprintf('DEP-%04d', $nextNum);

    $insurance_id = isset($_POST['Insurance_ID']) ? trim($_POST['Insurance_ID']) : '';
    $last_name = isset($_POST['Last_Name']) ? trim($_POST['Last_Name']) : '';
    $first_name = isset($_POST['First_Name']) ? trim($_POST['First_Name']) : '';
    $middle_name = isset($_POST['Middle_Name']) ? trim($_POST['Middle_Name']) : '';
    $dob = isset($_POST['Date_Of_Birth']) ? trim($_POST['Date_Of_Birth']) : null; // date 'Y-m-d' format
    $age = isset($_POST['Age']) ? intval($_POST['Age']) : null;

    if ($insurance_id === '' || $last_name === '' || $first_name === '') {
        $error = 'Insurance ID, Last Name and First Name are required.';
    } else {
        $istmt = mysqli_prepare($conn, "INSERT INTO tbl_insurance_dep (Dependent_ID, Insurance_ID, Last_Name, First_Name, Middle_Name, Date_Of_Birth, Age) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($istmt) {
            mysqli_stmt_bind_param($istmt, 'ssssssi', $dep_id, $insurance_id, $last_name, $first_name, $middle_name, $dob, $age);
            if (mysqli_stmt_execute($istmt)) {
                $success = 'Dependent saved successfully.';
            } else {
                $error = 'Insert failed: ' . mysqli_stmt_error($istmt);
            }
            mysqli_stmt_close($istmt);
        } else {
            $error = 'Insert prepare failed: ' . mysqli_error($conn);
        }
    }
}

// Handle delete dependent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_dependent'])) {
    $del_id = intval($_POST['delete_dependent']);
    $dstmt = mysqli_prepare($conn, "DELETE FROM tbl_insurance_dep WHERE id = ?");
    if ($dstmt) {
        mysqli_stmt_bind_param($dstmt, 'i', $del_id);
        if (mysqli_stmt_execute($dstmt)) {
            $success = 'Dependent deleted successfully.';
        } else {
            $error = 'Delete failed: ' . mysqli_stmt_error($dstmt);
        }
        mysqli_stmt_close($dstmt);
    } else {
        $error = 'Delete prepare failed: ' . mysqli_error($conn);
    }
}

// Handle edit/update dependent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edit_dependent'])) {
    $eid = intval($_POST['edit_dependent']);
    $einsurance = isset($_POST['edit_Insurance_ID']) ? trim($_POST['edit_Insurance_ID']) : null;
    $elast = isset($_POST['edit_Last_Name']) ? trim($_POST['edit_Last_Name']) : null;
    $efirst = isset($_POST['edit_First_Name']) ? trim($_POST['edit_First_Name']) : null;
    $emiddle = isset($_POST['edit_Middle_Name']) ? trim($_POST['edit_Middle_Name']) : null;
    $edob = isset($_POST['edit_Date_Of_Birth']) ? trim($_POST['edit_Date_Of_Birth']) : null;
    $eage = isset($_POST['edit_Age']) ? intval($_POST['edit_Age']) : null;

    $usql = "UPDATE tbl_insurance_dep SET Insurance_ID=?, Last_Name=?, First_Name=?, Middle_Name=?, Date_Of_Birth=?, Age=? WHERE id=?";
    $ustmt = mysqli_prepare($conn, $usql);
    if ($ustmt) {
        mysqli_stmt_bind_param($ustmt, 'sssssii', $einsurance, $elast, $efirst, $emiddle, $edob, $eage, $eid);
        if (mysqli_stmt_execute($ustmt)) {
            $success = 'Dependent updated successfully.';
        } else {
            $error = 'Update failed: ' . mysqli_stmt_error($ustmt);
        }
        mysqli_stmt_close($ustmt);
    } else {
        $error = 'Update prepare failed: ' . mysqli_error($conn);
    }
}

// Fetch dependents for display
$dependents = [];
$dq = mysqli_query($conn, "SELECT * FROM tbl_insurance_dep ORDER BY id DESC");
if ($dq) {
    while ($d = mysqli_fetch_assoc($dq)) {
        $dependents[] = $d;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>TPKI || Insurance Dependents</title>
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

        <?php include "includes/sidebar.php"; ?>

        <div class="content">
            <?php include "includes/navbar.php"; ?>

            <div class="container-fluid pt-4 px-4">
                <div class="row bg-secondary rounded p-4 mx-0">
                    <div class="col-md-8">
                        <h5 class="mb-3">Add Insurance Dependent</h5>
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Dependent ID</label>
                                <?php
                                    $preview_dep = 'DEP-0001';
                                    $last_q = mysqli_query($conn, "SELECT Dependent_ID FROM tbl_insurance_dep ORDER BY id DESC LIMIT 1");
                                    if ($last_q && mysqli_num_rows($last_q) > 0) {
                                        $lr = mysqli_fetch_assoc($last_q);
                                        if (preg_match('/DEP-(\d+)/', $lr['Dependent_ID'], $m)) $preview_dep = sprintf('DEP-%04d', intval($m[1]) + 1);
                                    }
                                ?>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($preview_dep); ?>" readonly>
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
                            <input type="hidden" name="save_dependent" value="1">
                            <button class="btn btn-primary">Save Dependent</button>
                        </form>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="bg-secondary rounded p-4">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h6 class="mb-0">Insurance Dependents</h6>
                            </div>
                            <div class="table-responsive">
                                <table id="depTable" class="table table-striped table-bordered mb-0" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th><input class="form-check-input" type="checkbox"></th>
                                            <th>Dependent ID</th>
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
                                    <?php if (!empty($dependents)) {
                                        foreach ($dependents as $row) {
                                            $did = (int)($row['id'] ?? 0);
                                            $depid = htmlspecialchars($row['Dependent_ID'] ?? '');
                                            $insid = htmlspecialchars($row['Insurance_ID'] ?? '');
                                            $ln = htmlspecialchars($row['Last_Name'] ?? '');
                                            $fn = htmlspecialchars($row['First_Name'] ?? '');
                                            $mn = htmlspecialchars($row['Middle_Name'] ?? '');
                                            $dob_val = htmlspecialchars($row['Date_Of_Birth'] ?? '');
                                            $agev = htmlspecialchars($row['Age'] ?? '');
                                            $data_attrs = 'data-id="' . $did . '" data-depid="' . $depid . '" data-insid="' . $insid . '"';
                                            $data_attrs .= ' data-ln="' . $ln . '" data-fn="' . $fn . '" data-mn="' . $mn . '" data-dob="' . $dob_val . '" data-age="' . $agev . '"';
                                            echo "<tr>";
                                            echo "<td><input class=\"form-check-input\" type=\"checkbox\"></td>";
                                            echo "<td>" . $depid . "</td>";
                                            echo "<td>" . $insid . "</td>";
                                            echo "<td>" . $ln . "</td>";
                                            echo "<td>" . $fn . "</td>";
                                            echo "<td>" . $mn . "</td>";
                                            echo "<td>" . $dob_val . "</td>";
                                            echo "<td>" . $agev . "</td>";
                                            echo "<td class=\"text-nowrap\">";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-primary view-dep me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#depViewModal\" $data_attrs><i class=\"bi bi-eye\"></i></button>";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-warning edit-dep me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#depEditModal\" $data_attrs><i class=\"bi bi-pencil\"></i></button>";
                                            echo "<form method=\"post\" class=\"d-inline delete-dep-form\">";
                                            echo "<input type=\"hidden\" name=\"delete_dependent\" value=\"" . $did . "\">";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-danger del-dep\"><i class=\"bi bi-trash\"></i></button>";
                                            echo "</form>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan=9 class=\"text-center\">No dependents found</td></tr>";
                                    } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dep View Modal -->
                <div class="modal fade" id="depViewModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-md modal-dialog-centered">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title">Dependent Details</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <dl class="row mb-0">
                                    <dt class="col-4 text-muted">Dependent ID</dt><dd class="col-8" id="dvDepID"></dd>
                                    <dt class="col-4 text-muted">Insurance ID</dt><dd class="col-8" id="dvInsID"></dd>
                                    <dt class="col-4 text-muted">Name</dt><dd class="col-8" id="dvName"></dd>
                                    <dt class="col-4 text-muted">DOB</dt><dd class="col-8" id="dvDOB"></dd>
                                    <dt class="col-4 text-muted">Age</dt><dd class="col-8" id="dvAge"></dd>
                                </dl>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dep Edit Modal -->
                <div class="modal fade" id="depEditModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-md modal-dialog-centered">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Dependent</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="edit_dependent" id="edit_dependent">
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

            </div>

            <?php include 'includes/footer.php'; ?>
        </div>

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
        if ($('#depTable').length) $('#depTable').DataTable({ paging:true, searching:true, info:true, ordering:true, columnDefs:[{orderable:false, targets:[0,8]}] });

        // View modal populate
        $(document).on('click', '.view-dep', function(){
            var btn = $(this);
            $('#dvDepID').text(btn.data('depid')||'');
            $('#dvInsID').text(btn.data('insid')||'');
            var name = (btn.data('ln')||'') + ', ' + (btn.data('fn')||'');
            $('#dvName').text(name);
            $('#dvDOB').text(btn.data('dob')||'');
            $('#dvAge').text(btn.data('age')||'');
        });

        // Edit modal populate
        $(document).on('click', '.edit-dep', function(){
            var btn = $(this);
            $('#edit_dependent').val(btn.data('id')||'');
            $('#edit_Insurance_ID').val(btn.data('insid')||'');
            $('#edit_Last_Name').val(btn.data('ln')||'');
            $('#edit_First_Name').val(btn.data('fn')||'');
            $('#edit_Middle_Name').val(btn.data('mn')||'');
            $('#edit_Date_Of_Birth').val(btn.data('dob')||'');
            $('#edit_Age').val(btn.data('age')||'');
        });

        // Delete confirmation
        $(document).on('click', '.del-dep', function(e){
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({title:'Delete dependent?', text:'This action cannot be undone.', icon:'warning', showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#d33'}).then((res)=>{ if(res.isConfirmed) form.submit(); });
        });
    });
    </script>

    <?php
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