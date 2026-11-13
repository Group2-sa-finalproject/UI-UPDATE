
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

$conn->set_charset("utf8mb4");

$resident_id = (int) $_SESSION["user_id"];

// GET RESIDENT INFORMATION
$stmt = $conn->prepare("
    SELECT id, fullname
    FROM users
    WHERE id = ?
    AND role = 'residents'
    LIMIT 1
");

if (!$stmt) {
    die("Resident query failed: " . $conn->error);
}

$stmt->bind_param("i", $resident_id);
$stmt->execute();

$resident = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resident) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

// ESCAPE OUTPUT
function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Track Garbage Truck | EcoTrack</title>

<link rel="stylesheet"
href="../assets/vendor/bootstrap/css/bootstrap.min.css">

<link rel="stylesheet"
href="../assets/css/style.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>


/* =========================================================
   GENERAL
========================================================= */

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

/* =========================================================
   SIDEBAR
========================================================= */

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

/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content {
    margin-left: 250px;
    min-height: 100vh;
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

.resident-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}

.resident-profile .profile-icon {
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

.resident-profile .profile-name {
    font-size: 14px;
    font-weight: 700;
    color: #183d2b;
}

.resident-profile .profile-role {
    display: block;
    font-size: 11px;
    color: #819087;
    font-weight: 500;
}

.content {
    padding: 30px;
    min-width: 0;
}

/* =========================================================
   PAGE HEADER
========================================================= */

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

/* =========================================================
   SECTION TITLE
========================================================= */

.section-title {
    color: #45564d;
    font-size: 17px;
    font-weight: 700;
    margin: 30px 0 18px;
}

/* =========================================================
   DASHBOARD CARDS
========================================================= */

.dashboard-card {
    background: #fff;
    padding: 25px;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
    border: 1px solid #edf1ee;
    height: 100%;
    transition: .2s ease;
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

/* =========================================================
   SERVICE CARDS
========================================================= */

.service-card {
    display: flex;
    flex-direction: column;
}

.service-card p {
    flex-grow: 1;
}

.service-card .btn {
    margin-top: 12px;
}

/* =========================================================
   ACCOUNT TABLE
========================================================= */

.info-table {
    margin-bottom: 0;
    border-color: #edf1ee;
}

.info-table th {
    width: 35%;
    color: #52635a;
    font-weight: 600;
    font-size: 14px;
    padding: 12px;
    background: #f8faf8;
}

.info-table td {
    color: #40594a;
    font-size: 14px;
    padding: 12px;
}

/* =========================================================
   DETAIL ROWS
========================================================= */

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    padding: 13px 0;
    border-bottom: 1px solid #edf1ee;
    font-size: 14px;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    color: #78877e;
}

.detail-value {
    color: #183d2b;
    font-weight: 700;
    text-align: right;
    overflow-wrap: anywhere;
}

/* =========================================================
   STATUS BADGES
========================================================= */

.payment-status,
.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
}

.status-paid {
    background: #d8f3df;
    color: #17643b;
}

.status-unpaid {
    background: #f8d7da;
    color: #842029;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-default {
    background: #e9ecef;
    color: #495057;
}

/* =========================================================
   PAYMENT
========================================================= */

.payment-amount {
    font-size: 26px;
    font-weight: 800;
    color: #183d2b;
    margin-top: 3px;
}

.payment-section-title {
    font-size: 15px;
    font-weight: 700;
    color: #183d2b;
    margin-bottom: 15px;
}

/* =========================================================
   BUTTONS
========================================================= */

.btn-ecotrack {
    background: #17643b;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 11px 18px;
    font-size: 13px;
    font-weight: 700;
    transition: .2s;
}

.btn-ecotrack:hover {
    background: #104b2c;
    color: #fff;
}

.btn-outline-ecotrack {
    border: 1px solid #17643b;
    color: #17643b;
    background: #fff;
    border-radius: 8px;
    padding: 10px 17px;
    font-size: 13px;
    font-weight: 700;
}

.btn-outline-ecotrack:hover {
    background: #17643b;
    color: #fff;
}

/* =========================================================
   ALERTS
========================================================= */

.alert {
    border-radius: 10px;
    font-size: 13px;
}

/* =========================================================
   FOOTER
========================================================= */

.info-box {
    background: #edf7ef;
    border-left: 4px solid #21834d;
    border-radius: 8px;
    padding: 15px;
    font-size: 13px;
    color: #355b43;
    margin-bottom: 18px;
}

.warning-box {
    background: #fff8e6;
    border-left: 4px solid #e0a800;
    border-radius: 8px;
    padding: 15px;
    font-size: 13px;
    color: #765b18;
    margin-bottom: 18px;
}


