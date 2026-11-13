<?php
session_start();
include "../config/database.php";

// Only residents can access this page
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] != "residents"
) {
    header("Location: ../index.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

// Resident name for the shared dashboard topbar.
$resident_name = 'Resident';
$profile_stmt = mysqli_prepare($conn, "SELECT fullname FROM users WHERE id = ? AND role = 'residents' LIMIT 1");
if ($profile_stmt) {
    mysqli_stmt_bind_param($profile_stmt, 'i', $user_id);
    mysqli_stmt_execute($profile_stmt);
    $profile_result = mysqli_stmt_get_result($profile_stmt);
    $profile_row = mysqli_fetch_assoc($profile_result);
    if ($profile_row) {
        $resident_name = $profile_row['fullname'];
    }
    mysqli_stmt_close($profile_stmt);
}


$message = "";
$message_type = "";

// Get selected month
$month = $_GET["month"] ?? date("Y-m");

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date("Y-m");
}

$first_day = new DateTime($month . "-01");
$last_day = clone $first_day;
$last_day->modify("last day of this month");

$today = date("Y-m-d");

// Schedule a collection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["schedule"])) {

    $collection_date = $_POST["collection_date"] ?? "";
    $collection_address = trim($_POST["collection_address"] ?? "");
    $requested_time = trim($_POST["requested_time"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    $valid_date = DateTime::createFromFormat("!Y-m-d", $collection_date);

    if (
        !$valid_date ||
        $valid_date->format("Y-m-d") !== $collection_date
    ) {
        $message = "Please select a valid collection date.";
        $message_type = "error";

    } elseif ($collection_date < $today) {

        $message = "You cannot schedule a collection on a past date.";
        $message_type = "error";

    } elseif ($collection_address == "") {

        $message = "Please enter your collection address.";
        $message_type = "error";

    } elseif (strlen($collection_address) > 255) {

        $message = "The address must not exceed 255 characters.";
        $message_type = "error";

    } elseif (empty($requested_time)) {

        $message = "Please select your preferred collection time.";
        $message_type = "error";

    } else {

        // Check if the selected date is already booked
        $check = mysqli_prepare(
            $conn,
            "SELECT id
             FROM collection_schedules
             WHERE collection_date = ?
             AND status IN ('Pending', 'Approved')
             LIMIT 1"
        );

        if (!$check) {
            die("Database error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($check, "s", $collection_date);
        mysqli_stmt_execute($check);
        $result = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($result) > 0) {

            $message = "Sorry! This date is already booked. Please choose another date.";
            $message_type = "error";

        } else {

            // Save the schedule
            $insert = mysqli_prepare(
                $conn,
                "INSERT INTO collection_schedules
                (user_id, collection_date, requested_time, collection_address, notes)
                VALUES (?, ?, ?, ?, ?)"
            );

            if (!$insert) {
                die("Database error: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param(
                $insert,
                "issss",
                $user_id,
                $collection_date,
                $requested_time,
                $collection_address,
                $notes
            );

            if (mysqli_stmt_execute($insert)) {

                $message = "Collection scheduled successfully! Your request is Pending approval.";
                $message_type = "success";

            } else {

                if (mysqli_errno($conn) == 1062) {
                    $message = "Sorry! Another resident has already booked this date. Please choose another date.";
                } else {
                    $message = "Unable to save your schedule. Please try again.";
                }

                $message_type = "error";
            }

            mysqli_stmt_close($insert);
        }

        mysqli_stmt_close($check);
    }
}

// Get booked dates in selected month
$booked_dates = [];

$start_date = $first_day->format("Y-m-d");
$end_date = $last_day->format("Y-m-d");

$booked_query = mysqli_prepare(
    $conn,
    "SELECT collection_date
     FROM collection_schedules
     WHERE collection_date BETWEEN ? AND ?
     AND status IN ('Pending', 'Approved')"
);

if (!$booked_query) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($booked_query, "ss", $start_date, $end_date);
mysqli_stmt_execute($booked_query);

$booked_result = mysqli_stmt_get_result($booked_query);

while ($row = mysqli_fetch_assoc($booked_result)) {
    $booked_dates[] = $row["collection_date"];
}

mysqli_stmt_close($booked_query);

// Get resident collection history
$history = mysqli_prepare(
    $conn,
    "SELECT id, collection_date, requested_time,
            collection_address, notes, status
     FROM collection_schedules
     WHERE user_id = ?
     ORDER BY collection_date DESC, id DESC"
);

if (!$history) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($history, "i", $user_id);
mysqli_stmt_execute($history);

$history_result = mysqli_stmt_get_result($history);

// Calendar navigation
$previous_month = clone $first_day;
$previous_month->modify("-1 month");

$next_month = clone $first_day;
$next_month->modify("+1 month");

$starting_day = (int) $first_day->format("w");
$days_in_month = (int) $first_day->format("t");

// Escape output
function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Collection Schedule | EcoTrack</title>

    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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



/* COLLECTION SCHEDULE PAGE */
.schedule-card { background:#fff; border:1px solid #edf1ee; border-radius:13px; padding:25px; margin-bottom:22px; box-shadow:0 3px 12px rgba(0,0,0,.05); }
.schedule-card h3 { color:#183d2b; font-size:18px; font-weight:800; margin:0 0 17px; }
.schedule-card h3 i { color:#198754; margin-right:8px; }
.schedule-card p { color:#66746b; font-size:14px; }
.calendar-header { display:flex; justify-content:space-between; align-items:center; gap:10px; margin:20px 0; }
.calendar-header h3 { margin:0; text-align:center; }
.nav-button { background:#17643b; color:white; text-decoration:none; padding:10px 14px; border-radius:8px; white-space:nowrap; font-size:13px; font-weight:700; }
.nav-button:hover { background:#104b2c; color:white; }
.calendar { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:5px; }
.day-name { text-align:center; font-weight:700; padding:10px 0; background:#edf4ef; border-radius:6px; color:#365342; }
.day { min-height:85px; padding:8px; border:1px solid #ddd; border-radius:6px; background:white; min-width:0; }
.day-number { font-weight:700; display:block; margin-bottom:8px; }
.day.available { background:#e8f7ee; border-color:#8bd2a5; }
.day.booked { background:#fdeaea; border-color:#e8aaaa; }
.day.past { background:#eee; color:#888; }
.available-button { display:block; width:100%; border:0; padding:6px 2px; background:#17643b; color:#fff; border-radius:5px; cursor:pointer; font-size:12px; }
.available-button:hover { background:#104b2c; }
.booked-label,.past-label { font-size:11px; display:block; line-height:1.4; overflow-wrap:anywhere; }
.booked-label { color:#b02a37; }
.past-label { color:#666; }
.legend { display:flex; flex-wrap:wrap; gap:15px; margin-top:18px; font-size:13px; }
.message { padding:14px 17px; margin-bottom:20px; border-radius:9px; font-size:14px; }
.message.success { background:#edf7ef; border:1px solid #c6e8d0; color:#17643b; }
.message.error { background:#fce8e8; border:1px solid #f3cccc; color:#842029; }
.form-group { margin-bottom:15px; }
.form-group label { display:block; margin:15px 0 6px; font-size:13px; color:#304b3b; font-weight:700; }
.form-group input,.form-group textarea { width:100%; padding:11px 13px; border:1px solid #d6e0d9; border-radius:8px; font:inherit; font-size:13px; color:#263b30; background:white; }
.form-group input:focus,.form-group textarea:focus { outline:0; border-color:#198754; box-shadow:0 0 0 3px rgba(25,135,84,.12); }
.form-group textarea { min-height:90px; resize:vertical; }
.submit-button { margin-top:10px; padding:11px 18px; background:#17643b; color:#fff; border:0; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; }
.submit-button:hover { background:#104b2c; }
.table-wrapper { overflow-x:auto; border:1px solid #e7ede9; border-radius:9px; }
.history-table { width:100%; border-collapse:collapse; min-width:700px; font-size:13px; }
.history-table th,.history-table td { padding:14px 15px; border-bottom:1px solid #e9eeeb; text-align:left; }
.history-table th { background:#f4f8f5; color:#365342; font-weight:700; }
.history-table td { color:#53645a; }
.history-table tbody tr:last-child td { border-bottom:0; }
.history-table tbody tr:hover { background:#f9fbf9; }
.status { font-weight:700; }
.status-pending { color:#b58100; }
.status-approved { color:#198754; }
.status-rejected { color:#dc3545; }
.status-completed { color:#0d6efd; }
.small-note { color:#66746b; font-size:13px; line-height:1.5; margin-top:15px; }
@media(max-width:700px) {
 .schedule-card { padding:18px; }
 .day { min-height:65px; padding:4px; }
 .day-name { font-size:11px; }
 .day-number { font-size:12px; }
 .available-button { font-size:10px; }
 .booked-label,.past-label { font-size:9px; }
 .calendar-header h3 { font-size:16px; }
 .nav-button { padding:8px; font-size:12px; }
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
            <a href="collection_schedule.php" class="active">
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

<!-- MAIN CONTENT -->
<div class="main-content">

    <div class="topbar">
        <div class="resident-profile">
            <div class="profile-icon"><i class="fas fa-user"></i></div>
            <div>
                <div class="profile-name"><?= h($resident_name) ?></div>
                <span class="profile-role">Resident</span>
            </div>
        </div>
    </div>

    <main class="content">

        <div class="page-heading">
            <h2>
                <i class="fas fa-calendar-alt me-2"></i>
                Garbage Collection Schedule
            </h2>

            <p>
                Select an available date to request garbage collection.
            </p>
        </div>

        <?php if ($message != ""): ?>
            <div class="message <?= h($message_type) ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <!-- CALENDAR CARD -->
        <div class="schedule-card">

            <h3>Available Collection Dates</h3>

            <p>
                Select an available date to request garbage collection.
                Only one resident can reserve each date.
            </p>

            <div class="calendar-header">

                <a class="nav-button"
                   href="?month=<?= h($previous_month->format('Y-m')) ?>">
                    &laquo; Previous
                </a>

                <h3><?= h($first_day->format("F Y")) ?></h3>

                <a class="nav-button"
                   href="?month=<?= h($next_month->format('Y-m')) ?>">
                    Next &raquo;
                </a>

            </div>

            <div class="calendar">

                <?php
                $weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

                foreach ($weekdays as $weekday):
                ?>
                    <div class="day-name">
                        <?= h($weekday) ?>
                    </div>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < $starting_day; $i++): ?>
                    <div class="day"></div>
                <?php endfor; ?>

                <?php
                for ($day = 1; $day <= $days_in_month; $day++):

                    $date = $first_day->format("Y-m-") .
                        str_pad($day, 2, "0", STR_PAD_LEFT);

                    $is_past = $date < $today;
                    $is_booked = in_array($date, $booked_dates, true);

                    if ($is_past) {
                        $class = "past";
                    } elseif ($is_booked) {
                        $class = "booked";
                    } else {
                        $class = "available";
                    }
                ?>

                    <div class="day <?= h($class) ?>">

                        <span class="day-number">
                            <?= $day ?>
                        </span>

                        <?php if ($is_past): ?>

                            <span class="past-label">Past date</span>

                        <?php elseif ($is_booked): ?>

                            <span class="booked-label">
                                Already booked
                            </span>

                        <?php else: ?>

                            <button
                                type="button"
                                class="available-button"
                                onclick="selectDate('<?= h($date) ?>')">
                                Select date
                            </button>

                        <?php endif; ?>

                    </div>

                <?php endfor; ?>

            </div>

            <div class="legend">
                <span>🟩 Available</span>
                <span>🟥 Already booked</span>
                <span>⬜ Past date</span>
            </div>

            <p class="small-note">
                A date becomes available again after the previous request
                is marked Completed or Rejected by authorized staff.
            </p>

        </div>

        <!-- SCHEDULE FORM -->
        <div class="schedule-card" id="schedule-form-card">

            <h3>Submit Collection Request</h3>

            <p>
                Complete the form below to request a garbage collection.
            </p>

            <form method="POST" action="" id="schedule-form">

                <div class="form-group">
                    <label for="collection_date">Collection Date</label>

                    <input
                        type="date"
                        id="collection_date"
                        name="collection_date"
                        min="<?= h($today) ?>"
                        value="<?= h($_POST['collection_date'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="requested_time">Requested Time</label>

                    <input
                        type="time"
                        id="requested_time"
                        name="requested_time"
                        value="<?= h($_POST['requested_time'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="collection_address">Collection Address</label>

                    <input
                        type="text"
                        id="collection_address"
                        name="collection_address"
                        maxlength="255"
                        value="<?= h($_POST['collection_address'] ?? '') ?>"
                        placeholder="Enter your complete collection address"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes (Optional)</label>

                    <textarea
                        id="notes"
                        name="notes"
                        placeholder="Example: Garbage is placed outside the gate."
                    ><?= h($_POST['notes'] ?? '') ?></textarea>
                </div>

                <button
                    type="submit"
                    name="schedule"
                    class="submit-button">
                    <i class="fas fa-calendar-check me-1"></i>
                    Submit Collection Request
                </button>

            </form>

        </div>

        <!-- COLLECTION HISTORY -->
        <div class="schedule-card">

            <h3>My Collection Requests</h3>

            <div class="table-wrapper">

                <table class="history-table">

                    <thead>
                        <tr>
                            <th>Collection Date</th>
                            <th>Requested Time</th>
                            <th>Address</th>
                            <th>Notes</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (mysqli_num_rows($history_result) > 0): ?>

                            <?php while ($row = mysqli_fetch_assoc($history_result)): ?>

                                <?php
                                $status_class = "status-" .
                                    strtolower(trim($row["status"]));
                                ?>

                                <tr>
                                    <td>
                                        <?= h($row["collection_date"]) ?>
                                    </td>

                                    <td>
                                        <?= h($row["requested_time"]) ?>
                                    </td>

                                    <td>
                                        <?= h($row["collection_address"]) ?>
                                    </td>

                                    <td>
                                        <?= h($row["notes"]) ?>
                                    </td>

                                    <td>
                                        <span class="status <?= h($status_class) ?>">
                                            <?= h($row["status"]) ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="text-center">
                                    You have no collection requests yet.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <div class="dashboard-footer">
            &copy; <?= date("Y") ?> EcoTrack Waste Collection Management System
            <br>
            Resident Dashboard
        </div>

    </main>
</div>

<script>
function selectDate(date) {
    const dateInput = document.getElementById("collection_date");

    dateInput.value = date;

    document.getElementById("schedule-form-card").scrollIntoView({
        behavior: "smooth",
        block: "start"
    });

    dateInput.focus();
}
</script>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php
mysqli_stmt_close($history);
?>
