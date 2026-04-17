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
    $e_branch    = $_POST['edit_Branch_ID'] ?? null;
    $e_last      = $_POST['edit_Last_Name'] ?? null;
    $e_first     = $_POST['edit_First_Name'] ?? null;
    $e_middle    = $_POST['edit_Middle_Name'] ?? null;
    $e_nick      = $_POST['edit_Nickname'] ?? null;
    $e_age       = $_POST['edit_Age'] ?? null;
    $e_gender    = $_POST['edit_Gender'] ?? null;
    $e_dob       = $_POST['edit_Date_Of_Birth'] ?? null;
    $e_pob       = $_POST['edit_Place_Of_Birth'] ?? null;
    $e_civil     = $_POST['edit_Civil_Status'] ?? null;
    $e_religion  = $_POST['edit_Religion'] ?? null;
    $e_mlast     = $_POST['edit_Mother_Last_Name'] ?? null;
    $e_mfirst    = $_POST['edit_Mother_First_Name'] ?? null;
    $e_mmiddle   = $_POST['edit_Mother_Middle_Name'] ?? null;
    $e_mobile    = $_POST['edit_Mobile_No'] ?? null;
    $e_email     = $_POST['edit_Email_Address'] ?? null;
    $e_house     = $_POST['edit_House_Street_Bldng'] ?? null;
    $e_barangay  = $_POST['edit_Barangay_Town'] ?? null;
    $e_city      = $_POST['edit_City_Municipality'] ?? null;
    $e_province  = $_POST['edit_Province'] ?? null;
    $e_zip       = $_POST['edit_Zip_Code'] ?? null;
    $e_edu       = $_POST['edit_Educational_Attainment'] ?? null;
    $e_children  = $_POST['edit_No_Of_Children'] ?? null;
    $e_idpres    = $_POST['edit_ID_Presented'] ?? null;
    $e_idref     = $_POST['edit_ID_Reference_No'] ?? null;
    $e_splast    = $_POST['edit_Spouse_Last_Name'] ?? null;
    $e_spfirst   = $_POST['edit_Spouse_First_Name'] ?? null;
    $e_spmiddle  = $_POST['edit_Spouse_Middle_Name'] ?? null;
    $e_spwork    = $_POST['edit_Spouse_Work'] ?? null;
    $e_spnick    = $_POST['edit_Spouse_Nickname'] ?? null;
    $e_spage     = $_POST['edit_Spouse_Age'] ?? null;
    $e_spdob     = $_POST['edit_Spouse_DOB'] ?? null;
    $e_spincome  = $_POST['edit_Spouse_Income'] ?? null;
    $e_exp       = $_POST['edit_Exp_ID'] ?? null;

    $update_sql = "UPDATE tbl_client_info SET
        Branch_ID=?, Last_Name=?, First_Name=?, Middle_Name=?, Nickname=?,
        Age=?, Gender=?, Date_Of_Birth=?, Place_Of_Birth=?, Civil_Status=?, Religion=?,
        Mother_Last_Name=?, Mother_First_Name=?, Mother_Middle_Name=?,
        Mobile_No=?, Email_Address=?, House_Street_Bldng=?, Barangay_Town=?, City_Municipality=?, Province=?,
        Zip_Code=?, Educational_Attainment=?, No_Of_Children=?, ID_Presented=?, ID_Reference_No=?,
        Spouse_Last_Name=?, Spouse_First_Name=?, Spouse_Middle_Name=?, Spouse_Work=?, Spouse_Nickname=?, Spouse_Age=?, Spouse_DOB=?, Spouse_Income=?,
        Exp_ID=?
        WHERE id=?";
    $ustmt = mysqli_prepare($conn, $update_sql);
    if ($ustmt) {
        mysqli_stmt_bind_param($ustmt, 'ssssssssssssssssssssssssssssssssssi',
            $e_branch, $e_last, $e_first, $e_middle, $e_nick,
            $e_age, $e_gender, $e_dob, $e_pob, $e_civil, $e_religion,
            $e_mlast, $e_mfirst, $e_mmiddle,
            $e_mobile, $e_email, $e_house, $e_barangay, $e_city, $e_province,
            $e_zip, $e_edu, $e_children, $e_idpres, $e_idref,
            $e_splast, $e_spfirst, $e_spmiddle, $e_spwork, $e_spnick, $e_spage, $e_spdob, $e_spincome,
            $e_exp, $eid);
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
    $sql = "SELECT id, Client_ID, Branch_ID, Last_Name, First_Name, Middle_Name, Nickname,
                   Age, Gender, Date_Of_Birth, Place_Of_Birth, Civil_Status, Religion,
                   Mother_Last_Name, Mother_First_Name, Mother_Middle_Name,
                   Mobile_No, Email_Address, House_Street_Bldng, Barangay_Town, City_Municipality, Province,
                   Zip_Code, Educational_Attainment, No_Of_Children, ID_Presented, ID_Reference_No,
                   Spouse_Last_Name, Spouse_First_Name, Spouse_Middle_Name, Spouse_Work, Spouse_Nickname, Spouse_Age, Spouse_DOB, Spouse_Income,
                   created_at, exp_id AS Exp_ID
            FROM tbl_client_info ORDER BY id DESC";
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
    <title>TPKI || Admin Dashboard</title>
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
                                    <th style="width:160px;">Action</th>
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
                            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
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

                                        <h6 class="border-bottom border-secondary pb-1 mb-2">Personal Information</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">Last Name</dt><dd class="col-7" id="cvLast"></dd>
                                                    <dt class="col-5 text-muted">First Name</dt><dd class="col-7" id="cvFirst"></dd>
                                                    <dt class="col-5 text-muted">Middle Name</dt><dd class="col-7" id="cvMiddle"></dd>
                                                    <dt class="col-5 text-muted">Nickname</dt><dd class="col-7" id="cvNick"></dd>
                                                    <dt class="col-5 text-muted">Age</dt><dd class="col-7" id="cvAge"></dd>
                                                    <dt class="col-5 text-muted">Gender</dt><dd class="col-7" id="cvGender"></dd>
                                                </dl>
                                            </div>
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">Date of Birth</dt><dd class="col-7" id="cvDOB"></dd>
                                                    <dt class="col-5 text-muted">Place of Birth</dt><dd class="col-7" id="cvPOB"></dd>
                                                    <dt class="col-5 text-muted">Civil Status</dt><dd class="col-7" id="cvCivil"></dd>
                                                    <dt class="col-5 text-muted">Religion</dt><dd class="col-7" id="cvReligion"></dd>
                                                    <dt class="col-5 text-muted">Exp ID</dt><dd class="col-7" id="cvExp"></dd>
                                                </dl>
                                            </div>
                                        </div>

                                        <h6 class="border-bottom border-secondary pb-1 mb-2 mt-3">Contact &amp; Address</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">Mobile No</dt><dd class="col-7" id="cvMobile"></dd>
                                                    <dt class="col-5 text-muted">Email</dt><dd class="col-7" id="cvEmail"></dd>
                                                </dl>
                                            </div>
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">House/Street/Bldng</dt><dd class="col-7" id="cvHouse"></dd>
                                                    <dt class="col-5 text-muted">Barangay/Town</dt><dd class="col-7" id="cvBarangay"></dd>
                                                    <dt class="col-5 text-muted">City/Municipality</dt><dd class="col-7" id="cvCity"></dd>
                                                    <dt class="col-5 text-muted">Province</dt><dd class="col-7" id="cvProvince"></dd>
                                                    <dt class="col-5 text-muted">Zip Code</dt><dd class="col-7" id="cvZip"></dd>
                                                </dl>
                                            </div>
                                        </div>

                                        <h6 class="border-bottom border-secondary pb-1 mb-2 mt-3">Other Details</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">Education</dt><dd class="col-7" id="cvEdu"></dd>
                                                    <dt class="col-5 text-muted">No. of Children</dt><dd class="col-7" id="cvChildren"></dd>
                                                </dl>
                                            </div>
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">ID Presented</dt><dd class="col-7" id="cvIDPres"></dd>
                                                    <dt class="col-5 text-muted">ID Reference No</dt><dd class="col-7" id="cvIDRef"></dd>
                                                </dl>
                                            </div>
                                        </div>

                                        <h6 class="border-bottom border-secondary pb-1 mb-2 mt-3">Mother's Maiden Name</h6>
                                        <div class="row">
                                            <div class="col-md-4"><div class="text-muted small">Last Name</div><div id="cvMotherLast"></div></div>
                                            <div class="col-md-4"><div class="text-muted small">First Name</div><div id="cvMotherFirst"></div></div>
                                            <div class="col-md-4"><div class="text-muted small">Middle Name</div><div id="cvMotherMiddle"></div></div>
                                        </div>

                                        <h6 class="border-bottom border-secondary pb-1 mb-2 mt-3">Spouse Information</h6>
                                        <div class="row">
                                            <div class="col-md-4"><div class="text-muted small">Last Name</div><div id="cvSpouseLast"></div></div>
                                            <div class="col-md-4"><div class="text-muted small">First Name</div><div id="cvSpouseFirst"></div></div>
                                            <div class="col-md-4"><div class="text-muted small">Middle Name</div><div id="cvSpouseMiddle"></div></div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-3"><div class="text-muted small">Nickname</div><div id="cvSpouseNick"></div></div>
                                            <div class="col-md-2"><div class="text-muted small">Age</div><div id="cvSpouseAge"></div></div>
                                            <div class="col-md-3"><div class="text-muted small">Date of Birth</div><div id="cvSpouseDOB"></div></div>
                                            <div class="col-md-2"><div class="text-muted small">Work</div><div id="cvSpouseWork"></div></div>
                                            <div class="col-md-2"><div class="text-muted small">Income</div><div id="cvSpouseIncome"></div></div>
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
                            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content bg-dark text-white">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="clientEditLabel">Edit Client</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="post">
                                    <div class="modal-body">
                                        <input type="hidden" name="edit_id" id="edit_id">

                                        <h6 class="border-bottom border-secondary pb-1 mb-2">Personal Information</h6>
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
                                                <label class="form-label">Exp ID</label>
                                                <input name="edit_Exp_ID" id="edit_Exp_ID" class="form-control" type="date">
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
                                            <div class="col-md-4">
                                                <label class="form-label">Nickname</label>
                                                <input name="edit_Nickname" id="edit_Nickname" class="form-control">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Age</label>
                                                <input name="edit_Age" id="edit_Age" class="form-control" type="number">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Gender</label>
                                                <select name="edit_Gender" id="edit_Gender" class="form-select">
                                                    <option value="">--</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Date of Birth</label>
                                                <input name="edit_Date_Of_Birth" id="edit_Date_Of_Birth" class="form-control" type="date">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Place of Birth</label>
                                                <input name="edit_Place_Of_Birth" id="edit_Place_Of_Birth" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Civil Status</label>
                                                <select name="edit_Civil_Status" id="edit_Civil_Status" class="form-select">
                                                    <option value="">--</option>
                                                    <option value="Single">Single</option>
                                                    <option value="Married">Married</option>
                                                    <option value="Widowed">Widowed</option>
                                                    <option value="Separated">Separated</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Religion</label>
                                                <input name="edit_Religion" id="edit_Religion" class="form-control">
                                            </div>
                                        </div>

                                        <h6 class="border-bottom border-secondary pb-1 mb-2 mt-3">Contact &amp; Address</h6>
                                        <div class="row g-2">
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
                                            <div class="col-md-4">
                                                <label class="form-label">City/Municipality</label>
                                                <input name="edit_City_Municipality" id="edit_City_Municipality" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Province</label>
                                                <input name="edit_Province" id="edit_Province" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Zip Code</label>
                                                <input name="edit_Zip_Code" id="edit_Zip_Code" class="form-control">
                                            </div>
                                        </div>

                                        <h6 class="border-bottom border-secondary pb-1 mb-2 mt-3">Other Details</h6>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label">Education</label>
                                                <input name="edit_Educational_Attainment" id="edit_Educational_Attainment" class="form-control">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">No. of Children</label>
                                                <input name="edit_No_Of_Children" id="edit_No_Of_Children" class="form-control" type="number">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">ID Presented</label>
                                                <input name="edit_ID_Presented" id="edit_ID_Presented" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">ID Reference No</label>
                                                <input name="edit_ID_Reference_No" id="edit_ID_Reference_No" class="form-control">
                                            </div>
                                        </div>

                                        <h6 class="border-bottom border-secondary pb-1 mb-2 mt-3">Mother's Maiden Name</h6>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label">Last Name</label>
                                                <input name="edit_Mother_Last_Name" id="edit_Mother_Last_Name" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">First Name</label>
                                                <input name="edit_Mother_First_Name" id="edit_Mother_First_Name" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Middle Name</label>
                                                <input name="edit_Mother_Middle_Name" id="edit_Mother_Middle_Name" class="form-control">
                                            </div>
                                        </div>

                                        <h6 class="border-bottom border-secondary pb-1 mb-2 mt-3">Spouse Information</h6>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label">Last Name</label>
                                                <input name="edit_Spouse_Last_Name" id="edit_Spouse_Last_Name" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">First Name</label>
                                                <input name="edit_Spouse_First_Name" id="edit_Spouse_First_Name" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Middle Name</label>
                                                <input name="edit_Spouse_Middle_Name" id="edit_Spouse_Middle_Name" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Nickname</label>
                                                <input name="edit_Spouse_Nickname" id="edit_Spouse_Nickname" class="form-control">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Age</label>
                                                <input name="edit_Spouse_Age" id="edit_Spouse_Age" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Date of Birth</label>
                                                <input name="edit_Spouse_DOB" id="edit_Spouse_DOB" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Work</label>
                                                <input name="edit_Spouse_Work" id="edit_Spouse_Work" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Income</label>
                                                <input name="edit_Spouse_Income" id="edit_Spouse_Income" class="form-control" type="number" step="0.01">
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

        function esc(v) { return $('<span>').text(v||'').html(); }

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
                { data: null, orderable: false, render: function(data, type, row){
                        var fields = {
                            'id': row.id, 'clientid': row.Client_ID, 'branchid': row.Branch_ID,
                            'last': row.Last_Name, 'first': row.First_Name, 'middle': row.Middle_Name,
                            'nick': row.Nickname, 'age': row.Age, 'gender': row.Gender,
                            'dob': row.Date_Of_Birth, 'pob': row.Place_Of_Birth,
                            'civil': row.Civil_Status, 'religion': row.Religion,
                            'mlast': row.Mother_Last_Name, 'mfirst': row.Mother_First_Name, 'mmiddle': row.Mother_Middle_Name,
                            'mobile': row.Mobile_No, 'email': row.Email_Address,
                            'house': row.House_Street_Bldng, 'barangay': row.Barangay_Town,
                            'city': row.City_Municipality, 'province': row.Province,
                            'zip': row.Zip_Code, 'edu': row.Educational_Attainment,
                            'children': row.No_Of_Children, 'idpres': row.ID_Presented, 'idref': row.ID_Reference_No,
                            'splast': row.Spouse_Last_Name, 'spfirst': row.Spouse_First_Name, 'spmiddle': row.Spouse_Middle_Name,
                            'spwork': row.Spouse_Work, 'spnick': row.Spouse_Nickname, 'spage': row.Spouse_Age,
                            'spdob': row.Spouse_DOB, 'spincome': row.Spouse_Income,
                            'exp': row.Exp_ID
                        };
                        var attrs = '';
                        for (var k in fields) { attrs += ' data-' + k + '="' + esc(fields[k]) + '"'; }

                        var viewBtn = '<button type="button" class="btn btn-sm btn-primary view-client me-1" data-bs-toggle="modal" data-bs-target="#clientViewModal"' + attrs + '><i class="bi bi-eye"></i></button>';
                        var editBtn = '<button type="button" class="btn btn-sm btn-warning edit-client me-1" data-bs-toggle="modal" data-bs-target="#clientEditModal"' + attrs + '><i class="bi bi-pencil"></i></button>';
                        var deleteBtn = '<form method="post" class="d-inline delete-form" style="display:inline">'
                            + '<input type="hidden" name="delete_id" value="'+esc(row.id)+'">'
                            + '<button type="button" class="btn btn-sm btn-danger del-client"><i class="bi bi-trash"></i></button>'
                            + '</form>';

                        return '<div class="text-nowrap">' + viewBtn + editBtn + deleteBtn + '</div>';
                    } }
            ]
        });

        // ---- Populate client VIEW modal ----
        $('#clientViewModal').on('show.bs.modal', function(event) {
            var b = $(event.relatedTarget);
            var m = $(this);

            m.find('#clientProfPic').html('');
            var fullName = (b.data('first')||'') + ' ' + (b.data('middle')||'') + ' ' + (b.data('last')||'');
            m.find('#cvFullName').text(fullName.trim());
            m.find('#cvClientID').text(b.data('clientid')||'');
            m.find('#cvBranch').text(b.data('branchid')||'');

            m.find('#cvLast').text(b.data('last')||'');
            m.find('#cvFirst').text(b.data('first')||'');
            m.find('#cvMiddle').text(b.data('middle')||'');
            m.find('#cvNick').text(b.data('nick')||'');
            m.find('#cvAge').text(b.data('age')||'');
            m.find('#cvGender').text(b.data('gender')||'');
            m.find('#cvDOB').text(b.data('dob')||'');
            m.find('#cvPOB').text(b.data('pob')||'');
            m.find('#cvCivil').text(b.data('civil')||'');
            m.find('#cvReligion').text(b.data('religion')||'');
            m.find('#cvExp').text(b.data('exp')||'');

            m.find('#cvMobile').text(b.data('mobile')||'');
            m.find('#cvEmail').text(b.data('email')||'');
            m.find('#cvHouse').text(b.data('house')||'');
            m.find('#cvBarangay').text(b.data('barangay')||'');
            m.find('#cvCity').text(b.data('city')||'');
            m.find('#cvProvince').text(b.data('province')||'');
            m.find('#cvZip').text(b.data('zip')||'');

            m.find('#cvEdu').text(b.data('edu')||'');
            m.find('#cvChildren').text(b.data('children')||'');
            m.find('#cvIDPres').text(b.data('idpres')||'');
            m.find('#cvIDRef').text(b.data('idref')||'');

            m.find('#cvMotherLast').text(b.data('mlast')||'');
            m.find('#cvMotherFirst').text(b.data('mfirst')||'');
            m.find('#cvMotherMiddle').text(b.data('mmiddle')||'');

            m.find('#cvSpouseLast').text(b.data('splast')||'');
            m.find('#cvSpouseFirst').text(b.data('spfirst')||'');
            m.find('#cvSpouseMiddle').text(b.data('spmiddle')||'');
            m.find('#cvSpouseNick').text(b.data('spnick')||'');
            m.find('#cvSpouseAge').text(b.data('spage')||'');
            m.find('#cvSpouseDOB').text(b.data('spdob')||'');
            m.find('#cvSpouseWork').text(b.data('spwork')||'');
            m.find('#cvSpouseIncome').text(b.data('spincome')||'');
        });

        // ---- Populate client EDIT modal ----
        $('#clientEditModal').on('show.bs.modal', function(event) {
            var b = $(event.relatedTarget);
            var m = $(this);
            m.find('#edit_id').val(b.data('id')||'');
            m.find('#edit_Branch_ID').val(b.data('branchid')||'');
            m.find('#edit_Exp_ID').val(b.data('exp')||'');
            m.find('#edit_Last_Name').val(b.data('last')||'');
            m.find('#edit_First_Name').val(b.data('first')||'');
            m.find('#edit_Middle_Name').val(b.data('middle')||'');
            m.find('#edit_Nickname').val(b.data('nick')||'');
            m.find('#edit_Age').val(b.data('age')||'');
            m.find('#edit_Gender').val(b.data('gender')||'');
            m.find('#edit_Date_Of_Birth').val(b.data('dob')||'');
            m.find('#edit_Place_Of_Birth').val(b.data('pob')||'');
            m.find('#edit_Civil_Status').val(b.data('civil')||'');
            m.find('#edit_Religion').val(b.data('religion')||'');
            m.find('#edit_Mobile_No').val(b.data('mobile')||'');
            m.find('#edit_Email_Address').val(b.data('email')||'');
            m.find('#edit_House_Street_Bldng').val(b.data('house')||'');
            m.find('#edit_Barangay_Town').val(b.data('barangay')||'');
            m.find('#edit_City_Municipality').val(b.data('city')||'');
            m.find('#edit_Province').val(b.data('province')||'');
            m.find('#edit_Zip_Code').val(b.data('zip')||'');
            m.find('#edit_Educational_Attainment').val(b.data('edu')||'');
            m.find('#edit_No_Of_Children').val(b.data('children')||'');
            m.find('#edit_ID_Presented').val(b.data('idpres')||'');
            m.find('#edit_ID_Reference_No').val(b.data('idref')||'');
            m.find('#edit_Mother_Last_Name').val(b.data('mlast')||'');
            m.find('#edit_Mother_First_Name').val(b.data('mfirst')||'');
            m.find('#edit_Mother_Middle_Name').val(b.data('mmiddle')||'');
            m.find('#edit_Spouse_Last_Name').val(b.data('splast')||'');
            m.find('#edit_Spouse_First_Name').val(b.data('spfirst')||'');
            m.find('#edit_Spouse_Middle_Name').val(b.data('spmiddle')||'');
            m.find('#edit_Spouse_Nickname').val(b.data('spnick')||'');
            m.find('#edit_Spouse_Age').val(b.data('spage')||'');
            m.find('#edit_Spouse_DOB').val(b.data('spdob')||'');
            m.find('#edit_Spouse_Work').val(b.data('spwork')||'');
            m.find('#edit_Spouse_Income').val(b.data('spincome')||'');
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