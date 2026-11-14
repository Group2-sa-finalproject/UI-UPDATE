
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| ADMIN LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../index.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is unavailable.');
}

$conn->set_charset('utf8mb4');

$admin_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| GET ADMIN INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id, fullname
    FROM users
    WHERE id = ?
      AND role = 'admin'
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

/*
|--------------------------------------------------------------------------
| HELPER FUNCTION
|--------------------------------------------------------------------------
*/

function adminEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| ALLOWED DASHBOARD PAGES
|--------------------------------------------------------------------------
*/

$page = $_GET['page'] ?? 'overview';

$allowed_pages = [
    'overview',
    'accounts',
    'routes',
    'feedback',
    'tracking',
    'pickup_schedule',
    'special_pickup_requests',

    // NEW MODULES FROM FIRST DASHBOARD
    'subscriptions',
    'households',
    'sales_report'
];

if (!in_array($page, $allowed_pages, true)) {
    $page = 'overview';
}

/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

$total_residents = 0;
$total_collectors = 0;
$total_households = 0;
$total_feedback = 0;

$queries = [
    'total_residents' => "
        SELECT COUNT(*) AS total
        FROM users
        WHERE role = 'residents'
    ",

    'total_collectors' => "
        SELECT COUNT(*) AS total
        FROM users
        WHERE role IN ('collector', 'collectors')
    ",

    'total_households' => "
        SELECT COUNT(*) AS total
        FROM households
    ",

    'total_feedback' => "
        SELECT COUNT(*) AS total
        FROM feedback
    "
];

foreach ($queries as $key => $sql) {
    $result = $conn->query($sql);

    if ($result) {
        $$key = (int) $result->fetch_assoc()['total'];
        $result->free();
    }
}

/*
|--------------------------------------------------------------------------
| SIDEBAR NAVIGATION
|--------------------------------------------------------------------------
*/

$nav = [
    'overview' => [
        'th-large',
        'Dashboard'
    ],

    'accounts' => [
        'users',
        'Accounts'
    ],

    'routes' => [
        'route',
        'Collection Routes'
    ],

    'tracking' => [
        'truck',
        'Live Collector Tracking'
    ],

    'pickup_schedule' => [
        'calendar-alt',
        'Pickup Schedule'
    ],

    'special_pickup_requests' => [
        'calendar-check',
        'Special Pickup Requests'
    ],

    'feedback' => [
        'comment-dots',
        'Resident Feedback'
    ],

    // NEW MODULES

    'subscriptions' => [
        'credit-card',
        'Subscriptions & Payments'
    ],

    'households' => [
        'home',
        'Residents & Households'
    ],

    'sales_report' => [
        'chart-line',
        'Review Sales'
    ]
];

/*
|--------------------------------------------------------------------------
| OVERVIEW STATISTICS CARDS
|--------------------------------------------------------------------------
*/

$cards = [
    [
        'users',
        'Total Residents',
        $total_residents
    ],

    [
        'truck',
        'Total Collectors',
        $total_collectors
    ],

    [
        'house',
        'Total Households',
        $total_households
    ],

    [
        'comment-dots',
        'Total Feedback',
        $total_feedback
    ]
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Admin Dashboard | EcoTrack</title>

<link
    rel="stylesheet"
    href="../assets/vendor/bootstrap/css/bootstrap.min.css"
>

<link
    rel="stylesheet"
    href="../assets/css/style.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>


<style>

/*
|--------------------------------------------------------------------------
| GLOBAL DESIGN
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f4f7f5;
    font-family: Arial, Helvetica, sans-serif;
    color: #263b30;
}

a {
    text-decoration: none;
}

/*
|--------------------------------------------------------------------------
| ECOTRACK SIDEBAR
|--------------------------------------------------------------------------
*/

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

.sidebar-menu li {
    margin-bottom: 8px;
}

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

/*
|--------------------------------------------------------------------------
| MAIN CONTENT
|--------------------------------------------------------------------------
*/

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

.admin-profile .profile-icon {
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

.admin-profile .profile-name {
    font-size: 14px;
    font-weight: 700;
    color: #183d2b;
}

.admin-profile .profile-role {
    display: block;
    font-size: 11px;
    color: #819087;
    font-weight: 500;
}

/*
|--------------------------------------------------------------------------
| PAGE CONTENT
|--------------------------------------------------------------------------
*/

.content {
    padding: 30px;
    min-width: 0;
}

.page-heading {
    margin-bottom: 25px;
}

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

/*
|--------------------------------------------------------------------------
| STATISTICS CARDS
|--------------------------------------------------------------------------
*/

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 20px;
    margin-top: 25px;
}

.dashboard-card {
    background: #fff;
    padding: 25px;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
    border: 1px solid #edf1ee;
    min-width: 0;
    transition: .2s ease;
}

.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 7px 18px rgba(0,0,0,.08);
}

.dashboard-card h6 {
    color: #78877e;
    font-size: 13px;
    margin-bottom: 12px;
    font-weight: 600;
}

.dashboard-card h2 {
    color: #183d2b;
    font-size: 28px;
    font-weight: 800;
    margin: 0;
}

.card-icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e7f5eb;
    color: #17643b;
    margin-bottom: 15px;
    font-size: 19px;
}