.dashboard-footer {
    margin-top: 35px;
    padding: 20px 0;
    border-top: 1px solid #e2e9e5;
    color: #929b96;
    font-size: 12px;
    text-align: center;
}

/* =========================================================
   RESPONSIVE
========================================================= */

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

    .section-title {
        font-size: 16px;
    }

    .dashboard-card {
        padding: 18px;
    }

    .payment-amount {
        font-size: 23px;
    }

    .info-table th,
    .info-table td {
        font-size: 12px;
        padding: 9px;
    }

}



/* TRACKING PAGE */


.page-header {
    margin-bottom: 25px;
}

.page-header h2 {
    color: #183d2b;
    font-size: 25px;
    font-weight: bold;
}

.page-header p {
    color: #77847c;
    margin-bottom: 0;
}

/* SECTION TITLE */

.section-title {
    color: #45564d;
    font-size: 17px;
    font-weight: 700;
    margin: 30px 0 18px;
}

/* TRACKING CARDS */

.tracking-card {
    background: white;
    border-radius: 12px;
    padding: 23px;
    border: 1px solid #e8eeea;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
    margin-bottom: 23px;
}

.tracking-card h5 {
    color: #2d4036;
    font-size: 17px;
    font-weight: 700;
    margin-bottom: 15px;
}

/* TRACKING ICON */

.dashboard-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e5f5e9;
    color: #23834b;
    margin-bottom: 17px;
    font-size: 21px;
}

/* COLLECTOR INFORMATION */

.collector-name {
    font-size: 16px;
    font-weight: 700;
    color: #263b32;
    margin-bottom: 8px;
}

.collector-status {
    font-size: 13px;
    margin-bottom: 0;
}

.status-online {
    color: #198754;
}

.status-offline {
    color: #dc3545;
}

.status-waiting {
    color: #77847c;
}

/* REFRESH BUTTON */

.refresh-btn {
    background: #198754;
    color: white;
    border: none;
    border-radius: 7px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 600;
    transition: .2s;
}

.refresh-btn:hover {
    background: #146c43;
    color: white;
}

/* MAP */

#map {
    width: 100%;
    height: 500px;
    border-radius: 10px;
    overflow: hidden;
    background: #e9efeb;
}

.map-container {
    width: 100%;
    overflow: hidden;
    border-radius: 10px;
}

/* TRACKING DETAILS */

.tracking-description {
    color: #77847c;
    font-size: 13px;
    line-height: 1.6;
}

/* FOOTER */

.dashboard-footer {
    margin-top: 35px;
    padding: 20px 0;
    border-top: 1px solid #e2e9e5;
    color: #929b96;
    font-size: 12px;
    text-align: center;
}


@media (max-width: 700px) {
  #map { height: 400px; }
  .tracking-card { padding: 18px; }
}

</style>

</head>

<body>

<!-- RESIDENT SIDEBAR -->
<aside class="sidebar">

    <div class="sidebar-logo">
        <h3>
            <i class="fas fa-recycle"></i>
            EcoTrack
        </h3>
    </div>

    <ul class="sidebar-menu">

        <li>
            <a href="dashboard.php">
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
            <a href="track_truck.php" class="active">
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


<!-- MAIN CONTENT -->

<div class="main-content">

    <!-- TOPBAR -->

    <div class="topbar">
        <div class="resident-profile">
            <div class="profile-icon"><i class="fas fa-user"></i></div>
            <div>
                <div class="profile-name"><?= e($resident["fullname"]); ?></div>
                <span class="profile-role">Resident</span>
            </div>
        </div>
    </div>

    <!-- PAGE CONTENT -->

    <main class="content">

        <!-- PAGE HEADER -->

        <div class="page-heading">

            <h2>
                <i class="fas fa-truck me-2"></i>
                Track Garbage Truck
            </h2>

            <p>
                Monitor the collector assigned to your household.
            </p>

        </div>


        <!-- COLLECTOR INFORMATION -->

        <h6 class="section-title">

            <i class="fas fa-user me-2"></i>

            Assigned Collector Information

        </h6>

        <div class="tracking-card">

            <div class="row align-items-center">

                <div class="col-md-8">

                    <div class="d-flex align-items-start gap-3">

                        <div class="dashboard-icon">
                            <i class="fas fa-truck"></i>
                        </div>

                        <div>

                            <h5 class="collector-name"
                                id="collectorName">

                                Searching for assigned collector...

                            </h5>

                            <p id="collectorStatus"
                               class="collector-status status-waiting">

                                <i class="fas fa-clock me-1"></i>
                                Waiting for location...

                            </p>

                        </div>

                    </div>

                </div>

               

            </div>

        </div>


        <!-- LIVE COLLECTOR LOCATION -->

        <h6 class="section-title">

            <i class="fas fa-map-marker-alt me-2"></i>

            Live Collector Location

        </h6>

        <div class="tracking-card">

            <h5>
                <i class="fas fa-location-dot me-2 text-success"></i>
                Collector Tracking Map
            </h5>

            <p class="tracking-description">
                View the real-time location of your assigned
                garbage collector. The map automatically updates
                every 5 seconds.
            </p>

            <div class="map-container">

                <div id="map"></div>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="dashboard-footer">

            EcoTrack Waste Collection Management System

            <br>

            &copy; <?= date("Y"); ?>

            <br>

            Resident Tracking Page

        </div>

    </main>

