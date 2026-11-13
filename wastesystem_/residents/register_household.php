
<?php
session_start();
require_once __DIR__ . "/../config/db_system.php";

/*
|--------------------------------------------------------------------------
| RESIDENT AUTHENTICATION
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "residents"
) {
    header("Location: ../index.php");
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection unavailable.");
}

$user_id = (int) $_SESSION["user_id"];
$message = "";
$message_type = "";

function householdEscape($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["household_csrf"])) {
    $_SESSION["household_csrf"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["household_csrf"];

/*
|--------------------------------------------------------------------------
| RESIDENT INFORMATION AND SAVED ADDRESS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, fullname, address
    FROM users
    WHERE id = ?
      AND role = 'residents'
    LIMIT 1
");

$stmt->execute([$user_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

$resident_name = $resident["fullname"] ?: "Resident";
$house_address = trim((string) ($resident["address"] ?? ""));

/*
|--------------------------------------------------------------------------
| EXISTING HOUSEHOLD
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, house_address, latitude, longitude
    FROM households
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$user_id]);
$existing_household = $stmt->fetch(PDO::FETCH_ASSOC);

$alreadyRegistered = (bool) $existing_household;

/*
|--------------------------------------------------------------------------
| RESTORE MAP COORDINATES AFTER VALIDATION ERROR
|--------------------------------------------------------------------------
*/
$selected_latitude = "";
$selected_longitude = "";

/*
|--------------------------------------------------------------------------
| REGISTER HOUSEHOLD
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrf_token, $token)) {
        $message = "Invalid request. Please refresh and try again.";
        $message_type = "danger";

    } elseif ($alreadyRegistered) {
        $message = "Your household is already registered.";
        $message_type = "warning";

    } elseif ($house_address === "") {
        $message = "No saved address was found in your account. Please update your registered address first.";
        $message_type = "danger";

    } else {
        $latitude = trim((string) ($_POST["latitude"] ?? ""));
        $longitude = trim((string) ($_POST["longitude"] ?? ""));

        $selected_latitude = $latitude;
        $selected_longitude = $longitude;

        if (
            !is_numeric($latitude) ||
            !is_numeric($longitude) ||
            (float) $latitude < -90 ||
            (float) $latitude > 90 ||
            (float) $longitude < -180 ||
            (float) $longitude > 180
        ) {
            $message = "Please select a valid house location on the map.";
            $message_type = "danger";

        } else {
            try {
                $pdo->beginTransaction();

                // Lock resident record to prevent duplicate submissions
                $check = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE id = ?
                      AND role = 'residents'
                    FOR UPDATE
                ");
                $check->execute([$user_id]);

                $duplicate = $pdo->prepare("
                    SELECT id
                    FROM households
                    WHERE user_id = ?
                    LIMIT 1
                ");
                $duplicate->execute([$user_id]);

                if ($duplicate->fetch()) {
                    $pdo->rollBack();
                    $message = "Your household is already registered.";
                    $message_type = "warning";
                    $alreadyRegistered = true;

                } else {
                    $insert = $pdo->prepare("
                        INSERT INTO households
                            (user_id, house_address, latitude, longitude)
                        VALUES (?, ?, ?, ?)
                    ");

                    $insert->execute([
                        $user_id,
                        $house_address,
                        (float) $latitude,
                        (float) $longitude
                    ]);

                    $pdo->commit();

                    $message = "Household registered successfully!";
                    $message_type = "success";
                    $alreadyRegistered = true;
                }

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log("Household registration error: " . $e->getMessage());

                $message = "Unable to register household. Please try again.";
                $message_type = "danger";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Register Household | EcoTrack</title>

<link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f4f7f5;
    color: #263d30;
    font-family: Arial, Helvetica, sans-serif;
}

a {
    text-decoration: none;
}

/* SIDEBAR */
.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: #123d2b;
    color: #fff;
    padding: 25px 15px;
    overflow-y: auto;
    z-index: 1000;
}

.sidebar-logo {
    margin-bottom: 35px;
    text-align: center;
}

.sidebar-logo h3 {
    color: white;
    font-size: 23px;
    font-weight: 800;
    margin: 0;
}

.sidebar-logo i {
    color: #9be7ad;
    margin-right: 7px;
}

.sidebar-menu {
    list-style: none;
    margin: 0;
    padding: 0;
}