/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.dashboard-footer {
    margin-top: 35px;
    padding: 20px 0;
    border-top: 1px solid #e2e9e5;
    color: #929b96;
    font-size: 12px;
    text-align: center;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE DESIGN
|--------------------------------------------------------------------------
*/

@media (max-width: 1200px) {
}

@media (max-width: 1100px) {

    .dashboard-cards {
        grid-template-columns: repeat(2, minmax(0,1fr));
    }

}

@media (max-width: 1000px) {

    .content {
        padding: 20px;
    }

}

@media (max-width: 700px) {

    .sidebar {
        width: 70px;
        padding: 20px 8px;
    }

    .sidebar-logo h3 {
        font-size: 0;
    }

    .sidebar-logo i {
        font-size: 25px;
        margin: 0;
    }

    .sidebar-menu a {
        justify-content: center;
        padding: 13px 8px;
    }

    .sidebar-menu a span {
        display: none;
    }

    .sidebar-menu a i {
        font-size: 18px;
        width: auto;
    }

    .main-content {
        margin-left: 70px;
    }

    .topbar {
        padding: 15px;
    }

    .content {
        padding: 18px 13px;
    }

    .page-heading h2 {
        font-size: 21px;
    }

    .dashboard-card {
        padding: 18px;
    }

    .dashboard-cards {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}

</style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <div class="sidebar-logo">
        <h3>
            <i class="fas fa-recycle"></i>
            EcoTrack
        </h3>
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

                <span>
                    <?= adminEscape($item[1]) ?>
                </span>

            </a>
        </li>

        <?php endforeach; ?>

        <li>
            <a
                href="../logout.php"
                class="logout-link"
                title="Logout"
            >
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>

    </ul>

</aside>

<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">

    <!-- TOPBAR -->

    <div class="topbar">

        <div class="admin-profile">

            <div class="profile-icon">
                <i class="fas fa-user-shield"></i>
            </div>

            <div>

                <div class="profile-name">
                    <?= adminEscape($admin['fullname']) ?>
                </div>

                <span class="profile-role">
                    Administrator
                </span>

            </div>

        </div>

    </div>

    <!-- PAGE CONTENT -->

    <main class="content">

        <?php if ($page === 'overview'): ?>

        <!-- DASHBOARD HEADER -->

        <div class="page-heading">

            <h2>
                Welcome,
                <?= adminEscape($admin['fullname']) ?>!
            </h2>

            <p>
                Monitor and manage your waste collection system.
            </p>

        </div>

        <!-- STATISTICS -->

        <div class="dashboard-cards">

            <?php foreach ($cards as $card): ?>

            <div class="dashboard-card">

                <div class="card-icon">
                    <i class="fas fa-<?= adminEscape($card[0]) ?>"></i>
                </div>

                <h6>
                    <?= adminEscape($card[1]) ?>
                </h6>

                <h2>
                    <?= number_format((int) $card[2]) ?>
                </h2>

            </div>

            <?php endforeach; ?>

        </div>
<!-- =================================================
             ACCOUNTS
        ================================================= -->

        <?php elseif ($page === 'accounts'): ?>

            <?php include __DIR__ . '/accounts.php'; ?>

        <!-- =================================================
             FEEDBACK
        ================================================= -->

        <?php elseif ($page === 'feedback'): ?>

            <?php include __DIR__ . '/feedback.php'; ?>

        <!-- =================================================
             COLLECTION ROUTES
        ================================================= -->

        <?php elseif ($page === 'routes'): ?>

            <?php include __DIR__ . '/admin_routes.php'; ?>

        <!-- =================================================
             LIVE COLLECTOR TRACKING
        ================================================= -->

        <?php elseif ($page === 'tracking'): ?>

            <?php include __DIR__ . '/track_trucks.php'; ?>

        <!-- =================================================
             PICKUP SCHEDULE
        ================================================= -->

        <?php elseif ($page === 'pickup_schedule'): ?>

            <?php include __DIR__ . '/pickup_schedule.php'; ?>

        <!-- =================================================
             SPECIAL PICKUP REQUESTS
        ================================================= -->

        <?php elseif ($page === 'special_pickup_requests'): ?>

            <?php include __DIR__ . '/special_pickup_requests.php'; ?>

        <!-- =================================================
             SUBSCRIPTIONS AND PAYMENTS
        ================================================= -->

        <?php elseif ($page === 'subscriptions'): ?>

            <?php
            $module_file = __DIR__ . '/subscriptions/index.php';

            if (is_file($module_file)) {
                include $module_file;
            } else {
                echo '<div class="alert alert-warning">
                    Subscriptions module file was not found.
                </div>';
            }
            ?>

        <!-- =================================================
             RESIDENTS AND HOUSEHOLDS
        ================================================= -->

        <?php elseif ($page === 'households'): ?>

            <?php
            $module_file = __DIR__ . '/households.php';

            if (is_file($module_file)) {
                include $module_file;
            } else {
                echo '<div class="alert alert-warning">
                    Households module file was not found.
                </div>';
            }
            ?>

        <!-- =================================================
             REVIEW SALES
        ================================================= -->

        <?php elseif ($page === 'sales_report'): ?>

            <?php
            $module_file = __DIR__ . '/sales_report.php';

            if (is_file($module_file)) {
                include $module_file;
            } else {
                echo '<div class="alert alert-warning">
                    Sales report module file was not found.
                </div>';
            }
            ?>

        <?php endif; ?>

        <!-- FOOTER -->

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
