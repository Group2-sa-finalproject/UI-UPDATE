
        <?php
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        require_once "../config/database.php";

        // RESIDENT LOGIN CHECK
        if (
            !isset($_SESSION["user_id"]) ||
            ($_SESSION["role"] ?? "") !== "residents"
        ) {
            header("Location: ../index.php");
            exit;
        }

        if (!isset($conn) || !($conn instanceof mysqli)) {
            die("Database connection is unavailable.");
        }

        $conn->set_charset("utf8mb4");

        $user_id = (int) $_SESSION["user_id"];

        // GET RESIDENT INFORMATION
        $stmt = $conn->prepare("
            SELECT id, fullname, users, role
            FROM users
            WHERE id = ?
            AND role = 'residents'
            LIMIT 1
        ");

        if (!$stmt) {
            die("Unable to retrieve resident information.");
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            session_unset();
            session_destroy();
            header("Location: ../index.php");
            exit;
        }

        // GET SUBSCRIPTION INFORMATION
        $subscription_sql = $conn->prepare("
            SELECT *
            FROM subscriptions
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        if (!$subscription_sql) {
            die("Subscription query failed: " . $conn->error);
        }

        $subscription_sql->bind_param("i", $user_id);
        $subscription_sql->execute();

        $subscription = $subscription_sql->get_result()->fetch_assoc();
        $subscription_sql->close();

        // SUBSCRIPTION DEFAULT VALUES
        $subscription_payment_status = $subscription['payment_status']
            ?? 'No Subscription';

        $subscription_status = $subscription['subscription_status']
            ?? 'Not Subscribed';

        $subscription_amount_paid = (float) (
            $subscription['amount_paid'] ?? 0
        );

        $subscription_balance_due = (float) (
            $subscription['balance_due'] ?? 0
        );

        $subscription_discount = (float) (
            $subscription['discount_amount'] ?? 0
        );

        if (isset($subscription['total_amount'])) {
            $subscription_total_amount = (float) $subscription['total_amount'];
        } else {
            $original_amount = (float) (
                $subscription['original_amount'] ?? 0
            );

            $subscription_total_amount = max(
                0,
                $original_amount - $subscription_discount
            );
        }

        $subscription_date = $subscription['created_at'] ?? null;

        $subscription_is_paid =
            strtolower((string) $subscription_payment_status) === 'paid';

        // FINAL SUBSCRIPTION DEFAULT VALUES
        if (!$subscription) {
            $subscription_payment_status = "No Subscription";
            $subscription_total_amount = 0;
            $subscription_amount_paid = 0;
            $subscription_balance_due = 0;
            $subscription_status = "Not Subscribed";
            $subscription_date = null;
        } else {
            $subscription_payment_status = $subscription["payment_status"] ?? "Unpaid";
            $subscription_total_amount = (float) (
                $subscription["total_amount"] ?? 0
            );
            $subscription_amount_paid = (float) (
                $subscription["amount_paid"] ?? 0
            );
            $subscription_balance_due = (float) (
                $subscription["balance_due"] ?? 0
            );
            $subscription_status = $subscription["subscription_status"] ?? "Pending";
            $subscription_date = $subscription["created_at"] ?? null;
        }

        $subscription_is_paid =
            strtolower((string) $subscription_payment_status) === "paid";

        // GET LATEST PAYMENT
        $payment_sql = $conn->prepare("
            SELECT amount, status, paid_at
            FROM resident_payments
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        if (!$payment_sql) {
            die("Payment query failed.");
        }

        $payment_sql->bind_param("i", $user_id);
        $payment_sql->execute();

        $payment = $payment_sql->get_result()->fetch_assoc();
        $payment_sql->close();

        // PAYMENT DEFAULT VALUES
        if (!$payment) {
            $payment_status = "Unpaid";
            $payment_amount = 0;
            $paid_at = null;
        } else {
            $payment_status = $payment["status"] ?? "Unpaid";
            $payment_amount = (float) ($payment["amount"] ?? 0);
            $paid_at = $payment["paid_at"] ?? null;
        }

        $is_paid = strtolower((string) $payment_status) === "paid";

        // ESCAPE OUTPUT HELPER
        function e($value)
        {
            return htmlspecialchars(
                (string) $value,
                ENT_QUOTES,
                "UTF-8"
            );
        }

        // STATUS BADGE HELPERS
        $subscription_payment_badge = "status-unpaid";

        if ($subscription_is_paid) {
            $subscription_payment_badge = "status-paid";
        } elseif (
            stripos($subscription_payment_status, "pending") !== false
        ) {
            $subscription_payment_badge = "status-pending";
        }

        $latest_payment_badge = "status-unpaid";

        if ($is_paid) {
            $latest_payment_badge = "status-paid";
        } elseif (
            stripos($payment_status, "pending") !== false
        ) {
            $latest_payment_badge = "status-pending";
        }

        $subscription_status_badge = "status-default";

        if (stripos($subscription_status, "pending") !== false) {
            $subscription_status_badge = "status-pending";
        } elseif (
            stripos($subscription_status, "approved") !== false
            || stripos($subscription_status, "active") !== false
        ) {
            $subscription_status_badge = "status-paid";
        }
        ?>

        <!DOCTYPE html>
        <html lang="en">

        <head>

        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Resident Dashboard | EcoTrack</title>

        <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="../assets/css/style.css">

        <link rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

        <style>
/* EcoTrack Resident Dashboard — visual styles only */
:root { --eco-deep:#123d2b; --eco-green:#1b7548; --eco-soft:#eaf5ee; --eco-border:#e5ece7; --eco-muted:#7b8c80; --eco-text:#263e30; }
* { box-sizing:border-box; }
body { margin:0; background:#f5f8f6; font-family:Arial,Helvetica,sans-serif; color:var(--eco-text); }
a { text-decoration:none; }
/* Original sidebar structure and links retained */
.sidebar { position:fixed; top:0; left:0; width:250px; height:100vh; background:var(--eco-deep); color:#fff; padding:25px 15px; overflow-y:auto; z-index:1000; }
.sidebar-logo { text-align:center; margin-bottom:35px; }
.sidebar-logo h3 { font-size:23px; font-weight:800; color:#fff; margin:0; }
.sidebar-logo i { color:#9be7ad; margin-right:7px; }
.sidebar-menu { list-style:none; padding:0; margin:0; }
.sidebar-menu li { margin-bottom:8px; }
.sidebar-menu a { display:flex; align-items:center; gap:13px; padding:13px 15px; color:#d8e8df; border-radius:8px; font-size:14px; font-weight:600; transition:.2s ease; }
.sidebar-menu a i { width:20px; text-align:center; font-size:16px; }
.sidebar-menu a:hover { background:rgba(255,255,255,.12); color:#fff; }
.sidebar-menu a.active { background:#fff; color:var(--eco-deep); }
.sidebar-menu .logout-link { margin-top:25px; color:#ffcccc; }
.sidebar-menu .logout-link:hover { background:rgba(255,255,255,.12); color:#fff; }
/* Dashboard content */
.main-content { margin-left:250px; min-width:0; min-height:100vh; }
.topbar { min-height:76px; background:#fff; padding:15px 32px; display:flex; justify-content:flex-end; align-items:center; border-bottom:1px solid var(--eco-border); }
.resident-profile { display:flex; align-items:center; gap:12px; }
.resident-profile .profile-icon { width:42px; height:42px; background:var(--eco-soft); color:var(--eco-green); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:17px; }
.resident-profile .profile-name { font-size:14px; font-weight:700; color:var(--eco-deep); }
.resident-profile .profile-role { display:block; font-size:11px; color:var(--eco-muted); font-weight:500; margin-top:3px; }
.content { max-width:1600px; margin:0 auto; padding:34px 34px 25px; min-width:0; }
.page-heading { padding:25px 28px; margin-bottom:30px; background:linear-gradient(110deg,#164d33,#26734a); border-radius:15px; box-shadow:0 7px 18px rgba(18,61,43,.10); }
.page-heading h2 { color:#fff; font-size:25px; font-weight:800; margin:0 0 8px; letter-spacing:-.4px; }
.page-heading p { color:#dceee2; font-size:13px; line-height:1.7; margin:0; }
.section-title { color:#244832; font-size:17px; font-weight:750; margin:28px 0 17px; padding-left:12px; border-left:4px solid #2c9056; }
/* Shared cards */
.dashboard-card { background:#fff; border:1px solid var(--eco-border); border-radius:14px; padding:25px; box-shadow:0 4px 18px rgba(20,62,38,.045); height:100%; min-width:0; transition:box-shadow .2s ease,transform .2s ease; }
.dashboard-card:hover { box-shadow:0 9px 25px rgba(20,62,38,.075); }
.dashboard-card h5 { font-size:17px; font-weight:750; color:var(--eco-deep); margin:0 0 9px; line-height:1.4; }
.dashboard-card p { color:var(--eco-muted); font-size:12.5px; line-height:1.7; }
.dashboard-icon { width:46px; height:46px; display:flex; align-items:center; justify-content:center; border-radius:12px; background:var(--eco-soft); color:var(--eco-green); margin-bottom:17px; font-size:18px; }
/* Account information */
.info-table { border-color:var(--eco-border); margin-bottom:0; }
.info-table th { width:36%; background:#f7faf8; color:#64766a; font-size:12px; font-weight:650; padding:15px 14px; border-color:var(--eco-border); }
.info-table td { color:#294a35; font-size:13px; font-weight:600; padding:15px 14px; border-color:var(--eco-border); overflow-wrap:anywhere; }
/* Billing */
.payment-section-title { font-size:13px; font-weight:750; color:var(--eco-deep); margin:19px 0 14px; }
.payment-amount { font-size:29px; font-weight:800; color:var(--eco-deep); margin:4px 0 12px; letter-spacing:-.5px; }
.detail-row { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; padding:13px 0; border-bottom:1px solid #edf2ee; font-size:12px; }
.detail-row:last-child { border-bottom:0; }
.detail-label { color:#788a7d; flex:1; }
.detail-value { color:#244833; font-weight:700; text-align:right; overflow-wrap:anywhere; max-width:60%; }
.payment-status,.status-badge { display:inline-flex; align-items:center; gap:4px; padding:7px 11px; border-radius:7px; font-size:10px; font-weight:750; letter-spacing:.15px; }
.status-paid { background:#e5f5e9; color:#17643b; }
.status-unpaid { background:#fdeceb; color:#a43c38; }
.status-pending { background:#fff3d9; color:#916515; }
.status-default { background:#eef1ef; color:#617268; }
.info-box,.warning-box { padding:14px 15px; border-radius:9px; font-size:12px; line-height:1.7; margin-bottom:15px; }
.info-box { background:#eff9f1; border:1px solid #d5ecdb; color:#2c6740; }
.warning-box { background:#fff9ec; border:1px solid #f4e6bf; color:#7c612b; }
/* Services */
.service-card { display:flex; flex-direction:column; min-height:225px; }
.service-card p { flex:1; margin-bottom:18px; }
.service-card .btn { margin-top:auto; }
.btn-ecotrack { background:#1b7548; color:#fff; border:1px solid #1b7548; border-radius:8px; padding:11px 16px; font-size:12px; font-weight:700; transition:.2s; }
.btn-ecotrack:hover { background:#135a36; color:#fff; border-color:#135a36; }
.btn-outline-ecotrack { border:1px solid #1b7548; color:#1b7548; background:#fff; border-radius:8px; padding:10px 16px; font-size:12px; font-weight:700; }
.btn-outline-ecotrack:hover { background:#1b7548; color:#fff; }
.alert { border-radius:10px; font-size:12px; }
.dashboard-footer { margin-top:38px; padding:22px 0 8px; border-top:1px solid var(--eco-border); color:#93a096; font-size:11px; line-height:1.8; text-align:center; }
/* Responsive spacing without changing sidebar behavior */
@media(max-width:1100px) { .content { padding:24px 22px; } .dashboard-card { padding:20px; } }
@media(max-width:700px) {
 .sidebar { width:70px; padding:20px 8px; }
 .sidebar-logo h3 { font-size:0; }
 .sidebar-logo i { font-size:25px; margin:0; }
 .sidebar-menu a { justify-content:center; padding:13px 8px; }
 .sidebar-menu a span { display:none; }
 .sidebar-menu a i { font-size:18px; width:auto; }
 .main-content { margin-left:70px; }
 .topbar { padding:12px 15px; min-height:65px; }
 .content { padding:17px 13px; }
 .page-heading { padding:20px 17px; margin-bottom:22px; }
 .page-heading h2 { font-size:20px; }
 .section-title { font-size:15px; }
 .dashboard-card { padding:18px; }
 .payment-amount { font-size:25px; }
 .info-table th,.info-table td { font-size:11px; padding:11px 9px; }
 .detail-row { gap:9px; }
}
@media(max-width:420px) { .resident-profile .profile-name { max-width:155px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; } .page-heading p { font-size:12px; } }

/* FULL-WIDTH PROFILE AND BALANCED BILLING ARRANGEMENT */
.profile-information-card { height:auto; }
.profile-card-heading { display:flex; align-items:center; gap:14px; margin-bottom:22px; }
.profile-card-heading .dashboard-icon { flex-shrink:0; margin:0; }
.profile-card-heading h5 { margin-bottom:4px; }
.profile-details-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
.profile-detail-item { display:flex; align-items:flex-start; gap:12px; min-width:0; padding:19px 15px; border:1px solid #e6eee8; background:#f8fbf9; border-radius:12px; }
.profile-detail-icon { display:flex; align-items:center; justify-content:center; width:36px; height:36px; flex-shrink:0; border-radius:9px; background:#e5f3e9; color:#197345; }
.profile-detail-content { min-width:0; display:flex; flex-direction:column; gap:7px; }
.profile-detail-label { color:#7b8c80; font-size:12px; font-weight:600; }
.profile-detail-content strong { color:#173d2b; font-size:14px; overflow-wrap:anywhere; line-height:1.5; }
.billing-card { height:auto; }
.billing-columns { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; margin-top:20px; align-items:start; }
.billing-panel { border:1px solid #e6eee8; border-radius:12px; padding:22px; min-width:0; }
.billing-panel > hr:first-child { display:none; }
.billing-panel .payment-section-title { margin-top:0; }
@media(max-width:1200px) { .profile-details-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:850px) { .billing-columns { grid-template-columns:1fr; } }
@media(max-width:520px) { .profile-details-grid { grid-template-columns:1fr; } .billing-panel { padding:16px; } }

/* FLAT PROFILE LAYOUT — no enclosing card or individual tiles */
.profile-information-card {
    background: transparent;
    border: 0;
    box-shadow: none;
    padding: 3px 0 20px;
    height: auto;
}
.profile-card-heading { margin-bottom: 22px; }
.profile-card-heading .dashboard-icon {
    background: transparent;
    border-radius: 0;
    width: 28px;
    height: 28px;
    font-size: 20px;
}
.profile-card-heading h5 { color: #173d2b; font-size: 17px; font-weight: 750; }
.profile-details-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0;
    padding: 16px 0;
    border-top: 1px solid #dce8df;
    border-bottom: 1px solid #dce8df;
}
.profile-detail-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    border: 0;
    border-right: 1px solid #dce8df;
    border-radius: 0;
    background: transparent;
    min-width: 0;
}
.profile-detail-item:first-child { padding-left: 0; }
.profile-detail-item:last-child { border-right: 0; }
.profile-detail-icon {
    background: transparent;
    color: #27804d;
    width: 24px;
    height: 28px;
    border-radius: 0;
    font-size: 17px;
}
.profile-detail-content { gap: 5px; }
.profile-detail-label { font-size: 12px; }
.profile-detail-content strong { font-size: 15px; }
@media (max-width: 1200px) {
    .profile-details-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); row-gap: 12px; }
    .profile-detail-item:nth-child(2) { border-right: 0; }
    .profile-detail-item:nth-child(3) { padding-left: 0; }
}
@media (max-width: 520px) {
    .profile-details-grid { grid-template-columns: 1fr; padding: 8px 0; }
    .profile-detail-item, .profile-detail-item:first-child, .profile-detail-item:nth-child(3) {
        padding: 14px 0;
        border-right: 0;
        border-bottom: 1px solid #e5eee8;
    }
    .profile-detail-item:last-child { border-bottom: 0; }
}
</style>

        </head>

        <body>

        <!-- =====================================================
            SIDEBAR
        ===================================================== -->

        <aside class="sidebar">

            <div class="sidebar-logo">
                <h3>
                    <i class="fas fa-recycle"></i>
                    EcoTrack
                </h3>
            </div>

            <ul class="sidebar-menu">

                <li>
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="eco_points.php">
                        <i class="fas fa-coins"></i>
                        <span>Eco Points</span>
                    </a>
                </li>

                <li>
                    <a href="collection_schedule.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Collection Schedule</span>
                    </a>
                </li>

                <li>
                    <a href="track_truck.php">
                        <i class="fas fa-truck"></i>
                        <span>Track Garbage Truck</span>
                    </a>
                </li>

                <li>
                    <a href="subscription.php">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Subscription</span>
                    </a>
                </li>

                <li>
                    <a href="register_household.php">
                        <i class="fas fa-house-user"></i>
                        <span>Register Household</span>
                    </a>
                </li>

                <li>
                    <a href="feedback.php">
                        <i class="fas fa-comment-dots"></i>
                        <span>Feedback</span>
                    </a>
                </li>

                <li>
                    <a href="qrcode.php">
                        <i class="fas fa-qrcode"></i>
                        <span>Resident QR Code</span>
                    </a>
                </li>

                <li>
                    <a href="../logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>

            </ul>

        </aside>


        <!-- =====================================================
            MAIN CONTENT
        ===================================================== -->

        <div class="main-content">

            <!-- TOPBAR -->

            <div class="topbar">

                <div class="resident-profile">

                    <div class="profile-icon">
                        <i class="fas fa-user"></i>
                    </div>

                    <div>
                        <div class="profile-name">
                            <?= e($user["fullname"]); ?>
                        </div>

                        <span class="profile-role">
                            Resident
                        </span>
                    </div>

                </div>

            </div>


            <!-- PAGE CONTENT -->

            <main class="content">

                <!-- PAGE HEADER -->

                <div class="page-heading">

                    <h2>
                        <i class="fas fa-th-large me-2"></i>
                        Resident Dashboard
                    </h2>

                    <p>
                        Monitor your resident account, subscription,
                        and available waste collection services.
                    </p>

                </div>


                <!-- ACCOUNT INFORMATION -->

                <h6 class="section-title">
                    <i class="fas fa-user-circle me-2"></i>
                    My Account
                </h6>

                <div class="row g-4 mb-4">

                    <!-- FULL-WIDTH PROFILE INFORMATION -->
                    <div class="col-12">
                        <section class="profile-information-card">
                            <div class="profile-card-heading">
                                <div class="dashboard-icon"><i class="fas fa-id-card"></i></div>
                                <div>
                                    <h5>Profile Information</h5>
                                    <p class="mb-0">Your registered resident account details.</p>
                                </div>
                            </div>
                            <div class="profile-details-grid">
                                <div class="profile-detail-item">
                                    <div class="profile-detail-icon"><i class="fas fa-hashtag"></i></div>
                                    <div class="profile-detail-content">
                                        <span class="profile-detail-label">Resident ID</span>
                                        <strong><?= e($user["id"]); ?></strong>
                                    </div>
                                </div>
                                <div class="profile-detail-item">
                                    <div class="profile-detail-icon"><i class="fas fa-user"></i></div>
                                    <div class="profile-detail-content">
                                        <span class="profile-detail-label">Full Name</span>
                                        <strong><?= e($user["fullname"]); ?></strong>
                                    </div>
                                </div>
                                <div class="profile-detail-item">
                                    <div class="profile-detail-icon"><i class="fas fa-at"></i></div>
                                    <div class="profile-detail-content">
                                        <span class="profile-detail-label">Username</span>
                                        <strong><?= e($user["users"]); ?></strong>
                                    </div>
                                </div>
                                <div class="profile-detail-item">
                                    <div class="profile-detail-icon"><i class="fas fa-user-check"></i></div>
                                    <div class="profile-detail-content">
                                        <span class="profile-detail-label">Account Type</span>
                                        <strong>Resident</strong>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>


                    <!-- SUBSCRIPTION AND PAYMENT -->

                    <div class="col-12">

                        <div class="dashboard-card billing-card">

                            <div class="dashboard-icon">
                                <i class="fas fa-wallet"></i>
                            </div>

                            <h5>Subscription Billing & Payment</h5>

                            <p>
                                View your subscription balance
                                and latest payment.
                            </p>

                            <div class="billing-columns">
                                <section class="billing-panel">
                            <hr>

                            <h6 class="payment-section-title">
                                <i class="fas fa-file-invoice-dollar me-2"></i>
                                Subscription Billing
                            </h6>

                            <p class="mb-2">
                                Subscription Payment Status
                            </p>

                            <?php if (!$subscription): ?>

                                <span class="payment-status status-unpaid">
                                    NO SUBSCRIPTION
                                </span>

                            <?php else: ?>

                                <span class="payment-status <?= $subscription_payment_badge; ?>">

                                    <?php if ($subscription_is_paid): ?>
                                        <i class="fas fa-check-circle me-1"></i>
                                    <?php endif; ?>

                                    <?= e(strtoupper($subscription_payment_status)); ?>

                                </span>

                            <?php endif; ?>


                            <p class="mt-3 mb-1">
                                Subscription Status
                            </p>

                            <span class="status-badge <?= $subscription_status_badge; ?>">
                                <?= e($subscription_status); ?>
                            </span>


                            <p class="mt-3 mb-1">
                                Subscription Amount
                            </p>

                            <div class="payment-amount">
                                ₱<?= number_format((float) $subscription_total_amount, 2); ?>
                            </div>


                            <div class="detail-row">

                                <span class="detail-label">
                                    Amount Paid
                                </span>

                                <span class="detail-value text-success">
                                    ₱<?= number_format((float) $subscription_amount_paid, 2); ?>
                                </span>

                            </div>


                            <div class="detail-row">

                                <span class="detail-label">
                                    Remaining Balance
                                </span>

                                <span class="detail-value">
                                    ₱<?= number_format((float) $subscription_balance_due, 2); ?>
                                </span>

                            </div>


                            <div class="detail-row">

                                <span class="detail-label">
                                    Subscription Date
                                </span>

                                <span class="detail-value">

                                    <?= $subscription_date
                                        ? e(date("F d, Y", strtotime($subscription_date)))
                                        : "No subscription yet"; ?>

                                </span>

                            </div>


                            <?php if (!$subscription): ?>

                                <div class="warning-box mt-3">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    You have not subscribed yet.
                                    Please visit the Subscription page to register.
                                </div>

                            <?php elseif (!$subscription_is_paid): ?>

                                <div class="warning-box mt-3">
                                    <i class="fas fa-clock me-2"></i>
                                    Your subscription payment is not yet fully recorded.
                                    Please visit the Subscription page for payment information.
                                </div>

                            <?php else: ?>

                                <div class="info-box mt-3">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Your subscription payment is recorded as paid.
                                </div>

                            <?php endif; ?>


                            <a href="subscription.php"
                            class="btn btn-ecotrack w-100 mt-2">

                                <i class="fas fa-file-invoice-dollar me-2"></i>
                                View Subscription Details

                            </a>


                                </section>
                                <section class="billing-panel">
                            <!-- LATEST PAYMENT -->

                            <h6 class="payment-section-title">
                                <i class="fas fa-money-bill-wave me-2"></i>
                                Latest Recorded Payment
                            </h6>

                            <p class="mb-2">
                                Payment Status
                            </p>

                            <span class="payment-status <?= $latest_payment_badge; ?>">
                                <?= e(strtoupper($payment_status)); ?>
                            </span>


                            <p class="mt-3 mb-1">
                                Payment Amount
                            </p>

                            <div class="payment-amount">
                                ₱<?= number_format((float) $payment_amount, 2); ?>
                            </div>


                            <div class="detail-row">

                                <span class="detail-label">
                                    Date Paid
                                </span>

                                <span class="detail-value">

                                    <?= ($is_paid && !empty($paid_at))
                                        ? e(date("F d, Y h:i A", strtotime($paid_at)))
                                        : "No payment recorded"; ?>

                                </span>

                            </div>


                            <?php if (!$is_paid): ?>

                                <div class="warning-box mt-3">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    Your payment has not yet been recorded as paid.
                                    Please contact the administrator if you have already paid.
                                </div>

                            <?php else: ?>

                                <div class="info-box mt-3">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Your payment is recorded as paid.
                                </div>

                            <?php endif; ?>

                                </section>
                            </div>
                        </div>

                    </div>

                </div>


                <!-- FOOTER -->

                <div class="dashboard-footer">

                    © <?= date("Y"); ?> EcoTrack Waste Collection Management System

                    <br>

                    Resident Dashboard

                </div>

            </main>

        </div>


        <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

        </body>
        </html>
