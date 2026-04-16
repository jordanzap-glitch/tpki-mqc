<?php
session_start();
require_once __DIR__ . '/../db/dbcon.php';

// Handle create dependent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_dependent'])) {
    // Generate next Dependent_ID in format DEP-XXX
    $last_q = mysqli_query($conn, "SELECT Dependent_ID FROM tbl_client_dep ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/DEP-(\d+)/', $row['Dependent_ID'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
    }
    $dependent_id = sprintf('DEP-%03d', $nextNum);

    $client_id = isset($_POST['Client_ID']) ? trim($_POST['Client_ID']) : '';
    $last = isset($_POST['Last_Name']) ? trim($_POST['Last_Name']) : '';
    $first = isset($_POST['First_Name']) ? trim($_POST['First_Name']) : '';
    $middle = isset($_POST['Middle_Name']) ? trim($_POST['Middle_Name']) : '';
    $dob = isset($_POST['Date_Of_Birth']) ? trim($_POST['Date_Of_Birth']) : null;
    $age = isset($_POST['Age']) && $_POST['Age'] !== '' ? intval($_POST['Age']) : null;
    $edu = isset($_POST['Educational_Attainment']) ? trim($_POST['Educational_Attainment']) : '';
    $work = isset($_POST['Work_Business']) ? trim($_POST['Work_Business']) : '';
    $other = isset($_POST['Other_Information']) ? trim($_POST['Other_Information']) : '';

    if ($client_id === '' || $last === '' || $first === '') {
        $error = 'Client and dependent name (last/first) are required.';
    } else {
        $sql = "INSERT INTO tbl_client_dep (Dependent_ID, Client_ID, Last_Name, First_Name, Middle_Name, Date_Of_Birth, Age, Educational_Attainment, Work_Business, Other_Information) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssssisss', $dependent_id, $client_id, $last, $first, $middle, $dob, $age, $edu, $work, $other);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Dependent saved successfully.';
            } else {
                $error = 'Insert failed: ' . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Insert prepare failed: ' . mysqli_error($conn);
        }
    }
}

