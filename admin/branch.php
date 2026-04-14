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


            <!-- Branch List Start -->
            <?php require_once __DIR__ . '/../db/dbcon.php'; ?>
            <div class="container-fluid pt-4 px-4">
                <div class="bg-secondary rounded p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h6 class="mb-0">Branches</h6>
                    </div>
                    <div class="table-responsive">
                        <?php
                        $q = "SELECT Branch_ID, Branch_Name, Contact_No, Street_Building_No, Barangay_Town, City_Municipality, Province, Is_Active FROM tbl_branch ORDER BY Branch_ID";
                        $res = mysqli_query($conn, $q);
                        ?>
                                                <table id="branchTable" class="table table-striped table-bordered mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th><input class="form-check-input" type="checkbox"></th>
                                    <th>Branch ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Street/Building No</th>
                                    <th>Barangay/Town</th>
                                    <th>City/Municipality</th>
                                    <th>Province</th>
                                                                        <th>Active</th>
                                                                        <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($res && mysqli_num_rows($res) > 0) {
                                    while ($r = mysqli_fetch_assoc($res)) {
                                        $bid = htmlspecialchars($r['Branch_ID']);
                                        $name = htmlspecialchars($r['Branch_Name']);
                                        $contact = htmlspecialchars($r['Contact_No']);
                                        $street = htmlspecialchars($r['Street_Building_No']);
                                        $barangay = htmlspecialchars($r['Barangay_Town']);
                                        $city = htmlspecialchars($r['City_Municipality']);
                                        $province = htmlspecialchars($r['Province']);
                                                                                $active = $r['Is_Active'];
                                                                                $active_badge = ($active == '1' || $active === 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                                                                                $active_attr = htmlspecialchars($active, ENT_QUOTES);
                                                                                $bid_attr = htmlspecialchars($r['Branch_ID'], ENT_QUOTES);
                                                                                $name_attr = htmlspecialchars($r['Branch_Name'], ENT_QUOTES);
                                                                                $contact_attr = htmlspecialchars($r['Contact_No'], ENT_QUOTES);
                                                                                $street_attr = htmlspecialchars($r['Street_Building_No'], ENT_QUOTES);
                                                                                $barangay_attr = htmlspecialchars($r['Barangay_Town'], ENT_QUOTES);
                                                                                $city_attr = htmlspecialchars($r['City_Municipality'], ENT_QUOTES);
                                                                                $province_attr = htmlspecialchars($r['Province'], ENT_QUOTES);
                                        echo "<tr>";
                                                                                echo "<td><input class=\"form-check-input\" type=\"checkbox\"></td>";
                                                                                echo "<td>$bid</td>";
                                                                                echo "<td>$name</td>";
                                                                                echo "<td>$contact</td>";
                                                                                echo "<td>$street</td>";
                                                                                echo "<td>$barangay</td>";
                                                                                echo "<td>$city</td>";
                                                                                echo "<td>$province</td>";
                                                                                echo "<td>$active_badge</td>";
                                                                                echo "<td>";
                                                                                echo "<button type=\"button\" class=\"btn btn-sm btn-primary view-branch\" data-bs-toggle=\"modal\" data-bs-target=\"#branchModal\" data-branchid=\"$bid_attr\" data-name=\"$name_attr\" data-contact=\"$contact_attr\" data-street=\"$street_attr\" data-barangay=\"$barangay_attr\" data-city=\"$city_attr\" data-province=\"$province_attr\" data-active=\"$active_attr\">View</button>";
                                                                                echo "</td>";
                                                                                echo "</tr>";
                                    }
                                } else {
                                                                        echo "<tr><td colspan=\"10\" class=\"text-center\">No branches found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
                        <!-- Branch View Modal (Card style) -->
                        <div class="modal fade" id="branchModal" tabindex="-1" aria-labelledby="branchModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content bg-dark text-white" style="background-color:#000; color:#fff;">
                                    <div class="modal-header align-items-center">
                                        <div>
                                            <h5 class="modal-title mb-0" id="branchModalLabel">Branch</h5>
                                            <small class="text-muted" id="bBranchIDHeader"></small>
                                        </div>
                                        <div class="ms-3" id="bActiveHeader"></div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-lg-8">
                                                <div class="mb-3">
                                                    <label class="text-muted small">Name</label>
                                                    <div id="bName" class="fs-5 fw-semibold"></div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-muted small">Contact</label>
                                                    <div id="bContact" class="fw-semibold"></div>
                                                </div>
                                                <hr class="border-secondary">
                                                <div>
                                                    <label class="text-muted small">Address</label>
                                                    <div id="bStreet" class="fw-semibold"></div>
                                                    <div id="bBarangay" class="fw-semibold"></div>
                                                    <div><span id="bCity" class="fw-semibold"></span>, <span id="bProvince" class="fw-semibold"></span></div>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="card bg-secondary text-white mb-3">
                                                    <div class="card-body p-3">
                                                        <h6 class="card-title">Branch Info</h6>
                                                        <p class="mb-1"><span class="text-muted small">Status</span><br><span id="bActive" class="fw-semibold"></span></p>
                                                        <p class="mb-1"><span class="text-muted small">Contact</span><br><span id="bContactCard" class="fw-semibold"></span></p>
                                                        
                                                    </div>
                                                </div>
                                                
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
            <!-- Branch List End -->


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
    // Populate branch modal (card style) when opening
    $('#branchModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var bid = button.data('branchid') || '';
        var name = button.data('name') || '';
        var contact = button.data('contact') || '';
        var street = button.data('street') || '';
        var barangay = button.data('barangay') || '';
        var city = button.data('city') || '';
        var province = button.data('province') || '';
        var active = button.data('active') || '';
        var modal = $(this);

        var statusBadge = (active == '1' || active === 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';

        modal.find('#branchModalLabel').text('Branch');
        modal.find('#bBranchIDHeader').text('ID: ' + bid);
        modal.find('#bActiveHeader').html(statusBadge);

        modal.find('#bBranchID').text(bid);
        modal.find('#bName').text(name);
        modal.find('#bContact').text(contact);
        modal.find('#bContactCard').text(contact);
        modal.find('#bStreet').text(street);
        modal.find('#bBarangay').text(barangay);
        modal.find('#bCity').text(city);
        modal.find('#bProvince').text(province);
        modal.find('#bActive').html(statusBadge);

        // optional placeholder for updated timestamp
        modal.find('#bUpdated').text('N/A');
    });

    $(document).ready(function() {
        $('#branchTable').DataTable({
            paging: true,
            searching: true,
            info: true,
            ordering: true
        });
    });
    </script>
</body>

</html>