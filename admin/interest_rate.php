<?php
session_start();
require_once __DIR__ . '/../db/dbcon.php';

$success = '';
$error = '';

// Handle create interest rate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_interest_rate'])) {
    // Generate next Interest_Rate_ID in format IN-001
    $last_q = mysqli_query($conn, "SELECT Interest_Rate_ID FROM tbl_interest_rate ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/IN-(\d+)/', $row['Interest_Rate_ID'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
    }
    $ir_id = sprintf('IN-%03d', $nextNum);

    $code = isset($_POST['Interest_Rate_Code']) ? trim($_POST['Interest_Rate_Code']) : '';
    $desc = isset($_POST['Interest_Rate_Description']) ? trim($_POST['Interest_Rate_Description']) : '';

    if ($code === '') {
        $error = 'Interest Rate Code is required.';
    } else {
        $istmt = mysqli_prepare($conn, "INSERT INTO tbl_interest_rate (Interest_Rate_ID, Interest_Rate_Code, Interest_Rate_Description) VALUES (?, ?, ?)");
        if ($istmt) {
            mysqli_stmt_bind_param($istmt, 'sss', $ir_id, $code, $desc);
            if (mysqli_stmt_execute($istmt)) {
                $success = 'Interest rate saved successfully.';
            } else {
                $error = 'Insert failed: ' . mysqli_stmt_error($istmt);
            }
            mysqli_stmt_close($istmt);
        } else {
            $error = 'Insert prepare failed: ' . mysqli_error($conn);
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_interest'])) {
    $del_id = intval($_POST['delete_interest']);
    $dstmt = mysqli_prepare($conn, "DELETE FROM tbl_interest_rate WHERE id = ?");
    if ($dstmt) {
        mysqli_stmt_bind_param($dstmt, 'i', $del_id);
        if (mysqli_stmt_execute($dstmt)) {
            $success = 'Interest rate deleted successfully.';
        } else {
            $error = 'Delete failed: ' . mysqli_stmt_error($dstmt);
        }
        mysqli_stmt_close($dstmt);
    } else {
        $error = 'Delete prepare failed: ' . mysqli_error($conn);
    }
}

// Handle edit/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edit_interest'])) {
    $eid = intval($_POST['edit_interest']);
    $ecode = isset($_POST['edit_Interest_Rate_Code']) ? trim($_POST['edit_Interest_Rate_Code']) : null;
    $edesc = isset($_POST['edit_Interest_Rate_Description']) ? trim($_POST['edit_Interest_Rate_Description']) : null;
    $usql = "UPDATE tbl_interest_rate SET Interest_Rate_Code=?, Interest_Rate_Description=? WHERE id=?";
    $ustmt = mysqli_prepare($conn, $usql);
    if ($ustmt) {
        mysqli_stmt_bind_param($ustmt, 'ssi', $ecode, $edesc, $eid);
        if (mysqli_stmt_execute($ustmt)) {
            $success = 'Interest rate updated successfully.';
        } else {
            $error = 'Update failed: ' . mysqli_stmt_error($ustmt);
        }
        mysqli_stmt_close($ustmt);
    } else {
        $error = 'Update prepare failed: ' . mysqli_error($conn);
    }
}

