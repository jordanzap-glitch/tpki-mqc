<?php
include 'includes/init.php';
include '../db/dbcon.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    // Simple helper: get POST value or null
    function gv($k) { return isset($_POST[$k]) && $_POST[$k] !== '' ? $_POST[$k] : null; }

    // Generate Client_ID: CL-xxxxx (5 alphanumeric characters) and ensure uniqueness
    function genClientID($conn)
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        // attempt generation until unique
        do {
            $part = '';
            for ($i = 0; $i < 5; $i++) {
                $part .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $id = 'CL-' . $part;

            $safe = mysqli_real_escape_string($conn, $id);
            $q = mysqli_query($conn, "SELECT 1 FROM tbl_client_info WHERE Client_ID = '" . $safe . "' LIMIT 1");
            $exists = $q && mysqli_num_rows($q) > 0;
        } while ($exists);

        return $id;
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
    // Expiration / extra date field from form (date only, no time)
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

// ── Handle co-maker form submission ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_comaker'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Generate next Comaker_ID in format C-0001
    $last_q = mysqli_query($conn, "SELECT Comaker_ID FROM tbl_comaker_info ORDER BY id DESC LIMIT 1");
    $cmNextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/C-(\d+)/', $row['Comaker_ID'], $m)) {
            $cmNextNum = intval($m[1]) + 1;
        }
    }
    $comaker_id = sprintf('C-%04d', $cmNextNum);

    $cm_Last_Name           = isset($_POST['cm_Last_Name'])           ? trim($_POST['cm_Last_Name'])           : '';
    $cm_First_Name          = isset($_POST['cm_First_Name'])          ? trim($_POST['cm_First_Name'])          : '';
    $cm_Middle_Name         = isset($_POST['cm_Middle_Name'])         ? trim($_POST['cm_Middle_Name'])         : '';
    $cm_Age                 = isset($_POST['cm_Age']) && $_POST['cm_Age'] !== '' ? intval($_POST['cm_Age']) : null;
    $cm_Gender              = isset($_POST['cm_Gender'])              ? trim($_POST['cm_Gender'])              : '';
    $cm_Date_Of_Birth       = isset($_POST['cm_Date_Of_Birth']) && $_POST['cm_Date_Of_Birth'] !== '' ? $_POST['cm_Date_Of_Birth'] : null;
    $cm_Place_Of_Birth      = isset($_POST['cm_Place_Of_Birth'])      ? trim($_POST['cm_Place_Of_Birth'])      : '';
    $cm_Civil_Status        = isset($_POST['cm_Civil_Status'])        ? trim($_POST['cm_Civil_Status'])        : '';
    $cm_Mobile_No           = isset($_POST['cm_Mobile_No'])           ? trim($_POST['cm_Mobile_No'])           : '';
    $cm_Email_Address       = isset($_POST['cm_Email_Address'])       ? trim($_POST['cm_Email_Address'])       : '';
    $cm_House_Street_Bldng  = isset($_POST['cm_House_Street_Bldng'])  ? trim($_POST['cm_House_Street_Bldng'])  : '';
    $cm_Barangay_Town       = isset($_POST['cm_Barangay_Town'])       ? trim($_POST['cm_Barangay_Town'])       : '';
    $cm_City_Municipality   = isset($_POST['cm_City_Municipality'])   ? trim($_POST['cm_City_Municipality'])  : '';
    $cm_Province            = isset($_POST['cm_Province'])            ? trim($_POST['cm_Province'])            : '';
    $cm_Zip_Code            = isset($_POST['cm_Zip_Code'])            ? trim($_POST['cm_Zip_Code'])            : '';
    $cm_No_Of_Children      = isset($_POST['cm_No_Of_Children']) && $_POST['cm_No_Of_Children'] !== '' ? intval($_POST['cm_No_Of_Children']) : null;
    $cm_ID_Presented        = isset($_POST['cm_ID_Presented'])        ? trim($_POST['cm_ID_Presented'])        : '';
    $cm_ID_Reference_No     = isset($_POST['cm_ID_Reference_No'])     ? trim($_POST['cm_ID_Reference_No'])     : '';
    $cm_Income_Source       = isset($_POST['cm_Income_Source'])       ? trim($_POST['cm_Income_Source'])       : '';
    $cm_Other_Income_Source = isset($_POST['cm_Other_Income_Source']) ? trim($_POST['cm_Other_Income_Source']) : '';
    $cm_Montly_Income       = isset($_POST['cm_Montly_Income'])       ? trim($_POST['cm_Montly_Income'])       : '';
    $cm_Business_Name       = isset($_POST['cm_Business_Name'])       ? trim($_POST['cm_Business_Name'])       : '';
    $cm_Business_Address    = isset($_POST['cm_Business_Address'])    ? trim($_POST['cm_Business_Address'])    : '';
    $cm_Name_Of_Spouse      = isset($_POST['cm_Name_Of_Spouse'])      ? trim($_POST['cm_Name_Of_Spouse'])      : '';
    $cm_Primary_Bank        = isset($_POST['cm_Primary_Bank'])        ? trim($_POST['cm_Primary_Bank'])        : '';
    $cm_Name_Of_Lending     = isset($_POST['cm_Name_Of_Lending'])     ? trim($_POST['cm_Name_Of_Lending'])     : '';
    $cm_Acquaintance_Duration = isset($_POST['cm_Acquaintance_Duration']) && $_POST['cm_Acquaintance_Duration'] !== '' ? floatval($_POST['cm_Acquaintance_Duration']) : null;
    $cm_Relationship        = isset($_POST['cm_Relationship'])        ? trim($_POST['cm_Relationship'])        : '';

    if (empty($cm_Last_Name) || empty($cm_First_Name)) {
        $cm_error = 'First and Last name are required.';
    } else {
        $sql_cm = "INSERT INTO tbl_comaker_info (Comaker_ID, Last_Name, First_Name, Middle_Name, Age, Gender, Date_Of_Birth, Place_Of_Birth, Civil_Status, Mobile_No, Email_Address, House_Street_Bldng, Barangay_Town, City_Municipality, Province, Zip_Code, No_Of_Children, ID_Presented, ID_Reference_No, Income_Source, Other_Income_Source, Montly_Income, Business_Name, Business_Address, Name_Of_Spouse, Primary_Bank, Name_Of_Lending, Acquaintance_Duration, Relationship) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_cm = mysqli_prepare($conn, $sql_cm);
        if ($stmt_cm) {
            mysqli_stmt_bind_param($stmt_cm, str_repeat('s', 29),
                $comaker_id, $cm_Last_Name, $cm_First_Name, $cm_Middle_Name, $cm_Age, $cm_Gender,
                $cm_Date_Of_Birth, $cm_Place_Of_Birth, $cm_Civil_Status, $cm_Mobile_No, $cm_Email_Address,
                $cm_House_Street_Bldng, $cm_Barangay_Town, $cm_City_Municipality, $cm_Province, $cm_Zip_Code,
                $cm_No_Of_Children, $cm_ID_Presented, $cm_ID_Reference_No, $cm_Income_Source,
                $cm_Other_Income_Source, $cm_Montly_Income, $cm_Business_Name, $cm_Business_Address,
                $cm_Name_Of_Spouse, $cm_Primary_Bank, $cm_Name_Of_Lending, $cm_Acquaintance_Duration, $cm_Relationship
            );
            if (mysqli_stmt_execute($stmt_cm)) {
                $cm_success = 'Co-maker saved successfully with ID: ' . $comaker_id;
            } else {
                $cm_error = 'Insert failed: ' . mysqli_stmt_error($stmt_cm);
            }
            mysqli_stmt_close($stmt_cm);
        } else {
            $cm_error = 'Insert prepare failed: ' . mysqli_error($conn);
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        if (!empty($cm_success)) {
            echo json_encode(['status' => 'success', 'message' => $cm_success]);
        } else {
            echo json_encode(['status' => 'error', 'message' => isset($cm_error) ? $cm_error : 'Unknown error']);
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

            

            

            <!-- Page styles -->
            <style>
                /* ── Form card base ── */
                .cf-card {
                    background: var(--secondary);
                    border: 1px solid rgba(255,255,255,0.06);
                    border-radius: 12px;
                    padding: 1.75rem 2rem;
                    margin-bottom: 1.5rem;
                    box-shadow: 0 2px 12px rgba(0,0,0,0.25);
                }

                /* Section heading strip */
                .cf-section-title {
                    display: flex;
                    align-items: center;
                    gap: .6rem;
                    font-size: .78rem;
                    font-weight: 700;
                    letter-spacing: .1em;
                    text-transform: uppercase;
                    color: var(--primary);
                    border-bottom: 1px solid rgba(61,242,118,0.2);
                    padding-bottom: .6rem;
                    margin-bottom: 1.25rem;
                }
                .cf-section-title i {
                    font-size: .95rem;
                    opacity: .85;
                }

                /* Input / select overrides for dark theme */
                .cf-card .form-control,
                .cf-card .form-select {
                    background: rgba(255,255,255,0.05);
                    border: 1px solid rgba(255,255,255,0.1);
                    color: #e2e5f1;
                    border-radius: 8px;
                    transition: border-color .2s, box-shadow .2s;
                }
                .cf-card .form-control:focus,
                .cf-card .form-select:focus {
                    background: rgba(255,255,255,0.08);
                    border-color: var(--primary);
                    box-shadow: 0 0 0 3px rgba(61,242,118,0.12);
                    color: #ffffff;
                }
                .cf-card .form-control::placeholder { color: rgba(255,255,255,0.3); }
                .cf-card .form-label {
                    font-size: .8rem;
                    font-weight: 600;
                    color: rgba(255,255,255,0.6);
                    margin-bottom: .3rem;
                    letter-spacing: .02em;
                }
                /* File input */
                .cf-card input[type="file"].form-control {
                    padding: .45rem .75rem;
                    cursor: pointer;
                }

                /* Profile picture preview box */
                .cf-avatar-wrap {
                    width: 110px;
                    height: 110px;
                    border: 2px dashed rgba(61,242,118,0.35);
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                    background: rgba(255,255,255,0.04);
                    flex-shrink: 0;
                }
                .cf-avatar-wrap img { width:100%; height:100%; object-fit:cover; }
                .cf-avatar-wrap .cf-avatar-placeholder {
                    font-size: 2.2rem;
                    color: rgba(255,255,255,0.2);
                }

                /* Step badge */
                .cf-step-badge {
                    width: 28px;
                    height: 28px;
                    border-radius: 50%;
                    background: rgba(61,242,118,0.15);
                    border: 1.5px solid var(--primary);
                    color: var(--primary);
                    font-size: .75rem;
                    font-weight: 700;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }

                /* Page header */
                .cf-page-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 1.75rem;
                }
                .cf-page-header h5 {
                    font-size: 1.15rem;
                    font-weight: 700;
                    color: #fff;
                    margin: 0;
                }
                .cf-page-header p {
                    font-size: .8rem;
                    color: rgba(255,255,255,0.45);
                    margin: .15rem 0 0;
                }

                /* Action bar */
                .cf-action-bar {
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: .75rem;
                    padding-top: 1rem;
                    border-top: 1px solid rgba(255,255,255,0.07);
                    margin-top: .5rem;
                }
                .btn-cf-primary {
                    background: var(--primary);
                    color: #000;
                    font-weight: 700;
                    border: none;
                    border-radius: 8px;
                    padding: .55rem 1.6rem;
                    letter-spacing: .04em;
                    transition: opacity .2s, transform .15s;
                }
                .btn-cf-primary:hover { opacity: .88; transform: translateY(-1px); color:#000; }
                .btn-cf-outline {
                    background: transparent;
                    color: rgba(255,255,255,0.55);
                    border: 1px solid rgba(255,255,255,0.15);
                    border-radius: 8px;
                    padding: .55rem 1.2rem;
                    transition: border-color .2s, color .2s;
                }
                .btn-cf-outline:hover { border-color: rgba(255,255,255,0.4); color:#fff; }

                /* ── Light mode overrides ── */
                [data-theme="light"] .cf-card {
                    background: #ffffff;
                    border-color: #e2e8f0;
                    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
                }
                [data-theme="light"] .cf-card .form-control,
                [data-theme="light"] .cf-card .form-select {
                    background: #f8fafc;
                    border-color: #d1d9e0;
                    color: #212529;
                }
                [data-theme="light"] .cf-card .form-control:focus,
                [data-theme="light"] .cf-card .form-select:focus {
                    background: #fff;
                    border-color: #1a7a3a;
                    box-shadow: 0 0 0 3px rgba(26,122,58,0.1);
                    color: #212529;
                }
                [data-theme="light"] .cf-card .form-control::placeholder { color: #adb5bd; }
                [data-theme="light"] .cf-card .form-label { color: #495057; }
                [data-theme="light"] .cf-section-title {
                    color: #1a7a3a;
                    border-bottom-color: rgba(26,122,58,0.2);
                }
                [data-theme="light"] .cf-step-badge {
                    background: rgba(26,122,58,0.1);
                    border-color: #1a7a3a;
                    color: #1a7a3a;
                }
                [data-theme="light"] .cf-avatar-wrap {
                    border-color: rgba(26,122,58,0.3);
                    background: #f8fafc;
                }
                [data-theme="light"] .cf-avatar-wrap .cf-avatar-placeholder { color: #cbd5e0; }
                [data-theme="light"] .cf-page-header h5 { color: #1e293b; }
                [data-theme="light"] .cf-page-header p { color: #64748b; }
                [data-theme="light"] .cf-action-bar { border-top-color: #e2e8f0; }
                [data-theme="light"] .btn-cf-outline { color: #64748b; border-color: #d1d9e0; }
                [data-theme="light"] .btn-cf-outline:hover { color: #1e293b; border-color: #94a3b8; }

                /* ── Tabs ── */
                .cf-tabs { border-bottom: 2px solid rgba(255,255,255,0.1); margin-bottom: 1.5rem; gap: .25rem; }
                .cf-tabs .nav-link {
                    color: rgba(255,255,255,0.45);
                    border: none;
                    border-bottom: 2px solid transparent;
                    border-radius: 0;
                    padding: .65rem 1.4rem;
                    font-size: .85rem;
                    font-weight: 600;
                    letter-spacing: .03em;
                    margin-bottom: -2px;
                    transition: color .2s, border-color .2s;
                }
                .cf-tabs .nav-link i { margin-right: .35rem; }
                .cf-tabs .nav-link:hover { color: rgba(255,255,255,0.85); }
                .cf-tabs .nav-link.active {
                    color: var(--primary);
                    border-bottom-color: var(--primary);
                    background: transparent;
                }
                [data-theme="light"] .cf-tabs { border-bottom-color: #e2e8f0; }
                [data-theme="light"] .cf-tabs .nav-link { color: #94a3b8; }
                [data-theme="light"] .cf-tabs .nav-link:hover { color: #1e293b; }
                [data-theme="light"] .cf-tabs .nav-link.active { color: #1a7a3a; border-bottom-color: #1a7a3a; }
            </style>

            <div class="container-fluid pt-4 px-4 pb-5">

                <!-- Page Header -->
                <div class="cf-page-header">
                    <div>
                        <h5><i class="fa fa-user-plus me-2" style="color:var(--primary)"></i>New Client Registration</h5>
                        <p>Fill in all required fields to register a new client.</p>
                    </div>
                    <a href="client.php" class="btn-cf-outline btn"><i class="fa fa-redo me-1"></i> Reset Form</a>
                </div>

                <!-- Tab Navigation -->
                <ul class="nav cf-tabs" id="mainTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="tab-client-lnk" data-bs-toggle="tab" href="#tab-client" role="tab">
                            <i class="fa fa-user-plus"></i> Client Registration
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="tab-comaker-lnk" data-bs-toggle="tab" href="#tab-comaker" role="tab">
                            <i class="fa fa-user-friends"></i> Co-maker Registration
                        </a>
                    </li>
                </ul>

                <div class="tab-content">

                <!-- ─── Tab 1: Client Registration ─────────────────────────── -->
                <div class="tab-pane fade show active" id="tab-client" role="tabpanel">

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" hidden><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" hidden><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" id="clientForm">

                    <!-- ── Section 1: Branch & Profile ── -->
                    <div class="cf-card">
                        <div class="cf-section-title">
                            <span class="cf-step-badge">1</span>
                            <i class="fa fa-id-card"></i> Basic Information
                        </div>
                        <div class="row g-3 align-items-start">
                            <!-- Avatar preview -->
                            <div class="col-12 col-md-auto d-flex flex-column align-items-center gap-2">
                                <div class="cf-avatar-wrap" id="avatarWrap">
                                    <span class="cf-avatar-placeholder"><i class="fa fa-user"></i></span>
                                </div>
                                <input name="Prof_Pic" type="file" accept="image/*" class="form-control form-control-sm" id="profPicInput" style="max-width:110px;font-size:.72rem;">
                                <small style="font-size:.7rem;color:rgba(255,255,255,0.35);">Profile Photo</small>
                            </div>
                            <!-- Fields -->
                            <div class="col">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Branch <span class="text-danger">*</span></label>
                                        <select name="Branch_ID" class="form-select" required>
                                            <option value="">— Select Branch —</option>
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
                                        <label class="form-label">Nickname <span class="text-danger">*</span></label>
                                        <input name="Nickname" class="form-control" placeholder="e.g. Juan" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input name="Last_Name" class="form-control" placeholder="Dela Cruz" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input name="First_Name" class="form-control" placeholder="Juan" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Middle Name</label>
                                        <input name="Middle_Name" class="form-control" placeholder="Santos">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Age</label>
                                        <input name="Age" type="number" min="0" class="form-control" placeholder="25">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Gender</label>
                                        <select name="Gender" class="form-select">
                                            <option value="">— Select —</option>
                                            <option>Male</option>
                                            <option>Female</option>
                                            <option>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input name="Date_Of_Birth" type="date" class="form-control">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Place of Birth <span class="text-danger">*</span></label>
                                        <input name="Place_Of_Birth" class="form-control" placeholder="City, Province" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Section 2: Personal Details ── -->
                    <div class="cf-card">
                        <div class="cf-section-title">
                            <span class="cf-step-badge">2</span>
                            <i class="fa fa-info-circle"></i> Personal Details
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Civil Status <span class="text-danger">*</span></label>
                                <select name="Civil_Status" class="form-select" required>
                                    <option value="">— Select —</option>
                                    <option value="M"<?php if(isset($_POST['Civil_Status']) && $_POST['Civil_Status']==='M') echo ' selected'; ?>>Married</option>
                                    <option value="S"<?php if(isset($_POST['Civil_Status']) && $_POST['Civil_Status']==='S') echo ' selected'; ?>>Single</option>
                                    <option value="SP"<?php if(isset($_POST['Civil_Status']) && $_POST['Civil_Status']==='SP') echo ' selected'; ?>>Single Parent</option>
                                    <option value="MO"<?php if(isset($_POST['Civil_Status']) && $_POST['Civil_Status']==='MO') echo ' selected'; ?>>Married w/o Child</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Religion <span class="text-danger">*</span></label>
                                <input name="Religion" class="form-control" placeholder="e.g. Roman Catholic" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">No. of Children</label>
                                <input name="No_Of_Children" type="number" min="0" class="form-control" placeholder="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Educational Attainment <span class="text-danger">*</span></label>
                                <input name="Educational_Attainment" class="form-control" placeholder="e.g. College Graduate" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mobile No. <span class="text-danger">*</span></label>
                                <input name="Mobile_No" class="form-control" placeholder="09XX-XXX-XXXX" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input name="Email_Address" type="email" class="form-control" placeholder="email@example.com" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ID Presented <span class="text-danger">*</span></label>
                                <input name="ID_Presented" class="form-control" placeholder="e.g. PhilSys ID" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ID Reference No. <span class="text-danger">*</span></label>
                                <input name="ID_Reference_No" class="form-control" placeholder="Reference number" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Expiration Date</label>
                                <input name="Exp_ID" type="date" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- ── Section 3: Address ── -->
                    <div class="cf-card">
                        <div class="cf-section-title">
                            <span class="cf-step-badge">3</span>
                            <i class="fa fa-map-marker-alt"></i> Address
                        </div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">House / Street / Building <span class="text-danger">*</span></label>
                                <input name="House_Street_Bldng" class="form-control" placeholder="Blk 1 Lot 2, Sampaguita St." required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Barangay / Town <span class="text-danger">*</span></label>
                                <input name="Barangay_Town" class="form-control" placeholder="Barangay Name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City / Municipality <span class="text-danger">*</span></label>
                                <input name="City_Municipality" class="form-control" placeholder="City Name" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Province <span class="text-danger">*</span></label>
                                <input name="Province" class="form-control" placeholder="Province Name" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Zip Code</label>
                                <input name="Zip_Code" class="form-control" placeholder="0000">
                            </div>
                        </div>
                    </div>

                    <!-- ── Section 4: Mother's Information ── -->
                    <div class="cf-card">
                        <div class="cf-section-title">
                            <span class="cf-step-badge">4</span>
                            <i class="fa fa-female"></i> Mother's Information
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input name="Mother_Last_Name" class="form-control" placeholder="Mother's Last Name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input name="Mother_First_Name" class="form-control" placeholder="Mother's First Name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle Name</label>
                                <input name="Mother_Middle_Name" class="form-control" placeholder="Mother's Middle Name">
                            </div>
                        </div>
                    </div>

                    <!-- ── Section 5: Spouse Information ── -->
                    <div class="cf-card">
                        <div class="cf-section-title">
                            <span class="cf-step-badge">5</span>
                            <i class="fa fa-heart"></i> Spouse Information
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Last Name</label>
                                <input name="Spouse_Last_Name" class="form-control" placeholder="Spouse's Last Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">First Name</label>
                                <input name="Spouse_First_Name" class="form-control" placeholder="Spouse's First Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle Name</label>
                                <input name="Spouse_Middle_Name" class="form-control" placeholder="Spouse's Middle Name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nickname</label>
                                <input name="Spouse_Nickname" class="form-control" placeholder="Nickname">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Age</label>
                                <input name="Spouse_Age" class="form-control" placeholder="Age">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date of Birth</label>
                                <input name="Spouse_DOB" type="date" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Monthly Income</label>
                                <input name="Spouse_Income" type="number" step="0.01" class="form-control" placeholder="0.00">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Work / Business</label>
                                <input name="Spouse_Work" class="form-control" placeholder="Occupation">
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="Latitude" value="">
                    <input type="hidden" name="Longitude" value="">
                    <?php $po_display = isset($_SESSION['User_ID']) ? htmlspecialchars($_SESSION['User_ID']) : (isset($_SESSION['UserID']) ? htmlspecialchars($_SESSION['UserID']) : ''); ?>
                    <input type="hidden" name="Project_Officer_ID" value="<?php echo $po_display; ?>">

                    <!-- Action Bar -->
                    <div class="cf-action-bar">
                        <button type="reset" class="btn-cf-outline btn"><i class="fa fa-times me-1"></i> Clear</button>
                        <button type="submit" class="btn-cf-primary btn"><i class="fa fa-save me-1"></i> Save Client</button>
                    </div>

                </form>
                </div><!-- end tab-client -->

                <!-- ─── Tab 2: Co-maker Registration ───────────────────────── -->
                <div class="tab-pane fade" id="tab-comaker" role="tabpanel">

                    <?php if (!empty($cm_success)): ?>
                        <div class="alert alert-success" hidden><?php echo htmlspecialchars($cm_success); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($cm_error)): ?>
                        <div class="alert alert-danger" hidden><?php echo htmlspecialchars($cm_error); ?></div>
                    <?php endif; ?>

                    <form method="post" id="comakerForm">
                        <input type="hidden" name="save_comaker" value="1">

                        <!-- Section 1: Personal Information -->
                        <div class="cf-card">
                            <div class="cf-section-title">
                                <span class="cf-step-badge">1</span>
                                <i class="fa fa-id-card"></i> Personal Information
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input name="cm_Last_Name" class="form-control" placeholder="Last Name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input name="cm_First_Name" class="form-control" placeholder="First Name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Middle Name</label>
                                    <input name="cm_Middle_Name" class="form-control" placeholder="Middle Name">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Age</label>
                                    <input name="cm_Age" type="number" min="0" class="form-control" placeholder="Age">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Gender</label>
                                    <select name="cm_Gender" class="form-select">
                                        <option value="">— Select —</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Date of Birth</label>
                                    <input name="cm_Date_Of_Birth" type="date" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Place of Birth</label>
                                    <input name="cm_Place_Of_Birth" class="form-control" placeholder="Place of Birth">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Civil Status</label>
                                    <select name="cm_Civil_Status" class="form-select">
                                        <option value="">— Select —</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">No. of Children</label>
                                    <input name="cm_No_Of_Children" type="number" min="0" class="form-control" placeholder="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Mobile No.</label>
                                    <input name="cm_Mobile_No" class="form-control" placeholder="09XX-XXX-XXXX">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Email Address</label>
                                    <input name="cm_Email_Address" type="email" class="form-control" placeholder="email@example.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ID Presented</label>
                                    <input name="cm_ID_Presented" class="form-control" placeholder="e.g. PhilSys ID">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ID Reference No.</label>
                                    <input name="cm_ID_Reference_No" class="form-control" placeholder="Reference number">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Name of Spouse</label>
                                    <input name="cm_Name_Of_Spouse" class="form-control" placeholder="Spouse's Full Name">
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Address -->
                        <div class="cf-card">
                            <div class="cf-section-title">
                                <span class="cf-step-badge">2</span>
                                <i class="fa fa-map-marker-alt"></i> Address
                            </div>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">House / Street / Building</label>
                                    <input name="cm_House_Street_Bldng" class="form-control" placeholder="Blk 1 Lot 2, Sampaguita St.">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Barangay / Town</label>
                                    <input name="cm_Barangay_Town" class="form-control" placeholder="Barangay Name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">City / Municipality</label>
                                    <input name="cm_City_Municipality" class="form-control" placeholder="City Name">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Province</label>
                                    <input name="cm_Province" class="form-control" placeholder="Province Name">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Zip Code</label>
                                    <input name="cm_Zip_Code" class="form-control" placeholder="0000">
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Financial Information -->
                        <div class="cf-card">
                            <div class="cf-section-title">
                                <span class="cf-step-badge">3</span>
                                <i class="fa fa-coins"></i> Financial Information
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Income Source</label>
                                    <input name="cm_Income_Source" class="form-control" placeholder="e.g. Employment">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Other Income Source</label>
                                    <input name="cm_Other_Income_Source" class="form-control" placeholder="Other sources">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Monthly Income</label>
                                    <input name="cm_Montly_Income" type="number" step="0.01" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Business Name</label>
                                    <input name="cm_Business_Name" class="form-control" placeholder="Business Name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Business Address</label>
                                    <input name="cm_Business_Address" class="form-control" placeholder="Business Address">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Primary Bank</label>
                                    <input name="cm_Primary_Bank" class="form-control" placeholder="e.g. BDO, BPI">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Name of Lending Institution</label>
                                    <input name="cm_Name_Of_Lending" class="form-control" placeholder="Lending institution name">
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Relationship to Client -->
                        <div class="cf-card">
                            <div class="cf-section-title">
                                <span class="cf-step-badge">4</span>
                                <i class="fa fa-handshake"></i> Relationship to Client
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Relationship</label>
                                    <input name="cm_Relationship" class="form-control" placeholder="e.g. Friend, Sibling, Colleague">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Acquaintance Duration (years)</label>
                                    <input name="cm_Acquaintance_Duration" type="number" step="0.1" min="0" class="form-control" placeholder="0.0">
                                </div>
                            </div>
                        </div>

                        <!-- Action Bar -->
                        <div class="cf-action-bar">
                            <button type="reset" class="btn-cf-outline btn"><i class="fa fa-times me-1"></i> Clear</button>
                            <button type="submit" class="btn-cf-primary btn"><i class="fa fa-save me-1"></i> Save Co-maker</button>
                        </div>

                    </form>
                </div><!-- end tab-comaker -->

                </div><!-- end tab-content -->
            </div><!-- end container-fluid -->

            <script>
            // Live avatar preview
            document.getElementById('profPicInput').addEventListener('change', function(){
                const wrap = document.getElementById('avatarWrap');
                const file = this.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = e => {
                    wrap.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
                };
                reader.readAsDataURL(file);
            });
            </script>

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
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const form = document.getElementById('clientForm');
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

                const text = await res.text();
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (parseErr) {
                    throw new Error('Invalid JSON response from server: ' + text);
                }

                if (data && data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Saved', text: data.message || 'Saved', confirmButtonText: 'OK' });
                    form.reset();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data && data.message ? data.message : 'Save failed', confirmButtonText: 'OK' });
                }
            } catch (err) {
                console.error('Submit error:', err);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network or server error: ' + err.message, confirmButtonText: 'OK' });
            }
        });
    });

    // ── Co-maker form AJAX submit ─────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function(){
        const cmForm = document.getElementById('comakerForm');
        if (!cmForm) return;
        cmForm.addEventListener('submit', async function(e){
            e.preventDefault();
            const fd = new FormData(cmForm);
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const text = await res.text();
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (parseErr) {
                    throw new Error('Invalid JSON response from server: ' + text);
                }
                if (data && data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Saved', text: data.message || 'Saved', confirmButtonText: 'OK' });
                    cmForm.reset();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data && data.message ? data.message : 'Save failed', confirmButtonText: 'OK' });
                }
            } catch (err) {
                console.error('Co-maker submit error:', err);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network or server error: ' + err.message, confirmButtonText: 'OK' });
            }
        });
    });
    </script>
</body>

</html>