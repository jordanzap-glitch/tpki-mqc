<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
include '../db/dbcon.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    // Simple helper: get POST value or null
    function gv($k) { return isset($_POST[$k]) && $_POST[$k] !== '' ? $_POST[$k] : null; }

    // Generate Client_ID: CL-xxxxx (5 alphanumeric characters) and ensure uniqueness
    function genClientID($conn)
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        do {
            $rand = '';
            for ($i = 0; $i < 5; $i++) {
                $rand .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $cid = 'CL-' . $rand;

            $chk = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM tbl_client_info WHERE Client_ID = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 's', $cid);
            mysqli_stmt_execute($chk);
            $chk_res = mysqli_stmt_get_result($chk);
            $chk_row = mysqli_fetch_assoc($chk_res);
            mysqli_stmt_close($chk);
        } while ($chk_row && intval($chk_row['cnt']) > 0);

        return $cid;
    }

    $client_id = genClientID($conn);

    // Collect fields (trim and nullify empty)
    $branch_id = gv('Branch_ID');
    $last_name = gv('Last_Name');
    $first_name = gv('First_Name');
    $middle_name = gv('Middle_Name');
    $nickname = gv('Nickname');
    $age = gv('Age');
    $gender = gv('Gender');
    $dob = gv('Date_Of_Birth');
    $pob = gv('Place_Of_Birth');
    $civil = gv('Civil_Status');
    $religion = gv('Religion');
    $mother_last = gv('Mother_Last_Name');
    $mother_first = gv('Mother_First_Name');
    $mother_middle = gv('Mother_Middle_Name');
    $mobile = gv('Mobile_No');
    $email = gv('Email_Address');
    $house = gv('House_Street_Bldng');
    $barangay = gv('Barangay_Town');
    $city = gv('City_Municipality');
    $province = gv('Province');
    $zip = gv('Zip_Code');
    $edu = gv('Educational_Attainment');
    $no_children = gv('No_Of_Children');
    $id_presented = gv('ID_Presented');
    $id_ref_no = gv('ID_Reference_No');
    $spouse_last = gv('Spouse_Last_Name');
    $spouse_first = gv('Spouse_First_Name');
    $spouse_middle = gv('Spouse_Middle_Name');
    $spouse_work = gv('Spouse_Work');
    $spouse_nick = gv('Spouse_Nickname');
    $spouse_age = gv('Spouse_Age');
    $spouse_dob = gv('Spouse_DOB');
    $spouse_income = gv('Spouse_Income');
    $latitude = gv('Latitude');
    $longitude = gv('Longitude');
    $project_officer = gv('Project_Officer_ID');
    // Prefer Project Officer ID from session if available
    if (isset($_SESSION['User_ID']) && $_SESSION['User_ID'] !== '') {
        $project_officer = $_SESSION['User_ID'];
    } elseif (isset($_SESSION['UserID']) && $_SESSION['UserID'] !== '') {
        $project_officer = $_SESSION['UserID'];
    }

    // Handle profile picture upload (optional)
    $prof_pic = null;
    if (!empty($_FILES['Prof_Pic']) && $_FILES['Prof_Pic']['error'] === UPLOAD_ERR_OK) {
        $prof_pic = file_get_contents($_FILES['Prof_Pic']['tmp_name']);
    }
    // Expiration / extra datetime field from form
    $exp_id = gv('Exp_ID');

    // Prepared INSERT
    $sql = "INSERT INTO tbl_client_info
                (Client_ID, Branch_ID, Last_Name, First_Name, Middle_Name, Nickname, Age, Gender, Date_Of_Birth, Place_Of_Birth, Civil_Status, Religion, Mother_Last_Name, Mother_First_Name, Mother_Middle_Name, Mobile_No, Email_Address, House_Street_Bldng, Barangay_Town, City_Municipality, Province, Zip_Code, Educational_Attainment, No_Of_Children, ID_Presented, ID_Reference_No, Spouse_Last_Name, Spouse_First_Name, Spouse_Middle_Name, Spouse_Work, Spouse_Nickname, Spouse_Age, Spouse_DOB, Spouse_Income, Latitude, Longitude, Project_Officer_ID, Exp_ID, Prof_Pic)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, str_repeat('s', 39), $client_id, $branch_id, $last_name, $first_name, $middle_name, $nickname, $age, $gender, $dob, $pob, $civil, $religion, $mother_last, $mother_first, $mother_middle, $mobile, $email, $house, $barangay, $city, $province, $zip, $edu, $no_children, $id_presented, $id_ref_no, $spouse_last, $spouse_first, $spouse_middle, $spouse_work, $spouse_nick, $spouse_age, $spouse_dob, $spouse_income, $latitude, $longitude, $project_officer, $exp_id, $prof_pic);

        // Because Prof_Pic is blob, use send_long_data if available
        if ($prof_pic !== null) {
            // send_long_data requires mysqli_stmt_send_long_data, which expects param index starting at 0
            // Prof_Pic is now the last param (zero-based index 38)
            $param_index = 38;
            mysqli_stmt_send_long_data($stmt, $param_index, $prof_pic);
        }

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Client successfully added with ID: ' . htmlspecialchars($client_id);
        } else {
            $error = 'Insert error: ' . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = 'Prepare failed: ' . mysqli_error($conn);
    }

    // If request is AJAX, return JSON and stop further output
    if ($isAjax) {
        header('Content-Type: application/json');
        if (!empty($success)) {
            echo json_encode(['status' => 'success', 'message' => $success]);
        } else {
            echo json_encode(['status' => 'error', 'message' => isset($error) ? $error : 'Unknown error']);
        }
        exit;
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

            

            

            <div class="container-fluid pt-4 px-4">
                <div class="bg-secondary text-start rounded p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h6 class="mb-0">Add Client</h6>
                        <a href="client.php" class="btn btn-sm btn-outline-light">Refresh</a>
                    </div>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" hidden><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" hidden><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Branch</label>
                                <select name="Branch_ID" class="form-select" required>
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
                                <input name="Nickname" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Last Name</label>
                                <input name="Last_Name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">First Name</label>
                                <input name="First_Name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle Name</label>
                                <input name="Middle_Name" class="form-control" required>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Age</label>
                                <input name="Age" type="number" min="0" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender</label>
                                <select name="Gender" class="form-select">
                                    <option value="">--</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input name="Date_Of_Birth" type="date" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Place of Birth</label>
                                <input name="Place_Of_Birth" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Civil Status</label>
                                <select name="Civil_Status" class="form-select" required>
                                    <option value="">-- Select --</option>
                                    <option value="M"<?php if(isset($_POST['Civil_Status']) && $_POST['Civil_Status']==='M') echo ' selected'; ?>>Married</option>
                                    <option value="S"<?php if(isset($_POST['Civil_Status']) && $_POST['Civil_Status']==='S') echo ' selected'; ?>>Single</option>
                                    <option value="SP"<?php if(isset($_POST['Civil_Status']) && $_POST['Civil_Status']==='SP') echo ' selected'; ?>>Single Parent</option>
                                    <option value="MO"<?php if(isset($_POST['Civil_Status']) && $_POST['Civil_Status']==='MO') echo ' selected'; ?>>Married w/o Child</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Religion</label>
                                <input name="Religion" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mobile No</label>
                                <input name="Mobile_No" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input name="Email_Address" type="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">House/Street/Bldng</label>
                                <input name="House_Street_Bldng" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Barangay/Town</label>
                                <input name="Barangay_Town" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City/Municipality</label>
                                <input name="City_Municipality" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Province</label>
                                <input name="Province" class="form-control" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Zip Code</label>
                                <input name="Zip_Code" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Educational Attainment</label>
                                <input name="Educational_Attainment" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">No. of Children</label>
                                <input name="No_Of_Children" type="number" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ID Presented</label>
                                <input name="ID_Presented" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ID Reference NO</label>
                                <input name="ID_Reference_No" class="form-control" required>
                            </div>

                            <div class="col-12"><hr class="border-secondary"></div>

                            <div class="col-md-4">
                                <label class="form-label">Mother's Last Name</label>
                                <input name="Mother_Last_Name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mother's First Name</label>
                                <input name="Mother_First_Name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mother's Middle Name</label>
                                <input name="Mother_Middle_Name" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Spouse Last Name</label>
                                <input name="Spouse_Last_Name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Spouse First Name</label>
                                <input name="Spouse_First_Name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Spouse Middle Name</label>
                                <input name="Spouse_Middle_Name" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Spouse Work</label>
                                <input name="Spouse_Work" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Spouse Nickname</label>
                                <input name="Spouse_Nickname" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Spouse Age</label>
                                <input name="Spouse_Age" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Spouse DOB</label>
                                <input name="Spouse_DOB" type="date" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Spouse Income</label>
                                <input name="Spouse_Income" type="number" step="0.01" class="form-control">
                            </div>

                            <input type="hidden" name="Latitude" value="">
                            <input type="hidden" name="Longitude" value="">

                            <?php $po_display = isset($_SESSION['User_ID']) ? htmlspecialchars($_SESSION['User_ID']) : (isset($_SESSION['UserID']) ? htmlspecialchars($_SESSION['UserID']) : ''); ?>
                            <input type="hidden" name="Project_Officer_ID" value="<?php echo $po_display; ?>">

                            <div class="col-md-6">
                                <label class="form-label">Expiration Date / Extra Date</label>
                                <input name="Exp_ID" type="datetime-local" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Profile Picture</label>
                                <input name="Prof_Pic" type="file" accept="image/*" class="form-control">
                            </div>

                            <div class="col-12 text-end mt-3">
                                <button type="submit" class="btn btn-primary">Save Client</button>
                            </div>
                        </div>
                    </form>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const form = document.querySelector('form[enctype="multipart/form-data"]');
        if (!form) return;
        form.addEventListener('submit', async function(e){
            e.preventDefault();
            const fd = new FormData(form);
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Saved', text: data.message, confirmButtonText: 'OK' });
                    form.reset();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Save failed', confirmButtonText: 'OK' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network or server error', confirmButtonText: 'OK' });
            }
        });
    });
    </script>
</body>

</html>