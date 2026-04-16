<?php 
session_start();
require_once __DIR__ . '/../db/dbcon.php';
?>
<?php
// Handle create asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_asset'])) {
    // Generate next Asset_ID in format AS-XXX
    $last_q = mysqli_query($conn, "SELECT Asset_ID FROM tbl_client_asset ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/AS-(\d+)/', $row['Asset_ID'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
    }
    $asset_id = sprintf('AS-%03d', $nextNum);

    $client_id = isset($_POST['Client_ID']) ? trim($_POST['Client_ID']) : '';
    $asset_name = isset($_POST['Asset_Name']) ? trim($_POST['Asset_Name']) : '';
    $asset_desc = isset($_POST['Asset_Description']) ? trim($_POST['Asset_Description']) : '';

    if ($client_id === '' || $asset_name === '') {
        $error = 'Client and Asset Name are required.';
    } else {
        $istmt = mysqli_prepare($conn, "INSERT INTO tbl_client_asset (Asset_ID, Client_ID, Asset_Name, Asset_Description) VALUES (?, ?, ?, ?)");
        if ($istmt) {
            mysqli_stmt_bind_param($istmt, 'ssss', $asset_id, $client_id, $asset_name, $asset_desc);
            if (mysqli_stmt_execute($istmt)) {
                $success = 'Asset saved successfully.';
            } else {
                $error = 'Insert failed: ' . mysqli_stmt_error($istmt);
            }
            mysqli_stmt_close($istmt);
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
// Handle delete asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_asset'])) {
    $del_id = intval($_POST['delete_asset']);
    $dstmt = mysqli_prepare($conn, "DELETE FROM tbl_client_asset WHERE id = ?");
    if ($dstmt) {
        mysqli_stmt_bind_param($dstmt, 'i', $del_id);
        if (mysqli_stmt_execute($dstmt)) {
            $success = 'Asset deleted successfully.';
        } else {
            $error = 'Delete failed: ' . mysqli_stmt_error($dstmt);
        }
        mysqli_stmt_close($dstmt);
    } else {
        $error = 'Delete prepare failed: ' . mysqli_error($conn);
    }
}

// Handle edit/update asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edit_asset'])) {
    $eid = intval($_POST['edit_asset']);
    $ecid = isset($_POST['edit_Client_ID']) ? trim($_POST['edit_Client_ID']) : null;
    $ename = isset($_POST['edit_Asset_Name']) ? trim($_POST['edit_Asset_Name']) : null;
    $edesc = isset($_POST['edit_Asset_Description']) ? trim($_POST['edit_Asset_Description']) : null;
    $usql = "UPDATE tbl_client_asset SET Client_ID=?, Asset_Name=?, Asset_Description=? WHERE id=?";
    $ustmt = mysqli_prepare($conn, $usql);
    if ($ustmt) {
        mysqli_stmt_bind_param($ustmt, 'sssi', $ecid, $ename, $edesc, $eid);
        if (mysqli_stmt_execute($ustmt)) {
            $success = 'Asset updated successfully.';
        } else {
            $error = 'Update failed: ' . mysqli_stmt_error($ustmt);
        }
        mysqli_stmt_close($ustmt);
    } else {
        $error = 'Update prepare failed: ' . mysqli_error($conn);
    }
}

