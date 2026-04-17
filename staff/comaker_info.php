<?php
session_start();
require_once __DIR__ . '/../db/dbcon.php';

$success = '';
$error = '';

// Handle save comaker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_comaker'])) {
    // Generate next Comaker_ID in format C-0001
    $last_q = mysqli_query($conn, "SELECT Comaker_ID FROM tbl_comaker_info ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/C-(\d+)/', $row['Comaker_ID'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
    }
    $comaker_id = sprintf('C-%04d', $nextNum);

    // Collect inputs (trimmed)
    $Last_Name = isset($_POST['Last_Name']) ? trim($_POST['Last_Name']) : '';
    $First_Name = isset($_POST['First_Name']) ? trim($_POST['First_Name']) : '';
    $Middle_Name = isset($_POST['Middle_Name']) ? trim($_POST['Middle_Name']) : '';
    $Age = isset($_POST['Age']) && $_POST['Age'] !== '' ? intval($_POST['Age']) : null;
    $Gender = isset($_POST['Gender']) ? trim($_POST['Gender']) : '';
    $Date_Of_Birth = isset($_POST['Date_Of_Birth']) && $_POST['Date_Of_Birth'] !== '' ? $_POST['Date_Of_Birth'] : null;
    $Place_Of_Birth = isset($_POST['Place_Of_Birth']) ? trim($_POST['Place_Of_Birth']) : '';
    $Civil_Status = isset($_POST['Civil_Status']) ? trim($_POST['Civil_Status']) : '';
    $Mobile_No = isset($_POST['Mobile_No']) ? trim($_POST['Mobile_No']) : '';
    $Email_Address = isset($_POST['Email_Address']) ? trim($_POST['Email_Address']) : '';
    $House_Street_Bldng = isset($_POST['House_Street_Bldng']) ? trim($_POST['House_Street_Bldng']) : '';
    $Barangay_Town = isset($_POST['Barangay_Town']) ? trim($_POST['Barangay_Town']) : '';
    $City_Municipality = isset($_POST['City_Municipality']) ? trim($_POST['City_Municipality']) : '';
    $Province = isset($_POST['Province']) ? trim($_POST['Province']) : '';
    $Zip_Code = isset($_POST['Zip_Code']) ? trim($_POST['Zip_Code']) : '';
    $No_Of_Children = isset($_POST['No_Of_Children']) && $_POST['No_Of_Children'] !== '' ? intval($_POST['No_Of_Children']) : null;
    $ID_Presented = isset($_POST['ID_Presented']) ? trim($_POST['ID_Presented']) : '';
    $ID_Reference_No = isset($_POST['ID_Reference_No']) ? trim($_POST['ID_Reference_No']) : '';
    $Income_Source = isset($_POST['Income_Source']) ? trim($_POST['Income_Source']) : '';
    $Other_Income_Source = isset($_POST['Other_Income_Source']) ? trim($_POST['Other_Income_Source']) : '';
    $Montly_Income = isset($_POST['Montly_Income']) ? trim($_POST['Montly_Income']) : '';
    $Business_Name = isset($_POST['Business_Name']) ? trim($_POST['Business_Name']) : '';
    $Business_Address = isset($_POST['Business_Address']) ? trim($_POST['Business_Address']) : '';
    $Name_Of_Spouse = isset($_POST['Name_Of_Spouse']) ? trim($_POST['Name_Of_Spouse']) : '';
    $Primary_Bank = isset($_POST['Primary_Bank']) ? trim($_POST['Primary_Bank']) : '';
    $Name_Of_Lending = isset($_POST['Name_Of_Lending']) ? trim($_POST['Name_Of_Lending']) : '';
    $Acquaintance_Duration = isset($_POST['Acquaintance_Duration']) && $_POST['Acquaintance_Duration'] !== '' ? floatval($_POST['Acquaintance_Duration']) : null;
    $Relationship = isset($_POST['Relationship']) ? trim($_POST['Relationship']) : '';

    // Basic validation
    if (empty($Last_Name) || empty($First_Name)) {
        $error = 'First and Last name are required.';
    } else {
        $sql = "INSERT INTO tbl_comaker_info (Comaker_ID, Last_Name, First_Name, Middle_Name, Age, Gender, Date_Of_Birth, Place_Of_Birth, Civil_Status, Mobile_No, Email_Address, House_Street_Bldng, Barangay_Town, City_Municipality, Province, Zip_Code, No_Of_Children, ID_Presented, ID_Reference_No, Income_Source, Other_Income_Source, Montly_Income, Business_Name, Business_Address, Name_Of_Spouse, Primary_Bank, Name_Of_Lending, Acquaintance_Duration, Relationship) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $types = str_repeat('s', 29);
            mysqli_stmt_bind_param($stmt, $types,
                $comaker_id, $Last_Name, $First_Name, $Middle_Name, $Age, $Gender, $Date_Of_Birth, $Place_Of_Birth, $Civil_Status, $Mobile_No, $Email_Address, $House_Street_Bldng, $Barangay_Town, $City_Municipality, $Province, $Zip_Code, $No_Of_Children, $ID_Presented, $ID_Reference_No, $Income_Source, $Other_Income_Source, $Montly_Income, $Business_Name, $Business_Address, $Name_Of_Spouse, $Primary_Bank, $Name_Of_Lending, $Acquaintance_Duration, $Relationship
            );
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Co-maker saved successfully.';
            } else {
                $error = 'Insert failed: ' . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Insert prepare failed: ' . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>TPKI || Co-maker Information</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <?php include "includes/head.php"; ?>
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
                    <div class="col-md-12">
                        <h5 class="mb-3">Co-maker Information</h5>
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="post" class="row g-3">
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
                                <input name="Middle_Name" class="form-control">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Age</label>
                                <input name="Age" type="number" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Gender</label>
                                <select name="Gender" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date Of Birth</label>
                                <input name="Date_Of_Birth" type="date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Place Of Birth</label>
                                <input name="Place_Of_Birth" class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Civil Status</label>
                                <input name="Civil_Status" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mobile No</label>
                                <input name="Mobile_No" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input name="Email_Address" type="email" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">House/Street/Building</label>
                                <input name="House_Street_Bldng" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Barangay/Town</label>
                                <input name="Barangay_Town" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City/Municipality</label>
                                <input name="City_Municipality" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Province</label>
                                <input name="Province" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Zip Code</label>
                                <input name="Zip_Code" class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">No. Of Children</label>
                                <input name="No_Of_Children" type="number" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ID Presented</label>
                                <input name="ID_Presented" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ID Reference No</label>
                                <input name="ID_Reference_No" class="form-control">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Income Source</label>
                                <input name="Income_Source" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Other Income Source</label>
                                <input name="Other_Income_Source" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Monthly Income</label>
                                <input name="Montly_Income" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Business Name</label>
                                <input name="Business_Name" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Address</label>
                                <input name="Business_Address" class="form-control">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Name Of Spouse</label>
                                <input name="Name_Of_Spouse" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Primary Bank</label>
                                <input name="Primary_Bank" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Name Of Lending</label>
                                <input name="Name_Of_Lending" class="form-control">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Acquaintance Duration (years)</label>
                                <input name="Acquaintance_Duration" type="number" step="0.1" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Relationship</label>
                                <input name="Relationship" class="form-control">
                            </div>

                            <input type="hidden" name="save_comaker" value="1">
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-success">Save Co-maker</button>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php
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