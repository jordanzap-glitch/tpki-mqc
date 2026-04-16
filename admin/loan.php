<?php
session_start();
require_once __DIR__ . '/../db/dbcon.php';

$success = '';
$error = '';

// Load interest rates map (Interest_Rate_ID => Interest_Rate_Code)
$ir_map = array();
$ir_q = mysqli_query($conn, "SELECT Interest_Rate_ID, Interest_Rate_Code FROM tbl_interest_rate");
if ($ir_q) {
    while ($r = mysqli_fetch_assoc($ir_q)) {
        $ir_map[$r['Interest_Rate_ID']] = $r['Interest_Rate_Code'];
    }
}

// Handle save loan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_loan'])) {
    // Generate next Loan_ID in format L-0001
    $last_q = mysqli_query($conn, "SELECT Loan_ID FROM tbl_loan_info ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($last_q && mysqli_num_rows($last_q) > 0) {
        $row = mysqli_fetch_assoc($last_q);
        if (preg_match('/L-(\d+)/', $row['Loan_ID'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
    }
    $loan_id = sprintf('L-%04d', $nextNum);

    // Collect inputs
    $client_id = isset($_POST['Client_ID']) ? trim($_POST['Client_ID']) : null;
    $loan_type = isset($_POST['Loan_Type']) ? trim($_POST['Loan_Type']) : null;
    $loan_cycle = null; // will be computed server-side based on existing loans for the client
    $effective_date = isset($_POST['Effective_Date']) && $_POST['Effective_Date'] !== '' ? $_POST['Effective_Date'] : null;
    $maturity_date = isset($_POST['Maturity_Date']) && $_POST['Maturity_Date'] !== '' ? $_POST['Maturity_Date'] : null;
    $premium = isset($_POST['Premium']) && $_POST['Premium'] !== '' ? floatval($_POST['Premium']) : null;
    $benefit = isset($_POST['Benefit']) && $_POST['Benefit'] !== '' ? floatval($_POST['Benefit']) : null;
    $loan_amount = isset($_POST['Loan_Amount']) && $_POST['Loan_Amount'] !== '' ? floatval($_POST['Loan_Amount']) : null;
    $no_of_months = isset($_POST['No_of_Months']) && $_POST['No_of_Months'] !== '' ? intval($_POST['No_of_Months']) : null;
    $payment_mode = isset($_POST['Payment_Mode']) ? trim($_POST['Payment_Mode']) : null;
    $no_of_periods = isset($_POST['No_of_Periods']) && $_POST['No_of_Periods'] !== '' ? intval($_POST['No_of_Periods']) : null;
    $interest_rate_id = isset($_POST['Interest_Rate_ID']) ? trim($_POST['Interest_Rate_ID']) : null;
    $total_interest_rate = isset($_POST['Total_Interest_Rate']) && $_POST['Total_Interest_Rate'] !== '' ? floatval($_POST['Total_Interest_Rate']) : null;
    $total_interest = isset($_POST['Total_Interest']) && $_POST['Total_Interest'] !== '' ? floatval($_POST['Total_Interest']) : null;
    $total_amount = isset($_POST['Total_Amount']) && $_POST['Total_Amount'] !== '' ? floatval($_POST['Total_Amount']) : null;
    $fixed_amount = isset($_POST['Fixed_Amount']) && $_POST['Fixed_Amount'] !== '' ? floatval($_POST['Fixed_Amount']) : null;
    // Force loan status to PENDING by default
    $loan_status = 'PENDING';

    // Employee ID from session if available
    $employee_id = isset($_SESSION['User_ID']) ? $_SESSION['User_ID'] : (isset($_SESSION['UserID']) ? $_SESSION['UserID'] : null);

    if (empty($client_id)) {
        $error = 'Client is required.';
    } else {
        // Compute loan cycle as (existing loans for client) + 1
        $loan_cycle = 1;
        $safe_client = mysqli_real_escape_string($conn, $client_id);
        $cnt_q = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM tbl_loan_info WHERE Client_ID = '$safe_client'");
        if ($cnt_q) {
            $cnt_row = mysqli_fetch_assoc($cnt_q);
            if ($cnt_row && isset($cnt_row['cnt'])) {
                $loan_cycle = intval($cnt_row['cnt']) + 1;
            }
            mysqli_free_result($cnt_q);
        }
        $sql = "INSERT INTO tbl_loan_info (Loan_ID, Client_ID, Loan_Type, Loan_Cycle, Effective_Date, Maturity_Date, Premium, Benefit, Loan_Amount, No_of_Months, Payment_Mode, No_of_Periods, Interest_Rate_ID, Total_Interest_Rate, Total_Interest, Total_Amount, Fixed_Amount, Loan_Status, Employee_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $types = 'ssssssdddisisddddss';
            mysqli_stmt_bind_param($stmt, $types,
                $loan_id, $client_id, $loan_type, $loan_cycle, $effective_date, $maturity_date,
                $premium, $benefit, $loan_amount, $no_of_months, $payment_mode, $no_of_periods,
                $interest_rate_id, $total_interest_rate, $total_interest, $total_amount, $fixed_amount,
                $loan_status, $employee_id
            );
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Loan saved successfully.';
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


            <!-- Blank Content Start: keep layout but remove inner content -->
            <div class="container-fluid pt-4 px-4">
                <div class="row bg-secondary rounded p-4 mx-0">
                    <div class="col-md-8">
                        <h5 class="mb-3">Loan - Select Client</h5>
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form id="loanForm" method="post" class="row g-2">
                            <div class="col-md-9">
                                <label class="form-label">Client</label>
                                <select id="loan_client" name="Client_ID" class="form-select" style="width:100%"></select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" id="verifyClient" class="btn btn-primary w-100">Verify</button>
                            </div>

                            <!-- Client details card placed directly under the dropdown -->
                            <div class="col-12">
                                <div id="clientDetails" class="card bg-dark text-white mt-3" style="display:none">
                                    <div class="card-body">
                                        <h6 class="card-title">Client Details</h6>
                                        <div class="row">
                                            <div class="col-md-6"><strong>Client ID:</strong> <div id="dClientID"></div></div>
                                            <div class="col-md-6"><strong>Name:</strong> <div id="dName"></div></div>
                                            <div class="col-md-6"><strong>Date of Birth:</strong> <div id="dDOB"></div></div>
                                            <div class="col-md-6"><strong>Age:</strong> <div id="dAge"></div></div>
                                            <div class="col-md-6"><strong>Civil Status:</strong> <div id="dCivilStatus"></div></div>
                                            <div class="col-md-6"><strong>City/Municipality:</strong> <div id="dCity"></div></div>
                                            <div class="col-md-6"><strong>Province:</strong> <div id="dProvince"></div></div>
                                            <div class="col-md-6"><strong>Email:</strong> <div id="dEmail"></div></div>
                                            <div class="col-md-6"><strong>Mobile No:</strong> <div id="dMobile"></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mt-3">
                                <label class="form-label">Loan Type</label>
                                <select name="Loan_Type" class="form-select">
                                    <option value="">-- Select Type --</option>
                                    <option value="1">Personal</option>
                                    <option value="2">Salary</option>
                                    <option value="3">Group</option>
                                </select>
                            </div>
                            <div class="col-md-4 mt-3">
                                <label class="form-label">Payment Mode</label>
                                <input name="Payment_Mode" class="form-control">
                            </div>

                            <div class="col-md-3 mt-3">
                                <label class="form-label">Effective Date</label>
                                <input id="Effective_Date" type="date" name="Effective_Date" class="form-control">
                            </div>
                            <div class="col-md-3 mt-3">
                                <label class="form-label">Maturity Date</label>
                                <input id="Maturity_Date" type="date" name="Maturity_Date" class="form-control">
                            </div>
                            <div class="col-md-3 mt-3">
                                <label class="form-label">No. of Months</label>
                                <input id="No_of_Months" type="number" name="No_of_Months" class="form-control" readonly>
                            </div>
                            <div class="col-md-3 mt-3">
                                <label class="form-label">No. of Periods</label>
                                <select id="No_of_Periods" name="No_of_Periods" class="form-select">
                                    <option value="">-- Select Period --</option>
                                </select>
                            </div>

                            <div class="col-md-4 mt-3">
                                <label class="form-label">Loan Amount</label>
                                <input id="Loan_Amount" type="number" step="0.01" name="Loan_Amount" class="form-control">
                            </div>
                            <div class="col-md-4 mt-3">
                                <label class="form-label">Premium</label>
                                <input type="number" step="0.01" name="Premium" class="form-control">
                            </div>
                            <div class="col-md-4 mt-3" hidden>
                                <label class="form-label">Benefit</label>
                                <input type="number" step="0.01" name="Benefit" class="form-control">
                            </div>

                            <div class="col-md-4 mt-3">
                                <label class="form-label">Interest Rate ID</label>
                                <input id="Interest_Rate_ID" name="Interest_Rate_ID" class="form-control" readonly>
                            </div>
                            <div class="col-md-4 mt-3">
                                <label class="form-label">Total Interest Rate</label>
                                <input id="Total_Interest_Rate" type="number" step="0.0001" name="Total_Interest_Rate" class="form-control" readonly>
                            </div>
                            <div class="col-md-4 mt-3">
                                <label class="form-label">Total Interest</label>
                                <input id="Total_Interest" type="number" step="0.01" name="Total_Interest" class="form-control">
                            </div>

                            <div class="col-md-4 mt-3">
                                <label class="form-label">Total Amount</label>
                                <input id="Total_Amount" type="number" step="0.01" name="Total_Amount" class="form-control" readonly>
                            </div>
                            <div class="col-md-4 mt-3">
                                <label class="form-label">Fixed Amount</label>
                                <input id="Fixed_Amount" type="number" step="0.01" name="Fixed_Amount" class="form-control">
                            </div>
                            <!-- Loan Status removed; set to PENDING server-side -->

                            <input type="hidden" name="save_loan" value="1">
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-success">Save Loan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Blank Content End -->


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

    <!-- Select2 & SweetAlert for verify UI -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Template Javascript -->
    <script src="../js/main.js"></script>
    <script>
    $(function(){
        // Previous civil status from PHP session (if any)
        var prevCivilStatus = <?php echo json_encode(isset($_SESSION['Civil_Status']) ? $_SESSION['Civil_Status'] : ''); ?> || '';

        // Map civil status codes to human-readable labels
        var civilStatusMap = {
            'M': 'Married',
            'S': 'Single',
            'SP': 'Single Parent',
            'MO': 'Married w/o Child'
        };

        var clientsMap = {};
        // initialize empty select2
        $('#loan_client').select2({
            placeholder: '-- Select client --',
            allowClear: true,
            width: '100%'
        });

        // load clients via existing endpoint
        $.getJSON('client_record.php?fetch_clients=1').done(function(res){
            if (res && res.data) {
                res.data.forEach(function(c){
                    var id = c.Client_ID || '';
                    var text = (c.Last_Name||'') + ', ' + (c.First_Name||'') + ' — ' + id;
                    clientsMap[id] = c;
                    var option = new Option(text, id, false, false);
                    $('#loan_client').append(option);
                });
                $('#loan_client').trigger('change');
            }
        }).fail(function(){
            console.warn('Failed to load clients.');
        });

        // Verify button handler
        $('#verifyClient').on('click', function(){
            var sel = $('#loan_client').val();
            if (!sel) {
                Swal.fire({icon:'warning', title:'Select a client', text:'Please choose a client first.'});
                return;
            }
            var c = clientsMap[sel];
            if (!c) {
                Swal.fire({icon:'error', title:'Not found', text:'Client data not found.'});
                return;
            }
            // Populate details
            $('#dClientID').text(c.Client_ID || '');
            var name = (c.Last_Name||'') + ', ' + (c.First_Name||'');
            $('#dName').text(name);
            $('#dDOB').text(c.Date_Of_Birth || '');
            $('#dAge').text(c.Age || '');
            // Prefer session-stored civil status if available, otherwise use client value
            var civCode = prevCivilStatus || (c.Civil_Status || '');
            var civLabel = civilStatusMap[civCode] || civCode || '';
            $('#dCivilStatus').text(civLabel);
            // Determine Fixed Amount based on civil status code and age ranges
            var ageNum = parseInt(c.Age, 10);
            var fixedVal = '';

            // mapping of fixed amounts by civil status and age ranges
            var fixedMap = {
                'M': [ {min:18,max:65,val:'481.80'}, {min:66,max:70,val:'258.53'} ],
                'S': [ {min:18,max:65,val:'300.80'}, {min:66,max:70,val:'193.20'}, {min:71,max:75,val:'633.60'} ],
                'SP': [ {min:18,max:65,val:'318.05'}, {min:66,max:70,val:'210.56'} ],
                'MO': [ {min:18,max:65,val:'464.55'}, {min:66,max:70,val:'241.28'}, {min:71,max:75,val:'802.56'} ]
            };

            // normalize civCode: if civCode is a human label, map back to code
            var civKey = civCode;
            if (!fixedMap[civKey]) {
                for (var k in civilStatusMap) {
                    if (civilStatusMap[k] === civCode) { civKey = k; break; }
                }
            }

            if (!isNaN(ageNum) && fixedMap[civKey]) {
                for (var i = 0; i < fixedMap[civKey].length; i++) {
                    var r = fixedMap[civKey][i];
                    if (ageNum >= r.min && ageNum <= r.max) {
                        fixedVal = r.val;
                        break;
                    }
                }
            }

            $('#Fixed_Amount').val(fixedVal);
            $('#dCity').text(c.City_Municipality || '');
            $('#dProvince').text(c.Province || '');
            $('#dEmail').text(c.Email_Address || '');
            $('#dMobile').text(c.Mobile_No || '');
            $('#clientDetails').show();
            // scroll to details
            $('html,body').animate({scrollTop: $('#clientDetails').offset().top - 80}, 300);
        });

        // Map loan type to interest rate ID (fallback to these IDs)
        var loanTypeToIR = {
            '1': 'IN-001', // Personal
            '2': 'IN-002', // Salary
            '3': 'IN-003'  // Group
        };

        // Interest rates map loaded from server: Interest_Rate_ID => Interest_Rate_Code
        var interestRates = <?php echo json_encode($ir_map, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?> || {};

        function setInterestRateFieldsByType(typeVal) {
            var irid = loanTypeToIR[typeVal] || '';
            $('#Interest_Rate_ID').val(irid);
            var code = '';
            if (irid && interestRates.hasOwnProperty(irid)) {
                code = interestRates[irid];
            }
            $('#Total_Interest_Rate').val(code);
            // recompute total interest when rate is set
            computeTotalInterest();
        }

        // When loan type changes, set the Interest_Rate_ID and total rate
        $('select[name="Loan_Type"]').on('change', function(){
            var t = $(this).val();
            setInterestRateFieldsByType(t);
            populatePeriodsByType(t);
        });

        // Populate No_of_Periods options depending on loan type
        function populatePeriodsByType(typeVal) {
            var map = {
                // Personal: Weekly=4, Monthly=1
                '1': [ {val:4, text:'Weekly'}, {val:1, text:'Monthly'} ],
                // Salary: default to Monthly
                '2': [ {val:1, text:'Monthly'} ],
                // Group: default to Monthly
                '3': [ {val:1, text:'Monthly'} ]
            };
            var opts = map[typeVal] || [ {val:1, text:'Monthly'} ];
            var $sel = $('#No_of_Periods');
            $sel.empty();
            $sel.append(new Option('-- Select Period --', ''));
            opts.forEach(function(o){
                var opt = new Option(o.text, o.val, false, false);
                $sel.append(opt);
            });
        }

        // If Interest_Rate_ID is manually changed (unlikely), update total rate too
        $('#Interest_Rate_ID').on('change input', function(){
            var irid = $(this).val() || '';
            var code = interestRates[irid] || '';
            $('#Total_Interest_Rate').val(code);
            computeTotalInterest();
        });

        // Initialize interest rate id and total rate if a loan type is preselected
        var initialType = $('select[name="Loan_Type"]').val();
        if (initialType) {
            setInterestRateFieldsByType(initialType);
            populatePeriodsByType(initialType);
        }

        // --- Auto-calc Effective / Maturity and No_of_Months ---
        function formatInputDate(d) {
            var yyyy = d.getFullYear();
            var mm = String(d.getMonth()+1).padStart(2,'0');
            var dd = String(d.getDate()).padStart(2,'0');
            return yyyy+'-'+mm+'-'+dd;
        }

        function addMonthsToDateStr(dateStr, months) {
            var d = new Date(dateStr);
            var day = d.getDate();
            d.setMonth(d.getMonth() + months);
            // If month overflow changed day (e.g., Feb), adjust to last day of prev month
            if (d.getDate() < day) {
                d.setDate(0);
            }
            return formatInputDate(d);
        }

        function calcMonthsBetween(effStr, matStr) {
            var d1 = new Date(effStr);
            var d2 = new Date(matStr);
            if (isNaN(d1) || isNaN(d2) || d2 < d1) return '';
            var months = (d2.getFullYear() - d1.getFullYear()) * 12 + (d2.getMonth() - d1.getMonth());
            // adjust if day-of-month in mat is earlier than eff
            if (d2.getDate() < d1.getDate()) months -= 1;
            return months;
        }

        // set Effective_Date default to today if empty
        var $eff = $('#Effective_Date');
        var todayStr = formatInputDate(new Date());
        if (!$eff.val()) $eff.val(todayStr);

        // No_of_Periods is driven only by Loan_Type (select). Do NOT connect it to maturity/months.
        $('#Effective_Date').on('change input', function(){
            // if maturity present, recalc months
            var eff = $(this).val();
            var mat = $('#Maturity_Date').val();
            if (eff && mat) {
                $('#No_of_Months').val(calcMonthsBetween(eff, mat));
                computeTotalAmount();
            }
        });

        // --- Total Interest calculation ---
        function computeTotalInterest() {
            var amt = parseFloat($('#Loan_Amount').val());
            var rate = parseFloat($('#Total_Interest_Rate').val());
            if (isNaN(amt) || isNaN(rate)) {
                $('#Total_Interest').val('');
                return;
            }
            var total = amt * rate;
            // Round to 2 decimals
            $('#Total_Interest').val(total.toFixed(2));
            computeTotalAmount();
        }

        // --- Total Amount calculation ---
        // Formula: (No_of_Months * No_of_Periods) / Loan_Amount + Total_Interest * No_of_Periods
        function computeTotalAmount() {
            var months = parseFloat($('#No_of_Months').val());
            var periods = parseFloat($('#No_of_Periods').val());
            var loanAmt = parseFloat($('#Loan_Amount').val());
            var totalInt = parseFloat($('#Total_Interest').val());
            if (isNaN(months) || isNaN(periods) || isNaN(loanAmt) || isNaN(totalInt) || loanAmt === 0) {
                $('#Total_Amount').val('');
                return;
            }
            var result1 = (months * periods);
                result2 =  loanAmt / result1;
                result3 = (result2 + totalInt) * result1;
            $('#Total_Amount').val(result3.toFixed(2));
        }

        // compute when amount or rate inputs change
        $('#Loan_Amount').on('input change', function(){ computeTotalInterest(); computeTotalAmount(); });
        $('#Total_Interest_Rate').on('input change', function(){ computeTotalInterest(); computeTotalAmount(); });
        $('#No_of_Periods').on('change', computeTotalAmount);
        $('#No_of_Months').on('input change', computeTotalAmount);
        $('#Total_Interest').on('input change', computeTotalAmount);

        // when Maturity changed manually, compute months
        $('#Maturity_Date').on('change input', function(){
            var eff = $('#Effective_Date').val();
            var mat = $(this).val();
            if (eff && mat) {
                $('#No_of_Months').val(calcMonthsBetween(eff, mat));
                computeTotalAmount();
            }
        });

        // ensure Verify triggers Effective default and updates maturity/months
        $('#verifyClient').on('click', function(){
            if (!$('#Effective_Date').val()) $('#Effective_Date').val(todayStr);
            // if maturity present, recalc months
            var eff = $('#Effective_Date').val();
            var mat = $('#Maturity_Date').val();
            if (eff && mat) {
                $('#No_of_Months').val(calcMonthsBetween(eff, mat));
                computeTotalAmount();
            }
            // recompute total interest and total amount based on populated fields
            computeTotalInterest();
            computeTotalAmount();
        });
    });
    </script>
</body>

</html>