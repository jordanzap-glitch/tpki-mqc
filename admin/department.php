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


            <!-- Department List Start -->
            <?php require_once __DIR__ . '/../db/dbcon.php'; ?>
            <div class="container-fluid pt-4 px-4">
                <div class="bg-secondary rounded p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h6 class="mb-0">Departments</h6>
                    </div>
                    <div class="table-responsive">
                        <?php
                        $q = "SELECT Department_id, Department_Code, Department_Description FROM tbl_dept ORDER BY Department_id";
                        $res = mysqli_query($conn, $q);
                        ?>
                        <table id="deptTable" class="table table-striped table-bordered mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th><input class="form-check-input" type="checkbox"></th>
                                    <th>Department ID</th>
                                    <th>Code</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($res && mysqli_num_rows($res) > 0) {
                                    while ($r = mysqli_fetch_assoc($res)) {
                                        $did = htmlspecialchars($r['Department_id']);
                                        $code = htmlspecialchars($r['Department_Code']);
                                        $desc = htmlspecialchars($r['Department_Description']);
                                        echo "<tr>";
                                        echo "<td><input class=\"form-check-input\" type=\"checkbox\"></td>";
                                        echo "<td>$did</td>";
                                        echo "<td>$code</td>";
                                        echo "<td>$desc</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan=\"4\" class=\"text-center\">No departments found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Department List End -->


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
    $(document).ready(function() {
        $('#deptTable').DataTable({
            paging: true,
            searching: true,
            info: true,
            ordering: true
        });
    });
    </script>
</body>

</html>