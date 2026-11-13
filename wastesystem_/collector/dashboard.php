
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// COLLECTOR LOGIN CHECK
if (
    !isset($_SESSION['user_id']) ||
    !in_array(
        $_SESSION['role'] ?? '',
        ['collector', 'collectors'],
        true
    )
) {
    header('Location: ../index.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is unavailable.');
}

$conn->set_charset('utf8mb4');

$user_id = (int) $_SESSION['user_id'];

// GET COLLECTOR INFORMATION
$stmt = $conn->prepare("
    SELECT id, fullname, users
    FROM users
    WHERE id = ?
      AND role IN ('collector', 'collectors')
    LIMIT 1
");

if (!$stmt) {
    die('Unable to retrieve collector information.');
}

$stmt->bind_param('i', $user_id);
$stmt->execute();

$collector = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$collector) {
    session_unset();
    session_destroy();

    header('Location: ../index.php');
    exit;
}

// HTML ESCAPE FUNCTION
function collector_escape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$collector_name = $collector['fullname'];

// ALLOWED DASHBOARD PAGES
$selected_page = $_GET['page'] ?? 'dashboard';

$allowed_pages = [
    'dashboard',
    'regular_route',
    'special_pickup',
    'qr_scanner'
];

if (!in_array($selected_page, $allowed_pages, true)) {
    $selected_page = 'dashboard';
}