// Handle delete dependent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_dependent'])) {
    $del_id = intval($_POST['delete_dependent']);
    $dstmt = mysqli_prepare($conn, "DELETE FROM tbl_client_dep WHERE id = ?");
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
    $ecid = isset($_POST['edit_Client_ID']) ? trim($_POST['edit_Client_ID']) : null;
    $eln = isset($_POST['edit_Last_Name']) ? trim($_POST['edit_Last_Name']) : null;
    $efn = isset($_POST['edit_First_Name']) ? trim($_POST['edit_First_Name']) : null;
    $emn = isset($_POST['edit_Middle_Name']) ? trim($_POST['edit_Middle_Name']) : null;
    $edob = isset($_POST['edit_Date_Of_Birth']) ? trim($_POST['edit_Date_Of_Birth']) : null;
    $eage = isset($_POST['edit_Age']) && $_POST['edit_Age'] !== '' ? intval($_POST['edit_Age']) : null;
    $eedu = isset($_POST['edit_Educational_Attainment']) ? trim($_POST['edit_Educational_Attainment']) : null;
    $ework = isset($_POST['edit_Work_Business']) ? trim($_POST['edit_Work_Business']) : null;
    $eother = isset($_POST['edit_Other_Information']) ? trim($_POST['edit_Other_Information']) : null;

    $usql = "UPDATE tbl_client_dep SET Client_ID=?, Last_Name=?, First_Name=?, Middle_Name=?, Date_Of_Birth=?, Age=?, Educational_Attainment=?, Work_Business=?, Other_Information=? WHERE id=?";
    $ustmt = mysqli_prepare($conn, $usql);
    if ($ustmt) {
        mysqli_stmt_bind_param($ustmt, 'sssssiisss', $ecid, $eln, $efn, $emn, $edob, $eage, $eedu, $ework, $eother, $eid);
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

// Fetch clients for dropdown
$clients = [];
$cq = mysqli_query($conn, "SELECT Client_ID, First_Name, Last_Name FROM tbl_client_info ORDER BY Last_Name, First_Name");
if ($cq) {
    while ($c = mysqli_fetch_assoc($cq)) {
        $clients[] = $c;
    }
}

// Fetch dependents for table display (join to get client name)
$dependents = [];
$dq = mysqli_query($conn, "SELECT d.*, c.First_Name AS client_first, c.Last_Name AS client_last, c.Client_ID AS client_id FROM tbl_client_dep d LEFT JOIN tbl_client_info c ON d.Client_ID = c.Client_ID ORDER BY d.id DESC");
if ($dq) {
    while ($r = mysqli_fetch_assoc($dq)) {
        $dependents[] = $r;
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


            <!-- Blank Content Start: keep layout but remove inner content -->
            <!-- Dependent Form Start -->
            <div class="container-fluid pt-4 px-4">
                <div class="row bg-secondary rounded p-4 mx-0">
                    <div class="col-md-8">
                        <h5 class="mb-3">Add Client Dependent</h5>
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Dependent ID</label>
                                <input type="text" class="form-control" value="<?php echo isset($dependent_id) ? htmlspecialchars($dependent_id) : 'DEP-001'; ?>" readonly>
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
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input name="Date_Of_Birth" type="date" class="form-control">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Age</label>
                                    <input name="Age" type="number" class="form-control">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Educational Attainment</label>
                                    <input name="Educational_Attainment" class="form-control">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Work / Business</label>
                                    <input name="Work_Business" class="form-control">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Other Information</label>
                                    <textarea name="Other_Information" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            <input type="hidden" name="save_dependent" value="1">
                            <button class="btn btn-primary">Save Dependent</button>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Dependent Form End -->

            <!-- Dependents Table Card -->
            <div class="container-fluid pt-4 px-4">
                <div class="col-12">
                    <div class="bg-secondary rounded p-4">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h6 class="mb-0">Client Dependents</h6>
                        </div>
                        <div class="table-responsive">
                            <table id="dependentsTable" class="table table-striped table-bordered mb-0" style="width:100%">
                                <thead>
                                    <tr>
                                        <th><input class="form-check-input" type="checkbox"></th>
                                        <th>Dependent ID</th>
                                        <th>Client</th>
                                        <th>Last Name</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Date of Birth</th>
                                        <th style="width:140px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dependents)) {
                                        foreach ($dependents as $d) {
                                            $rowId = (int)($d['id'] ?? 0);
                                            $did = htmlspecialchars($d['Dependent_ID'] ?? '', ENT_QUOTES);
                                            $clientLabel = htmlspecialchars((($d['client_last'] ?? '') . ', ' . ($d['client_first'] ?? '') . ' (' . ($d['client_id'] ?? '') . ')'));
                                            $ln = htmlspecialchars($d['Last_Name'] ?? '', ENT_QUOTES);
                                            $fn = htmlspecialchars($d['First_Name'] ?? '', ENT_QUOTES);
                                            $mn = htmlspecialchars($d['Middle_Name'] ?? '', ENT_QUOTES);
                                            $dob = htmlspecialchars($d['Date_Of_Birth'] ?? '', ENT_QUOTES);
                                            $data_attrs = 'data-id="' . $rowId . '" data-dependentid="' . $did . '" data-clientid="' . htmlspecialchars($d['Client_ID'] ?? '', ENT_QUOTES) . '"';
                                            $data_attrs .= ' data-last="' . $ln . '" data-first="' . $fn . '" data-middle="' . $mn . '"';
                                            $data_attrs .= ' data-dob="' . $dob . '" data-age="' . htmlspecialchars($d['Age'] ?? '', ENT_QUOTES) . '"';
                                            $data_attrs .= ' data-edu="' . htmlspecialchars($d['Educational_Attainment'] ?? '', ENT_QUOTES) . '"';
                                            $data_attrs .= ' data-work="' . htmlspecialchars($d['Work_Business'] ?? '', ENT_QUOTES) . '"';
                                            $data_attrs .= ' data-other="' . htmlspecialchars($d['Other_Information'] ?? '', ENT_QUOTES) . '"';
                                            echo '<tr>';
                                            echo '<td><input class="form-check-input" type="checkbox"></td>';
                                            echo '<td>' . $did . '</td>';
                                            echo '<td>' . $clientLabel . '</td>';
                                            echo '<td>' . $ln . '</td>';
                                            echo '<td>' . $fn . '</td>';
                                            echo '<td>' . $mn . '</td>';
                                            echo '<td>' . $dob . '</td>';
                                            echo '<td class="text-nowrap">';
                                            echo '<button type="button" class="btn btn-sm btn-primary view-dependent me-1" data-bs-toggle="modal" data-bs-target="#dependentViewModal" ' . $data_attrs . '><i class="bi bi-eye"></i></button>';
                                            echo '<button type="button" class="btn btn-sm btn-warning edit-dependent-btn me-1" data-bs-toggle="modal" data-bs-target="#dependentEditModal" ' . $data_attrs . '><i class="bi bi-pencil"></i></button>';
                                            echo '<form method="post" class="d-inline delete-dependent-form">';
                                            echo '<input type="hidden" name="delete_dependent" value="' . $rowId . '">';
                                            echo '<button type="button" class="btn btn-sm btn-danger del-dependent"><i class="bi bi-trash"></i></button>';
                                            echo '</form>';
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="8" class="text-center">No dependents found</td></tr>';
                                    } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dependent View Modal -->
            <div class="modal fade" id="dependentViewModal" tabindex="-1" aria-labelledby="dependentViewLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content bg-dark text-white">
                        <div class="modal-header">
                            <h5 class="modal-title" id="dependentViewLabel">Dependent Details</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="d-flex mb-3">
                                <div id="dvIcon" class="me-3" style="width:96px;height:96px;background:#222;display:flex;align-items:center;justify-content:center;border-radius:6px;overflow:hidden">
                                    <i class="bi bi-person" style="font-size:32px;color:#fff;margin:auto"></i>
                                </div>
                                <div>
                                    <h5 id="dvName" class="mb-0"></h5>
                                    <div class="text-muted" id="dvDependentID"></div>
                                    <div class="text-muted" id="dvClient"></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-5 text-muted">Last Name</dt><dd class="col-7" id="dvLast"></dd>
                                        <dt class="col-5 text-muted">First Name</dt><dd class="col-7" id="dvFirst"></dd>
                                        <dt class="col-5 text-muted">Middle Name</dt><dd class="col-7" id="dvMiddle"></dd>
                                        <dt class="col-5 text-muted">Date of Birth</dt><dd class="col-7" id="dvDOB"></dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-5 text-muted">Age</dt><dd class="col-7" id="dvAge"></dd>
                                        <dt class="col-5 text-muted">Education</dt><dd class="col-7" id="dvEdu"></dd>
                                        <dt class="col-5 text-muted">Work/Business</dt><dd class="col-7" id="dvWork"></dd>
                                        <dt class="col-5 text-muted">Other Info</dt><dd class="col-7" id="dvOther"></dd>
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

            <!-- Dependent Edit Modal -->
            <div class="modal fade" id="dependentEditModal" tabindex="-1" aria-labelledby="dependentEditLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content bg-dark text-white">
                        <div class="modal-header">
                            <h5 class="modal-title" id="dependentEditLabel">Edit Dependent</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                        <div class="modal-body">
                            <input type="hidden" name="edit_dependent" id="edit_dependent">
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
                                    <label class="form-label">Last Name</label>
                                    <input name="edit_Last_Name" id="edit_Last_Name" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input name="edit_First_Name" id="edit_First_Name" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Middle Name</label>
                                    <input name="edit_Middle_Name" id="edit_Middle_Name" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input name="edit_Date_Of_Birth" id="edit_Date_Of_Birth" type="date" class="form-control">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Age</label>
                                    <input name="edit_Age" id="edit_Age" type="number" class="form-control">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Educational Attainment</label>
                                    <input name="edit_Educational_Attainment" id="edit_Educational_Attainment" class="form-control">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Work / Business</label>
                                    <input name="edit_Work_Business" id="edit_Work_Business" class="form-control">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Other Information</label>
                                    <textarea name="edit_Other_Information" id="edit_Other_Information" class="form-control" rows="3"></textarea>
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
        if ($('#dependentsTable').length) {
            $('#dependentsTable').DataTable({ paging:true, searching:true, info:true, ordering:true, columnDefs:[{orderable:false, targets:[0]}] });
        }
        // Populate view modal
        $(document).on('click', '.view-dependent', function() {
            var btn = $(this);
            $('#dvName').text((btn.data('last') || '') + ', ' + (btn.data('first') || ''));
            $('#dvDependentID').text(btn.data('dependentid') || '');
            var clientLabel = btn.data('clientid') ? btn.closest('tr').find('td:nth-child(3)').text() : '';
            $('#dvClient').text(clientLabel);
            $('#dvLast').text(btn.data('last') || '');
            $('#dvFirst').text(btn.data('first') || '');
            $('#dvMiddle').text(btn.data('middle') || '');
            $('#dvDOB').text(btn.data('dob') || '');
            $('#dvAge').text(btn.data('age') || '');
            $('#dvEdu').text(btn.data('edu') || '');
            $('#dvWork').text(btn.data('work') || '');
            $('#dvOther').text(btn.data('other') || '');
        });

        // Populate edit modal
        $(document).on('click', '.edit-dependent-btn', function() {
            var btn = $(this);
            $('#edit_dependent').val(btn.data('id') || '');
            $('#edit_Client_ID').val(btn.data('clientid') || '').trigger('change');
            $('#edit_Last_Name').val(btn.data('last') || '');
            $('#edit_First_Name').val(btn.data('first') || '');
            $('#edit_Middle_Name').val(btn.data('middle') || '');
            $('#edit_Date_Of_Birth').val(btn.data('dob') || '');
            $('#edit_Age').val(btn.data('age') || '');
            $('#edit_Educational_Attainment').val(btn.data('edu') || '');
            $('#edit_Work_Business').val(btn.data('work') || '');
            $('#edit_Other_Information').val(btn.data('other') || '');
        });

        // Delete confirmation
        $(document).on('click', '.del-dependent', function(e){
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({title:'Delete dependent?', text:'This action cannot be undone.', icon:'warning', showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#d33'}).then((res)=>{ if(res.isConfirmed) form.submit(); });
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