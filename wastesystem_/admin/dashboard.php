<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/* ADMIN ACCESS */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is unavailable.');
}

$conn->set_charset('utf8mb4');

$admin_id = (int) $_SESSION['user_id'];

/* GET ADMIN INFORMATION */
$stmt = $conn->prepare("
    SELECT id, fullname
    FROM users
    WHERE id = ? AND role = 'admin'
    LIMIT 1
");

if (!$stmt) {
    die('Unable to retrieve admin information.');
}

$stmt->bind_param('i', $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    session_unset();
    session_destroy();
    header('Location: ../index.php');
    exit;
}

/* HELPER FUNCTIONS */
function adminEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* Return a single COUNT(*) value. Failed optional queries safely return zero. */
function adminCount(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return (int)($row['total'] ?? 0);
}

/* Return a numeric SUM value. */
function adminSum(mysqli $conn, string $sql): float
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return (float)($row['total'] ?? 0);
}

/* PAGE ROUTING */
$page = $_GET['page'] ?? 'overview';

$allowed_pages = [
    'overview',
    'accounts',
    'routes',
    'feedback',
    'tracking',
    'pickup_schedule',
    'special_pickup_requests',
    'subscriptions',
    'households',
    'sales_report'
];

if (!in_array($page, $allowed_pages, true)) {
    $page = 'overview';
}

/* DASHBOARD COUNTS
   These queries use the tables and columns used by the current WMS modules.
*/

/* General overview */
$total_residents = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'residents'
");

$total_households = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM households
");

/* Total collections means collection schedules marked Completed. */
$total_collections = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM collection_schedules
    WHERE status = 'Completed'
");

/* Accounts */
$total_admins = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'admin'
");

$total_collectors = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'collector'
");

$total_accounts = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM users
");

/* Collection schedules */
$total_schedules = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM collection_schedules
");

$pending_schedules = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM collection_schedules
    WHERE status = 'Pending'
");

$approved_schedules = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM collection_schedules
    WHERE status = 'Approved'
");

$completed_schedules = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM collection_schedules
    WHERE status = 'Completed'
");

/* Special pickup requests */
$total_special_requests = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM collection_schedules
");

$pending_special_requests = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM collection_schedules
    WHERE LOWER(TRIM(status)) = 'pending'
");

$approved_special_requests = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM collection_schedules
    WHERE LOWER(TRIM(status)) IN ('approved', 'scheduled')
");

/* Collection routes */
$total_routes = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM collection_routes
");

$active_routes = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM collection_routes
    WHERE LOWER(status) IN ('active', 'assigned', 'in_progress')
");

$total_route_stops = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM route_stops
");

/* Feedback table stores feedback_type, not a review-status field. */
$total_feedback = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM feedback
");

$complaint_feedback = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM feedback
    WHERE LOWER(feedback_type) = 'complaint'
");

$suggestion_feedback = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM feedback
    WHERE LOWER(feedback_type) = 'suggestion'
");

$satisfaction_feedback = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM feedback
    WHERE LOWER(feedback_type) = 'satisfaction'
");

/* Sales: subscriptions is the source of subscription sales records. */
$total_transactions = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM subscriptions
");

$paid_transactions = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM subscriptions
    WHERE LOWER(payment_status) = 'paid'
");

$unpaid_transactions = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM subscriptions
    WHERE LOWER(payment_status) = 'unpaid'
");

$pending_transactions = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM subscriptions
    WHERE LOWER(payment_status) LIKE '%pending%'
");

/* Subscription management summary (based on the Manage Subscriptions module). */
$total_subscription_records = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM subscriptions
");

$pending_payment_confirmations = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM subscriptions
    WHERE payment_status = 'Pending Confirmation'
");

$active_paid_subscriptions = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM subscriptions
    WHERE subscription_status = 'Active'
      AND payment_status = 'Paid'
      AND balance_due <= 0
");

