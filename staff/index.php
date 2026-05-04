<?php
include 'includes/init.php';
include '../db/dbcon.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>TPKI || Staff Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <?php include "includes/head.php"; ?>
    <style>
    .widget-section-title {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--primary);
        border-bottom: 1px solid rgba(61,242,118,0.2);
        padding-bottom: .5rem;
        margin-bottom: 1.25rem;
        margin-top: 2rem;
    }
    .widget-card {
        background: var(--secondary);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 14px;
        padding: 1.6rem 1.4rem;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: .6rem;
        text-decoration: none;
        transition: border-color .2s, box-shadow .2s, transform .15s;
        height: 100%;
        cursor: pointer;
    }
    .widget-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 22px rgba(61,242,118,0.1);
        transform: translateY(-2px);
        text-decoration: none;
    }
    .widget-card .wc-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        background: rgba(61,242,118,0.12);
        border: 1.5px solid rgba(61,242,118,0.28);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: var(--primary);
        flex-shrink: 0;
    }
    .widget-card .wc-title {
        font-size: .95rem;
        font-weight: 700;
        color: #e2e5f1;
        margin: 0;
    }
    .widget-card .wc-desc {
        font-size: .78rem;
        color: rgba(255,255,255,0.42);
        line-height: 1.4;
        margin: 0;
    }
    .widget-card .wc-arrow {
        margin-top: auto;
        font-size: .75rem;
        color: var(--primary);
        opacity: 0;
        transition: opacity .2s;
    }
    .widget-card:hover .wc-arrow { opacity: 1; }

    [data-theme="light"] .widget-section-title { color: #1a7a3a; border-bottom-color: rgba(26,122,58,0.2); }
    [data-theme="light"] .widget-card { background: #fff; border-color: #e2e8f0; }
    [data-theme="light"] .widget-card:hover { border-color: #1a7a3a; box-shadow: 0 4px 22px rgba(26,122,58,0.1); }
    [data-theme="light"] .widget-card .wc-icon { background: rgba(26,122,58,0.1); border-color: rgba(26,122,58,0.28); color: #1a7a3a; }
    [data-theme="light"] .widget-card .wc-title { color: #1e293b; }
    [data-theme="light"] .widget-card .wc-desc { color: #64748b; }
    [data-theme="light"] .widget-card .wc-arrow { color: #1a7a3a; }

    .dash-welcome {
        background: var(--secondary);
        border: 1px solid rgba(61,242,118,0.18);
        border-radius: 14px;
        padding: 1.6rem 2rem;
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }
    .dash-welcome .dw-icon { font-size: 2rem; color: var(--primary); flex-shrink: 0; }
    .dash-welcome .dw-title { font-size: 1.1rem; font-weight: 700; color: #e2e5f1; margin: 0 0 .2rem; }
    .dash-welcome .dw-sub { font-size: .82rem; color: rgba(255,255,255,0.45); margin: 0; }
    [data-theme="light"] .dash-welcome { background: #fff; border-color: rgba(26,122,58,0.2); }
    [data-theme="light"] .dash-welcome .dw-title { color: #1e293b; }
    [data-theme="light"] .dash-welcome .dw-sub { color: #64748b; }
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

            <div class="container-fluid pt-4 px-4 pb-5">

                <!-- Welcome Banner -->
                <div class="dash-welcome mb-2">
                    <div class="dw-icon"><i class="fa fa-tachometer-alt"></i></div>
                    <div>
                        <p class="dw-title">Welcome to the Staff Dashboard</p>
                        <p class="dw-sub">Select a module below to get started.</p>
                    </div>
                </div>

                <!-- ── Clients ── -->
                <div class="widget-section-title"><i class="fa fa-handshake me-2"></i>Clients</div>
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4 col-xl-3">
                        <a href="client.php" class="widget-card">
                            <div class="wc-icon"><i class="fa fa-user-plus"></i></div>
                            <p class="wc-title">Add Client Info</p>
                            <p class="wc-desc">Register a new client and their co-maker information.</p>
                            <span class="wc-arrow"><i class="fa fa-arrow-right me-1"></i>Open</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-3">
                        <a href="client_record.php" class="widget-card">
                            <div class="wc-icon"><i class="fa fa-address-book"></i></div>
                            <p class="wc-title">Client Record</p>
                            <p class="wc-desc">View and manage existing client records.</p>
                            <span class="wc-arrow"><i class="fa fa-arrow-right me-1"></i>Open</span>
                        </a>
                    </div>
                </div>

                <!-- ── Comaker ── -->
                <div class="widget-section-title"><i class="fa fa-user-plus me-2"></i>Comaker</div>
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4 col-xl-3">
                        <a href="comaker_record.php" class="widget-card">
                            <div class="wc-icon"><i class="fa fa-users"></i></div>
                            <p class="wc-title">Comaker Record</p>
                            <p class="wc-desc">View and manage co-maker records linked to clients.</p>
                            <span class="wc-arrow"><i class="fa fa-arrow-right me-1"></i>Open</span>
                        </a>
                    </div>
                </div>

                <!-- ── Loans ── -->
                <div class="widget-section-title"><i class="fa fa-hand-holding-usd me-2"></i>Loans</div>
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4 col-xl-3">
                        <a href="loan.php" class="widget-card">
                            <div class="wc-icon"><i class="fa fa-file-invoice-dollar"></i></div>
                            <p class="wc-title">Loan Information</p>
                            <p class="wc-desc">Submit a new loan application for a client.</p>
                            <span class="wc-arrow"><i class="fa fa-arrow-right me-1"></i>Open</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-3">
                        <a href="loan_record.php" class="widget-card">
                            <div class="wc-icon"><i class="fa fa-list-alt"></i></div>
                            <p class="wc-title">Loan Record</p>
                            <p class="wc-desc">Browse and manage all loan applications and their statuses.</p>
                            <span class="wc-arrow"><i class="fa fa-arrow-right me-1"></i>Open</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-3">
                        <a href="loan_ledger.php" class="widget-card">
                            <div class="wc-icon"><i class="fa fa-book"></i></div>
                            <p class="wc-title">Loan Ledger</p>
                            <p class="wc-desc">View amortization schedules and payment ledgers.</p>
                            <span class="wc-arrow"><i class="fa fa-arrow-right me-1"></i>Open</span>
                        </a>
                    </div>
                </div>

            </div><!-- end container-fluid -->

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
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>"></script>
</body>

</html>