// Fetch interest rates
$rates = [];
$rq = mysqli_query($conn, "SELECT * FROM tbl_interest_rate ORDER BY id DESC");
if ($rq) {
    while ($r = mysqli_fetch_assoc($rq)) {
        $rates[] = $r;
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
    .table th:first-child, .table td:first-child {
        width: 44px;
        padding: 0.35rem 0.5rem;
        text-align: center;
        vertical-align: middle;
    }
    .table .form-check-input { width: 16px; height: 16px; margin: 0; transform: none; }
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


            <!-- Content -->
            <div class="container-fluid pt-4 px-4">
                <div class="row bg-secondary rounded p-4 mx-0">
                    <div class="col-md-8">
                        <h5 class="mb-3">Add Interest Rate</h5>
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Interest Rate ID</label>
                                <input type="text" class="form-control" value="<?php echo isset($ir_id) ? htmlspecialchars($ir_id) : 'IN-001'; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Interest Rate Code</label>
                                <input type="number" name="Interest_Rate_Code" class="form-control" inputmode="decimal" pattern="^[0-9]+(\.[0-9]+)?$" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Interest Rate Description</label>
                                <textarea name="Interest_Rate_Description" class="form-control" rows="3"></textarea>
                            </div>
                            <input type="hidden" name="save_interest_rate" value="1">
                            <button class="btn btn-primary">Save Interest Rate</button>
                        </form>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="bg-secondary rounded p-4">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h6 class="mb-0">Interest Rates</h6>
                            </div>
                            <div class="table-responsive">
                                <table id="ratesTable" class="table table-striped table-bordered mb-0" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th><input class="form-check-input" type="checkbox"></th>
                                            <th>Interest Rate ID</th>
                                            <th>Code</th>
                                            <th>Description</th>
                                            <th style="width:160px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($rates)) {
                                        foreach ($rates as $row) {
                                            $rid = (int)($row['id'] ?? 0);
                                            $irid = htmlspecialchars($row['Interest_Rate_ID'] ?? '');
                                            $code = htmlspecialchars($row['Interest_Rate_Code'] ?? '');
                                            $desc = htmlspecialchars($row['Interest_Rate_Description'] ?? '');
                                            $data_attrs = 'data-id="' . $rid . '" data-irid="' . $irid . '" data-code="' . $code . '" data-desc="' . $desc . '"';
                                            echo "<tr>";
                                            echo "<td><input class=\"form-check-input\" type=\"checkbox\"></td>";
                                            echo "<td>{$irid}</td>";
                                            echo "<td>{$code}</td>";
                                            echo "<td>{$desc}</td>";
                                            echo "<td class=\"text-nowrap\">";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-primary view-rate me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#rateViewModal\" {$data_attrs}><i class=\"bi bi-eye\"></i></button>";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-warning edit-rate me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#rateEditModal\" {$data_attrs}><i class=\"bi bi-pencil\"></i></button>";
                                            echo "<form method=\"post\" class=\"d-inline delete-rate-form\">";
                                            echo "<input type=\"hidden\" name=\"delete_interest\" value=\"{$rid}\">";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-danger del-rate\"><i class=\"bi bi-trash\"></i></button>";
                                            echo "</form>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan=5 class=\"text-center\">No interest rates found</td></tr>";
                                    } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- View Modal -->
                <div class="modal fade" id="rateViewModal" tabindex="-1" aria-labelledby="rateViewLabel" aria-hidden="true">
                    <div class="modal-dialog modal-md modal-dialog-centered">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title" id="rateViewLabel">Interest Rate Details</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <dl class="row mb-0">
                                    <dt class="col-5 text-muted">Interest Rate ID</dt><dd class="col-7" id="vrIRID"></dd>
                                    <dt class="col-5 text-muted">Code</dt><dd class="col-7" id="vrCode"></dd>
                                    <dt class="col-5 text-muted">Description</dt><dd class="col-7" id="vrDesc"></dd>
                                </dl>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div class="modal fade" id="rateEditModal" tabindex="-1" aria-labelledby="rateEditLabel" aria-hidden="true">
                    <div class="modal-dialog modal-md modal-dialog-centered">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title" id="rateEditLabel">Edit Interest Rate</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="edit_interest" id="edit_interest">
                                <div class="mb-3">
                                    <label class="form-label">Interest Rate Code</label>
                                    <input type="number" name="edit_Interest_Rate_Code" id="edit_Interest_Rate_Code" class="form-control" inputmode="decimal" pattern="^[0-9]+(\.[0-9]+)?$" step="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Interest Rate Description</label>
                                    <textarea name="edit_Interest_Rate_Description" id="edit_Interest_Rate_Description" class="form-control" rows="3"></textarea>
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
            <!-- Content End -->


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
        $('#ratesTable').DataTable({ paging:true, searching:true, info:true, ordering:true, columnDefs:[{orderable:false, targets:[0,4]}] });

        // Populate view modal
        $(document).on('click', '.view-rate', function() {
            var btn = $(this);
            $('#vrIRID').text(btn.data('irid') || '');
            $('#vrCode').text(btn.data('code') || '');
            $('#vrDesc').text(btn.data('desc') || '');
        });

        // Populate edit modal
        $(document).on('click', '.edit-rate', function() {
            var btn = $(this);
            $('#edit_interest').val(btn.data('id') || '');
            $('#edit_Interest_Rate_Code').val(btn.data('code') || '');
            $('#edit_Interest_Rate_Description').val(btn.data('desc') || '');
        });

        // Delete confirmation
        $(document).on('click', '.del-rate', function(e){
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({title:'Delete interest rate?', text:'This action cannot be undone.', icon:'warning', showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#d33'}).then((res)=>{ if(res.isConfirmed) form.submit(); });
        });
    });
    </script>
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