.sidebar-menu li {
    margin-bottom: 8px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 15px;
    border-radius: 8px;
    color: #d8e8df;
    font-size: 14px;
    font-weight: 600;
    transition: .2s;
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

/* MAIN CONTENT */
.main-content {
    margin-left: 250px;
    min-height: 100vh;
    min-width: 0;
}

.topbar {
    min-height: 75px;
    background: #fff;
    border-bottom: 1px solid #e6ede8;
    padding: 15px 30px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
}

.resident-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}

.profile-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    background: #e6f3e9;
    color: #17643b;
    border-radius: 12px;
}

.profile-name {
    font-size: 13px;
    font-weight: 700;
    color: #183d2b;
}

.profile-role {
    display: block;
    color: #849288;
    font-size: 11px;
    margin-top: 3px;
}

.content {
    padding: 32px;
    max-width: 1550px;
    margin: 0 auto;
}

/* HEADER */
.page-banner {
    background: linear-gradient(110deg,#154d34,#27754e);
    border-radius: 15px;
    padding: 27px 30px;
    margin-bottom: 25px;
    color: #fff;
}

.page-banner h1 {
    font-size: 25px;
    font-weight: 800;
    margin: 0 0 9px;
}

.page-banner p {
    font-size: 13px;
    color: #e2f0e6;
    margin: 0;
    line-height: 1.7;
}

/* STEP INDICATORS */
.steps {
    display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.step {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    border: 1px solid #e4ede7;
    border-radius: 11px;
    background: white;
}

.step-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #e5f3e9;
    color: #187442;
    font-size: 13px;
    font-weight: 800;
    flex-shrink: 0;
}

.step-title {
    font-size: 12px;
    font-weight: 700;
    color: #254b35;
}

.step-description {
    font-size: 11px;
    color: #89978d;
    margin-top: 4px;
}

/* SECTION */
.section {
    background: #fff;
    border: 1px solid #e6eee8;
    border-radius: 13px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(20,65,38,.025);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 23px;
}

.section-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 43px;
    height: 43px;
    background: #eaf5ed;
    border-radius: 11px;
    color: #198754;
    font-size: 17px;
    flex-shrink: 0;
}

.section-title {
    color: #193f2b;
    font-size: 17px;
    font-weight: 800;
    margin: 0 0 5px;
}

.section-subtitle {
    font-size: 12px;
    color: #849187;
    margin: 0;
}

/* ADDRESS */
.address-display {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 20px;
    border: 1px solid #d9e9df;
    border-radius: 11px;
    background: #f7fbf8;
}

.address-pin {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #e1f1e6;
    color: #198754;
    flex-shrink: 0;
}

.address-content {
    min-width: 0;
    flex: 1;
}

.address-label {
    color: #789083;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 7px;
}

.address-value {
    color: #1d4530;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.saved-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 6px;
    background: #e2f3e8;
    color: #198754;
    font-size: 10px;
    font-weight: 700;
    margin-top: 11px;
}

.address-note {
    color: #839188;
    font-size: 12px;
    line-height: 1.6;
    margin: 13px 0 0;
}

/* MAP */
.map-instructions {
    background: #edf7ef;
    color: #356247;
    border-radius: 9px;
    padding: 13px 16px;
    font-size: 12px;
    line-height: 1.7;
    margin-bottom: 17px;
}

.map-container {
    border: 1px solid #e1e9e3;
    border-radius: 11px;
    overflow: hidden;
    background: #edf2ee;
}

#map {
    width: 100%;
    height: 420px;
}

.location-info {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 15px;
    margin-top: 18px;
}

.location-field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #4b6755;
    margin-bottom: 8px;
}

.location-field input {
    width: 100%;
    border: 1px solid #dfe9e2;
    border-radius: 8px;
    padding: 12px;
    background: #f8faf8;
    color: #365b43;
    font-size: 13px;
    outline: none;
}

.location-status {
    margin-top: 17px;
    padding: 13px 15px;
    border-radius: 9px;
    background: #f3f7f4;
    color: #6b8072;
    font-size: 12px;
    line-height: 1.6;
}

/* BUTTONS */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    flex-wrap: wrap;
    margin: 22px 0 30px;
}

.btn-ecotrack {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    background: #17643b;
    color: #fff;
    border: none;
    border-radius: 9px;
    padding: 13px 22px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.btn-ecotrack:hover {
    background: #104b2c;
    color: #fff;
}

.btn-cancel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #fff;
    border: 1px solid #d9e4dc;
    color: #53675a;
    border-radius: 9px;
    padding: 12px 21px;
    font-size: 13px;
    font-weight: 700;
}

/* SUCCESS */
.registered-state {
    text-align: center;
    padding: 50px 25px;
}