</div>


<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>


<!-- GOOGLE MAPS TRACKING -->

<script>

let map;

let collectorMarker = null;

let firstLocationLoaded = false;

let trackingInterval = null;

let isLoading = false;


// LOAD COLLECTOR LOCATION

async function loadCollectorLocation() {

    if (isLoading || !map) {
        return;
    }

    isLoading = true;

    const nameElement =
        document.getElementById("collectorName");

    const statusElement =
        document.getElementById("collectorStatus");

    try {

        const response = await fetch(
            "get_assigned_collector_location.php",
            {
                method: "GET",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            }
        );

        const responseText = await response.text();

        let collector;

        try {

            collector = JSON.parse(responseText);

        } catch (jsonError) {

            console.error(
                "Invalid API response:",
                responseText
            );

            throw new Error(
                "The tracking API returned an invalid response. Check the PHP file for errors."
            );

        }

        if (!response.ok) {

            throw new Error(
                collector.message || "Unable to load collector."
            );

        }

        if (!collector.success) {

            nameElement.textContent =
                collector.message || "No assigned collector found.";

            statusElement.textContent =
                "Waiting for an assigned collector.";

            statusElement.className =
                "collector-status status-waiting";

            if (collectorMarker) {

                collectorMarker.setMap(null);

                collectorMarker = null;

            }

            firstLocationLoaded = false;

            return;

        }

        // COLLECTOR NAME

        nameElement.textContent =
            "Collector: " + collector.fullname;


        // COLLECTOR COORDINATES

        const position = {

            lat: Number(collector.latitude),

            lng: Number(collector.longitude)

        };


        if (
            !Number.isFinite(position.lat) ||
            !Number.isFinite(position.lng) ||
            Math.abs(position.lat) > 90 ||
            Math.abs(position.lng) > 180
        ) {

            throw new Error(
                "Invalid collector coordinates."
            );

        }


        // ONLINE / OFFLINE STATUS

        const isOnline =
            Number(collector.is_online) === 1;


        if (isOnline) {

            statusElement.innerHTML =
                '<i class="fas fa-circle me-1"></i> Online • GPS location updating';

            statusElement.className =
                "collector-status status-online";

        } else {

            statusElement.innerHTML =
                '<i class="fas fa-circle me-1"></i> Offline • Showing last known location';

            statusElement.className =
                "collector-status status-offline";

        }


        // CREATE OR UPDATE MARKER

        if (!collectorMarker) {

            collectorMarker = new google.maps.Marker({

                position: position,

                map: map,

                title: collector.fullname,

                label: "T"

            });

        } else {

            collectorMarker.setPosition(position);

        }


        // CENTER MAP ON FIRST LOCATION

        if (!firstLocationLoaded) {

            map.setCenter(position);

            map.setZoom(16);

            firstLocationLoaded = true;

        }

    } catch (error) {

        console.error(
            "Tracking error:",
            error
        );

        nameElement.textContent =
            "Collector Information";

        statusElement.textContent =
            error.message || "Unable to retrieve collector location. Retrying...";

        statusElement.className =
            "collector-status status-offline";

    } finally {

        isLoading = false;

    }

}


// INITIALIZE GOOGLE MAP

function initMap() {

    map = new google.maps.Map(

        document.getElementById("map"),

        {

            center: {
                lat: 15.4802,
                lng: 120.5979
            },

            zoom: 13,

            mapTypeControl: true,

            streetViewControl: false,

            fullscreenControl: true

        }

    );


    // LOAD INITIAL LOCATION

    loadCollectorLocation();


    // REFRESH EVERY 5 SECONDS

    if (trackingInterval) {

        clearInterval(trackingInterval);

    }

    trackingInterval = setInterval(

        loadCollectorLocation,

        5000

    );

}

</script>


<!-- GOOGLE MAPS API -->

<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCz8mz80N6FN03Q1q1nQFGeZLduXsZpNHY&callback=initMap">
</script>

</body>
</html>
