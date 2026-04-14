<?php 
error_reporting (E_ALL);

require_once __DIR__ . '/../db/dbcon.php';





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
        <?php include "includes/spinner.php"; ?>
        <!-- Spinner End -->


        <!-- Sidebar Start -->
        <?php include "includes/sidebar.php"; ?>
        <!-- Sidebar End -->


        <!-- Content Start -->
        <div class="content">
            <!-- Navbar Start -->
           <?php include "includes/navbar.php"; ?>
            <!-- Navbar End -->


            <!-- User Information Start -->
            <div class="container-fluid pt-4 px-4">
                <div class="bg-secondary text-center rounded p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h6 class="mb-0">User Information</h6>
                    </div>
                    <div class="table-responsive">
                        <?php

                        $query = "SELECT u.id, u.User_ID, u.Last_Name, u.First_Name, u.Middle_Name, u.Gender, u.Email_Address, u.User_Type_ID, t.User_Type_Code FROM tbl_user u LEFT JOIN tbl_user_type t ON u.User_Type_ID = t.id";
                        $result = mysqli_query($conn, $query);
                        ?>
                        <table id="myTable" class="table text-start align-middle table-bordered table-hover mb-0">
                                                        <thead>
                                                                <tr class="text-white">
                                                                        <th scope="col"><input class="form-check-input" type="checkbox"></th>
                                                                        <th scope="col">User ID</th>
                                                                        <th scope="col">Last Name</th>
                                                                        <th scope="col">First Name</th>
                                                                        <th scope="col">Middle Name</th>
                                                                        <th scope="col">Email Address</th>
                                                                        <th scope="col">User Type</th>
                                                                        <th scope="col">Action</th>
                                                                    </tr>
                                                        </thead>
                                                        <tbody>
                                                                <?php
                                                                if ($result && mysqli_num_rows($result) > 0) {
                                                                        while ($row = mysqli_fetch_assoc($result)) {
                                                                            $id = (int) $row['id'];
                                                                            $user_id = !empty($row['User_ID']) ? htmlspecialchars($row['User_ID']) : $id;
                                                                            $last = htmlspecialchars($row['Last_Name']);
                                                                            $first = htmlspecialchars($row['First_Name']);
                                                                            $middle = htmlspecialchars($row['Middle_Name']);
                                                                            $gender = htmlspecialchars($row['Gender']);
                                                                            $email = htmlspecialchars($row['Email_Address']);
                                                                                $type = htmlspecialchars($row['User_Type_Code'] ?? $row['User_Type_ID']);
                                                                            $last_attr = htmlspecialchars($row['Last_Name'], ENT_QUOTES);
                                                                            $first_attr = htmlspecialchars($row['First_Name'], ENT_QUOTES);
                                                                            $middle_attr = htmlspecialchars($row['Middle_Name'], ENT_QUOTES);
                                                                            $gender_attr = htmlspecialchars($row['Gender'], ENT_QUOTES);
                                                                            $email_attr = htmlspecialchars($row['Email_Address'], ENT_QUOTES);
                                                                            $type_attr = htmlspecialchars($row['User_Type_Code'] ?? $row['User_Type_ID'], ENT_QUOTES);
                                                                            $user_id_attr = htmlspecialchars($user_id, ENT_QUOTES);

                                                                                echo "<tr>";
                                                                                echo "<td><input class=\"form-check-input\" type=\"checkbox\"></td>";
                                                                                echo "<td>$user_id</td>";
                                                                                echo "<td>$last</td>";
                                                                                echo "<td>$first</td>";
                                                                                echo "<td>$middle</td>";
                                                                                echo "<td>$email</td>";
                                                                                echo "<td>$type</td>";
                                                                                echo "<td>";
                                                                                echo "<button type=\"button\" class=\"btn btn-sm btn-primary view-btn\" data-bs-toggle=\"modal\" data-bs-target=\"#viewModal\" data-id=\"$id\" data-userid=\"$user_id_attr\" data-last=\"$last_attr\" data-first=\"$first_attr\" data-middle=\"$middle_attr\" data-gender=\"$gender_attr\" data-email=\"$email_attr\" data-type=\"$type_attr\">View</button>";
                                                                                echo "</td>";
                                                                                echo "</tr>";
                                                                        }
                                                                } else {
                                                                    echo "<tr><td colspan=\"8\" class=\"text-center\">No users found</td></tr>";
                                                                }
                                                                ?>
                                                        </tbody>
                                                </table>
                                        </div>

                                        <!-- View Modal (Profile Style) -->
                                        <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-md">
                                                <div class="modal-content bg-dark text-white" style="background-color:#000; color:#fff;">
                                                    <div class="modal-header border-0">
                                                        <h5 class="modal-title" id="viewModalLabel">Profile</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="d-flex align-items-center">
                                                            <div id="viewAvatar" class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:88px;height:88px;font-size:28px;"></div>
                                                            <div class="ms-3">
                                                                <h5 id="viewFullName" class="mb-0"></h5>
                                                                <small id="viewUserType" class="text-muted"></small>
                                                                <div class="text-muted" id="viewUserIDSmall"></div>
                                                            </div>
                                                        </div>
                                                        <hr class="border-secondary">
                                                        <div class="row">
                                                            <div class="col-6 mb-2">
                                                                <div class="text-muted small">Email</div>
                                                                <div id="viewEmail" class="fw-semibold"></div>
                                                            </div>
                                                            <div class="col-6 mb-2">
                                                                <div class="text-muted small">Gender</div>
                                                                <div id="viewGender" class="fw-semibold"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                </div>
            </div>
            <!-- Recent Loan End -->


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
    // Populate the modal from data attributes when it's shown (profile layout)
    $('#viewModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var userid = button.data('userid') || '';
        var last = button.data('last') || '';
        var first = button.data('first') || '';
        var gender = button.data('gender') || '';
        var email = button.data('email') || '';
        var type = button.data('type') || '';
        var modal = $(this);

        var fullName = [first, last].filter(Boolean).join(' ');
        modal.find('#viewFullName').text(fullName || userid);
        modal.find('#viewUserType').text(type);
        modal.find('#viewUserIDSmall').text('User ID: ' + userid);

        modal.find('#viewEmail').text(email);
        modal.find('#viewGender').text(gender);

        // Build avatar initials
        var initials = '';
        if (first) initials += first.charAt(0).toUpperCase();
        if (last) initials += last.charAt(0).toUpperCase();
        if (!initials && userid) initials = userid.toString().slice(-2).toUpperCase();
        modal.find('#viewAvatar').text(initials);
    });

    $(document).ready(function() {
        $('#myTable').DataTable({
            searching: true,
            paging: true,
            info: true,
            ordering: true
        });
    });
    </script>
</body>

</html>