// Fetch assets for display
$assets = [];
$aq = mysqli_query($conn, "SELECT a.*, c.First_Name, c.Last_Name FROM tbl_client_asset a LEFT JOIN tbl_client_info c ON a.Client_ID = c.Client_ID ORDER BY a.id DESC");
if ($aq) {
    while ($a = mysqli_fetch_assoc($aq)) {
        $assets[] = $a;
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
    /* Dark scrollbar for table container */
    .table-responsive::-webkit-scrollbar { height:12px; width:12px; }
    .table-responsive::-webkit-scrollbar-thumb { background:#000; border-radius:6px; }
    .table-responsive::-webkit-scrollbar-track { background:#333; }
    .table-responsive { scrollbar-color: #000 #333; scrollbar-width: thin; }
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


            <!-- Blank Content Start: keep layout but remove inner content -->
            <div class="container-fluid pt-4 px-4">
                <div class="row bg-secondary rounded p-4 mx-0">
                    <div class="col-md-8">
                        <h5 class="mb-3">Add Client Asset</h5>
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Asset ID</label>
                                <input type="text" class="form-control" value="<?php echo isset($asset_id) ? htmlspecialchars($asset_id) : 'AS-001'; ?>" readonly>
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
                            <div class="mb-3">
                                <label class="form-label">Asset Name</label>
                                <input name="Asset_Name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Asset Description</label>
                                <textarea name="Asset_Description" class="form-control" rows="4"></textarea>
                            </div>
                            <input type="hidden" name="save_asset" value="1">
                            <button class="btn btn-primary">Save Asset</button>
                        </form>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="bg-secondary rounded p-4">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h6 class="mb-0">Client Assets</h6>
                            </div>
                            <div class="table-responsive">
                                <table id="assetsTable" class="table table-striped table-bordered mb-0" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th><input class="form-check-input" type="checkbox"></th>
                                            <th>Asset ID</th>
                                            <th>Client</th>
                                            <th>Asset Name</th>
                                            <th>Asset Description</th>
                                            <th style="width:160px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($assets)) {
                                        foreach ($assets as $row) {
                                            $aid = (int)($row['id'] ?? 0);
                                            $assetid = htmlspecialchars($row['Asset_ID'] ?? '');
                                            $clientLabel = htmlspecialchars((($row['Last_Name'] ?? '') . ', ' . ($row['First_Name'] ?? '') . ' (' . ($row['Client_ID'] ?? '') . ')'));
                                            $aname = htmlspecialchars($row['Asset_Name'] ?? '');
                                            $adesc = htmlspecialchars($row['Asset_Description'] ?? '');
                                            $data_attrs = 'data-id="' . $aid . '" data-assetid="' . $assetid . '" data-clientid="' . htmlspecialchars($row['Client_ID'] ?? '', ENT_QUOTES) . '"';
                                            $data_attrs .= ' data-aname="' . $aname . '" data-adesc="' . $adesc . '"';
                                            echo "<tr>";
                                            echo "<td><input class=\"form-check-input\" type=\"checkbox\"></td>";
                                            echo "<td>" . $assetid . "</td>";
                                            echo "<td>" . $clientLabel . "</td>";
                                            echo "<td>" . $aname . "</td>";
                                            echo "<td>" . $adesc . "</td>";
                                            echo "<td class=\"text-nowrap\">";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-primary view-asset me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#assetViewModal\" $data_attrs><i class=\"bi bi-eye\"></i></button>";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-warning edit-asset me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#assetEditModal\" $data_attrs><i class=\"bi bi-pencil\"></i></button>";
                                            echo "<form method=\"post\" class=\"d-inline delete-asset-form\">";
                                            echo "<input type=\"hidden\" name=\"delete_asset\" value=\"" . $aid . "\">";
                                            echo "<button type=\"button\" class=\"btn btn-sm btn-danger del-asset\"><i class=\"bi bi-trash\"></i></button>";
                                            echo "</form>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan=6 class=\"text-center\">No assets found</td></tr>";
                                    } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Asset View Modal (redesigned like client view) -->
                <div class="modal fade" id="assetViewModal" tabindex="-1" aria-labelledby="assetViewLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title" id="assetViewLabel">Asset Details</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="d-flex mb-3">
                                    <div id="assetProfPic" class="me-3" style="width:96px;height:96px;background:#222;display:flex;align-items:center;justify-content:center;border-radius:6px;overflow:hidden"></div>
                                    <div>
                                        <h5 id="avTitle" class="mb-0"></h5>
                                        <div class="text-muted" id="avAssetID"></div>
                                        <div class="text-muted" id="avClientLabel"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <dl class="row mb-0">
                                            <dt class="col-5 text-muted">Asset Name</dt><dd class="col-7" id="avName"></dd>
                                            <dt class="col-5 text-muted">Description</dt><dd class="col-7" id="avDesc"></dd>
                                        </dl>
                                    </div>
                                    <div class="col-md-6">
                                        <dl class="row mb-0">
                                            <dt class="col-5 text-muted">Client</dt><dd class="col-7" id="avClient"></dd>
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

                <!-- Asset Edit Modal -->
                <div class="modal fade" id="assetEditModal" tabindex="-1" aria-labelledby="assetEditLabel" aria-hidden="true">
                    <div class="modal-dialog modal-md modal-dialog-centered">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title" id="assetEditLabel">Edit Asset</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="edit_asset" id="edit_asset">
                                <div class="mb-3">
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
                                <div class="mb-3">
                                    <label class="form-label">Asset Name</label>
                                    <input name="edit_Asset_Name" id="edit_Asset_Name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Asset Description</label>
                                    <textarea name="edit_Asset_Description" id="edit_Asset_Description" class="form-control" rows="3"></textarea>
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
            <!-- Blank Content End -->


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
        if ($('.select2').length) {
            $('.select2').select2({ width: '100%' });
        }
        $('#assetsTable').DataTable({ paging:true, searching:true, info:true, ordering:true, columnDefs:[{orderable:false, targets:[0,5]}] });

        // Populate view modal (redesigned)
        $(document).on('click', '.view-asset', function() {
            var btn = $(this);
            var modal = $('#assetViewModal');
            // show a simple icon in the left box for assets
            modal.find('#assetProfPic').html('<div style="width:96px;height:96px;display:flex;align-items:center;justify-content:center;background:#222;border-radius:6px;"><i class="bi bi-box-seam" style="font-size:32px;color:#fff;"></i></div>');
            modal.find('#avTitle').text(btn.data('aname') || '');
            modal.find('#avAssetID').text(btn.data('assetid') || '');
            var clientLabel = btn.data('clientid') ? (btn.closest('tr').find('td:nth-child(3)').text()) : '';
            modal.find('#avClientLabel').text(clientLabel);
            modal.find('#avClient').text(clientLabel);
            modal.find('#avName').text(btn.data('aname') || '');
            modal.find('#avDesc').text(btn.data('adesc') || '');
        });

        // Populate edit modal
        $(document).on('click', '.edit-asset', function() {
            var btn = $(this);
            $('#edit_asset').val(btn.data('id') || '');
            $('#edit_Client_ID').val(btn.data('clientid') || '').trigger('change');
            $('#edit_Asset_Name').val(btn.data('aname') || '');
            $('#edit_Asset_Description').val(btn.data('adesc') || '');
        });

        // Delete confirmation
        $(document).on('click', '.del-asset', function(e){
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({title:'Delete asset?', text:'This action cannot be undone.', icon:'warning', showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#d33'}).then((res)=>{ if(res.isConfirmed) form.submit(); });
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