// SIDEBAR NAVIGATION
$nav = [
    [
        'href' => 'dashboard.php',
        'icon' => 'fa-th-large',
        'label' => 'Dashboard',
        'active' => $selected_page === 'dashboard'
    ],
    [
        'href' => 'dashboard.php?page=regular_route',
        'icon' => 'fa-route',
        'label' => 'Regular Collection Route',
        'active' => $selected_page === 'regular_route'
    ],
    [
        'href' => 'dashboard.php?page=special_pickup',
        'icon' => 'fa-calendar-check',
        'label' => 'Special Pickup Requests',
        'active' => $selected_page === 'special_pickup'
    ],
    [
        'href' => 'dashboard.php?page=qr_scanner',
        'icon' => 'fa-qrcode',
        'label' => 'QR Code Scanner',
        'active' => $selected_page === 'qr_scanner'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Collector Dashboard | EcoTrack</title>

<link rel="stylesheet"
href="../assets/vendor/bootstrap/css/bootstrap.min.css">

<link rel="stylesheet"
href="../assets/css/style.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
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

/* SIDEBAR */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background: #123d2b;
    color: white;
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
    color: white;
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
    color: white;
}

.sidebar-menu a.active {
    background: white;
    color: #123d2b;
}

.sidebar-menu .logout-link {
    margin-top: 25px;
    color: #ffcccc;
}

.sidebar-menu .logout-link:hover {
    background: rgba(255,255,255,.12);
    color: white;
}

/* MAIN CONTENT */

.main-content {
    margin-left: 250px;
    min-height: 100vh;
}

.topbar {
    background: white;
    padding: 18px 30px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    border-bottom: 1px solid #e5eae6;
    min-height: 75px;
}

.collector-profile {
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
    align-items: center;
    justify-content: center;
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
}

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

/* DASHBOARD CARDS */

.dashboard-card {
    background: white;
    padding: 25px;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
    border: 1px solid #edf1ee;
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: .2s ease;
}

.dashboard-card:hover {
    box-shadow: 0 6px 18px rgba(0,0,0,.08);
}

.dashboard-card h5 {
    font-size: 18px;
    font-weight: 800;
    color: #183d2b;
    margin-bottom: 12px;
}

.dashboard-card p {
    color: #78877e;
    font-size: 13px;
    line-height: 1.6;
    flex-grow: 1;
}

.dashboard-icon {
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

.btn-ecotrack {
    background: #17643b;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 11px 18px;
    font-size: 13px;
    font-weight: 700;
    align-self: flex-start;
}

.btn-ecotrack:hover {
    background: #104b2c;
    color: white;
}

/* EMBEDDED PAGES */

.route-frame {
    display: block;
    width: 100%;
    min-height: 1100px;
    border: 0;
    background: transparent;
    border-radius: 13px;
}

.scanner-frame {
    display: block;
    width: 100%;
    min-height: 850px;
    border: 0;
    background: transparent;
    border-radius: 13px;
}

/* GPS STATUS */

.gps-status {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    background: white;
    border: 1px solid #edf1ee;
    border-radius: 13px;
    padding: 20px 24px;
    margin-bottom: 25px;
}

.gps-status .dashboard-icon {
    flex-shrink: 0;
    margin: 0;
}

.gps-status h5 {
    font-size: 16px;
    font-weight: 800;
    color: #183d2b;
    margin-bottom: 6px;
}

.gps-status p {
    font-size: 13px;
    margin: 0;
    color: #78877e;
}

.gps-active {
    color: #17643b !important;
}

.gps-error {
    color: #b42332 !important;
}

.dashboard-footer {
    margin-top: 35px;
    padding: 20px 0;
    border-top: 1px solid #e2e9e5;
    color: #929b96;
    font-size: 12px;
    text-align: center;
}

/* RESPONSIVE */

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
        width: auto;
        font-size: 18px;
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

    .route-frame {
        min-height: 950px;
    }

    .scanner-frame {
        min-height: 750px;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar" aria-label="Collector navigation">

    <div class="sidebar-logo">
        <h3>
            <i class="fas fa-recycle"></i>
            EcoTrack
        </h3>
    </div>

    <ul class="sidebar-menu">

        <?php foreach ($nav as $item): ?>

        <li>
            <a
                href="<?= collector_escape($item['href']) ?>"
                class="<?= $item['active'] ? 'active' : '' ?>"
                title="<?= collector_escape($item['label']) ?>"
                <?= $item['active'] ? 'aria-current="page"' : '' ?>
            >
                <i class="fas <?= collector_escape($item['icon']) ?>"></i>

                <span>
                    <?= collector_escape($item['label']) ?>
                </span>
            </a>
        </li>

        <?php endforeach; ?>

        <li>
            <a href="../logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>

    </ul>

</aside>

<!-- MAIN CONTENT -->

<div class="main-content">

    <header class="topbar">

        <div class="collector-profile">

            <div class="profile-icon">
                <i class="fas fa-user"></i>
            </div>

            <div>
                <div class="profile-name">
                    <?= collector_escape($collector_name) ?>
                </div>

                <span class="profile-role">
                    Collector
                </span>
            </div>

        </div>

    </header>

    <main class="content">

        <?php if ($selected_page === 'regular_route'): ?>

            <!-- REGULAR COLLECTION ROUTE -->

            <div class="page-heading">
                <h2>Regular Collection Route</h2>

                <p>
                    View your assigned collection routes and household stops.
                </p>
            </div>

            <iframe
                class="route-frame"
                src="regular_route.php"
                title="Regular Collection Route"
                loading="eager"
                allow="geolocation">
            </iframe>

        <?php elseif ($selected_page === 'special_pickup'): ?>

            <!-- SPECIAL PICKUP REQUESTS -->

            <div class="page-heading">
                <h2>Special Pickup Requests</h2>

                <p>
                    View your approved and assigned pickup requests.
                </p>
            </div>

            <iframe
                class="route-frame"
                src="route.php"
                title="Special Pickup Requests"
                loading="eager"
                allow="geolocation">
            </iframe>

        <?php elseif ($selected_page === 'qr_scanner'): ?>

            <!-- QR CODE SCANNER -->

            <div class="page-heading">
                <h2>QR Code Scanner</h2>

                <p>
                    Scan household QR codes to verify registered residents.
                </p>
            </div>

            <iframe
                class="scanner-frame"
                src="scanner.php"
                title="QR Code Scanner"
                loading="eager"
                allow="camera">
            </iframe>

        <?php else: ?>

            <!-- DASHBOARD OVERVIEW -->

            <div class="page-heading">
                <h2>Collector Dashboard</h2>

                <p>
                    Welcome, <?= collector_escape($collector_name) ?>!
                    Manage your assigned waste collection tasks.
                </p>
            </div>

            <div class="gps-status" role="status" aria-live="polite">

                <div class="dashboard-icon">
                    <i class="fas fa-location-dot"></i>
                </div>

                <div>
                    <h5>Live GPS Tracking</h5>

                    <p id="locationStatus">
                        Waiting for GPS permission...
                    </p>
                </div>

            </div>

            <h3 class="fw-bold mb-3"
                style="font-size:17px;color:#45564d;">
                Collection Services
            </h3>

            <div class="row g-4">

                <!-- REGULAR COLLECTION ROUTE CARD -->

                <div class="col-lg-4 col-md-6">

                    <div class="dashboard-card">

                        <div class="dashboard-icon">
                            <i class="fas fa-route"></i>
                        </div>

                        <h5>Regular Collection Route</h5>

                        <p>
                            View your admin-assigned route,
                            household locations, and collection stops.
                        </p>

                        <a
                            href="dashboard.php?page=regular_route"
                            class="btn btn-ecotrack">
                            <i class="fas fa-arrow-right me-1"></i>
                            View Regular Route
                        </a>

                    </div>

                </div>

                <!-- SPECIAL PICKUP REQUESTS CARD -->

                <div class="col-lg-4 col-md-6">

                    <div class="dashboard-card">

                        <div class="dashboard-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>

                        <h5>Special Pickup Requests</h5>

                        <p>
                            View your approved pickup requests
                            and household destinations.
                        </p>

                        <a
                            href="dashboard.php?page=special_pickup"
                            class="btn btn-ecotrack">
                            <i class="fas fa-arrow-right me-1"></i>
                            View Route
                        </a>

                    </div>

                </div>

                <!-- QR CODE SCANNER CARD -->

                <div class="col-lg-4 col-md-6">

                    <div class="dashboard-card">

                        <div class="dashboard-icon">
                            <i class="fas fa-qrcode"></i>
                        </div>

                        <h5>QR Code Scanner</h5>

                        <p>
                            Scan household QR codes to verify
                            registered residents before collection.
                        </p>

                        <a
                            href="dashboard.php?page=qr_scanner"
                            class="btn btn-ecotrack">
                            <i class="fas fa-arrow-right me-1"></i>
                            Open Scanner
                        </a>

                    </div>

                </div>

            </div>

        <?php endif; ?>

        <footer class="dashboard-footer">
            &copy; <?= date('Y') ?>
            EcoTrack Waste Collection Management System
            <br>
            Collector Dashboard
        </footer>

    </main>

</div>

<script>
const locationStatus = document.getElementById('locationStatus');

function setLocationStatus(message, state) {
    if (!locationStatus) return;

    locationStatus.textContent = message;
    locationStatus.className = state || '';
}

// COLLECTOR GPS TRACKING
if (locationStatus && 'geolocation' in navigator) {

    navigator.geolocation.watchPosition(

        async function(position) {

            const latitude = position.coords.latitude;
            const longitude = position.coords.longitude;

            try {

                const response = await fetch(
                    'update_collector_location.php',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            latitude,
                            longitude
                        })
                    }
                );

                const result = await response.json();

                if (response.ok && result.success) {

                    setLocationStatus(
                        'GPS Active — Location updated',
                        'gps-active'
                    );

                } else {

                    setLocationStatus(
                        result.message || 'Unable to update location.',
                        'gps-error'
                    );
                }

            } catch (error) {

                setLocationStatus(
                    'Connection error. Unable to update location.',
                    'gps-error'
                );

                console.error(
                    'Collector GPS update error:',
                    error
                );
            }
        },

        function(error) {

            setLocationStatus(
                'GPS error: ' + error.message,
                'gps-error'
            );
        },

        {
            enableHighAccuracy: true,
            maximumAge: 5000,
            timeout: 15000
        }
    );

} else if (locationStatus) {

    setLocationStatus(
        'GPS is not supported by this browser.',
        'gps-error'
    );
}
</script>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>
