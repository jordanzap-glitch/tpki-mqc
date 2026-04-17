<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include '../db/dbcon.php';
// Fetch total users count from tbl_user
$totalUsers = 0;
$res = mysqli_query($conn, "SELECT COUNT(id) AS cnt FROM tbl_user");
if ($res) {
    $r = mysqli_fetch_assoc($res);
    $totalUsers = intval($r['cnt'] ?? 0);
    mysqli_free_result($res);
}
// Fetch active loans count from tbl_loan_info
$activeLoans = 0;
$q = "SELECT COUNT(id) AS cnt FROM tbl_loan_info WHERE Loan_Status NOT IN ('DENIED','CANCELLED','PAID')";
$res2 = mysqli_query($conn, $q);
if ($res2) {
    $r2 = mysqli_fetch_assoc($res2);
    $activeLoans = intval($r2['cnt'] ?? 0);
    mysqli_free_result($res2);
}
// Fetch approved loans count
$approvedLoans = 0;
$q3 = "SELECT COUNT(id) AS cnt FROM tbl_loan_info WHERE Loan_Status = 'APPROVED'";
$res3 = mysqli_query($conn, $q3);
if ($res3) {
    $r3 = mysqli_fetch_assoc($res3);
    $approvedLoans = intval($r3['cnt'] ?? 0);
    mysqli_free_result($res3);
}
// Fetch total branches count from tbl_branch
$totalBranches = 0;
$res4 = mysqli_query($conn, "SELECT COUNT(id) AS cnt FROM tbl_branch");
if ($res4) {
    $r4 = mysqli_fetch_assoc($res4);
    $totalBranches = intval($r4['cnt'] ?? 0);
    mysqli_free_result($res4);
}
// Prepare loan summary aggregated by year and quarter
$loanSummaryData = [];
$sqlSum = "SELECT YEAR(STR_TO_DATE(Effective_Date, '%Y-%m-%d')) AS yr,
                                             QUARTER(STR_TO_DATE(Effective_Date, '%Y-%m-%d')) AS q,
                                             COUNT(id) AS cnt
                                         FROM tbl_loan_info
                                         WHERE Effective_Date IS NOT NULL
                                             AND Effective_Date <> ''
                                             AND Effective_Date <> '0000-00-00'
                                         GROUP BY yr, q
                                         ORDER BY yr, q";
$resSum = mysqli_query($conn, $sqlSum);
if ($resSum) {
    while ($row = mysqli_fetch_assoc($resSum)) {
        $yr = intval($row['yr']);
        $q = intval($row['q']);
        $cnt = intval($row['cnt']);
        // map quarter to month start
        $month = 1;
        if ($q === 1) $month = 1;
        elseif ($q === 2) $month = 4;
        elseif ($q === 3) $month = 7;
        elseif ($q === 4) $month = 10;
        $dateStr = sprintf('%04d/%02d/01', $yr, $month);
        $loanSummaryData[] = ['x' => $dateStr, 'y' => $cnt];
    }
    mysqli_free_result($resSum);
}

// Prepare branch summary: categories (Branch_Name) and data (client counts)
$branchCategories = [];
$branchData = [];
$sqlBranch = "SELECT b.Branch_ID, b.Branch_Name, COUNT(c.Client_ID) AS cnt
              FROM tbl_branch b
              LEFT JOIN tbl_client_info c ON c.Branch_ID = b.Branch_ID
              GROUP BY b.Branch_ID, b.Branch_Name
              ORDER BY cnt DESC";
