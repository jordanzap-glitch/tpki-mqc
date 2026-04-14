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
    /* Compact checkbox column */
    .table th:first-child, .table td:first-child {
        width: 36px;
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


            <!-- Employee List Start -->
            <?php require_once __DIR__ . '/../db/dbcon.php'; ?>
            <div class="container-fluid pt-4 px-4">
                <div class="bg-secondary rounded p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h6 class="mb-0">Employees</h6>
                    </div>
                    <div class="table-responsive">
                        <?php
                        $q = "SELECT e.Employee_ID, e.Last_Name, e.First_Name, e.Middle_Name, e.Gender, e.Birhtday, e.Civil_Status, e.Date_Hired, e.Branch_ID, b.Branch_Name, e.Position_ID, e.Department_ID, d.Department_Code, e.Mobile_No, e.Email_Address, e.House_Street_Bldng, e.Barangay_Town, e.City_Municipality, e.Province, e.SSS_No, e.PagIbig_No, e.PhilHealth_No, e.TIN_No, e.Emergency_Contact_Name, e.Emergency_Contact_No, e.Emergency_Contact_Relationship, e.Prof_Pic FROM tbl_emp_info e LEFT JOIN tbl_branch b ON e.Branch_ID = b.Branch_ID LEFT JOIN tbl_dept d ON e.Department_ID = d.Department_id ORDER BY e.Employee_ID";
                        $res = mysqli_query($conn, $q);
                        ?>
                        <table id="empTable" class="table table-striped table-bordered mb-0" style="width:100%">
                                                        <thead>
                                                                <tr>
                                                                        <th><input class="form-check-input" type="checkbox"></th>
                                                                        <th>Employee ID</th>
                                                                        <th>Last Name</th>
                                                                        <th>First Name</th>
                                                                        <th>Middle Name</th>
                                                                        <th>Gender</th>
                                                                        <th>Branch</th>
                                                                        <th>Department</th>
                                                                        <th>Action</th>
                                                                </tr>
                                                        </thead>
                            <tbody>
                                <?php
                                if ($res && mysqli_num_rows($res) > 0) {
                                    while ($r = mysqli_fetch_assoc($res)) {
                                                                                $eid = htmlspecialchars($r['Employee_ID']);
                                                                                $last = htmlspecialchars($r['Last_Name']);
                                                                                $first = htmlspecialchars($r['First_Name']);
                                                                                $middle = htmlspecialchars($r['Middle_Name']);
                                                                                $gender = htmlspecialchars($r['Gender']);
                                                                                $branch = htmlspecialchars($r['Branch_Name'] ?? $r['Branch_ID']);
                                                                                $dept = htmlspecialchars($r['Department_Code'] ?? $r['Department_ID']);

                                                                                // Additional fields
                                                                                $birthday = htmlspecialchars($r['Birhtday'] ?? '');
                                                                                $civil = htmlspecialchars($r['Civil_Status'] ?? '');
                                                                                $date_hired = htmlspecialchars($r['Date_Hired'] ?? '');
                                                                                $position = htmlspecialchars($r['Position_ID'] ?? '');
                                                                                $mobile = htmlspecialchars($r['Mobile_No'] ?? '');
                                                                                $email = htmlspecialchars($r['Email_Address'] ?? '');
                                                                                $house = htmlspecialchars($r['House_Street_Bldng'] ?? '');
                                                                                $barangay = htmlspecialchars($r['Barangay_Town'] ?? '');
                                                                                $city = htmlspecialchars($r['City_Municipality'] ?? '');
                                                                                $province = htmlspecialchars($r['Province'] ?? '');
                                                                                $sss = htmlspecialchars($r['SSS_No'] ?? '');
                                                                                $pagibig = htmlspecialchars($r['PagIbig_No'] ?? '');
                                                                                $phil = htmlspecialchars($r['PhilHealth_No'] ?? '');
                                                                                $tin = htmlspecialchars($r['TIN_No'] ?? '');
                                                                                $emer_name = htmlspecialchars($r['Emergency_Contact_Name'] ?? '');
                                                                                $emer_no = htmlspecialchars($r['Emergency_Contact_No'] ?? '');
                                                                                $emer_rel = htmlspecialchars($r['Emergency_Contact_Relationship'] ?? '');

                                                                                // Profile picture (base64) if available
                                                                                $pic_src = '';
                                                                                if (!empty($r['Prof_Pic'])) {
                                                                                    $pic_src = 'data:image/jpeg;base64,' . base64_encode($r['Prof_Pic']);
                                                                                }

                                                                                // Attributes (ENT_QUOTES for safe HTML attributes)
                                                                                $eid_attr = htmlspecialchars($r['Employee_ID'], ENT_QUOTES);
                                                                                $last_attr = htmlspecialchars($r['Last_Name'], ENT_QUOTES);
                                                                                $first_attr = htmlspecialchars($r['First_Name'], ENT_QUOTES);
                                                                                $middle_attr = htmlspecialchars($r['Middle_Name'], ENT_QUOTES);
                                                                                $gender_attr = htmlspecialchars($r['Gender'], ENT_QUOTES);
                                                                                $branch_attr = htmlspecialchars($r['Branch_Name'] ?? $r['Branch_ID'], ENT_QUOTES);
                                                                                $dept_attr = htmlspecialchars($r['Department_Code'] ?? $r['Department_ID'], ENT_QUOTES);
                                                                                $birthday_attr = htmlspecialchars($r['Birhtday'] ?? '', ENT_QUOTES);
                                                                                $civil_attr = htmlspecialchars($r['Civil_Status'] ?? '', ENT_QUOTES);
                                                                                $dateh_attr = htmlspecialchars($r['Date_Hired'] ?? '', ENT_QUOTES);
                                                                                $position_attr = htmlspecialchars($r['Position_ID'] ?? '', ENT_QUOTES);
                                                                                $mobile_attr = htmlspecialchars($r['Mobile_No'] ?? '', ENT_QUOTES);
                                                                                $email_attr = htmlspecialchars($r['Email_Address'] ?? '', ENT_QUOTES);
                                                                                $house_attr = htmlspecialchars($r['House_Street_Bldng'] ?? '', ENT_QUOTES);
                                                                                $barangay_attr = htmlspecialchars($r['Barangay_Town'] ?? '', ENT_QUOTES);
                                                                                $city_attr = htmlspecialchars($r['City_Municipality'] ?? '', ENT_QUOTES);
                                                                                $province_attr = htmlspecialchars($r['Province'] ?? '', ENT_QUOTES);
                                                                                $sss_attr = htmlspecialchars($r['SSS_No'] ?? '', ENT_QUOTES);
                                                                                $pagibig_attr = htmlspecialchars($r['PagIbig_No'] ?? '', ENT_QUOTES);
                                                                                $phil_attr = htmlspecialchars($r['PhilHealth_No'] ?? '', ENT_QUOTES);
                                                                                $tin_attr = htmlspecialchars($r['TIN_No'] ?? '', ENT_QUOTES);
                                                                                $emer_name_attr = htmlspecialchars($r['Emergency_Contact_Name'] ?? '', ENT_QUOTES);
                                                                                $emer_no_attr = htmlspecialchars($r['Emergency_Contact_No'] ?? '', ENT_QUOTES);
                                                                                $emer_rel_attr = htmlspecialchars($r['Emergency_Contact_Relationship'] ?? '', ENT_QUOTES);
                                                                                $pic_attr = htmlspecialchars($pic_src, ENT_QUOTES);

                                                                                echo "<tr>";
                                                                                echo "<td><input class=\"form-check-input\" type=\"checkbox\"></td>";
                                                                                echo "<td>$eid</td>";
                                                                                echo "<td>$last</td>";
                                                                                echo "<td>$first</td>";
                                                                                echo "<td>$middle</td>";
                                                                                echo "<td>$gender</td>";
                                                                                echo "<td>$branch</td>";
                                                                                echo "<td>$dept</td>";
                                                                                echo "<td>";
                                                                                echo "<button type=\"button\" class=\"btn btn-sm btn-primary view-emp\" data-bs-toggle=\"modal\" data-bs-target=\"#empModal\" data-eid=\"$eid_attr\" data-last=\"$last_attr\" data-first=\"$first_attr\" data-middle=\"$middle_attr\" data-gender=\"$gender_attr\" data-branch=\"$branch_attr\" data-dept=\"$dept_attr\" data-birthday=\"$birthday_attr\" data-civil=\"$civil_attr\" data-dateh=\"$dateh_attr\" data-position=\"$position_attr\" data-mobile=\"$mobile_attr\" data-email=\"$email_attr\" data-house=\"$house_attr\" data-barangay=\"$barangay_attr\" data-city=\"$city_attr\" data-province=\"$province_attr\" data-sss=\"$sss_attr\" data-pagibig=\"$pagibig_attr\" data-phil=\"$phil_attr\" data-tin=\"$tin_attr\" data-emer-name=\"$emer_name_attr\" data-emer-no=\"$emer_no_attr\" data-emer-rel=\"$emer_rel_attr\" data-pic=\"$pic_attr\">View</button>";
                                                                                echo "</td>";
                                                                                echo "</tr>";
                                    }
                                } else {
                                                                        echo "<tr><td colspan=\"9\" class=\"text-center\">No employees found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
                        <!-- Employee View Modal -->
                        <div class="modal fade" id="empModal" tabindex="-1" aria-labelledby="empModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content bg-dark text-white" style="background-color:#000; color:#fff;">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="empModalLabel">Employee Details</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-start">
                                        <div class="row">
                                            <div class="col-md-4 text-center mb-3">
                                                <img id="eProfPic" src="" alt="Profile pic" class="img-fluid rounded" style="max-height:200px; display:none;" />
                                            </div>
                                            <div class="col-md-8">
                                                <dl class="row mb-0">
                                                    <dt class="col-sm-4">Employee ID</dt>
                                                    <dd class="col-sm-8" id="eEID"></dd>

                                                    <dt class="col-sm-4">Last Name</dt>
                                                    <dd class="col-sm-8" id="eLast"></dd>

                                                    <dt class="col-sm-4">First Name</dt>
                                                    <dd class="col-sm-8" id="eFirst"></dd>

                                                    <dt class="col-sm-4">Middle Name</dt>
                                                    <dd class="col-sm-8" id="eMiddle"></dd>

                                                    <dt class="col-sm-4">Gender</dt>
                                                    <dd class="col-sm-8" id="eGender"></dd>

                                                    <dt class="col-sm-4">Birthday</dt>
                                                    <dd class="col-sm-8" id="eBirthday"></dd>

                                                    <dt class="col-sm-4">Civil Status</dt>
                                                    <dd class="col-sm-8" id="eCivil"></dd>

                                                    <dt class="col-sm-4">Date Hired</dt>
                                                    <dd class="col-sm-8" id="eDateHired"></dd>

                                                    <dt class="col-sm-4">Position</dt>
                                                    <dd class="col-sm-8" id="ePosition"></dd>

                                                    <dt class="col-sm-4">Branch</dt>
                                                    <dd class="col-sm-8" id="eBranch"></dd>

                                                    <dt class="col-sm-4">Department</dt>
                                                    <dd class="col-sm-8" id="eDept"></dd>

                                                    <dt class="col-sm-4">Mobile No</dt>
                                                    <dd class="col-sm-8" id="eMobile"></dd>

                                                    <dt class="col-sm-4">Email</dt>
                                                    <dd class="col-sm-8" id="eEmail"></dd>

                                                    <dt class="col-sm-4">House / Street</dt>
                                                    <dd class="col-sm-8" id="eHouse"></dd>

                                                    <dt class="col-sm-4">Barangay / Town</dt>
                                                    <dd class="col-sm-8" id="eBarangay"></dd>

                                                    <dt class="col-sm-4">City / Municipality</dt>
                                                    <dd class="col-sm-8" id="eCity"></dd>

                                                    <dt class="col-sm-4">Province</dt>
                                                    <dd class="col-sm-8" id="eProvince"></dd>

                                                    <dt class="col-sm-4">SSS No</dt>
                                                    <dd class="col-sm-8" id="eSSS"></dd>

                                                    <dt class="col-sm-4">Pag-IBIG No</dt>
                                                    <dd class="col-sm-8" id="ePagIbig"></dd>

                                                    <dt class="col-sm-4">PhilHealth No</dt>
                                                    <dd class="col-sm-8" id="ePhil"></dd>

                                                    <dt class="col-sm-4">TIN No</dt>
                                                    <dd class="col-sm-8" id="eTIN"></dd>

                                                    <dt class="col-sm-4">Emergency Contact</dt>
                                                    <dd class="col-sm-8" id="eEmerName"></dd>

                                                    <dt class="col-sm-4">Emergency No</dt>
                                                    <dd class="col-sm-8" id="eEmerNo"></dd>

                                                    <dt class="col-sm-4">Emergency Relationship</dt>
                                                    <dd class="col-sm-8" id="eEmerRel"></dd>
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
            <!-- Employee List End -->


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

    <!-- Template Javascript -->
    <script src="../js/main.js"></script>
    <script>
    // Populate employee view modal and initialize DataTable
    $(document).ready(function() {
        $(document).on('show.bs.modal', '#empModal', function (event) {
            var button = $(event.relatedTarget);
            $('#eEID').text(button.data('eid'));
            $('#eLast').text(button.data('last'));
            $('#eFirst').text(button.data('first'));
            $('#eMiddle').text(button.data('middle'));
            $('#eGender').text(button.data('gender'));
            $('#eBirthday').text(button.data('birthday'));
            $('#eCivil').text(button.data('civil'));
            $('#eDateHired').text(button.data('dateh'));
            $('#ePosition').text(button.data('position'));
            $('#eBranch').text(button.data('branch'));
            $('#eDept').text(button.data('dept'));
            $('#eMobile').text(button.data('mobile'));
            $('#eEmail').text(button.data('email'));
            $('#eHouse').text(button.data('house'));
            $('#eBarangay').text(button.data('barangay'));
            $('#eCity').text(button.data('city'));
            $('#eProvince').text(button.data('province'));
            $('#eSSS').text(button.data('sss'));
            $('#ePagIbig').text(button.data('pagibig'));
            $('#ePhil').text(button.data('phil'));
            $('#eTIN').text(button.data('tin'));
            $('#eEmerName').text(button.data('emerName'));
            $('#eEmerNo').text(button.data('emerNo'));
            $('#eEmerRel').text(button.data('emerRel'));

            var pic = button.data('pic');
            if (pic) {
                $('#eProfPic').attr('src', pic).show();
            } else {
                $('#eProfPic').attr('src', '').hide();
            }
        });

        $('#empTable').DataTable({
            paging: true,
            searching: true,
            info: true,
            ordering: true
        });
    });
    </script>
</body>

</html>