.success-icon {
    width: 75px;
    height: 75px;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #e3f4e9;
    color: #198754;
    font-size: 32px;
}

.registered-state h3 {
    font-size: 22px;
    color: #193f2b;
    font-weight: 800;
    margin-bottom: 10px;
}

.registered-state p {
    color: #7d8c81;
    font-size: 13px;
    margin-bottom: 25px;
}

.dashboard-footer {
    text-align: center;
    border-top: 1px solid #e3eae5;
    padding: 20px;
    color: #98a39a;
    font-size: 11px;
}

/* RESPONSIVE */
@media(max-width: 1050px) {
    .steps {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 700px) {
    .sidebar {
        width: 70px;
        padding: 20px 8px;
    }

    .sidebar-logo h3 {
        font-size: 0;
    }

    .sidebar-logo i {
        font-size: 25px;
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

    .content {
        padding: 18px 13px;
    }

    .topbar {
        padding: 14px;
    }

    .page-banner {
        padding: 22px;
    }

    .page-banner h1 {
        font-size: 21px;
    }

    .section {
        padding: 18px;
    }

    .location-info {
        grid-template-columns: 1fr;
    }

    #map {
        height: 320px;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .form-actions a,
    .form-actions button {
        width: 100%;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
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
            <a href="register_household.php" class="active">
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
    <header class="topbar">
        <div class="resident-profile">
            <div class="profile-icon">
                <i class="fas fa-user"></i>
            </div>

            <div>
                <div class="profile-name">
                    <?= householdEscape($resident_name) ?>
                </div>

                <span class="profile-role">
                    Resident
                </span>
            </div>
        </div>
    </header>

    <main class="content">

        <!-- PAGE HEADER -->
        <div class="page-banner">
            <h1>
                <i class="fas fa-house-user me-2"></i>
                Register Your Household
            </h1>

            <p>
                Confirm your registered home address and
                select your exact household location for
                accurate waste collection services.
            </p>
        </div>

        <!-- STEPS -->
        <div class="steps">

            <div class="step">
                <div class="step-number">01</div>
                <div>
                    <div class="step-title">Saved Address</div>
                    <div class="step-description">
                        Automatically retrieved from sign-up
                    </div>
                </div>
            </div>

            <div class="step">
                <div class="step-number">02</div>
                <div>
                    <div class="step-title">House Location</div>
                    <div class="step-description">
                        Select your exact location on the map
                    </div>
                </div>
            </div>

            <div class="step">
                <div class="step-number">03</div>
                <div>
                    <div class="step-title">Registration</div>
                    <div class="step-description">
                        Save your household information
                    </div>
                </div>
            </div>

        </div>

        <!-- ALERT -->
        <?php if ($message !== ""): ?>
            <div class="alert alert-<?= householdEscape($message_type) ?>">
                <?= householdEscape($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$alreadyRegistered): ?>

        <form method="POST" id="household-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= householdEscape($csrf_token) ?>"
            >

            <!-- SAVED ADDRESS -->
            <section class="section">

                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>

                    <div>
                        <h3 class="section-title">
                            Household Address
                        </h3>

                        <p class="section-subtitle">
                            Address provided during account registration
                        </p>
                    </div>
                </div>

                <div class="address-display">

                    <div class="address-pin">
                        <i class="fas fa-home"></i>
                    </div>

                    <div class="address-content">

                        <div class="address-label">
                            REGISTERED HOME ADDRESS
                        </div>

                        <div class="address-value">
                            <?php if ($house_address !== ""): ?>
                                <?= householdEscape($house_address) ?>
                            <?php else: ?>
                                <span class="text-danger">
                                    No address saved in your account.
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($house_address !== ""): ?>
                            <span class="saved-badge">
                                <i class="fas fa-check-circle"></i>
                                Automatically Filled
                            </span>
                        <?php endif; ?>

                    </div>
                </div>

                <p class="address-note">
                    <i class="fas fa-info-circle me-1"></i>
                    This address is automatically retrieved from your
                    registered account. You only need to select your
                    exact house location below.
                </p>

            </section>

            <!-- MAP -->
            <section class="section">

                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>

                    <div>
                        <h3 class="section-title">
                            Select Your House Location
                        </h3>

                        <p class="section-subtitle">
                            Pin your household location on the map
                        </p>
                    </div>
                </div>

                <div class="map-instructions">
                    <i class="fas fa-info-circle me-2"></i>
                    Click the exact location of your house on the map.
                    A marker will appear, and the GPS coordinates
                    will automatically fill in.
                </div>

                <div class="map-container">
                    <div id="map"></div>
                </div>

                <div class="location-info">

                    <div class="location-field">
                        <label for="latitude">
                            Latitude
                        </label>

                        <input
                            type="number"
                            name="latitude"
                            id="latitude"
                            step="any"
                            placeholder="Select on map"
                            value="<?= householdEscape($selected_latitude) ?>"
                            readonly
                            required
                        >
                    </div>

                    <div class="location-field">
                        <label for="longitude">
                            Longitude
                        </label>

                        <input
                            type="number"
                            name="longitude"
                            id="longitude"
                            step="any"
                            placeholder="Select on map"
                            value="<?= householdEscape($selected_longitude) ?>"
                            readonly
                            required
                        >
                    </div>

                </div>

                <div class="location-status">
                    <i class="fas fa-map-pin text-success me-2"></i>
                    <strong>Selected Location:</strong>
                    <span id="location-status">
                        No location selected yet.
                    </span>
                </div>

            </section>

            <!-- ACTIONS -->
            <div class="form-actions">

                <a href="dashboard.php" class="btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn-ecotrack"
                    <?= $house_address === "" ? "disabled" : "" ?>
                >
                    <i class="fas fa-check-circle"></i>
                    Register Household
                </button>

            </div>

        </form>

        <?php else: ?>

            <!-- ALREADY REGISTERED -->
            <section class="section registered-state">

                <div class="success-icon">
                    <i class="fas fa-house-user"></i>
                </div>

                <h3>Household Already Registered</h3>

                <p>
                    Your household has already been registered
                    in the EcoTrack system.
                </p>

                <?php if ($existing_household): ?>
                    <p>
                        <strong>Registered Address:</strong>
                        <?= householdEscape($existing_household["house_address"]) ?>
                    </p>
                <?php endif; ?>

                <a href="dashboard.php" class="btn-ecotrack">
                    <i class="fas fa-th-large"></i>
                    Back to Dashboard
                </a>

            </section>

        <?php endif; ?>

        <footer class="dashboard-footer">
            Waste Collection Management System by Group 2
            &copy; <?= date("Y") ?>
        </footer>

    </main>
</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<?php if (!$alreadyRegistered): ?>

<script>
let map;
let marker;

function initMap() {
    const defaultLocation = {
        lat: 15.2200,
        lng: 120.5700
    };

    map = new google.maps.Map(
        document.getElementById("map"),
        {
            center: defaultLocation,
            zoom: 15,
            mapTypeControl: true,
            streetViewControl: false,
            fullscreenControl: true
        }
    );

    const latitudeInput = document.getElementById("latitude");
    const longitudeInput = document.getElementById("longitude");
    const status = document.getElementById("location-status");

    function selectLocation(lat, lng) {
        if (
            !Number.isFinite(lat) ||
            !Number.isFinite(lng) ||
            lat < -90 || lat > 90 ||
            lng < -180 || lng > 180
        ) {
            return;
        }

        const position = { lat, lng };

        if (marker) {
            marker.setPosition(position);
        } else {
            marker = new google.maps.Marker({
                position: position,
                map: map,
                title: "Your Household Location"
            });
        }

        latitudeInput.value = lat.toFixed(8);
        longitudeInput.value = lng.toFixed(8);

        status.textContent =
            lat.toFixed(6) + ", " + lng.toFixed(6);
    }

    // Restore coordinates after validation error
    const savedLat = latitudeInput.value;
    const savedLng = longitudeInput.value;

    if (savedLat !== "" && savedLng !== "") {
        const lat = Number(savedLat);
        const lng = Number(savedLng);

        if (
            Number.isFinite(lat) &&
            Number.isFinite(lng) &&
            lat >= -90 && lat <= 90 &&
            lng >= -180 && lng <= 180
        ) {
            selectLocation(lat, lng);
            map.setCenter({ lat, lng });
        }
    }

    // Select exact location on map
    map.addListener("click", function(event) {
        const lat = event.latLng.lat();
        const lng = event.latLng.lng();

        selectLocation(lat, lng);
    });
}

// Validate before submission
document.getElementById("household-form")
    ?.addEventListener("submit", function(event) {

        const lat = document.getElementById("latitude").value;
        const lng = document.getElementById("longitude").value;

        if (lat === "" || lng === "") {
            event.preventDefault();

            alert(
                "Please select your exact house location on the map."
            );
        }
    });
</script>

<!-- Replace YOUR_GOOGLE_MAPS_API_KEY with your configured key -->
<script
    src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&callback=initMap"
    async
    defer>
</script>

<?php endif; ?>

</body>
</html>