$subscription_approval_logs = adminCount($conn, "
    SELECT COUNT(*) AS total
    FROM subscription_approval_history
");

$total_revenue = adminSum($conn, "
    SELECT COALESCE(SUM(amount_paid), 0) AS total
    FROM subscriptions
    WHERE LOWER(payment_status) = 'paid'
");

/* SIDEBAR NAVIGATION */
$nav = [
    'overview' => ['th-large', 'Dashboard'],
    'accounts' => ['users', 'Accounts'],
    'routes' => ['route', 'Collection Routes'],
    'tracking' => ['truck', 'Live Collector Tracking'],
    'pickup_schedule' => ['calendar-alt', 'Pickup Schedule'],
    'special_pickup_requests' => ['calendar-check', 'Special Pickup Requests'],
    'feedback' => ['comment-dots', 'Resident Feedback'],
    'subscriptions' => ['credit-card', 'Subscriptions & Payments'],
    'households' => ['home', 'Residents & Households'],
    'sales_report' => ['chart-line', 'Review Sales']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard | EcoTrack</title>

<link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    background: #f4f7f5;
    font-family: Arial, Helvetica, sans-serif;
    color: #263b30;
}

a { text-decoration: none; }

/* SIDEBAR */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background: #123d2b;
    color: #fff;
    padding: 25px 15px;
    overflow-y: auto;
    z-index: 1000;
}

.sidebar-logo {
    text-align: center;
    margin-bottom: 35px;
}

.sidebar-logo h3 {
    font-size: 23px;
    font-weight: 800;
    color: #fff;
    margin: 0;
}

.sidebar-logo i {
    color: #9be7ad;
    margin-right: 7px;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-menu li { margin-bottom: 8px; }

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 15px;
    color: #d8e8df;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    transition: .2s ease;
}

.sidebar-menu a i {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.sidebar-menu a:hover {
    background: rgba(255,255,255,.12);
    color: #fff;
}

.sidebar-menu a.active {
    background: #fff;
    color: #123d2b;
}

.sidebar-menu .logout-link {
    margin-top: 25px;
    color: #ffcccc;
}

.sidebar-menu .logout-link:hover {
    background: rgba(255,255,255,.12);
    color: #fff;
}

/* MAIN LAYOUT */
.main-content {
    margin-left: 250px;
    min-height: 100vh;
    min-width: 0;
}

.topbar {
    background: #fff;
    padding: 18px 30px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    border-bottom: 1px solid #e5eae6;
    min-height: 75px;
}

.admin-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}

.profile-icon {
    width: 40px;
    height: 40px;
    background: #e3f3e8;
    color: #17643b;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 17px;
}

.profile-name {
    font-size: 14px;
    font-weight: 700;
    color: #183d2b;
}

.profile-role {
    display: block;
    font-size: 11px;
    color: #819087;
    font-weight: 500;
}

.content {
    padding: 30px;
    min-width: 0;
}

.page-heading { margin-bottom: 25px; }

.page-heading h2 {
    font-size: 26px;
    color: #183d2b;
    font-weight: 800;
    margin-bottom: 7px;
}

.page-heading p {
    color: #78877e;
    font-size: 14px;
    margin: 0;
}

/* ONE COMBINED CARD PER DASHBOARD SECTION */
.overview-section { margin-bottom: 24px; }

.overview-section-title {
    color: #183d2b;
    font-size: 17px;
    font-weight: 800;
    margin: 0 0 12px;
}

.overview-card {
    background: #fff;
    border: 1px solid #edf1ee;
    border-radius: 13px;
    padding: 22px 24px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
}

.overview-card-top {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.overview-card-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e7f5eb;
    color: #17643b;
    font-size: 18px;
}

.overview-card-top h4 {
    color: #183d2b;
    font-size: 16px;
    font-weight: 800;
    margin: 0 0 3px;
}

.overview-card-top p {
    color: #78877e;
    font-size: 12px;
    margin: 0;
}

.overview-metrics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.metric {
    min-width: 0;
    padding: 13px 15px;
    background: #f7faf8;
    border: 1px solid #edf2ee;
    border-radius: 9px;
}

.metric-value {
    display: block;
    color: #183d2b;
    font-size: 25px;
    font-weight: 800;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

.metric-label {
    display: block;
    color: #78877e;
    font-size: 12px;
    font-weight: 600;
    margin-top: 6px;
}

.metric.money .metric-value { color: #198754; }

.section-link {
    display: inline-block;
    margin-top: 16px;
    color: #198754;
    font-size: 12px;
    font-weight: 700;
}

.section-link:hover { color: #125c38; }

/* FOOTER */
.dashboard-footer {
    margin-top: 35px;
    padding: 20px 0;
    border-top: 1px solid #e2e9e5;
    color: #929b96;
    font-size: 12px;
    text-align: center;
}

/* RESPONSIVE */
@media (max-width: 1100px) {
    .overview-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 700px) {
    .sidebar {
        width: 70px;
        padding: 20px 8px;
    }

    .sidebar-logo h3 { font-size: 0; }
    .sidebar-logo i { font-size: 25px; margin: 0; }

    .sidebar-menu a {
        justify-content: center;
        padding: 13px 8px;
    }

    .sidebar-menu a span { display: none; }
    .sidebar-menu a i { font-size: 18px; width: auto; }

    .main-content { margin-left: 70px; }
    .topbar { padding: 15px; }
    .content { padding: 18px 13px; }
    .page-heading h2 { font-size: 21px; }
    .overview-card { padding: 17px; }
    .overview-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .metric { padding: 11px; }
    .metric-value { font-size: 22px; }
}

@media (max-width: 380px) {
    .overview-metrics { grid-template-columns: 1fr; }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <h3><i class="fas fa-recycle"></i> EcoTrack</h3>
    </div>

    <ul class="sidebar-menu">
        <?php foreach ($nav as $key => $item): ?>
            <li>
                <a
                    href="dashboard.php?page=<?= adminEscape($key) ?>"
                    class="<?= $page === $key ? 'active' : '' ?>"
                    <?= $page === $key ? 'aria-current="page"' : '' ?>
                    title="<?= adminEscape($item[1]) ?>"
                >
                    <i class="fas fa-<?= adminEscape($item[0]) ?>"></i>
                    <span><?= adminEscape($item[1]) ?></span>
                </a>
            </li>
        <?php endforeach; ?>

        <li>
            <a href="../logout.php" class="logout-link" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

<div class="main-content">
    <div class="topbar">
        <div class="admin-profile">
            <div class="profile-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <div>
                <div class="profile-name"><?= adminEscape($admin['fullname']) ?></div>
                <span class="profile-role">Administrator</span>
            </div>
        </div>
    </div>

    <main class="content">

    <?php if ($page === 'overview'): ?>

        <div class="page-heading">
            <h2>Welcome, <?= adminEscape($admin['fullname']) ?>!</h2>
            <p>Monitor and manage your waste collection system.</p>
        </div>

        <!-- GENERAL OVERVIEW: feedback intentionally excluded here -->
        <section class="overview-section">
            <h3 class="overview-section-title">General Overview</h3>
            <div class="overview-card">
                <div class="overview-card-top">
                    <div class="overview-card-icon"><i class="fas fa-chart-pie"></i></div>
                    <div>
                        <h4>System Overview</h4>
                        <p>Quick summary of residents, households, and completed collections.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_residents) ?></span>
                        <span class="metric-label">Total Residents</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_collections) ?></span>
                        <span class="metric-label">Completed Collections</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_households) ?></span>
                        <span class="metric-label">Registered Households</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ACCOUNT OVERVIEW -->
        <section class="overview-section">
            <h3 class="overview-section-title">Account Management</h3>
            <div class="overview-card">
                <div class="overview-card-top">
                    <div class="overview-card-icon"><i class="fas fa-users"></i></div>
                    <div>
                        <h4>Accounts Overview</h4>
                        <p>Summary of registered system accounts by role.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_admins) ?></span>
                        <span class="metric-label">Administrators</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_collectors) ?></span>
                        <span class="metric-label">Collectors</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_residents) ?></span>
                        <span class="metric-label">Residents</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_accounts) ?></span>
                        <span class="metric-label">Total Accounts</span>
                    </div>
                </div>
                <a class="section-link" href="dashboard.php?page=accounts">Manage Accounts <i class="fas fa-arrow-right"></i></a>
            </div>
        </section>

        <!-- COLLECTION SCHEDULE OVERVIEW -->
        <section class="overview-section">
            <h3 class="overview-section-title">Collection Management</h3>
            <div class="overview-card">
                <div class="overview-card-top">
                    <div class="overview-card-icon"><i class="fas fa-calendar-check"></i></div>
                    <div>
                        <h4>Collection Schedule Overview</h4>
                        <p>Summary of resident collection schedules and their current status.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_schedules) ?></span>
                        <span class="metric-label">Total Schedules</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($pending_schedules) ?></span>
                        <span class="metric-label">Pending Requests</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($approved_schedules) ?></span>
                        <span class="metric-label">Approved Requests</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($completed_schedules) ?></span>
                        <span class="metric-label">Completed Collections</span>
                    </div>
                </div>
                <a class="section-link" href="dashboard.php?page=pickup_schedule">Manage Pickup Schedule <i class="fas fa-arrow-right"></i></a>
            </div>
        </section>

        <!-- SPECIAL PICKUP OVERVIEW -->
        <section class="overview-section">
            <h3 class="overview-section-title">Special Pickup Requests</h3>
            <div class="overview-card">
                <div class="overview-card-top">
                    <div class="overview-card-icon"><i class="fas fa-truck-pickup"></i></div>
                    <div>
                        <h4>Special Pickup Overview</h4>
                        <p>Summary of additional pickup requests submitted by residents.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_special_requests) ?></span>
                        <span class="metric-label">Total Requests</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($pending_special_requests) ?></span>
                        <span class="metric-label">Pending Requests</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($approved_special_requests) ?></span>
                        <span class="metric-label">Approved / Scheduled</span>
                    </div>
                </div>
                <a class="section-link" href="dashboard.php?page=special_pickup_requests">Review Special Pickup Requests <i class="fas fa-arrow-right"></i></a>
            </div>
        </section>

        <!-- ROUTE OVERVIEW -->
        <section class="overview-section">
            <h3 class="overview-section-title">Route Management</h3>
            <div class="overview-card">
                <div class="overview-card-top">
                    <div class="overview-card-icon"><i class="fas fa-route"></i></div>
                    <div>
                        <h4>Collection Route Overview</h4>
                        <p>Summary of generated routes, active assignments, and route stops.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_routes) ?></span>
                        <span class="metric-label">Total Routes</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($active_routes) ?></span>
                        <span class="metric-label">Active / Assigned Routes</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_route_stops) ?></span>
                        <span class="metric-label">Total Route Stops</span>
                    </div>
                </div>
                <a class="section-link" href="dashboard.php?page=routes">Manage Collection Routes <i class="fas fa-arrow-right"></i></a>
            </div>
        </section>

        <!-- FEEDBACK OVERVIEW -->
        <section class="overview-section">
            <h3 class="overview-section-title">Resident Feedback</h3>
            <div class="overview-card">
                <div class="overview-card-top">
                    <div class="overview-card-icon"><i class="fas fa-comments"></i></div>
                    <div>
                        <h4>Feedback Overview</h4>
                        <p>Breakdown of feedback by the types stored in the feedback table.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_feedback) ?></span>
                        <span class="metric-label">Total Feedbacks</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($complaint_feedback) ?></span>
                        <span class="metric-label">Complaints</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($suggestion_feedback) ?></span>
                        <span class="metric-label">Suggestions</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($satisfaction_feedback) ?></span>
                        <span class="metric-label">Satisfaction</span>
                    </div>
                </div>
                <a class="section-link" href="dashboard.php?page=feedback">Review Resident Feedback <i class="fas fa-arrow-right"></i></a>
            </div>
        </section>

        <!-- SUBSCRIPTION MANAGEMENT OVERVIEW -->
        <section class="overview-section">
            <h3 class="overview-section-title">Subscription Management</h3>
            <div class="overview-card">
                <div class="overview-card-top">
                    <div class="overview-card-icon"><i class="fas fa-credit-card"></i></div>
                    <div>
                        <h4>Subscriptions and Payments Overview</h4>
                        <p>Summary of resident subscription records, payment confirmations, active subscriptions, and approval history.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_subscription_records) ?></span>
                        <span class="metric-label">All Subscription Records</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($pending_payment_confirmations) ?></span>
                        <span class="metric-label">Awaiting Payment Confirmation</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($active_paid_subscriptions) ?></span>
                        <span class="metric-label">Active and Paid Subscriptions</span>
                    </div>
                </div>
                <a class="section-link" href="dashboard.php?page=subscriptions">Manage Subscriptions and Payments <i class="fas fa-arrow-right"></i></a>
            </div>
        </section>

        <!-- SALES OVERVIEW -->
        <section class="overview-section">
            <h3 class="overview-section-title">Sales Management</h3>
            <div class="overview-card">
                <div class="overview-card-top">
                    <div class="overview-card-icon"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <h4>Sales Overview</h4>
                        <p>Summary of subscription transactions and collected payments.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <div class="metric money">
                        <span class="metric-value">₱<?= number_format($total_revenue, 2) ?></span>
                        <span class="metric-label">Revenue Collected</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($total_transactions) ?></span>
                        <span class="metric-label">Total Transactions</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($paid_transactions) ?></span>
                        <span class="metric-label">Paid</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?= number_format($unpaid_transactions + $pending_transactions) ?></span>
                        <span class="metric-label">Unpaid / Pending</span>
                    </div>
                </div>
                <a class="section-link" href="dashboard.php?page=sales_report">Review Sales <i class="fas fa-arrow-right"></i></a>
            </div>
        </section>

    <?php elseif ($page === 'accounts'): ?>

        <?php include __DIR__ . '/accounts.php'; ?>

    <?php elseif ($page === 'feedback'): ?>

        <?php include __DIR__ . '/feedback.php'; ?>

    <?php elseif ($page === 'routes'): ?>

        <?php include __DIR__ . '/admin_routes.php'; ?>

    <?php elseif ($page === 'tracking'): ?>

        <?php include __DIR__ . '/track_trucks.php'; ?>

    <?php elseif ($page === 'pickup_schedule'): ?>

        <?php include __DIR__ . '/pickup_schedule.php'; ?>

    <?php elseif ($page === 'special_pickup_requests'): ?>

        <?php include __DIR__ . '/special_pickup_requests.php'; ?>

    <?php elseif ($page === 'subscriptions'): ?>

        <?php
        $module_file = __DIR__ . '/subscriptions/index.php';
        if (is_file($module_file)) {
            include $module_file;
        } else {
            echo '<div class="alert alert-warning">Subscriptions module file was not found.</div>';
        }
        ?>

    <?php elseif ($page === 'households'): ?>

        <?php
        $module_file = __DIR__ . '/households.php';
        if (is_file($module_file)) {
            include $module_file;
        } else {
            echo '<div class="alert alert-warning">Households module file was not found.</div>';
        }
        ?>

    <?php elseif ($page === 'sales_report'): ?>

        <?php
        $module_file = __DIR__ . '/sales_report.php';
        if (is_file($module_file)) {
            include $module_file;
        } else {
            echo '<div class="alert alert-warning">Sales report module file was not found.</div>';
        }
        ?>

    <?php endif; ?>

        <div class="dashboard-footer">
            Waste Collection Management System by Group 2
            &copy; <?= date('Y') ?>
            <br>
            Admin Dashboard
        </div>

    </main>
</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