$resB = mysqli_query($conn, $sqlBranch);
if ($resB) {
    while ($br = mysqli_fetch_assoc($resB)) {
        $branchCategories[] = $br['Branch_Name'] ?? '';
        $branchData[] = intval($br['cnt'] ?? 0);
    }
    mysqli_free_result($resB);
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


            <!-- Card Count Start -->
            <div class="container-fluid pt-4 px-4">
                <div class="row g-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="bg-secondary rounded d-flex align-items-center justify-content-between p-4">
                            <i class="fa fa-users fa-3x text-primary"></i>
                            <div class="ms-3">
                                <p class="mb-2">Total Users</p>
                                <h3 class="mb-0"><?php echo number_format($totalUsers); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="bg-secondary rounded d-flex align-items-center justify-content-between p-4">
                            <i class="fa fa-hand-holding-usd fa-3x text-primary"></i>
                            <div class="ms-3">
                                <p class="mb-2">Active Loans</p>
                                <h3 class="mb-0"><?php echo number_format($activeLoans); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="bg-secondary rounded d-flex align-items-center justify-content-between p-4">
                            <i class="fa <?php echo ($approvedLoans > 0) ? 'fa-check-circle text-success' : 'fa-coins text-primary'; ?> fa-3x"></i>
                            <div class="ms-3">
                                <p class="mb-2">Approved Loans</p>
                                <h3 class="mb-0"><?php echo number_format($approvedLoans); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="bg-secondary rounded d-flex align-items-center justify-content-between p-4">
                            <i class="fa fa-building fa-3x text-primary"></i>
                            <div class="ms-3">
                                <p class="mb-2">Total Branches</p>
                                <h3 class="mb-0"><?php echo number_format($totalBranches); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Card Count End -->


            <!-- Loan & Branch Summary Charts -->
            <div class="container-fluid pt-4 px-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="bg-secondary text-center rounded p-4">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h6 class="mb-0">Loan Summary</h6>
                            </div>
                            <div id="chart"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-secondary text-center rounded p-4">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h6 class="mb-0">Client Per Branch</h6>
                            </div>
                            <div id="chart_branch"></div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Recent Loan Start -->
            <div class="container-fluid pt-4 px-4">
                <div class="bg-secondary text-center rounded p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h6 class="mb-0">Recent Loan Activities</h6>
                        <a href="">Show All</a>
                    </div>
                    <div class="table-responsive">
                        <table id="recentLoansTable" class="table text-start align-middle table-bordered table-hover mb-0">
                            <thead>
                                <tr class="text-white">
                                    <th scope="col"><input class="form-check-input" type="checkbox"></th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Invoice</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="6" class="text-center">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Recent Loan End -->


            <!-- Can Start another cards -->
            <!-- End of the another cards-->


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

    <!-- ApexCharts + dayjs for Loan Summary -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>

    <!-- Template Javascript -->
    <script src="../js/main.js"></script>
        <script>
        (function(){
                var seriesData = <?php echo json_encode($loanSummaryData); ?> || [];

                var options = {
                    series: [{
                        name: 'loans',
                        data: seriesData
                    }],
                    chart: {
                        type: 'bar',
                        height: 380
                    },
                    xaxis: {
                        type: 'category',
                        labels: {
                            formatter: function(val) {
                                var d = dayjs(val);
                                var q = Math.floor(d.month()/3) + 1;
                                return 'Q' + q;
                            }
                        },
                        group: {
                            style: {
                                fontSize: '10px',
                                fontWeight: 700
                            },
                            groups: []
                        }
                    },
                    title: { text: 'Loan Summary' },
                    tooltip: {
                        x: { formatter: function(val){ var d = dayjs(val); var q = Math.floor(d.month()/3)+1; return 'Q'+q+' '+d.format('YYYY'); } }
                    }
                };

                // Build group titles by year from seriesData
                var groups = [];
                if (Array.isArray(seriesData) && seriesData.length) {
                        var byYear = {};
                        seriesData.forEach(function(pt){
                                var d = dayjs(pt.x);
                                var yr = d.format('YYYY');
                                byYear[yr] = (byYear[yr] || 0) + 1;
                        });
                        Object.keys(byYear).sort().forEach(function(yr){ groups.push({ title: yr, cols: byYear[yr] }); });
                        options.xaxis.group.groups = groups;
                }

                var chartEl = document.querySelector('#chart');
                if (chartEl) {
                        var chart = new ApexCharts(chartEl, options);
                        chart.render();
                }
                // Branch summary horizontal bar
                var branchCategories = <?php echo json_encode($branchCategories); ?> || [];
                var branchData = <?php echo json_encode($branchData); ?> || [];
                var optionsBranch = {
                  series: [{ data: branchData }],
                  chart: { type: 'bar', height: 350 },
                  plotOptions: { bar: { borderRadius: 4, borderRadiusApplication: 'end', horizontal: true } },
                  dataLabels: { enabled: false },
                  xaxis: { categories: branchCategories }
                };
                var chartElB = document.querySelector('#chart_branch');
                if (chartElB) {
                    var chartB = new ApexCharts(chartElB, optionsBranch);
                    chartB.render();
                }
            })();
            </script>
    <script>
    // Populate Recent Loan Activities table using loan_record.php fetch_loans endpoint
    (function(){
        function fmtAmt(v){ return isFinite(v) ? Number(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) : '-'; }
        $.getJSON('loan_record.php', { fetch_loans: 1 }).done(function(res){
            var rows = (res && res.data) ? res.data : [];
            var $tbody = $('#recentLoansTable tbody');
            if (!rows.length) {
                $tbody.html('<tr><td colspan="6" class="text-center">No recent loans</td></tr>');
                return;
            }
            var html = '';
            rows.forEach(function(r){
                var name = ((r.Last_Name||'') + ', ' + (r.First_Name||'')).toUpperCase();
                var status = (r.Loan_Status||'PENDING').toString().toUpperCase();
                var cls = 'bg-secondary';
                if (status === 'PENDING') cls = 'bg-warning text-dark';
                if (status === 'APPROVED') cls = 'bg-success';
                if (status === 'DENIED') cls = 'bg-danger';
                html += '<tr>' +
                        '<td><input class="form-check-input" type="checkbox"></td>' +
                        '<td>' + (r.Effective_Date||'') + '</td>' +
                        '<td>' + (r.Loan_ID||'') + '</td>' +
                        '<td>' + name + '</td>' +
                        '<td>' + fmtAmt(r.Loan_Amount) + '</td>' +
                        '<td><span class="badge '+cls+'">'+status+'</span></td>' +
                        '</tr>';
            });
            $tbody.html(html);
        }).fail(function(){
            $('#recentLoansTable tbody').html('<tr><td colspan="6" class="text-center text-danger">Failed to load data</td></tr>');
        });
    })();
    </script>
</body>

</html>