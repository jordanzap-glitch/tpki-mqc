<?php
session_start();
require_once __DIR__ . '/../db/dbcon.php';

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    $dstmt = mysqli_prepare($conn, "DELETE FROM tbl_comaker_info WHERE id = ?");
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
    // Collect editable fields (map to tbl_comaker_info columns)
    $e_last = isset($_POST['edit_Last_Name']) ? $_POST['edit_Last_Name'] : null;
    $e_first = isset($_POST['edit_First_Name']) ? $_POST['edit_First_Name'] : null;
    $e_middle = isset($_POST['edit_Middle_Name']) ? $_POST['edit_Middle_Name'] : null;
    $e_age = isset($_POST['edit_Age']) && $_POST['edit_Age'] !== '' ? intval($_POST['edit_Age']) : null;
    $e_gender = isset($_POST['edit_Gender']) ? $_POST['edit_Gender'] : null;
    $e_dob = isset($_POST['edit_Date_Of_Birth']) ? $_POST['edit_Date_Of_Birth'] : null;
    $e_pob = isset($_POST['edit_Place_Of_Birth']) ? $_POST['edit_Place_Of_Birth'] : null;
    $e_civil = isset($_POST['edit_Civil_Status']) ? $_POST['edit_Civil_Status'] : null;
    $e_mobile = isset($_POST['edit_Mobile_No']) ? $_POST['edit_Mobile_No'] : null;
    $e_email = isset($_POST['edit_Email_Address']) ? $_POST['edit_Email_Address'] : null;
    $e_house = isset($_POST['edit_House_Street_Bldng']) ? $_POST['edit_House_Street_Bldng'] : null;
    $e_barangay = isset($_POST['edit_Barangay_Town']) ? $_POST['edit_Barangay_Town'] : null;
    $e_city = isset($_POST['edit_City_Municipality']) ? $_POST['edit_City_Municipality'] : null;
    $e_province = isset($_POST['edit_Province']) ? $_POST['edit_Province'] : null;
    $e_zip = isset($_POST['edit_Zip_Code']) ? $_POST['edit_Zip_Code'] : null;
    $e_children = isset($_POST['edit_No_Of_Children']) && $_POST['edit_No_Of_Children'] !== '' ? intval($_POST['edit_No_Of_Children']) : null;
    $e_idpres = isset($_POST['edit_ID_Presented']) ? $_POST['edit_ID_Presented'] : null;
    $e_idref = isset($_POST['edit_ID_Reference_No']) ? $_POST['edit_ID_Reference_No'] : null;
    $e_income = isset($_POST['edit_Income_Source']) ? $_POST['edit_Income_Source'] : null;
    $e_other_income = isset($_POST['edit_Other_Income_Source']) ? $_POST['edit_Other_Income_Source'] : null;
    $e_monthly = isset($_POST['edit_Montly_Income']) ? $_POST['edit_Montly_Income'] : null;
    $e_bname = isset($_POST['edit_Business_Name']) ? $_POST['edit_Business_Name'] : null;
    $e_baddr = isset($_POST['edit_Business_Address']) ? $_POST['edit_Business_Address'] : null;
    $e_spouse = isset($_POST['edit_Name_Of_Spouse']) ? $_POST['edit_Name_Of_Spouse'] : null;
    $e_pbank = isset($_POST['edit_Primary_Bank']) ? $_POST['edit_Primary_Bank'] : null;
    $e_lending = isset($_POST['edit_Name_Of_Lending']) ? $_POST['edit_Name_Of_Lending'] : null;
    $e_acq = isset($_POST['edit_Acquaintance_Duration']) && $_POST['edit_Acquaintance_Duration'] !== '' ? floatval($_POST['edit_Acquaintance_Duration']) : null;
    $e_relation = isset($_POST['edit_Relationship']) ? $_POST['edit_Relationship'] : null;

    $update_sql = "UPDATE tbl_comaker_info SET Last_Name=?, First_Name=?, Middle_Name=?, Age=?, Gender=?, Date_Of_Birth=?, Place_Of_Birth=?, Civil_Status=?, Mobile_No=?, Email_Address=?, House_Street_Bldng=?, Barangay_Town=?, City_Municipality=?, Province=?, Zip_Code=?, No_Of_Children=?, ID_Presented=?, ID_Reference_No=?, Income_Source=?, Other_Income_Source=?, Montly_Income=?, Business_Name=?, Business_Address=?, Name_Of_Spouse=?, Primary_Bank=?, Name_Of_Lending=?, Acquaintance_Duration=?, Relationship=? WHERE id=?";
    $ustmt = mysqli_prepare($conn, $update_sql);
    if ($ustmt) {
        // bind all editable fields as strings and id as integer
        $types = str_repeat('s', 28) . 'i';
        mysqli_stmt_bind_param($ustmt, $types,
            $e_last, $e_first, $e_middle, $e_age, $e_gender, $e_dob, $e_pob, $e_civil,
            $e_mobile, $e_email, $e_house, $e_barangay, $e_city, $e_province, $e_zip,
            $e_children, $e_idpres, $e_idref, $e_income, $e_other_income, $e_monthly,
            $e_bname, $e_baddr, $e_spouse, $e_pbank, $e_lending, $e_acq, $e_relation,
            $eid
        );
        // Note: mysqli_stmt_bind_param will raise a warning if the types string is invalid; PHP will coerce values.
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


// JSON endpoint for client-side fetching
if (isset($_GET['fetch_comakers'])) {
    $out = ['data' => []];
    $sql = "SELECT id, Comaker_ID, Last_Name, First_Name, Middle_Name, Age, Gender, Date_Of_Birth, Place_Of_Birth, Civil_Status, Mobile_No, Email_Address, House_Street_Bldng, Barangay_Town, City_Municipality, Province, Zip_Code, No_Of_Children, ID_Presented, ID_Reference_No, Income_Source, Other_Income_Source, Montly_Income, Business_Name, Business_Address, Name_Of_Spouse, Primary_Bank, Name_Of_Lending, Acquaintance_Duration, Relationship FROM tbl_comaker_info ORDER BY id DESC";
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
    .table-responsive::-webkit-scrollbar { height:12px; width:12px; }
    .table-responsive::-webkit-scrollbar-thumb { background:#000; border-radius:6px; }
    .table-responsive::-webkit-scrollbar-track { background:#333; }
    .table-responsive { scrollbar-color: #000 #333; scrollbar-width: thin; }
    .table tbody td { text-transform: uppercase; }
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

            <div class="container-fluid pt-4 px-4">
                <div class="row bg-secondary rounded p-4 mx-0">
                    <div class="col-12">
                        <h5 class="mb-3">Comaker Records</h5>
                        <div class="table-responsive">
                            <table id="comakersTable" class="table table-striped table-bordered mb-0" style="width:100%">
                                <thead>
                                    <tr>
                                        <th><input class="form-check-input" type="checkbox"></th>
                                        <th>Comaker ID</th>
                                        <th>Last Name</th>
                                        <th>First Name</th>
                                        <th>Mobile No</th>
                                        <th>Email Address</th>
                                        <th>Barangay/Town</th>
                                        <th>City/Municipality</th>
                                        <th style="width:160px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Comaker View Modal -->
                        <div class="modal fade" id="comakerViewModal" tabindex="-1" aria-labelledby="comakerViewLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content bg-dark text-white">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="comakerViewLabel">Comaker Details</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="d-flex mb-3">
                                            <div id="comakerProfPic" class="me-3" style="width:96px;height:96px;background:#222;display:flex;align-items:center;justify-content:center;border-radius:6px;overflow:hidden"></div>
                                            <div>
                                                <h5 id="cvFullName" class="mb-0"></h5>
                                                <div class="text-muted" id="cvComakerID"></div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">Age</dt><dd class="col-7" id="cvAge"></dd>
                                                    <dt class="col-5 text-muted">Gender</dt><dd class="col-7" id="cvGender"></dd>
                                                    <dt class="col-5 text-muted">DOB</dt><dd class="col-7" id="cvDOB"></dd>
                                                    <dt class="col-5 text-muted">Place of Birth</dt><dd class="col-7" id="cvPOB"></dd>
                                                    <dt class="col-5 text-muted">Civil Status</dt><dd class="col-7" id="cvCivil"></dd>
                                                </dl>
                                            </div>
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">Mobile</dt><dd class="col-7" id="cvMobile"></dd>
                                                    <dt class="col-5 text-muted">Email</dt><dd class="col-7" id="cvEmail"></dd>
                                                    <dt class="col-5 text-muted">Address</dt><dd class="col-7" id="cvAddress"></dd>
                                                    <dt class="col-5 text-muted">Province</dt><dd class="col-7" id="cvProvince"></dd>
                                                    <dt class="col-5 text-muted">Zip</dt><dd class="col-7" id="cvZip"></dd>
                                                </dl>
                                            </div>
                                        </div>
                                        <hr class="border-secondary">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">No. of Children</dt><dd class="col-7" id="cvChildren"></dd>
                                                    <dt class="col-5 text-muted">ID Presented</dt><dd class="col-7" id="cvIDPres"></dd>
                                                    <dt class="col-5 text-muted">ID Ref. No</dt><dd class="col-7" id="cvIDRef"></dd>
                                                </dl>
                                            </div>
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-5 text-muted">Income Source</dt><dd class="col-7" id="cvIncome"></dd>
                                                    <dt class="col-5 text-muted">Monthly Income</dt><dd class="col-7" id="cvMonthly"></dd>
                                                    <dt class="col-5 text-muted">Relationship</dt><dd class="col-7" id="cvRelation"></dd>
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

                        <!-- Comaker Edit Modal -->
                        <div class="modal fade" id="comakerEditModal" tabindex="-1" aria-labelledby="comakerEditLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content bg-dark text-white">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="comakerEditLabel">Edit Comaker</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="post">
                                    <div class="modal-body">
                                        <input type="hidden" name="edit_id" id="edit_id">
                                        <div class="row g-2">
                                            <div class="col-md-3">
                                                <label class="form-label">Comaker ID</label>
                                                <input name="edit_Comaker_ID" id="edit_Comaker_ID" class="form-control" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Last Name</label>
                                                <input name="edit_Last_Name" id="edit_Last_Name" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">First Name</label>
                                                <input name="edit_First_Name" id="edit_First_Name" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Middle Name</label>
                                                <input name="edit_Middle_Name" id="edit_Middle_Name" class="form-control">
                                            </div>

                                            <div class="col-md-2">
                                                <label class="form-label">Age</label>
                                                <input name="edit_Age" id="edit_Age" type="number" class="form-control">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Gender</label>
                                                <select name="edit_Gender" id="edit_Gender" class="form-select">
                                                    <option value="">--</option>
                                                    <option>Male</option>
                                                    <option>Female</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Date of Birth</label>
                                                <input name="edit_Date_Of_Birth" id="edit_Date_Of_Birth" type="date" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Place of Birth</label>
                                                <input name="edit_Place_Of_Birth" id="edit_Place_Of_Birth" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Civil Status</label>
                                                <input name="edit_Civil_Status" id="edit_Civil_Status" class="form-control">
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Mobile No</label>
                                                <input name="edit_Mobile_No" id="edit_Mobile_No" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Email Address</label>
                                                <input name="edit_Email_Address" id="edit_Email_Address" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Zip Code</label>
                                                <input name="edit_Zip_Code" id="edit_Zip_Code" class="form-control">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">House / Street / Bldg</label>
                                                <input name="edit_House_Street_Bldng" id="edit_House_Street_Bldng" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Barangay / Town</label>
                                                <input name="edit_Barangay_Town" id="edit_Barangay_Town" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">City / Municipality</label>
                                                <input name="edit_City_Municipality" id="edit_City_Municipality" class="form-control">
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label">No. of Children</label>
                                                <input name="edit_No_Of_Children" id="edit_No_Of_Children" type="number" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">ID Presented</label>
                                                <input name="edit_ID_Presented" id="edit_ID_Presented" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">ID Reference No</label>
                                                <input name="edit_ID_Reference_No" id="edit_ID_Reference_No" class="form-control">
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Income Source</label>
                                                <input name="edit_Income_Source" id="edit_Income_Source" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Other Income Source</label>
                                                <input name="edit_Other_Income_Source" id="edit_Other_Income_Source" class="form-control">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Monthly Income</label>
                                                <input name="edit_Montly_Income" id="edit_Montly_Income" class="form-control">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Business Name</label>
                                                <input name="edit_Business_Name" id="edit_Business_Name" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Business Address</label>
                                                <input name="edit_Business_Address" id="edit_Business_Address" class="form-control">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Name of Spouse</label>
                                                <input name="edit_Name_Of_Spouse" id="edit_Name_Of_Spouse" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Primary Bank</label>
                                                <input name="edit_Primary_Bank" id="edit_Primary_Bank" class="form-control">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Name of Lending</label>
                                                <input name="edit_Name_Of_Lending" id="edit_Name_Of_Lending" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Acquaintance Duration</label>
                                                <input name="edit_Acquaintance_Duration" id="edit_Acquaintance_Duration" type="number" step="any" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Relationship</label>
                                                <input name="edit_Relationship" id="edit_Relationship" class="form-control">
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
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        $('#comakersTable').DataTable({
            ajax: 'comaker_record.php?fetch_comakers=1',
            paging: true,
            searching: true,
            info: true,
            ordering: true,
            columns: [
                { data: null, orderable: false, render: function(){ return '<input class="form-check-input" type="checkbox">'; } },
                { data: 'Comaker_ID' },
                { data: 'Last_Name' },
                { data: 'First_Name' },
                { data: 'Mobile_No' },
                { data: 'Email_Address' },
                { data: 'Barangay_Town' },
                { data: 'City_Municipality' },
                { data: null, orderable: false, render: function(data, type, row){
                        var id = row.id || '';
                        var comakerid = row.Comaker_ID || '';
                        var last = row.Last_Name || '';
                        var first = row.First_Name || '';
                        var middle = row.Middle_Name || '';
                        var mobile = row.Mobile_No || '';
                        var email = row.Email_Address || '';
                        var barangay = row.Barangay_Town || '';
                        var city = row.City_Municipality || '';
                        var province = row.Province || '';

                        var viewBtn = '<button type="button" class="btn btn-sm btn-primary view-comaker me-1" data-bs-toggle="modal" data-bs-target="#comakerViewModal" '
                            + 'data-id="'+id+'" data-comakerid="'+comakerid+'" data-last="'+last+'" data-first="'+first+'" data-middle="'+middle+'" '
                            + 'data-age="'+(row.Age||'')+'" data-gender="'+(row.Gender||'')+'" data-date_of_birth="'+(row.Date_Of_Birth||'')+'" data-place_of_birth="'+(row.Place_Of_Birth||'')+'" data-civil_status="'+(row.Civil_Status||'')+'" '
                            + 'data-mobile="'+mobile+'" data-email="'+email+'" data-house="'+(row.House_Street_Bldng||'')+'" data-barangay="'+barangay+'" data-city="'+city+'" data-province="'+province+'" data-zip_code="'+(row.Zip_Code||'')+'" '
                            + 'data-no_of_children="'+(row.No_Of_Children||'')+'" data-id_presented="'+(row.ID_Presented||'')+'" data-id_reference_no="'+(row.ID_Reference_No||'')+'" data-income_source="'+(row.Income_Source||'')+'" data-other_income_source="'+(row.Other_Income_Source||'')+'" data-montly_income="'+(row.Montly_Income||'')+'" '
                            + 'data-business_name="'+(row.Business_Name||'')+'" data-business_address="'+(row.Business_Address||'')+'" data-name_of_spouse="'+(row.Name_Of_Spouse||'')+'" data-primary_bank="'+(row.Primary_Bank||'')+'" data-name_of_lending="'+(row.Name_Of_Lending||'')+'" data-acquaintance_duration="'+(row.Acquaintance_Duration||'')+'" data-relationship="'+(row.Relationship||'')+'">'
                            + '<i class="bi bi-eye"></i></button>';

                        var editBtn = '<button type="button" class="btn btn-sm btn-warning edit-comaker me-1" data-bs-toggle="modal" data-bs-target="#comakerEditModal" '
                            + 'data-id="'+id+'" data-comakerid="'+comakerid+'" data-last="'+last+'" data-first="'+first+'" data-middle="'+middle+'" '
                            + 'data-age="'+(row.Age||'')+'" data-gender="'+(row.Gender||'')+'" data-date_of_birth="'+(row.Date_Of_Birth||'')+'" data-place_of_birth="'+(row.Place_Of_Birth||'')+'" data-civil_status="'+(row.Civil_Status||'')+'" '
                            + 'data-mobile="'+mobile+'" data-email="'+email+'" data-house="'+(row.House_Street_Bldng||'')+'" data-barangay="'+barangay+'" data-city="'+city+'" data-province="'+province+'" data-zip_code="'+(row.Zip_Code||'')+'" '
                            + 'data-no_of_children="'+(row.No_Of_Children||'')+'" data-id_presented="'+(row.ID_Presented||'')+'" data-id_reference_no="'+(row.ID_Reference_No||'')+'" data-income_source="'+(row.Income_Source||'')+'" data-other_income_source="'+(row.Other_Income_Source||'')+'" data-montly_income="'+(row.Montly_Income||'')+'" '
                            + 'data-business_name="'+(row.Business_Name||'')+'" data-business_address="'+(row.Business_Address||'')+'" data-name_of_spouse="'+(row.Name_Of_Spouse||'')+'" data-primary_bank="'+(row.Primary_Bank||'')+'" data-name_of_lending="'+(row.Name_Of_Lending||'')+'" data-acquaintance_duration="'+(row.Acquaintance_Duration||'')+'" data-relationship="'+(row.Relationship||'')+'">'
                            + '<i class="bi bi-pencil"></i></button>';

                        var deleteBtn = '<form method="post" class="d-inline delete-form">'
                            + '<input type="hidden" name="delete_id" value="'+id+'">'
                            + '<button type="button" class="btn btn-sm btn-danger del-comaker"><i class="bi bi-trash"></i></button>'
                            + '</form>';

                        return '<div class="text-nowrap">' + viewBtn + editBtn + deleteBtn + '</div>';
                    } }
            ]
        });

        // Populate view modal
        $('#comakerViewModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var modal = $(this);
            var fullName = (button.data('first') || '') + ' ' + (button.data('middle') || '') + ' ' + (button.data('last') || '');
            modal.find('#cvFullName').text(fullName.trim());
            modal.find('#cvComakerID').text(button.data('comakerid') || '');
            modal.find('#cvAge').text(button.data('age') || '');
            modal.find('#cvGender').text(button.data('gender') || '');
            modal.find('#cvDOB').text(button.data('date_of_birth') || '');
            modal.find('#cvPOB').text(button.data('place_of_birth') || '');
            modal.find('#cvCivil').text(button.data('civil_status') || '');
            modal.find('#cvMobile').text(button.data('mobile') || '');
            modal.find('#cvEmail').text(button.data('email') || '');
            var address = (button.data('house') || '') + '\n' + (button.data('barangay') || '') + '\n' + (button.data('city') || '') + ', ' + (button.data('province') || '');
            modal.find('#cvAddress').text(address.replace(/(^\n|\n$)/g, ''));
            modal.find('#cvProvince').text(button.data('province') || '');
            modal.find('#cvZip').text(button.data('zip_code') || '');
            modal.find('#cvChildren').text(button.data('no_of_children') || '');
            modal.find('#cvIDPres').text(button.data('id_presented') || '');
            modal.find('#cvIDRef').text(button.data('id_reference_no') || '');
            modal.find('#cvIncome').text(button.data('income_source') || '');
            modal.find('#cvMonthly').text(button.data('montly_income') || '');
            modal.find('#cvRelation').text(button.data('relationship') || '');
        });

        // Populate edit modal
        $('#comakerEditModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var modal = $(this);
            modal.find('#edit_id').val(button.data('id') || '');
            modal.find('#edit_Comaker_ID').val(button.data('comakerid') || '');
            modal.find('#edit_Last_Name').val(button.data('last') || '');
            modal.find('#edit_First_Name').val(button.data('first') || '');
            modal.find('#edit_Middle_Name').val(button.data('middle') || '');
            modal.find('#edit_Age').val(button.data('age') || '');
            modal.find('#edit_Gender').val(button.data('gender') || '');
            modal.find('#edit_Date_Of_Birth').val(button.data('date_of_birth') || '');
            modal.find('#edit_Place_Of_Birth').val(button.data('place_of_birth') || '');
            modal.find('#edit_Civil_Status').val(button.data('civil_status') || '');
            modal.find('#edit_Mobile_No').val(button.data('mobile') || '');
            modal.find('#edit_Email_Address').val(button.data('email') || '');
            modal.find('#edit_House_Street_Bldng').val(button.data('house') || '');
            modal.find('#edit_Barangay_Town').val(button.data('barangay') || '');
            modal.find('#edit_City_Municipality').val(button.data('city') || '');
            modal.find('#edit_Province').val(button.data('province') || '');
            modal.find('#edit_Zip_Code').val(button.data('zip_code') || '');
            modal.find('#edit_No_Of_Children').val(button.data('no_of_children') || '');
            modal.find('#edit_ID_Presented').val(button.data('id_presented') || '');
            modal.find('#edit_ID_Reference_No').val(button.data('id_reference_no') || '');
            modal.find('#edit_Income_Source').val(button.data('income_source') || '');
            modal.find('#edit_Other_Income_Source').val(button.data('other_income_source') || '');
            modal.find('#edit_Montly_Income').val(button.data('montly_income') || '');
            modal.find('#edit_Business_Name').val(button.data('business_name') || '');
            modal.find('#edit_Business_Address').val(button.data('business_address') || '');
            modal.find('#edit_Name_Of_Spouse').val(button.data('name_of_spouse') || '');
            modal.find('#edit_Primary_Bank').val(button.data('primary_bank') || '');
            modal.find('#edit_Name_Of_Lending').val(button.data('name_of_lending') || '');
            modal.find('#edit_Acquaintance_Duration').val(button.data('acquaintance_duration') || '');
            modal.find('#edit_Relationship').val(button.data('relationship') || '');
        });

        // Delete confirmation
        $(document).on('click', '.del-comaker', function(e){
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({title:'Delete record?', text:'This action cannot be undone.', icon:'warning', showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#d33'}).then((res)=>{ if(res.isConfirmed) form.submit(); });
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