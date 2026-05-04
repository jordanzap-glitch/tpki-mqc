<?php
include 'includes/init.php';
include '../db/dbcon.php';

// ── Handle client search AJAX ─────────────────────────────────────────────────
if (!empty($_GET['action']) && $_GET['action'] === 'search_client') {
    header('Content-Type: application/json');
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (mb_strlen($q) < 2) { echo json_encode([]); exit; }
    $like = '%' . $q . '%';
    $stmt_s = mysqli_prepare($conn, "SELECT Client_ID, CONCAT(Last_Name, ', ', First_Name, IF(Middle_Name IS NOT NULL AND Middle_Name != '', CONCAT(' ', Middle_Name), '')) AS Full_Name FROM tbl_client_info WHERE Client_ID LIKE ? OR Last_Name LIKE ? OR First_Name LIKE ? ORDER BY Last_Name LIMIT 15");
    if ($stmt_s) {
        mysqli_stmt_bind_param($stmt_s, 'sss', $like, $like, $like);
        mysqli_stmt_execute($stmt_s);
        $cid_s = $fname_s = null;
        mysqli_stmt_bind_result($stmt_s, $cid_s, $fname_s);
        $rows_s = [];
        while (mysqli_stmt_fetch($stmt_s)) {
            $rows_s[] = ['Client_ID' => $cid_s, 'Full_Name' => $fname_s];
        }
        mysqli_stmt_close($stmt_s);
        echo json_encode($rows_s);
    } else { echo json_encode([]); }
    exit;
}

// Load region + province + city/mun lists once for both address dropdowns
$regData     = json_decode(file_get_contents(__DIR__ . '/includes/refregion.json'),  true);
$provData    = json_decode(file_get_contents(__DIR__ . '/includes/refprovince.json'), true);
$citymunData = json_decode(file_get_contents(__DIR__ . '/includes/refcitymun.json'),  true);
$brgyData    = json_decode(file_get_contents(__DIR__ . '/includes/refbrgy.json'),     true);
$eduData     = json_decode(file_get_contents(__DIR__ . '/includes/refedu.json'),     true);
// ID types reference
$idsData     = json_decode(file_get_contents(__DIR__ . '/includes/refids.json'),  true);

