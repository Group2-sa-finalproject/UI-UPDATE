<?php
// Included by dashboard.php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/send_email.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";

// HANDLE ADMIN ACTIONS
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {

        $error = "Invalid request. Please try again.";

    } else {

        $action = $_POST["action"] ?? "";
        $schedule_id = (int)($_POST["schedule_id"] ?? 0);

        // APPROVE, COMPLETE, OR REJECT
        if ($action === "update_status") {

            $new_status = $_POST["status"] ?? "";

            if (
                $schedule_id < 1 ||
                !in_array($new_status, ["Approved", "Completed", "Rejected"], true)
            ) {

                $error = "Invalid schedule or status.";

            } else {

                $check = $conn->prepare("
                    SELECT
                        cs.status,
                        cs.collection_date,
                        cs.requested_time,
                        cs.collection_address,
                        u.fullname,
                        u.email,
                        u.email_verified
                    FROM collection_schedules cs
                    LEFT JOIN users u ON cs.user_id = u.id
                    WHERE cs.id = ?
                    LIMIT 1
                ");

                if (!$check) {

                    $error = "Database error: " . $conn->error;

                } else {

                    $check->bind_param("i", $schedule_id);
                    $check->execute();

                    $request = $check->get_result()->fetch_assoc();
                    $check->close();

                    if (!$request) {

                        $error = "Pickup request not found.";

                    } else {

                        $old_status = $request["status"];

                        $stmt = $conn->prepare("
                            UPDATE collection_schedules
                            SET status = ?
                            WHERE id = ?
                        ");

                        if (!$stmt) {

                            $error = "Database error: " . $conn->error;

                        } else {

                            $stmt->bind_param("si", $new_status, $schedule_id);

                            if ($stmt->execute()) {

                                $message = "Collection schedule status updated successfully.";

                                // SEND EMAIL FOR NEW APPROVAL OR REJECTION
                                if (
                                    $old_status !== $new_status &&
                                    in_array($new_status, ["Approved", "Rejected"], true) &&
                                    !empty($request["email"]) &&
                                    (int)$request["email_verified"] === 1
                                ) {

                                    $resident = htmlspecialchars(
                                        $request["fullname"] ?? "Resident",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    $date = htmlspecialchars(
                                        date("F d, Y", strtotime($request["collection_date"])),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    $time = htmlspecialchars(
                                        $request["requested_time"] ?? "Not specified",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    $address = htmlspecialchars(
                                        $request["collection_address"] ?? "Not specified",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    if ($new_status === "Approved") {

                                        $subject = "Your Special Pickup Request Has Been Approved";
                                        $color = "#198754";

                                        $status_message = "
                                            <p>Good news! Your special pickup request
                                            has been <strong>approved</strong>.</p>

                                            <p>Please be ready on your scheduled
                                            collection date.</p>
                                        ";

                                    } else {

                                        $subject = "Your Special Pickup Request Has Been Rejected";
                                        $color = "#dc3545";

                                        $status_message = "
                                            <p>We regret to inform you that your
                                            special pickup request has been
                                            <strong>rejected/cancelled</strong>.</p>

                                            <p>Please contact the Waste Collection
                                            Management Office if you need assistance.</p>
                                        ";
                                    }

                                    $body = "
                                        <div style='font-family:Arial,sans-serif;
                                                    max-width:600px;
                                                    margin:auto;
                                                    padding:20px;
                                                    border:1px solid #ddd;
                                                    border-radius:10px;'>

                                            <h2 style='color:#198754;'>
                                                Waste Collection Management System
                                            </h2>

                                            <p>Hello $resident,</p>

                                            $status_message

                                            <div style='background:#f5f7f6;
                                                        padding:15px;
                                                        border-radius:8px;'>

                                                <h3>Pickup Details</h3>

                                                <p><strong>Date:</strong> $date</p>
                                                <p><strong>Time:</strong> $time</p>
                                                <p><strong>Address:</strong> $address</p>

                                                <p>
                                                    <strong>Status:</strong>
                                                    <span style='color:$color;font-weight:bold;'>
                                                        $new_status
                                                    </span>
                                                </p>

                                            </div>

                                            <p>Thank you for using our
                                            Waste Collection Management System.</p>

                                            <p>Regards,<br>
                                            Waste Collection Management Team</p>

                                        </div>
                                    ";

                                    if (sendEmail($request["email"], $subject, $body)) {

                                        $message .= " Notification email sent successfully.";

                                    } else {

                                        $message .= " However, the status was updated but the notification email could not be sent.";
                                    }
                                }

                            } else {

                                $error = "Unable to update the collection schedule.";
                            }

                            $stmt->close();
                        }
                    }
                }
            }

        // ASSIGN COLLECTOR
        } elseif ($action === "assign_collector") {

            $collector_id = (int)($_POST["collector_id"] ?? 0);

            if ($schedule_id < 1 || $collector_id < 1) {

                $error = "Please select a valid collector.";

            } else {

                $check = $conn->prepare("
                    SELECT id
                    FROM collection_schedules
                    WHERE id = ? AND status = 'Approved'
                ");

                $check->bind_param("i", $schedule_id);
                $check->execute();

                $approved = $check->get_result()->fetch_assoc();
                $check->close();

                $cc = $conn->prepare("
                    SELECT id
                    FROM users
                    WHERE id = ? AND role = 'collector'
                ");

                $cc->bind_param("i", $collector_id);
                $cc->execute();

                $valid = $cc->get_result()->fetch_assoc();
                $cc->close();

                if (!$approved) {

                    $error = "Only approved requests can be assigned.";

                } elseif (!$valid) {

                    $error = "Invalid collector account.";

                } else {

                    $stmt = $conn->prepare("
                        UPDATE collection_schedules
                        SET collector_id = ?
                        WHERE id = ? AND status = 'Approved'
                    ");

                    $stmt->bind_param("ii", $collector_id, $schedule_id);

                    $message = $stmt->execute()
                        ? "Collector assigned successfully."
                        : "Unable to assign collector.";

                    $stmt->close();
                }
            }

        } else {

            $error = "Invalid action.";
        }
    }
}


// CALENDAR MONTH
$month = $_GET["month"] ?? date("Y-m");

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date("Y-m");
}

$month_start = $month . "-01";

$date_object = DateTime::createFromFormat("!Y-m-d", $month_start);

if (!$date_object || $date_object->format("Y-m-d") !== $month_start) {

    $month = date("Y-m");
    $month_start = $month . "-01";
    $date_object = new DateTime($month_start);
}

$month_end = date("Y-m-t", strtotime($month_start));
$days_in_month = (int)date("t", strtotime($month_start));
$first_day = (int)date("w", strtotime($month_start));

$previous_month = date("Y-m", strtotime($month_start . " -1 month"));
$next_month = date("Y-m", strtotime($month_start . " +1 month"));

$month_label = $date_object->format("F Y");
$today = date("Y-m-d");


// DATABASE HELPER
function ecotrackRows($conn, $sql, $types = null, $params = []) {

    $rows = [];

    if ($types !== null) {

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return $rows;
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();

    } else {

        $result = $conn->query($sql);
    }

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    if (isset($stmt)) {
        $stmt->close();
    }

    return $rows;
}


// GET MONTH SCHEDULES
$schedules = ecotrackRows(
    $conn,
    "SELECT
        cs.id,
        cs.user_id,
        cs.collection_date,
        cs.requested_time,
        cs.collection_address,
        cs.notes,
        cs.status,
        cs.created_at,
        u.fullname,
        u.users
     FROM collection_schedules cs
     LEFT JOIN users u ON cs.user_id = u.id
     WHERE cs.collection_date BETWEEN ? AND ?
     ORDER BY cs.collection_date ASC, cs.id DESC",
    "ss",
    [$month_start, $month_end]
);


// GET PENDING REQUESTS
$pending_schedules = ecotrackRows(
    $conn,
    "SELECT
        cs.id,
        cs.collection_date,
        cs.requested_time,
        cs.collection_address,
        cs.notes,
        cs.status,
        cs.created_at,
        u.fullname,
        u.users
     FROM collection_schedules cs
     LEFT JOIN users u ON cs.user_id = u.id
     WHERE cs.status = 'Pending'
     ORDER BY cs.collection_date ASC"
);


// GET COLLECTORS
$collectors = ecotrackRows(
    $conn,
    "SELECT id, fullname, users
     FROM users
     WHERE role = 'collector'
     ORDER BY fullname ASC"
);


// GET APPROVED REQUESTS
$approved_schedules = ecotrackRows(
    $conn,
    "SELECT
        cs.id,
        cs.collection_date,
        cs.requested_time,
        cs.collection_address,
        cs.notes,
        cs.collector_id,
        u.fullname,
        u.users,
        c.fullname AS collector_name
     FROM collection_schedules cs
     LEFT JOIN users u ON cs.user_id = u.id
     LEFT JOIN users c ON cs.collector_id = c.id
     WHERE cs.status = 'Approved'
     ORDER BY cs.collection_date ASC, cs.requested_time ASC"
);


// BOOKED DATES
$booked_dates = [];

foreach ($schedules as $s) {

    if (in_array($s["status"], ["Pending", "Approved"], true)) {

        $booked_dates[$s["collection_date"]][] = $s;
    }
}
?>

<style>
/* EcoTrack Admin — Collection Schedule */
.schedule-page { --forest:#173e2b; --green:#23834a; --border:#e5ece7; --muted:#748278; color:#283b30; width:100%; min-width:0; }
.schedule-page * { box-sizing:border-box; }
.schedule-page .schedule-heading { display:flex; align-items:center; gap:13px; margin-bottom:24px; }
.schedule-page .heading-icon { width:46px; height:46px; border-radius:12px; display:grid; place-items:center; background:#e8f4eb; color:var(--green); font-size:20px; flex-shrink:0; }
.schedule-page .page-title { color:var(--forest); font-weight:750; font-size:25px; margin:0 0 4px; }
.schedule-page .page-subtitle { color:var(--muted); font-size:13px; margin:0; }
.schedule-page .schedule-card { background:#fff; border:1px solid var(--border); border-radius:14px; box-shadow:0 3px 16px rgba(20,60,38,.045); margin-bottom:22px; overflow:hidden; }
.schedule-page .card-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:20px 23px; border-bottom:1px solid #edf1ee; }
.schedule-page .section-title { color:var(--forest); font-size:16px; font-weight:700; margin:0; display:flex; align-items:center; gap:9px; }
.schedule-page .section-title i { color:var(--green); }
.schedule-page .section-caption { font-size:12px; color:var(--muted); margin:6px 0 0; }
.schedule-page .card-content { padding:22px 23px; }
.schedule-page .summary-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:13px; margin-bottom:22px; }
.schedule-page .summary-item { background:#fff; border:1px solid var(--border); border-radius:12px; padding:17px 19px; display:flex; align-items:center; gap:14px; }
.schedule-page .summary-icon { width:43px; height:43px; display:grid; place-items:center; border-radius:11px; background:#eaf5ed; color:#25814a; flex-shrink:0; }
.schedule-page .summary-icon.amber { background:#fff4df; color:#a86c12; }
.schedule-page .summary-icon.blue { background:#eaf2ff; color:#3466b0; }
.schedule-page .summary-value { font-size:24px; font-weight:750; line-height:1.1; color:var(--forest); }
.schedule-page .summary-label { font-size:12px; color:var(--muted); margin-top:5px; }
.schedule-page .month-controls { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.schedule-page .month-label { font-size:17px; font-weight:700; color:var(--forest); min-width:140px; text-align:center; }
.schedule-page .btn-month { display:inline-flex; align-items:center; gap:7px; padding:8px 12px; border:1px solid #dce8df; border-radius:8px; background:#f8fbf9; color:#27633f; text-decoration:none; font-size:12px; font-weight:650; }
.schedule-page .btn-month:hover { background:#e8f4eb; color:#194f30; }
.schedule-page .calendar-scroll { overflow-x:auto; }
.schedule-page .calendar-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:7px; min-width:560px; }
.schedule-page .weekday { text-align:center; padding:11px 3px; background:#f1f6f2; border-radius:8px; font-size:12px; font-weight:700; color:#52705c; }
.schedule-page .calendar-day { min-height:105px; border:1px solid #e7ede8; border-radius:9px; padding:10px; background:#fff; min-width:0; }
.schedule-page .calendar-day.available { background:#f5fbf6; border-color:#d7e9dc; }
.schedule-page .calendar-day.booked { background:#fff7f4; border-color:#f3ded5; }
.schedule-page .calendar-day.past { background:#f6f7f6; color:#a0aaa3; }
.schedule-page .calendar-day.today { outline:2px solid #4a9b66; outline-offset:-2px; }
.schedule-page .day-number { font-weight:750; font-size:13px; margin-bottom:8px; }
.schedule-page .calendar-name { display:block; font-size:11px; line-height:1.4; margin-top:7px; overflow-wrap:anywhere; }
.schedule-page .calendar-name strong { display:block; margin-bottom:4px; }
.schedule-page .calendar-empty { font-size:11px; color:#92a096; }
.schedule-page .calendar-legend { display:flex; flex-wrap:wrap; gap:18px; margin-top:18px; font-size:12px; color:#617368; }
.schedule-page .legend-dot { display:inline-block; width:10px; height:10px; border-radius:3px; margin-right:6px; vertical-align:middle; }
.schedule-page .legend-dot.available { background:#a9d9b5; }
.schedule-page .legend-dot.booked { background:#e8a59a; }
.schedule-page .legend-dot.past { background:#cbd1cd; }
.schedule-page .calendar-note { font-size:12px; color:var(--muted); margin:13px 0 0; }
.schedule-page .table-responsive { width:100%; overflow-x:auto; }
.schedule-page .schedule-table { margin:0; font-size:12px; border-collapse:collapse; width:100%; }
.schedule-page .schedule-table thead th { background:#f5f8f6; color:#526b5b; font-size:11px; font-weight:750; text-transform:uppercase; letter-spacing:.25px; padding:13px 14px; border-bottom:1px solid var(--border); white-space:nowrap; }
.schedule-page .schedule-table tbody td { padding:14px; border-bottom:1px solid #eef2ef; vertical-align:middle; color:#405246; }
.schedule-page .schedule-table tbody tr:last-child td { border-bottom:0; }
.schedule-page .schedule-table tbody tr:hover { background:#fafcfb; }
.schedule-page .resident-name { color:#234b34; font-weight:700; }
.schedule-page .cell-secondary { color:#87948a; }
.schedule-page .address-cell { min-width:160px; max-width:250px; white-space:normal; overflow-wrap:anywhere; }
.schedule-page .status-badge { display:inline-block; padding:5px 9px; border-radius:6px; font-size:10px; font-weight:700; white-space:nowrap; }
.schedule-page .status-pending { background:#fff1d6; color:#956512; }
.schedule-page .status-approved { background:#e4efff; color:#285f9c; }
.schedule-page .status-completed { background:#dff3e5; color:#237547; }
.schedule-page .status-rejected { background:#fde7e7; color:#a43e3e; }
.schedule-page .action-form { display:flex; align-items:center; gap:7px; min-width:205px; }
.schedule-page .action-form .form-select { min-width:115px; font-size:12px; border-color:#dce6df; border-radius:7px; }
.schedule-page .btn-action { background:#23834a; border:1px solid #23834a; border-radius:7px; color:#fff; padding:7px 11px; font-size:11px; font-weight:650; white-space:nowrap; }
.schedule-page .btn-action:hover { background:#176b39; border-color:#176b39; color:#fff; }
.schedule-page .empty-state { padding:34px 18px !important; text-align:center; color:#8a968d !important; }
.schedule-page .empty-state i { display:block; font-size:24px; margin-bottom:9px; color:#b3c5b8; }
.schedule-page .schedule-alert { border-radius:10px; margin-bottom:18px; }
@media(max-width:800px) { .schedule-page .summary-grid { grid-template-columns:1fr; } .schedule-page .card-heading,.schedule-page .card-content { padding:17px; } }
@media(max-width:560px) { .schedule-page .page-title { font-size:21px; } .schedule-page .month-controls { width:100%; justify-content:space-between; } .schedule-page .month-label { min-width:0; font-size:14px; } .schedule-page .btn-month { padding:7px 9px; } .schedule-page .schedule-heading { align-items:flex-start; } }

/* Refined tables: fit the dashboard content without changing its sidebar */
.schedule-page .table-responsive { overflow-x: auto; }
.schedule-page .schedule-table { table-layout: fixed; width: 100%; min-width: 0; }
.schedule-page .schedule-table thead th {
    padding: 13px 9px; font-size: 10px; line-height: 1.35;
    white-space: normal; overflow-wrap: anywhere; vertical-align: middle;
}
.schedule-page .schedule-table tbody td {
    padding: 13px 9px; font-size: 11px; line-height: 1.5;
    overflow-wrap: anywhere; word-break: normal; vertical-align: middle;
}
.schedule-page .schedule-table .address-cell { min-width: 0; max-width: none; }
.schedule-page .schedule-table .action-form {
    display: flex; flex-direction: column; align-items: stretch;
    gap: 7px; min-width: 0; width: 100%; margin: 0;
}
.schedule-page .schedule-table .action-form .form-select {
    width: 100%; min-width: 0; max-width: 100%; font-size: 11px;
    padding: 7px 24px 7px 7px; border-radius: 6px;
}
.schedule-page .schedule-table .action-form .btn-action {
    width: 100%; padding: 8px 6px; white-space: normal;
}
.schedule-page .schedule-table .status-badge { max-width: 100%; }
/* Pending table: proportionate columns, no forced desktop overflow */
.schedule-page .pending-table th:nth-child(1) { width: 10%; }
.schedule-page .pending-table th:nth-child(2) { width: 9%; }
.schedule-page .pending-table th:nth-child(3) { width: 13%; }
.schedule-page .pending-table th:nth-child(4) { width: 10%; }
.schedule-page .pending-table th:nth-child(5) { width: 14%; }
.schedule-page .pending-table th:nth-child(6) { width: 13%; }
.schedule-page .pending-table th:nth-child(7) { width: 10%; }
.schedule-page .pending-table th:nth-child(8) { width: 21%; }
/* Approved table */
.schedule-page .approved-table th:nth-child(1) { width: 15%; }
.schedule-page .approved-table th:nth-child(2) { width: 16%; }
.schedule-page .approved-table th:nth-child(3) { width: 13%; }
.schedule-page .approved-table th:nth-child(4) { width: 20%; }
.schedule-page .approved-table th:nth-child(5) { width: 16%; }
.schedule-page .approved-table th:nth-child(6) { width: 20%; }
@media (max-width: 800px) {
    .schedule-page .pending-table { min-width: 780px; }
    .schedule-page .approved-table { min-width: 660px; }
    .schedule-page .all-schedules-table { min-width: 560px; }
}
</style>

<div class="schedule-page">
    <div class="schedule-heading">
        <div class="heading-icon"><i class="fas fa-calendar-alt"></i></div>
        <div>
            <h2 class="page-title">Collection Schedule</h2>
            <p class="page-subtitle">Manage collection bookings, review requests, and assign collectors.</p>
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-success schedule-alert" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error !== ""): ?>
        <div class="alert alert-danger schedule-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="summary-grid">
        <div class="summary-item">
            <span class="summary-icon"><i class="fas fa-calendar-check"></i></span>
            <div><div class="summary-value"><?= count($schedules) ?></div><div class="summary-label">Schedules This Month</div></div>
        </div>
        <div class="summary-item">
            <span class="summary-icon amber"><i class="fas fa-clock"></i></span>
            <div><div class="summary-value"><?= count($pending_schedules) ?></div><div class="summary-label">Pending Requests</div></div>
        </div>
        <div class="summary-item">
            <span class="summary-icon blue"><i class="fas fa-truck"></i></span>
            <div><div class="summary-value"><?= count($approved_schedules) ?></div><div class="summary-label">Approved Requests</div></div>
        </div>
    </div>

    <!-- CALENDAR -->
    <section class="schedule-card">
        <div class="card-heading">
            <div>
                <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Collection Calendar</h3>
                <p class="section-caption">View scheduled and available collection dates.</p>
            </div>
            <nav class="month-controls" aria-label="Calendar month navigation">
                <a class="btn-month" href="dashboard.php?page=pickup_schedule&amp;month=<?= htmlspecialchars($previous_month, ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                <span class="month-label"><?= htmlspecialchars($month_label, ENT_QUOTES, 'UTF-8') ?></span>
                <a class="btn-month" href="dashboard.php?page=pickup_schedule&amp;month=<?= htmlspecialchars($next_month, ENT_QUOTES, 'UTF-8') ?>">Next <i class="fas fa-chevron-right"></i></a>
            </nav>
        </div>
        <div class="card-content">
            <div class="calendar-scroll">
                <div class="calendar-grid">
                    <?php foreach (["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"] as $weekday): ?>
                        <div class="weekday"><?= $weekday ?></div>
                    <?php endforeach; ?>
                    <?php for ($i = 0; $i < $first_day; $i++): ?>
                        <div aria-hidden="true"></div>
                    <?php endfor; ?>
                    <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                        <?php
                        $calendar_date = $month . "-" . str_pad((string)$day, 2, "0", STR_PAD_LEFT);
                        $is_past = $calendar_date < $today;
                        $is_booked = isset($booked_dates[$calendar_date]);
                        $day_class = $is_past ? "past" : ($is_booked ? "booked" : "available");
                        ?>
                        <div class="calendar-day <?= $day_class ?> <?= $calendar_date === $today ? 'today' : '' ?>">
                            <div class="day-number"><?= $day ?></div>
                            <?php if ($is_booked): ?>
                                <?php foreach ($booked_dates[$calendar_date] as $booking): ?>
                                    <span class="calendar-name">
                                        <strong><?= htmlspecialchars($booking['fullname'] ?? 'Unknown Resident', ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span class="status-badge status-<?= strtolower(htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8')) ?>"><?= htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="calendar-empty"><?= $is_past ? 'Past date' : 'Available' ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="calendar-legend">
                <span><i class="legend-dot available"></i>Available</span>
                <span><i class="legend-dot booked"></i>Booked</span>
                <span><i class="legend-dot past"></i>Past date</span>
            </div>
            <p class="calendar-note">Pending and approved requests reserve their dates. Completed or rejected requests do not reserve a date.</p>
        </div>
    </section>

    <!-- PENDING REQUESTS -->
    <section class="schedule-card">
        <div class="card-heading">
            <div>
                <h3 class="section-title"><i class="fas fa-clock"></i> Pending Collection Requests</h3>
                <p class="section-caption">Review and update residents' pending collection requests.</p>
            </div>
            <span class="status-badge status-pending"><?= count($pending_schedules) ?> Pending</span>
        </div>
        <div class="table-responsive">
            <table class="table schedule-table pending-table">
                <thead><tr><th>Resident</th><th>Username</th><th>Collection Date</th><th>Requested Time</th><th>Address</th><th>Notes</th><th>Status</th><th>Admin Action</th></tr></thead>
                <tbody>
                    <?php if ($pending_schedules): ?>
                        <?php foreach ($pending_schedules as $s): ?>
                            <tr>
                                <td class="resident-name"><?= htmlspecialchars($s['fullname'] ?? 'Unknown Resident', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="cell-secondary"><?= htmlspecialchars($s['users'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(date('F d, Y', strtotime($s['collection_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($s['requested_time'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="address-cell"><?= htmlspecialchars($s['collection_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="address-cell"><?= htmlspecialchars($s['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>
                                    <form method="POST" action="dashboard.php?page=pickup_schedule" class="action-form">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                                        <select name="status" class="form-select form-select-sm" aria-label="Update request status" required>
                                            <option value="">Choose Action</option>
                                            <option value="Approved">Approve</option>
                                            <option value="Completed">Mark Completed</option>
                                            <option value="Rejected">Reject</option>
                                        </select>
                                        <button type="submit" class="btn btn-action">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="empty-state"><i class="fas fa-check-circle"></i>No pending collection requests.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- APPROVED REQUESTS -->
    <section class="schedule-card">
        <div class="card-heading">
            <div>
                <h3 class="section-title"><i class="fas fa-truck"></i> Approved Requests &amp; Collector Assignment</h3>
                <p class="section-caption">Assign a collector to each approved collection request.</p>
            </div>
            <span class="status-badge status-approved"><?= count($approved_schedules) ?> Approved</span>
        </div>
        <div class="table-responsive">
            <table class="table schedule-table approved-table">
                <thead><tr><th>Resident</th><th>Collection Date</th><th>Requested Time</th><th>Address</th><th>Assigned Collector</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if ($approved_schedules): ?>
                        <?php foreach ($approved_schedules as $s): ?>
                            <tr>
                                <td class="resident-name"><?= htmlspecialchars($s['fullname'] ?? 'Unknown Resident', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(date('F d, Y', strtotime($s['collection_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($s['requested_time'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="address-cell"><?= htmlspecialchars($s['collection_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($s['collector_name'] ?? 'Not assigned', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <form method="POST" action="dashboard.php?page=pickup_schedule" class="action-form">
                                        <input type="hidden" name="action" value="assign_collector">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                                        <select name="collector_id" class="form-select form-select-sm" aria-label="Choose collector" required>
                                            <option value="">Choose Collector</option>
                                            <?php foreach ($collectors as $c): ?>
                                                <option value="<?= (int)$c['id'] ?>" <?= (int)$s['collector_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['fullname'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-action">Assign</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="empty-state"><i class="fas fa-inbox"></i>No approved requests available for assignment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ALL SCHEDULES -->
    <section class="schedule-card">
        <div class="card-heading">
            <div>
                <h3 class="section-title"><i class="fas fa-list-alt"></i> All Collection Schedules</h3>
                <p class="section-caption">All collection requests for <?= htmlspecialchars($month_label, ENT_QUOTES, 'UTF-8') ?>.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table schedule-table all-schedules-table">
                <thead><tr><th>Resident</th><th>Collection Date</th><th>Address</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if ($schedules): ?>
                        <?php foreach ($schedules as $s): ?>
                            <?php
                            $status_classes = [
                                'Pending' => 'status-pending',
                                'Approved' => 'status-approved',
                                'Completed' => 'status-completed',
                                'Rejected' => 'status-rejected'
                            ];
                            $status_class = $status_classes[$s['status']] ?? 'status-pending';
                            ?>
                            <tr>
                                <td class="resident-name"><?= htmlspecialchars($s['fullname'] ?? 'Unknown Resident', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(date('F d, Y', strtotime($s['collection_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="address-cell"><?= htmlspecialchars($s['collection_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($s['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="empty-state"><i class="fas fa-calendar-times"></i>No collection schedules found for this month.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
