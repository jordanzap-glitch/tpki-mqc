<?php 
session_start();

include __DIR__ . '/../db/dbcon.php';

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    $dstmt = mysqli_prepare($conn, "DELETE FROM tbl_client_info WHERE id = ?");
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

// Handle edit/update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edit_id'])) {
    $eid = intval($_POST['edit_id']);
    // collect editable fields
    $e_branch = isset($_POST['edit_Branch_ID']) ? $_POST['edit_Branch_ID'] : null;
    $e_last = isset($_POST['edit_Last_Name']) ? $_POST['edit_Last_Name'] : null;
    $e_first = isset($_POST['edit_First_Name']) ? $_POST['edit_First_Name'] : null;
    $e_middle = isset($_POST['edit_Middle_Name']) ? $_POST['edit_Middle_Name'] : null;
    $e_nick = isset($_POST['edit_Nickname']) ? $_POST['edit_Nickname'] : null;
    $e_mobile = isset($_POST['edit_Mobile_No']) ? $_POST['edit_Mobile_No'] : null;
    $e_email = isset($_POST['edit_Email_Address']) ? $_POST['edit_Email_Address'] : null;
    $e_house = isset($_POST['edit_House_Street_Bldng']) ? $_POST['edit_House_Street_Bldng'] : null;
    $e_barangay = isset($_POST['edit_Barangay_Town']) ? $_POST['edit_Barangay_Town'] : null;
    $e_city = isset($_POST['edit_City_Municipality']) ? $_POST['edit_City_Municipality'] : null;
    $e_province = isset($_POST['edit_Province']) ? $_POST['edit_Province'] : null;

    $update_sql = "UPDATE tbl_client_info SET Branch_ID=?, Last_Name=?, First_Name=?, Middle_Name=?, Nickname=?, Mobile_No=?, Email_Address=?, House_Street_Bldng=?, Barangay_Town=?, City_Municipality=?, Province=? WHERE id=?";
    $ustmt = mysqli_prepare($conn, $update_sql);
    if ($ustmt) {
        mysqli_stmt_bind_param($ustmt, 'sssssssssssi', $e_branch, $e_last, $e_first, $e_middle, $e_nick, $e_mobile, $e_email, $e_house, $e_barangay, $e_city, $e_province, $eid);
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

// Simple JSON endpoint for client-side fetching
if (isset($_GET['fetch_clients'])) {
    $out = ['data' => []];
    $sql = "SELECT id, Client_ID, Branch_ID, Last_Name, First_Name, Middle_Name, Mobile_No, Email_Address, Date_Of_Birth, Age, Civil_Status, Barangay_Town, City_Municipality, Province FROM tbl_client_info ORDER BY id DESC";
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

?>





<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>TPKI || Client Records</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">

    <?php include "includes/head.php"; ?>
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
        
        <!-- Spinner End -->


        <!-- Sidebar Start -->
        <?php include "includes/sidebar.php"; ?>
        <!-- Sidebar End -->


        <!-- Content Start -->
        <div class="content">
            <!-- Navbar Start -->
           <?php include "includes/navbar.php"; ?>
            <!-- Navbar End -->


            <!-- Records Table Start -->
            <div class="container-fluid pt-4 px-4">
                <div class="bg-secondary text-start rounded p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h6 class="mb-0">Client Records</h6>
                    </div>

                    <div class="table-responsive">
                        <table id="recordsTable" class="table table-striped table-bordered mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th><input class="form-check-input" type="checkbox"></th>
                                    <th>Client ID</th>
                                    <th>Branch</th>
                                    <th>Last Name</th>
                                    <th>First Name</th>
                                    <th>Mobile No</th>
                                    <th>Barangay/Town</th>
                                    <th>City/Municipality</th>
                                    <th style="display:none">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Server-side rendering of table rows removed.
                                // Populate table rows client-side (AJAX) or re-enable server fetch if needed.
                                echo '<tr><td colspan="9" class="text-center">No records loaded (server-side fetch removed)</td></tr>';
                                ?>
                            </tbody>
                        </table>

                        <!-- Client View Modal -->
                        <div class="modal fade" id="clientViewModal" tabindex="-1" aria-labelledby="clientViewLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content bg-dark text-white">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="clientViewLabel">Client Details</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="d-flex mb-3">
                                            <div id="clientProfPic" class="me-3" style="width:96px;height:96px;background:#222;display:flex;align-items:center;justify-content:center;border-radius:6px;overflow:hidden"></div>
                                            <div>
                                                <h5 id="cvFullName" class="mb-0"></h5>
                                                <div class="text-muted" id="cvClientID"></div>
                                                <div class="text-muted" id="cvBranch"></div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">Last Name</dt><dd class="col-7" id="cvLast"></dd>
                                                    <dt class="col-5 text-muted">First Name</dt><dd class="col-7" id="cvFirst"></dd>
                                                    <dt class="col-5 text-muted">Middle Name</dt><dd class="col-7" id="cvMiddle"></dd>
                                                    <dt class="col-5 text-muted">Nickname</dt><dd class="col-7" id="cvNick"></dd>
                                                    <dt class="col-5 text-muted">Age</dt><dd class="col-7" id="cvAge"></dd>
                                                    <dt class="col-5 text-muted">Gender</dt><dd class="col-7" id="cvGender"></dd>
                                                    <dt class="col-5 text-muted">Date of Birth</dt><dd class="col-7" id="cvDOB"></dd>
                                                    <dt class="col-5 text-muted">Place of Birth</dt><dd class="col-7" id="cvPOB"></dd>
                                                    <dt class="col-5 text-muted">Civil Status</dt><dd class="col-7" id="cvCivil"></dd>
                                                    <dt class="col-5 text-muted">Religion</dt><dd class="col-7" id="cvReligion"></dd>
                                                </dl>
                                            </div>
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">Mobile No</dt><dd class="col-7" id="cvMobile"></dd>
                                                    <dt class="col-5 text-muted">Email</dt><dd class="col-7" id="cvEmail"></dd>
                                                    <dt class="col-5 text-muted">Address</dt><dd class="col-7" id="cvAddress"></dd>
                                                    <dt class="col-5 text-muted">Zip Code</dt><dd class="col-7" id="cvZip"></dd>
                                                    <dt class="col-5 text-muted">Education</dt><dd class="col-7" id="cvEdu"></dd>
                                                    <dt class="col-5 text-muted">No. of Children</dt><dd class="col-7" id="cvChildren"></dd>
                                                    <dt class="col-5 text-muted">ID Presented</dt><dd class="col-7" id="cvIDPres"></dd>
                                                    <dt class="col-5 text-muted">ID Reference No</dt><dd class="col-7" id="cvIDRef"></dd>
                                                </dl>
                                            </div>
                                        </div>
                                        <hr class="border-secondary">
                                        <h6>Spouse</h6>
                                        <div class="row">
                                            <div class="col-md-4"><div class="text-muted small">Name</div><div id="cvSpouseName"></div></div>
                                            <div class="col-md-4"><div class="text-muted small">Work</div><div id="cvSpouseWork"></div></div>
                                            <div class="col-md-4"><div class="text-muted small">Income</div><div id="cvSpouseIncome"></div></div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Client Edit Modal -->
                        <div class="modal fade" id="clientEditModal" tabindex="-1" aria-labelledby="clientEditLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content bg-dark text-white">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="clientEditLabel">Edit Client</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="post">
                                    <div class="modal-body">
                                        <input type="hidden" name="edit_id" id="edit_id">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="form-label">Branch</label>
                                                <select name="edit_Branch_ID" id="edit_Branch_ID" class="form-select">
                                                    <option value="">-- Select Branch --</option>
                                                    <?php
                                                    $bq = mysqli_query($conn, "SELECT Branch_ID, Branch_Name FROM tbl_branch WHERE Is_Active = 1 ORDER BY Branch_Name");
                                                    if ($bq) {
                                                        while ($b = mysqli_fetch_assoc($bq)) {
                                                            $bid = htmlspecialchars($b['Branch_ID']);
                                                            $bname = htmlspecialchars($b['Branch_Name']);
                                                            echo "<option value=\"$bid\">$bname</option>";
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Nickname</label>
                                                <input name="edit_Nickname" id="edit_Nickname" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Last Name</label>
                                                <input name="edit_Last_Name" id="edit_Last_Name" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">First Name</label>
                                                <input name="edit_First_Name" id="edit_First_Name" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Middle Name</label>
                                                <input name="edit_Middle_Name" id="edit_Middle_Name" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Mobile No</label>
                                                <input name="edit_Mobile_No" id="edit_Mobile_No" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Email Address</label>
                                                <input name="edit_Email_Address" id="edit_Email_Address" class="form-control" type="email">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">House/Street/Bldng</label>
                                                <input name="edit_House_Street_Bldng" id="edit_House_Street_Bldng" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Barangay/Town</label>
                                                <input name="edit_Barangay_Town" id="edit_Barangay_Town" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">City/Municipality</label>
                                                <input name="edit_City_Municipality" id="edit_City_Municipality" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Province</label>
                                                <input name="edit_Province" id="edit_Province" class="form-control">
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
                </div>
            </div>
            <!-- Records Table End -->


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
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Template Javascript -->
    <script src="../js/main.js"></script>
    <script>
    $(document).ready(function() {
        $('#recordsTable').DataTable({
            ajax: 'client_record.php?fetch_clients=1',
            paging: true,
            searching: true,
            info: true,
            ordering: true,
            columns: [
                { data: null, orderable: false, render: function(){ return '<input class="form-check-input" type="checkbox">'; } },
                { data: 'Client_ID' },
                { data: 'Branch_ID' },
                { data: 'Last_Name' },
                { data: 'First_Name' },
                { data: 'Mobile_No' },
                { data: 'Barangay_Town' },
                { data: 'City_Municipality' },
                { data: null, orderable: false, render: function(){ return ''; } }
            ]
        });

        // Populate client view modal
        $('#clientViewModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var modal = $(this);

            var prof = button.data('profpic') || '';
            if (prof) {
                modal.find('#clientProfPic').html('<img src="'+prof+'" style="width:96px;height:96px;object-fit:cover;">');
            } else {
                modal.find('#clientProfPic').html('');
            }

            var fullName = (button.data('first') || '') + ' ' + (button.data('middle') || '') + ' ' + (button.data('last') || '');
            modal.find('#cvFullName').text(fullName.trim());
            modal.find('#cvClientID').text(button.data('clientid') || '');
            modal.find('#cvBranch').text(button.data('branchname') || button.data('branchid') || '');

            modal.find('#cvLast').text(button.data('last') || '');
            modal.find('#cvFirst').text(button.data('first') || '');
            modal.find('#cvMiddle').text(button.data('middle') || '');
            modal.find('#cvNick').text(button.data('nick') || '');
            modal.find('#cvAge').text(button.data('age') || '');
            modal.find('#cvGender').text(button.data('gender') || '');
            modal.find('#cvDOB').text(button.data('dob') || '');
            modal.find('#cvPOB').text(button.data('pob') || '');
            modal.find('#cvCivil').text(button.data('civil') || '');
            modal.find('#cvReligion').text(button.data('religion') || '');

            modal.find('#cvMobile').text(button.data('mobile') || '');
            modal.find('#cvEmail').text(button.data('email') || '');
            var address = (button.data('house') || '') + '\n' + (button.data('barangay') || '') + '\n' + (button.data('city') || '') + ', ' + (button.data('province') || '');
            modal.find('#cvAddress').text(address.replace(/(^\n|\n$)/g, ''));
            modal.find('#cvZip').text(button.data('zip') || '');
            modal.find('#cvEdu').text(button.data('edu') || '');
            modal.find('#cvChildren').text(button.data('children') || '');
            modal.find('#cvIDPres').text(button.data('idpres') || '');
            modal.find('#cvIDRef').text(button.data('idref') || '');

            var spouseName = (button.data('splast') || '') + ', ' + (button.data('spfirst') || '') + ' ' + (button.data('spmid') || '');
            modal.find('#cvSpouseName').text(spouseName.trim());
            modal.find('#cvSpouseWork').text(button.data('spwork') || '');
            modal.find('#cvSpouseIncome').text(button.data('spincome') || '');
        });

        // Populate edit modal
        $('#clientEditModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var modal = $(this);
            modal.find('#edit_id').val(button.data('id') || '');
            modal.find('#edit_Branch_ID').val(button.data('branchid') || '');
            modal.find('#edit_Last_Name').val(button.data('last') || '');
            modal.find('#edit_First_Name').val(button.data('first') || '');
            modal.find('#edit_Middle_Name').val(button.data('middle') || '');
            modal.find('#edit_Nickname').val(button.data('nick') || '');
            modal.find('#edit_Mobile_No').val(button.data('mobile') || '');
            modal.find('#edit_Email_Address').val(button.data('email') || '');
            modal.find('#edit_House_Street_Bldng').val(button.data('house') || '');
            modal.find('#edit_Barangay_Town').val(button.data('barangay') || '');
            modal.find('#edit_City_Municipality').val(button.data('city') || '');
            modal.find('#edit_Province').val(button.data('province') || '');
        });

        // Delete confirmation
        $(document).on('click', '.del-client', function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({
                title: 'Delete record?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
    </script>
    <?php
    // Show SweetAlert feedback for server-side actions (edit/delete)
    if (!empty($success)) {
        $msg = addslashes($success);
        echo "<script>Swal.fire({icon: 'success', title: 'Success', text: '{$msg}'});</script>";
    } elseif (!empty($error)) {
        $emsg = addslashes($error);
        echo "<script>Swal.fire({icon: 'error', title: 'Error', text: '{$emsg}'});</script>";
    }
    ?>
</body>

</html>