// Handle form submission (client only — skip when co-maker sub-form is posted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['save_comaker'])) {
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

    // Lookup User_ID from tbl_user for processed_by
    $processed_by = null;
    $_uid = (int)($_SESSION['userId'] ?? 0);
    if ($_uid > 0) {
        $_pb_stmt = mysqli_prepare($conn, "SELECT User_ID FROM tbl_user WHERE id = ? LIMIT 1");
        if ($_pb_stmt) {
            mysqli_stmt_bind_param($_pb_stmt, 'i', $_uid);
            mysqli_stmt_execute($_pb_stmt);
            mysqli_stmt_bind_result($_pb_stmt, $processed_by);
            mysqli_stmt_fetch($_pb_stmt);
            mysqli_stmt_close($_pb_stmt);
        }
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
                (Client_ID, Branch_ID, Last_Name, First_Name, Middle_Name, Nickname, Age, Gender, Date_Of_Birth, Place_Of_Birth, Civil_Status, Religion, Mother_Last_Name, Mother_First_Name, Mother_Middle_Name, Mobile_No, Email_Address, House_Street_Bldng, Barangay_Town, City_Municipality, Province, Zip_Code, Educational_Attainment, No_Of_Children, ID_Presented, ID_Reference_No, Spouse_Last_Name, Spouse_First_Name, Spouse_Middle_Name, Spouse_Work, Spouse_Nickname, Spouse_Age, Spouse_DOB, Spouse_Income, Latitude, Longitude, Project_Officer_ID, Exp_ID, Prof_Pic, processed_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, str_repeat('s', 40), $client_id, $branch_id, $last_name, $first_name, $middle_name, $nickname, $age, $gender, $dob, $pob, $civil, $religion, $mother_last, $mother_first, $mother_middle, $mobile, $email, $house, $barangay, $city, $province, $zip, $edu, $no_children, $id_presented, $id_ref_no, $spouse_last, $spouse_first, $spouse_middle, $spouse_work, $spouse_nick, $spouse_age, $spouse_dob, $spouse_income, $latitude, $longitude, $project_officer, $exp_id, $prof_pic, $processed_by);

        // Because Prof_Pic is blob, use send_long_data if available
        if ($prof_pic !== null) {
            // send_long_data requires mysqli_stmt_send_long_data, which expects param index starting at 0
            // Prof_Pic is now the second-to-last param (zero-based index 38)
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

    $cm_Client_ID           = isset($_POST['cm_Client_ID'])           ? trim($_POST['cm_Client_ID'])           : '';
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
        $sql_cm = "INSERT INTO tbl_comaker_info (Comaker_ID, Client_ID, Last_Name, First_Name, Middle_Name, Age, Gender, Date_Of_Birth, Place_Of_Birth, Civil_Status, Mobile_No, Email_Address, House_Street_Bldng, Barangay_Town, City_Municipality, Province, Zip_Code, No_Of_Children, ID_Presented, ID_Reference_No, Income_Source, Other_Income_Source, Montly_Income, Business_Name, Business_Address, Name_Of_Spouse, Primary_Bank, Name_Of_Lending, Acquaintance_Duration, Relationship) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_cm = mysqli_prepare($conn, $sql_cm);
        if ($stmt_cm) {
            mysqli_stmt_bind_param($stmt_cm, str_repeat('s', 30),
                $comaker_id, $cm_Client_ID, $cm_Last_Name, $cm_First_Name, $cm_Middle_Name, $cm_Age, $cm_Gender,
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

                /* ── Client search result items ── */
                .cm-search-result-item {
                    padding: .55rem .9rem;
                    border-radius: 8px;
                    cursor: pointer;
                    color: #e2e5f1;
                    font-size: .85rem;
                    border-bottom: 1px solid rgba(255,255,255,0.05);
                    transition: background .15s;
                }
                .cm-search-result-item:hover { background: rgba(61,242,118,0.1); }
                .cm-search-result-item:last-child { border-bottom: none; }
                [data-theme="light"] .cm-search-result-item { color: #1e293b; border-bottom-color: #f1f5f9; }
                [data-theme="light"] .cm-search-result-item:hover { background: rgba(26,122,58,0.07); }

                /* ── Selected client card ── */
                #selectedClientCard {
                    background: var(--primary);
                    border: none;
                    color: #000;
                }
                #selectedClientCard .cf-section-title { color: #000 !important; border-bottom-color: rgba(0,0,0,0.15) !important; }
                #selectedClientCard #selectedClientName { color: #000; }
                #selectedClientCard #selectedClientIdDisplay { color: rgba(0,0,0,0.65); }
                [data-theme="light"] #selectedClientCard { background: var(--primary); }
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

                <!-- ── Loan Type Instructions ── -->
                <style>
                .reg-guidelines {
                    background: rgba(61,242,118,0.1);
                    border: 1.5px solid rgba(61,242,118,0.4);
                    border-radius: 12px;
                    padding: 1.1rem 1.4rem;
                    margin-bottom: 1.5rem;
                }
                .reg-guidelines-title {
                    font-size: .78rem; font-weight: 700; letter-spacing: .08em;
                    text-transform: uppercase; color: var(--primary); margin-bottom: .75rem;
                }
                .reg-guideline-box {
                    background: rgba(255,255,255,0.08);
                    border: 1px solid rgba(255,255,255,0.18);
                    border-radius: 10px; padding: .85rem 1rem; height: 100%;
                }
                .reg-guideline-box-title {
                    font-size: .85rem; font-weight: 700; color: #fff; margin-bottom: .5rem;
                }
                .reg-guideline-box ul {
                    margin: 0; padding-left: 1.15rem;
                    font-size: .82rem; color: #e2e5f1; line-height: 1.8;
                }
                .reg-guideline-box ul li { margin-bottom: .1rem; }
                /* Light mode */
                [data-theme="light"] .reg-guidelines {
                    background: rgba(26,122,58,0.07);
                    border-color: rgba(26,122,58,0.35);
                }
                [data-theme="light"] .reg-guidelines-title { color: #1a7a3a; }
                [data-theme="light"] .reg-guideline-box {
                    background: #f8fafc;
                    border-color: #d1d9e0;
                }
                [data-theme="light"] .reg-guideline-box-title { color: #1e293b; }
                [data-theme="light"] .reg-guideline-box ul { color: #475569; }
                </style>
                <div class="reg-guidelines">
                    <div class="reg-guidelines-title">
                        <i class="fa fa-info-circle me-2"></i>Registration Guidelines by Loan Type
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="reg-guideline-box">
                                <div class="reg-guideline-box-title">
                                    <i class="fa fa-user me-2" style="color:var(--primary)"></i>Personal &amp; Salary Loans
                                </div>
                                <ul>
                                    <li>Register the borrower on the <strong>Client Registration</strong> tab.</li>
                                    <li>A co-maker is <strong style="color:var(--primary);">required</strong> for these loan types.</li>
                                    <li>After completing the client's details, go to the <strong>Co-maker(s)</strong> tab to register one or more co-makers and link them to this client.</li>
                                    <li>To add a co-maker to a client registered in a previous session, use the <strong>Add to Existing Client</strong> tab.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="reg-guideline-box">
                                <div class="reg-guideline-box-title">
                                    <i class="fa fa-users me-2" style="color:var(--primary)"></i>Group Loans
                                </div>
                                <ul>
                                    <li><strong style="color:var(--primary);">Do not</strong> register group loan members as co-makers.</li>
                                    <li>Within a group, each member acts as a co-maker for the others.</li>
                                    <li>Register <strong>every group member individually</strong> on the <strong>Client Registration</strong> tab — one registration per member.</li>
                                    <li>Members will be linked together when a Group Loan is created in the Loan module.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
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
                            <i class="fa fa-user-friends"></i> Co-maker(s)
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="tab-existing-lnk" data-bs-toggle="tab" href="#tab-existing" role="tab">
                            <i class="fa fa-search-plus"></i> Add to Existing Client
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
                                        <input id="branchInput" list="branchList" class="form-control" placeholder="Select or type branch..." autocomplete="off" required>
                                        <datalist id="branchList">
                                            <?php
                                            $bq = mysqli_query($conn, "SELECT Branch_ID, Branch_Name FROM tbl_branch WHERE Is_Active = 1 ORDER BY Branch_Name");
                                            if ($bq) {
                                                while ($b = mysqli_fetch_assoc($bq)) {
                                                    $bname = htmlspecialchars($b['Branch_Name']);
                                                    echo "<option value=\"$bname\">";
                                                }
                                            }
                                            ?>
                                        </datalist>
                                        <input type="hidden" name="Branch_ID" id="branchIdHidden" required>
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
                                        <input id="ageClient" name="Age" type="number" min="0" class="form-control" placeholder="25" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Gender</label>
                                        <input list="genderList" name="Gender" class="form-control" placeholder="Select or type..." autocomplete="off">
                                        <datalist id="genderList">
                                            <option value="Male">
                                            <option value="Female">
                                            <option value="Other">
                                        </datalist>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input id="dobClient" name="Date_Of_Birth" type="date" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Place of Birth <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-2">
                                            <input id="pobProvClient" list="pobProvClientList" class="form-control" placeholder="Province..." required autocomplete="off">
                                            <datalist id="pobProvClientList"></datalist>
                                            <input id="pobCityClient" list="pobCityClientList" class="form-control" placeholder="City / Municipality..." required autocomplete="off">
                                            <datalist id="pobCityClientList"></datalist>
                                        </div>
                                    </div>
                                    <input type="hidden" name="Place_Of_Birth" id="pobHiddenClient">
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
                                <input list="civilClientList" name="Civil_Status" class="form-control" placeholder="Select or type..." required autocomplete="off">
                                <datalist id="civilClientList">
                                    <option value="Married">
                                    <option value="Single">
                                    <option value="Single Parent">
                                    <option value="Married w/o Child">
                                </datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Religion <span class="text-danger">*</span></label>
                                <input name="Religion" class="form-control" placeholder="e.g. Roman Catholic" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">No. of Children</label>
                                <input list="childrenList" name="No_Of_Children" class="form-control" placeholder="Select or type..." autocomplete="off">
                                <datalist id="childrenList">
                                    <option value="1">
                                    <option value="2">
                                    <option value="3">
                                    <option value="4">
                                    <option value="5">
                                    <option value="6">
                                    <option value="7">
                                    <option value="8">
                                    <option value="9">
                                    <option value="10">
                                    <option value="11">
                                    <option value="12">
                                    <option value="13">
                                    <option value="14">
                                    <option value="15">
                                    <option value="More than 15">
                                </datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Educational Attainment <span class="text-danger">*</span></label>
                                <input list="eduList" name="Educational_Attainment" class="form-control" placeholder="Select or type..." required>
                                <datalist id="eduList">
                                    <?php
                                    if (isset($eduData['educational_attainment']) && is_array($eduData['educational_attainment'])) {
                                        foreach ($eduData['educational_attainment'] as $e) {
                                            if (isset($e['label'])) echo '<option value="' . htmlspecialchars($e['label']) . '">';
                                        }
                                    }
                                    ?>
                                </datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mobile No. <span class="text-danger">*</span></label>
                                <input id="mobileClient" name="Mobile_No" type="tel" inputmode="numeric" pattern="09[0-9]{2}-[0-9]{3}-[0-9]{4}" class="form-control" placeholder="09xx-xxx-xxxx" maxlength="13" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input name="Email_Address" type="email" class="form-control" placeholder="email@example.com" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ID Presented <span class="text-danger">*</span></label>
                                <input list="idPresentedList" name="ID_Presented" class="form-control" placeholder="Select or type..." required autocomplete="off">
                                <datalist id="idPresentedList">
                                    <?php
                                    if (isset($idsData['valid_ids']) && is_array($idsData['valid_ids'])) {
                                        foreach ($idsData['valid_ids'] as $id) {
                                            $label = isset($id['id_name']) ? $id['id_name'] : (isset($id['id_name']) ? $id['id_name'] : '');
                                            $abbr = isset($id['abbreviation']) && $id['abbreviation'] ? ' (' . $id['abbreviation'] . ')' : '';
                                            if ($label) echo '<option value="' . htmlspecialchars($label . $abbr) . '">';
                                        }
                                    }
                                    ?>
                                </datalist>
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
                            <div class="col-md-4">
                                <label class="form-label">Region <span class="text-danger">*</span></label>
                                <input id="regionClient" list="regionClientList" name="Region" class="form-control" placeholder="Select or type region..." required autocomplete="off">
                                <datalist id="regionClientList">
                                    <?php
                                    if ($regData && isset($regData['RECORDS'])) {
                                        foreach ($regData['RECORDS'] as $reg) {
                                            $rDesc = htmlspecialchars($reg['regDesc']);
                                            echo "<option value=\"$rDesc\">";
                                        }
                                    }
                                    ?>
                                </datalist>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">House / Street / Building <span class="text-danger">*</span></label>
                                <input name="House_Street_Bldng" class="form-control" placeholder="Blk 1 Lot 2, Sampaguita St." required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Province <span class="text-danger">*</span></label>
                                <input id="provinceClient" list="provClientList" name="Province" class="form-control" placeholder="Select or type province..." required autocomplete="off">
                                <datalist id="provClientList"></datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City / Municipality <span class="text-danger">*</span></label>
                                <input id="cityClient" list="cityClientList" name="City_Municipality" class="form-control" placeholder="Select or type city..." required autocomplete="off">
                                <datalist id="cityClientList"></datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Barangay / Town <span class="text-danger">*</span></label>
                                <input id="brgyClient" list="brgyClientList" name="Barangay_Town" class="form-control" placeholder="Select or type barangay..." required autocomplete="off">
                                <datalist id="brgyClientList"></datalist>
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
                                <input id="ageSpouse" name="Spouse_Age" class="form-control" placeholder="Age" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date of Birth</label>
                                <input id="dobSpouse" name="Spouse_DOB" type="date" class="form-control">
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
                    </div>

                </form>
                </div><!-- end tab-client -->

                <!-- ─── Tab 2: Co-maker Registration ───────────────────────── -->
                <div class="tab-pane fade" id="tab-comaker" role="tabpanel">

                    <!-- Co-maker usage note -->
                    <div style="background:rgba(255,193,7,0.13);border:1.5px solid rgba(255,193,7,0.55);border-radius:10px;padding:.9rem 1.15rem;margin-bottom:1.25rem;font-size:.84rem;color:#e2e5f1;line-height:1.7;">
                        <div style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#ffc107;margin-bottom:.4rem;"><i class="fa fa-exclamation-triangle me-2"></i>For Personal &amp; Salary Loans Only</div>
                        Co-makers registered here will be linked to the client being registered in the <em>Client Registration</em> tab.
                        Each co-maker must have a valid first and last name.<br>
                        <strong style="color:#ffc107;">&#9888; Group loan members should not be registered as co-makers</strong> — register each group member individually as a client instead.
                    </div>

                    <!-- Count selector -->
                    <div class="cf-card">
                        <div class="cf-section-title">
                            <i class="fa fa-users"></i> Co-maker Setup
                        </div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div>
                                <label class="form-label mb-1">Number of Co-makers to Add</label>
                                <input id="comakerCountSelect" list="comakerCountList" class="form-control" value="" style="width:auto;min-width:180px;" autocomplete="off" placeholder="Set or select count...">
                                <datalist id="comakerCountList">
                                    <option value="1">
                                    <option value="2">
                                    <option value="3">
                                    <option value="4">
                                    <option value="5">
                                    <option value="6">
                                </datalist>
                                <small style="display:block;margin-top:.3rem;color:rgba(255,255,255,0.45);font-size:.76rem;">
                                    <i class="fa fa-keyboard me-1"></i>You can type a number e.g. 1, 2, 3, 4, 5, 6
                                </small>
                            </div>
                            <small style="color:rgba(255,255,255,0.4);font-size:.78rem;align-self:flex-end;margin-bottom:1.5rem;">
                                <i class="fa fa-info-circle me-1"></i>Co-makers will be linked to the newly registered client.
                            </small>
                        </div>
                    </div>

                    <!-- Dynamic co-maker panels rendered here -->
                    <div id="comakerPanelsContainer"></div>

                </div><!-- end tab-comaker -->

                <!-- ─── Tab 3: Add Co-maker to Existing Client ──────────────── -->
                <div class="tab-pane fade" id="tab-existing" role="tabpanel">

                    <!-- Tab 3 usage note -->
                    <div style="background:rgba(255,193,7,0.13);border:1.5px solid rgba(255,193,7,0.55);border-radius:10px;padding:.9rem 1.15rem;margin-bottom:1.25rem;font-size:.84rem;color:#e2e5f1;line-height:1.7;">
                        <div style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#ffc107;margin-bottom:.4rem;"><i class="fa fa-exclamation-triangle me-2"></i>For Personal &amp; Salary Loans Only</div>
                        Use this tab to attach a co-maker to a client who was registered in a previous session.
                        Search for the existing client, then fill in the co-maker details below.<br>
                        <strong style="color:#ffc107;">&#9888; Do not use this tab for Group Loan members</strong> — group members serve as each other's co-makers and must each be registered individually as clients.
                    </div>

                    <!-- Client Search -->
                    <div class="cf-card">
                        <div class="cf-section-title">
                            <i class="fa fa-user-check"></i> Find Existing Client
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Search by Client ID or Name</label>
                                <div class="input-group">
                                    <input id="existingClientSearch" class="form-control" placeholder="Type Client ID or full name..." autocomplete="off">
                                    <button type="button" class="btn btn-cf-primary" id="btnSearchExistingClient" style="border-radius:0 8px 8px 0;padding:.48rem 1rem;">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                                <div id="existingClientResultsList" class="mt-2" style="max-height:240px;overflow-y:auto;border-radius:8px;"></div>
                            </div>
                            <div class="col-md-6">
                                <div id="selectedClientCard" style="display:none;" class="p-3 rounded h-100">
                                    <div class="cf-section-title" style="border:none;padding:0;margin-bottom:.5rem;color:#000;">
                                        <i class="fa fa-check-circle"></i> Selected Client
                                    </div>
                                    <div class="fw-bold fs-6" id="selectedClientName"></div>
                                    <div class="mt-1" id="selectedClientIdDisplay" style="font-size:.82rem;opacity:.75;"></div>
                                    <input type="hidden" id="existingClientIdHidden" value="">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Co-maker count + panels (shown after client selected) -->
                    <div id="existingCmSetup" style="display:none;">
                        <div class="cf-card">
                            <div class="cf-section-title">
                                <i class="fa fa-users"></i> Co-maker Setup
                            </div>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div>
                                    <label class="form-label mb-1">Number of Co-makers to Add</label>
                                    <input id="existingComakerCount" list="existingComakerCountList" class="form-control" value="1 Co-maker" style="width:auto;min-width:160px;" autocomplete="off" placeholder="Select count...">
                                    <datalist id="existingComakerCountList">
                                        <option value="1 Co-maker">
                                        <option value="2 Co-makers">
                                        <option value="3 Co-makers">
                                        <option value="4 Co-makers">
                                        <option value="5 Co-makers">
                                    </datalist>
                                </div>
                            </div>
                        </div>
                        <div id="existingComakerPanels"></div>
                    </div>

                </div><!-- end tab-existing -->

                </div><!-- end tab-content -->

                <!-- ── Combined Save ──────────────────────────────────────────────── -->
                <div class="cf-action-bar mt-3" style="border-top:1px solid rgba(255,255,255,0.1);padding-top:1.25rem;">
                    <button type="button" id="btnSaveAll" class="btn-cf-primary btn px-5">
                        <i class="fa fa-save me-1"></i> Save Registration
                    </button>
                    <button type="button" id="btnSaveExistingComakers" class="btn-cf-primary btn px-5" style="display:none;">
                        <i class="fa fa-save me-1"></i> Save Co-maker(s)
                    </button>
                </div>

            </div><!-- end container-fluid -->

            <!-- ── Shared datalists for co-maker panels ────────────────────────── -->
            <datalist id="cmSharedRegionList">
                <?php
                if ($regData && isset($regData['RECORDS'])) {
                    foreach ($regData['RECORDS'] as $reg) {
                        echo '<option value="' . htmlspecialchars($reg['regDesc']) . '">';
                    }
                }
                ?>
            </datalist>
            <datalist id="cmSharedCivilList">
                <option value="Single"><option value="Married"><option value="Widowed"><option value="Separated"><option value="Single Parent">
            </datalist>
            <datalist id="cmSharedChildrenList">
                <option value="0"><option value="1"><option value="2"><option value="3"><option value="4"><option value="5">
                <option value="6"><option value="7"><option value="8"><option value="9"><option value="10"><option value="More than 10">
            </datalist>

            <!-- ── Co-maker Panel Template ──────────────────────────────────────── -->
            <template id="comakerPanelTpl">
<div class="comaker-panel cf-card" data-panel-index="__IDX__" style="border-left:3px solid var(--primary);margin-bottom:1.5rem;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <span class="cf-step-badge">__IDX__</span>
            <span style="font-size:.9rem;font-weight:700;color:#fff;">Co-maker __IDX__</span>
        </div>
        <button type="button" class="btn-cf-outline btn btn-sm py-1 px-2 comaker-clear-btn" title="Clear this co-maker">
            <i class="fa fa-eraser me-1"></i> Clear
        </button>
    </div>

    <!-- Personal Info -->
    <div class="cf-section-title"><i class="fa fa-id-card"></i> Personal Information</div>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Last Name <span class="text-danger">*</span></label>
            <input data-cm-field="cm_Last_Name" class="form-control cm-required" placeholder="Last Name">
        </div>
        <div class="col-md-4">
            <label class="form-label">First Name <span class="text-danger">*</span></label>
            <input data-cm-field="cm_First_Name" class="form-control cm-required" placeholder="First Name">
        </div>
        <div class="col-md-4">
            <label class="form-label">Middle Name</label>
            <input data-cm-field="cm_Middle_Name" class="form-control" placeholder="Middle Name">
        </div>
        <div class="col-md-2">
            <label class="form-label">Age</label>
            <input id="ageCm___IDX__" data-cm-field="cm_Age" type="number" min="0" class="form-control" placeholder="Age" readonly>
        </div>
        <div class="col-md-2">
            <label class="form-label">Gender</label>
            <input list="genderList" data-cm-field="cm_Gender" class="form-control" placeholder="Select or type..." autocomplete="off">
        </div>
        <div class="col-md-4">
            <label class="form-label">Date of Birth</label>
            <input id="dobCm___IDX__" data-cm-field="cm_Date_Of_Birth" type="date" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Place of Birth</label>
            <div class="d-flex gap-2">
                <input id="pobProvCm___IDX__" list="pobProvCmList___IDX__" class="form-control" placeholder="Province..." autocomplete="off">
                <datalist id="pobProvCmList___IDX__"></datalist>
                <input id="pobCityCm___IDX__" list="pobCityCmList___IDX__" class="form-control" placeholder="City/Municipality..." autocomplete="off">
                <datalist id="pobCityCmList___IDX__"></datalist>
            </div>
            <input type="hidden" data-cm-field="cm_Place_Of_Birth" id="pobHiddenCm___IDX__">
        </div>
        <div class="col-md-3">
            <label class="form-label">Civil Status</label>
            <input list="cmSharedCivilList" data-cm-field="cm_Civil_Status" class="form-control" placeholder="Select or type..." autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label">No. of Children</label>
            <input list="cmSharedChildrenList" data-cm-field="cm_No_Of_Children" class="form-control" placeholder="Select or type..." autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label">Mobile No.</label>
            <input id="mobileCm___IDX__" data-cm-field="cm_Mobile_No" type="tel" inputmode="numeric" class="form-control cm-mobile" placeholder="09xx-xxx-xxxx" maxlength="13">
        </div>
        <div class="col-md-3">
            <label class="form-label">Email Address</label>
            <input data-cm-field="cm_Email_Address" type="email" class="form-control" placeholder="email@example.com">
        </div>
        <div class="col-md-4">
            <label class="form-label">ID Presented</label>
            <input list="idPresentedList" data-cm-field="cm_ID_Presented" class="form-control" placeholder="Select or type..." autocomplete="off">
        </div>
        <div class="col-md-4">
            <label class="form-label">ID Reference No.</label>
            <input data-cm-field="cm_ID_Reference_No" class="form-control" placeholder="Reference number">
        </div>
        <div class="col-md-4">
            <label class="form-label">Name of Spouse</label>
            <input data-cm-field="cm_Name_Of_Spouse" class="form-control" placeholder="Spouse's Full Name">
        </div>
    </div>

    <!-- Address -->
    <div class="cf-section-title" style="margin-top:1.25rem"><i class="fa fa-map-marker-alt"></i> Address</div>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Region</label>
            <input id="regionCm___IDX__" list="cmSharedRegionList" class="form-control" placeholder="Select or type region..." autocomplete="off">
        </div>
        <div class="col-md-12">
            <label class="form-label">House / Street / Building</label>
            <input data-cm-field="cm_House_Street_Bldng" class="form-control" placeholder="Blk 1 Lot 2, Sampaguita St.">
        </div>
        <div class="col-md-3">
            <label class="form-label">Province</label>
            <input id="provinceCm___IDX__" list="provCmList___IDX__" data-cm-field="cm_Province" class="form-control" placeholder="Select or type province..." autocomplete="off">
            <datalist id="provCmList___IDX__"></datalist>
        </div>
        <div class="col-md-4">
            <label class="form-label">City / Municipality</label>
            <input id="cityCm___IDX__" list="cityCmList___IDX__" data-cm-field="cm_City_Municipality" class="form-control" placeholder="Select or type city..." autocomplete="off">
            <datalist id="cityCmList___IDX__"></datalist>
        </div>
        <div class="col-md-4">
            <label class="form-label">Barangay / Town</label>
            <input id="brgyCm___IDX__" list="brgyCmList___IDX__" data-cm-field="cm_Barangay_Town" class="form-control" placeholder="Select or type barangay..." autocomplete="off">
            <datalist id="brgyCmList___IDX__"></datalist>
        </div>
        <div class="col-md-1">
            <label class="form-label">Zip Code</label>
            <input data-cm-field="cm_Zip_Code" class="form-control" placeholder="0000">
        </div>
    </div>

    <!-- Financial Info -->
    <div class="cf-section-title" style="margin-top:1.25rem"><i class="fa fa-coins"></i> Financial Information</div>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Income Source</label>
            <input data-cm-field="cm_Income_Source" class="form-control" placeholder="e.g. Employment">
        </div>
        <div class="col-md-4">
            <label class="form-label">Other Income Source</label>
            <input data-cm-field="cm_Other_Income_Source" class="form-control" placeholder="Other sources">
        </div>
        <div class="col-md-4">
            <label class="form-label">Monthly Income</label>
            <input data-cm-field="cm_Montly_Income" type="number" step="0.01" class="form-control" placeholder="0.00">
        </div>
        <div class="col-md-6">
            <label class="form-label">Business Name</label>
            <input data-cm-field="cm_Business_Name" class="form-control" placeholder="Business Name">
        </div>
        <div class="col-md-6">
            <label class="form-label">Business Address</label>
            <input data-cm-field="cm_Business_Address" class="form-control" placeholder="Business Address">
        </div>
        <div class="col-md-6">
            <label class="form-label">Primary Bank</label>
            <input data-cm-field="cm_Primary_Bank" class="form-control" placeholder="e.g. BDO, BPI">
        </div>
        <div class="col-md-6">
            <label class="form-label">Name of Lending Institution</label>
            <input data-cm-field="cm_Name_Of_Lending" class="form-control" placeholder="Lending institution name">
        </div>
    </div>

    <!-- Relationship -->
    <div class="cf-section-title" style="margin-top:1.25rem"><i class="fa fa-handshake"></i> Relationship to Client</div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Relationship</label>
            <input data-cm-field="cm_Relationship" class="form-control" placeholder="e.g. Friend, Sibling, Colleague">
        </div>
        <div class="col-md-6">
            <label class="form-label">Acquaintance Duration (years)</label>
            <input data-cm-field="cm_Acquaintance_Duration" type="number" step="0.1" min="0" class="form-control" placeholder="0.0">
        </div>
    </div>
</div>
            </template>

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

            // Province data keyed by regCode for JS filtering
            var PROVINCE_DATA = <?php
                $provByRegion = [];
                if ($provData && isset($provData['RECORDS'])) {
                    foreach ($provData['RECORDS'] as $p) {
                        $provByRegion[$p['regCode']][] = [
                            'code' => $p['provCode'],
                            'desc' => $p['provDesc'],
                        ];
                    }
                }
                echo json_encode($provByRegion);
            ?>;

            // City/mun data keyed by provCode for JS filtering
            var CITYMUN_DATA = <?php
                $cityByProv = [];
                if ($citymunData && isset($citymunData['RECORDS'])) {
                    foreach ($citymunData['RECORDS'] as $c) {
                        $cityByProv[$c['provCode']][] = [
                            'code' => $c['citymunCode'],
                            'desc' => $c['citymunDesc'],
                        ];
                    }
                }
                echo json_encode($cityByProv);
            ?>;

            // Barangay data keyed by citymunCode for JS filtering
            var BRGY_DATA = <?php
                $brgyByCity = [];
                if ($brgyData && isset($brgyData['RECORDS'])) {
                    foreach ($brgyData['RECORDS'] as $b) {
                        $brgyByCity[$b['citymunCode']][] = $b['brgyDesc'];
                    }
                }
                echo json_encode($brgyByCity);
            ?>;

            // Branch map: branchName -> Branch_ID (for datalist selection)
            var BRANCH_MAP = <?php
                $branchMap = [];
                $bq = mysqli_query($conn, "SELECT Branch_ID, Branch_Name FROM tbl_branch WHERE Is_Active = 1 ORDER BY Branch_Name");
                if ($bq) {
                    while ($b = mysqli_fetch_assoc($bq)) {
                        $branchMap[$b['Branch_Name']] = $b['Branch_ID'];
                    }
                }
                echo json_encode($branchMap);
            ?>;

            // Region lookup: regDesc → regCode (needed because input value = text desc)
            var REGION_DATA = <?php
                $regionDescToCode = [];
                if ($regData && isset($regData['RECORDS'])) {
                    foreach ($regData['RECORDS'] as $r) {
                        $regionDescToCode[$r['regDesc']] = $r['regCode'];
                    }
                }
                echo json_encode($regionDescToCode);
            ?>;

            // Build provDesc → provCode lookup
            var PROV_CODE_MAP = {};
            Object.keys(PROVINCE_DATA).forEach(function(regCode) {
                PROVINCE_DATA[regCode].forEach(function(p) {
                    PROV_CODE_MAP[p.desc] = p.code;
                });
            });

            // Build cityDesc → citymunCode lookup
            var CITY_CODE_MAP = {};
            Object.keys(CITYMUN_DATA).forEach(function(provCode) {
                CITYMUN_DATA[provCode].forEach(function(c) {
                    CITY_CODE_MAP[c.desc] = c.code;
                });
            });

            function populateDatalist(datalistId, items) {
                var dl = document.getElementById(datalistId);
                if (!dl) return;
                dl.innerHTML = '';
                items.forEach(function(item) {
                    var opt = document.createElement('option');
                    opt.value = item;
                    dl.appendChild(opt);
                });
            }

            function bindProvinceDropdown(regionInputId, provinceInputId, provinceDatalistId) {
                var regionInput   = document.getElementById(regionInputId);
                var provinceInput = document.getElementById(provinceInputId);
                if (!regionInput || !provinceInput) return;

                function populate() {
                    var regCode = REGION_DATA[regionInput.value];
                    provinceInput.value = '';
                    provinceInput.dispatchEvent(new Event('change'));
                    populateDatalist(provinceDatalistId,
                        (regCode && PROVINCE_DATA[regCode])
                            ? PROVINCE_DATA[regCode].map(function(p) { return p.desc; })
                            : []
                    );
                }

                regionInput.addEventListener('change', populate);
            }

            function bindCityDropdown(provinceInputId, cityInputId, cityDatalistId) {
                var provinceInput = document.getElementById(provinceInputId);
                var cityInput     = document.getElementById(cityInputId);
                if (!provinceInput || !cityInput) return;

                function populate() {
                    var provCode = PROV_CODE_MAP[provinceInput.value];
                    cityInput.value = '';
                    cityInput.dispatchEvent(new Event('change'));
                    populateDatalist(cityDatalistId,
                        (provCode && CITYMUN_DATA[provCode])
                            ? CITYMUN_DATA[provCode].map(function(c) { return c.desc; })
                            : []
                    );
                }

                provinceInput.addEventListener('change', populate);
            }

            function bindBarangayDropdown(cityInputId, brgyInputId, brgyDatalistId) {
                var cityInput = document.getElementById(cityInputId);
                var brgyInput = document.getElementById(brgyInputId);
                if (!cityInput || !brgyInput) return;

                function populate() {
                    var cityCode = CITY_CODE_MAP[cityInput.value];
                    brgyInput.value = '';
                    populateDatalist(brgyDatalistId,
                        (cityCode && BRGY_DATA[cityCode]) ? BRGY_DATA[cityCode] : []
                    );
                }

                cityInput.addEventListener('change', populate);
            }

            function bindPobDropdowns(provInputId, cityInputId, provDatalistId, cityDatalistId, hiddenId) {
                var provInput  = document.getElementById(provInputId);
                var cityInput  = document.getElementById(cityInputId);
                var hiddenEl   = document.getElementById(hiddenId);
                if (!provInput || !cityInput || !hiddenEl) return;

                // Populate province datalist with all provinces sorted alphabetically
                var allProvinces = [];
                Object.keys(PROVINCE_DATA).forEach(function(regCode) {
                    PROVINCE_DATA[regCode].forEach(function(p) { allProvinces.push(p.desc); });
                });
                allProvinces.sort();
                populateDatalist(provDatalistId, allProvinces);

                function updateHidden() {
                    var prov = provInput.value;
                    var city = cityInput.value;
                    hiddenEl.value = (city && prov) ? city + ', ' + prov : (prov || city || '');
                }

                function populateCities() {
                    var provCode = PROV_CODE_MAP[provInput.value];
                    cityInput.value = '';
                    populateDatalist(cityDatalistId,
                        (provCode && CITYMUN_DATA[provCode])
                            ? CITYMUN_DATA[provCode].map(function(c) { return c.desc; })
                            : []
                    );
                    updateHidden();
                }

                provInput.addEventListener('change', populateCities);
                provInput.addEventListener('input',  function(){ if (PROV_CODE_MAP[provInput.value]) populateCities(); });
                cityInput.addEventListener('change', updateHidden);
                cityInput.addEventListener('input',  updateHidden);
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Client form dropdowns
                bindProvinceDropdown('regionClient', 'provinceClient', 'provClientList');
                bindCityDropdown('provinceClient', 'cityClient', 'cityClientList');
                bindBarangayDropdown('cityClient', 'brgyClient', 'brgyClientList');
                bindPobDropdowns('pobProvClient', 'pobCityClient', 'pobProvClientList', 'pobCityClientList', 'pobHiddenClient');

                // Wire branch input to hidden Branch_ID
                var branchInput  = document.getElementById('branchInput');
                var branchHidden = document.getElementById('branchIdHidden');
                if (branchInput && branchHidden) {
                    branchInput.addEventListener('change', function(){ branchHidden.value = BRANCH_MAP[branchInput.value] || ''; });
                    branchInput.addEventListener('input',  function(){ if (!branchInput.value) branchHidden.value = ''; });
                }
            });

            // ── Age calc ────────────────────────────────────────────────────
            function calcAge(dobStr) {
                if (!dobStr) return '';
                var today = new Date(), dob = new Date(dobStr);
                if (isNaN(dob)) return '';
                var age = today.getFullYear() - dob.getFullYear();
                var m = today.getMonth() - dob.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
                return age >= 0 ? age : '';
            }
            function bindDobAge(dobId, ageId) {
                var dob = document.getElementById(dobId);
                var age = document.getElementById(ageId);
                if (!dob || !age) return;
                age.readOnly = true;
                dob.addEventListener('change', function(){ age.value = calcAge(dob.value); });
                if (dob.value) age.value = calcAge(dob.value);
            }

            // ── Mobile formatter ─────────────────────────────────────────────
            function formatMobileValue(val) {
                var digits = val.replace(/\D/g,'').slice(0,11);
                if (digits.length <= 4) return digits;
                if (digits.length <= 7) return digits.slice(0,4) + '-' + digits.slice(4);
                return digits.slice(0,4) + '-' + digits.slice(4,7) + '-' + digits.slice(7);
            }
            function bindMobileInput(el) {
                if (!el) return;
                el.addEventListener('input', function(){
                    el.value = formatMobileValue(el.value);
                });
                if (el.value) el.value = formatMobileValue(el.value);
            }

            document.addEventListener('DOMContentLoaded', function(){
                bindDobAge('dobClient', 'ageClient');
                bindDobAge('dobSpouse', 'ageSpouse');
                bindMobileInput(document.getElementById('mobileClient'));
            });

            // ── Co-maker panel factory ───────────────────────────────────────
            var CM_TEMPLATE_HTML = '';
            document.addEventListener('DOMContentLoaded', function(){
                var tpl = document.getElementById('comakerPanelTpl');
                if (tpl) CM_TEMPLATE_HTML = tpl.innerHTML;
            });

            function createComakerPanel(idx) {
                var html = CM_TEMPLATE_HTML.replace(/__IDX__/g, idx);
                var wrap = document.createElement('div');
                wrap.innerHTML = html;
                return wrap.firstElementChild;
            }

            function renderComakerPanels(containerId, count) {
                var container = document.getElementById(containerId);
                if (!container) return;
                container.innerHTML = '';
                for (var i = 1; i <= count; i++) {
                    var panel = createComakerPanel(i);
                    container.appendChild(panel);
                    // Bind DOB→age using panel-scoped query to avoid ID conflicts between containers
                    (function(p){
                        var dobEl = p.querySelector('[data-cm-field="cm_Date_Of_Birth"]');
                        var ageEl = p.querySelector('[data-cm-field="cm_Age"]');
                        if (dobEl && ageEl) {
                            ageEl.readOnly = true;
                            function recalcAge(){ ageEl.value = calcAge(dobEl.value); }
                            dobEl.addEventListener('change', recalcAge);
                            dobEl.addEventListener('input',  recalcAge);
                            if (dobEl.value) recalcAge();
                        }
                    })(panel);
                    bindMobileInput(document.getElementById('mobileCm_' + i));
                    bindProvinceDropdown('regionCm_' + i, 'provinceCm_' + i, 'provCmList_' + i);
                    bindCityDropdown('provinceCm_' + i, 'cityCm_' + i, 'cityCmList_' + i);
                    bindBarangayDropdown('cityCm_' + i, 'brgyCm_' + i, 'brgyCmList_' + i);
                    bindPobDropdowns('pobProvCm_' + i, 'pobCityCm_' + i, 'pobProvCmList_' + i, 'pobCityCmList_' + i, 'pobHiddenCm_' + i);
                    (function(idx, p){
                        var clearBtn = p.querySelector('.comaker-clear-btn');
                        if (clearBtn) {
                            clearBtn.addEventListener('click', function(){
                                p.querySelectorAll('input:not([readonly])').forEach(function(el){ el.value = ''; });
                                var ageEl = document.getElementById('ageCm_' + idx);
                                if (ageEl) ageEl.value = '';
                            });
                        }
                    })(i, panel);
                }
            }

            // ── Extract FormData from a single panel ─────────────────────────
            function buildCmFormData(panel, clientId) {
                var fd = new FormData();
                fd.append('save_comaker', '1');
                fd.append('cm_Client_ID', clientId || '');
                panel.querySelectorAll('[data-cm-field]').forEach(function(el){
                    fd.append(el.getAttribute('data-cm-field'), el.value || '');
                });
                return fd;
            }
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

        // ── Tab-based button visibility ────────────────────────────────────────
        var btnSaveAll       = document.getElementById('btnSaveAll');
        var btnSaveExisting2 = document.getElementById('btnSaveExistingComakers');
        document.querySelectorAll('#mainTabs .nav-link').forEach(function(link){
            link.addEventListener('shown.bs.tab', function(){
                var target = this.getAttribute('href') || this.dataset.bsTarget || '';
                var isExisting = target === '#tab-existing';
                if (btnSaveAll)       btnSaveAll.style.display       = isExisting ? 'none' : '';
                if (btnSaveExisting2) btnSaveExisting2.style.display = isExisting ? ''     : 'none';
            });
        });

        // ── Co-maker count selector ──────────────────────────────────────────
        var comakerCountSel = document.getElementById('comakerCountSelect');
        if (comakerCountSel) {
            function refreshComakerPanels() {
                var n = parseInt(comakerCountSel.value) || 0;
                renderComakerPanels('comakerPanelsContainer', n);
            }
            comakerCountSel.addEventListener('change', refreshComakerPanels);
            comakerCountSel.addEventListener('input', refreshComakerPanels);
            // Render initial panels (value is "None (skip)" → 0 panels)
            refreshComakerPanels();
        }

        // ── Existing client co-maker count selector ──────────────────────────
        var existingCountSel = document.getElementById('existingComakerCount');
        if (existingCountSel) {
            existingCountSel.addEventListener('change', function(){
                var n = parseInt(existingCountSel.value) || 1;
                renderComakerPanels('existingComakerPanels', n);
            });
            existingCountSel.addEventListener('input', function(){
                var n = parseInt(existingCountSel.value) || 1;
                renderComakerPanels('existingComakerPanels', n);
            });
        }

        // ── Existing client search ───────────────────────────────────────────
        var searchInput    = document.getElementById('existingClientSearch');
        var searchBtn      = document.getElementById('btnSearchExistingClient');
        var resultsList    = document.getElementById('existingClientResultsList');
        var selectedCard   = document.getElementById('selectedClientCard');
        var selectedName   = document.getElementById('selectedClientName');
        var selectedIdDisp = document.getElementById('selectedClientIdDisplay');
        var existingIdHid  = document.getElementById('existingClientIdHidden');
        var existingSetup  = document.getElementById('existingCmSetup');

        function doClientSearch() {
            var q = searchInput ? searchInput.value.trim() : '';
            if (!q || q.length < 2) return;
            resultsList.innerHTML = '<div style="padding:.5rem;color:rgba(255,255,255,.4);font-size:.8rem;"><i class="fa fa-spinner fa-spin me-1"></i> Searching…</div>';
            fetch(window.location.href + '?action=search_client&q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r){ return r.json(); })
            .then(function(data){
                resultsList.innerHTML = '';
                if (!data.length) {
                    resultsList.innerHTML = '<div style="padding:.5rem;color:rgba(255,255,255,.35);font-size:.8rem;">No clients found.</div>';
                    return;
                }
                data.forEach(function(row){
                    var item = document.createElement('div');
                    item.className = 'cm-search-result-item';
                    item.innerHTML = '<strong>' + escHtml(row.Full_Name) + '</strong> <span style="opacity:.55;font-size:.8rem;">' + escHtml(row.Client_ID) + '</span>';
                    item.addEventListener('click', function(){
                        selectExistingClient(row.Client_ID, row.Full_Name);
                    });
                    resultsList.appendChild(item);
                });
            })
            .catch(function(){ resultsList.innerHTML = '<div style="color:#f87171;padding:.5rem;font-size:.8rem;">Search failed.</div>'; });
        }

        if (searchBtn)   searchBtn.addEventListener('click', doClientSearch);
        if (searchInput) searchInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); doClientSearch(); } });

        function selectExistingClient(cid, name) {
            if (existingIdHid)  existingIdHid.value = cid;
            if (selectedName)   selectedName.textContent  = name;
            if (selectedIdDisp) selectedIdDisp.textContent = 'Client ID: ' + cid;
            if (selectedCard)   selectedCard.style.display = '';
            if (existingSetup)  existingSetup.style.display = '';
            if (resultsList)    resultsList.innerHTML = '';
            // Render co-maker panels for existing client
            var n = parseInt((existingCountSel ? existingCountSel.value : '1')) || 1;
            renderComakerPanels('existingComakerPanels', n);
        }

        // ── Save existing client co-makers ───────────────────────────────────
        var btnSaveExisting = document.getElementById('btnSaveExistingComakers');
        if (btnSaveExisting) {
            btnSaveExisting.addEventListener('click', async function(){
                var cid = existingIdHid ? existingIdHid.value.trim() : '';
                if (!cid) {
                    Swal.fire({ icon:'warning', title:'No Client Selected', text:'Please search and select a client first.', confirmButtonText:'OK' });
                    return;
                }
                var panels = document.querySelectorAll('#existingComakerPanels .comaker-panel');
                if (!panels.length) return;
                // validate at least first + last name in each panel
                var valid = true;
                panels.forEach(function(panel, i){
                    var ln = panel.querySelector('[data-cm-field="cm_Last_Name"]');
                    var fn = panel.querySelector('[data-cm-field="cm_First_Name"]');
                    if (!ln || !ln.value.trim() || !fn || !fn.value.trim()) {
                        Swal.fire({ icon:'error', title:'Missing Name', text:'Co-maker ' + (i+1) + ': First and Last name are required.', confirmButtonText:'OK' });
                        valid = false;
                    }
                });
                if (!valid) return;

                btnSaveExisting.disabled = true;
                btnSaveExisting.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Saving…';

                var saved = 0, errors = [];
                for (var i = 0; i < panels.length; i++) {
                    var fd = buildCmFormData(panels[i], cid);
                    try {
                        var res = await postAjax(fd);
                        if (res && res.status === 'success') { saved++; }
                        else { errors.push('Co-maker ' + (i+1) + ': ' + (res ? res.message : 'Unknown error')); }
                    } catch(err) { errors.push('Co-maker ' + (i+1) + ': ' + err.message); }
                }

                btnSaveExisting.disabled = false;
                btnSaveExisting.innerHTML = '<i class="fa fa-save me-1"></i> Save Co-maker(s)';

                if (errors.length === 0) {
                    Swal.fire({ icon:'success', title:'Saved', html: saved + ' co-maker(s) added to <b>' + escHtml(cid) + '</b>.', confirmButtonText:'OK' });
                    renderComakerPanels('existingComakerPanels', parseInt(existingCountSel ? existingCountSel.value : '1') || 1);
                } else {
                    var msg = saved > 0 ? saved + ' saved. Errors:<br>' : 'Errors:<br>';
                    msg += errors.map(escHtml).join('<br>');
                    Swal.fire({ icon:'warning', title:'Partial Save', html: msg, confirmButtonText:'OK' });
                }
            });
        }

        // ── Combined Save (new client + co-makers) ───────────────────────────
        var btnSaveAll = document.getElementById('btnSaveAll');
        var clientForm = document.getElementById('clientForm');
        if (clientForm) clientForm.addEventListener('submit', function(e){ e.preventDefault(); });

        function digitsOnly(str){ return str ? str.replace(/\D/g,'') : ''; }

        async function postAjax(formData){
            var res = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            var text = await res.text();
            try { return JSON.parse(text); }
            catch(e){ throw new Error('Invalid JSON: ' + text.substring(0, 300)); }
        }

        function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

        function resetSaveBtn(){
            if (btnSaveAll) {
                btnSaveAll.disabled = false;
                btnSaveAll.innerHTML = '<i class="fa fa-save me-1"></i> Save Registration';
            }
        }

        function fullReset(){
            if (clientForm) clientForm.reset();
            var avatarWrap = document.getElementById('avatarWrap');
            if (avatarWrap) avatarWrap.innerHTML = '<span class="cf-avatar-placeholder"><i class="fa fa-user"></i></span>';
            // Reset co-maker panels to 1
            var sel = document.getElementById('comakerCountSelect');
            if (sel) { sel.value = '1'; renderComakerPanels('comakerPanelsContainer', 1); }
            // Return to client tab
            var tabClientLnk = document.getElementById('tab-client-lnk');
            if (tabClientLnk) tabClientLnk.click();
        }

        if (btnSaveAll) {
            btnSaveAll.addEventListener('click', async function(){
                if (!clientForm.checkValidity()){ clientForm.reportValidity(); return; }
                var mobileEl = document.getElementById('mobileClient');
                if (mobileEl && mobileEl.value) {
                    if (digitsOnly(mobileEl.value).length !== 11 || !mobileEl.value.startsWith('09')){
                        Swal.fire({ icon:'error', title:'Invalid Mobile', text:'Client mobile must be 11 digits starting with 09.', confirmButtonText:'OK' });
                        return;
                    }
                }

                btnSaveAll.disabled = true;
                btnSaveAll.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Saving…';

                // Step 1: Save client
                var clientResult, clientId = '';
                try {
                    clientResult = await postAjax(new FormData(clientForm));
                } catch(err) {
                    Swal.fire({ icon:'error', title:'Network Error', text:err.message, confirmButtonText:'OK' });
                    resetSaveBtn(); return;
                }
                if (!clientResult || clientResult.status !== 'success'){
                    Swal.fire({ icon:'error', title:'Client Save Failed', text: clientResult ? clientResult.message : 'Unknown error', confirmButtonText:'OK' });
                    resetSaveBtn(); return;
                }
                var idMatch = clientResult.message.match(/CL-[A-Z0-9]{5}/);
                clientId = idMatch ? idMatch[0] : '';

                // Step 2: Save co-makers (all panels that have name filled)
                var cmPanels = document.querySelectorAll('#comakerPanelsContainer .comaker-panel');
                var cmSaved = 0, cmErrors = [];
                for (var i = 0; i < cmPanels.length; i++) {
                    var ln = cmPanels[i].querySelector('[data-cm-field="cm_Last_Name"]');
                    var fn = cmPanels[i].querySelector('[data-cm-field="cm_First_Name"]');
                    if (!ln || !ln.value.trim()) continue; // skip empty panels
                    // Validate mobile if filled
                    var mob = cmPanels[i].querySelector('[data-cm-field="cm_Mobile_No"]');
                    if (mob && mob.value && (digitsOnly(mob.value).length !== 11 || !mob.value.startsWith('09'))) {
                        cmErrors.push('Co-maker ' + (i+1) + ': invalid mobile number.');
                        continue;
                    }
                    try {
                        var fd = buildCmFormData(cmPanels[i], clientId);
                        var res = await postAjax(fd);
                        if (res && res.status === 'success') { cmSaved++; }
                        else { cmErrors.push('Co-maker ' + (i+1) + ': ' + (res ? res.message : 'Unknown')); }
                    } catch(err) { cmErrors.push('Co-maker ' + (i+1) + ': ' + err.message); }
                }

                resetSaveBtn();

                if (cmErrors.length) {
                    Swal.fire({ icon:'warning', title:'Partial Save',
                        html:'Client saved &mdash; <b>' + escHtml(clientId) + '</b>.<br>' + cmSaved + ' co-maker(s) saved.<br>Errors:<br>' + cmErrors.map(escHtml).join('<br>'),
                        confirmButtonText:'OK' });
                } else if (cmSaved > 0) {
                    Swal.fire({ icon:'success', title:'Registration Complete',
                        html:'Client and ' + cmSaved + ' co-maker(s) saved.<br><b>Client ID:</b> ' + escHtml(clientId),
                        confirmButtonText:'OK' });
                } else {
                    Swal.fire({ icon:'success', title:'Client Saved',
                        html:'Client registered successfully.<br><b>Client ID:</b> ' + escHtml(clientId),
                        confirmButtonText:'OK' });
                }
                fullReset();
            });
        }
    });
    </script>
</body>

</html>