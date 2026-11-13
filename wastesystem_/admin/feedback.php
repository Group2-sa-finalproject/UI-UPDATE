
<?php
// Included inside admin/dashboard.php.
// No additional HTML, HEAD, BODY, or SIDEBAR.

// Get all feedback from residents
$sql = "
    SELECT
        id,
        user_id,
        feedback_type,
        message,
        created_at
    FROM feedback
    ORDER BY created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database error: " . htmlspecialchars($conn->error));
}

$feedbacks = [];
$counts = [
    "total" => 0,
    "complaint" => 0,
    "suggestion" => 0,
    "satisfaction" => 0
];

while ($row = $result->fetch_assoc()) {
    $feedbacks[] = $row;
    $counts["total"]++;

    $type = strtolower(trim($row["feedback_type"] ?? ""));

    if (isset($counts[$type])) {
        $counts[$type]++;
    }
}

$result->free();

function feedbackEscape($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<style>
/* ==========================================
   RESIDENT FEEDBACK - ECOTRACK ADMIN
   ========================================== */

.feedback-container {
    width: 100%;
    min-width: 0;
    color: #344b3d;
}

/* PAGE HEADER */
.feedback-header {
    margin-bottom: 25px;
}

.feedback-container .page-title {
    color: #173e2b;
    font-size: 25px;
    font-weight: 700;
    margin: 0 0 7px;
}

.feedback-container .page-description {
    color: #829087;
    font-size: 13px;
    line-height: 1.6;
    margin: 0;
}

/* SUMMARY CARDS */
.feedback-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}

.feedback-stat-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: #fff;
    border: 1px solid #e7eee9;
    border-radius: 12px;
    padding: 19px;
    box-shadow: 0 3px 12px rgba(30, 70, 45, .03);
}

.feedback-stat-label {
    color: #7a897f;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 8px;
}

.feedback-stat-value {
    color: #183e2c;
    font-size: 27px;
    font-weight: 700;
    line-height: 1;
}

.feedback-stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    font-size: 17px;
}

.feedback-stat-icon.total {
    background: #eaf5ed;
    color: #25804a;
}

.feedback-stat-icon.complaints {
    background: #fff0ee;
    color: #d35b4f;
}

.feedback-stat-icon.suggestions {
    background: #eaf3ff;
    color: #397bc3;
}

.feedback-stat-icon.satisfaction {
    background: #e8f7ef;
    color: #25945a;
}

/* MAIN CARD */
.feedback-card {
    background: #fff;
    border: 1px solid #e7eee9;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(30, 70, 45, .035);
    overflow: hidden;
}

.feedback-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    padding: 21px 23px;
    border-bottom: 1px solid #edf1ee;
}

.feedback-card-title {
    color: #1b432f;
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 5px;
}

.feedback-card-subtitle {
    color: #89958c;
    font-size: 12px;
    margin: 0;
}

.feedback-count {
    background: #eaf5ed;
    color: #267747;
    padding: 7px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}

/* TABLE */
.feedback-container .table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.feedback-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin: 0;
}

.feedback-table thead {
    background: #f3f7f4;
}

.feedback-table th {
    color: #526a5b;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .4px;
    text-transform: uppercase;
    padding: 16px 18px;
    text-align: left;
    border-bottom: 1px solid #e5ece7;
}

.feedback-table td {
    color: #45594c;
    font-size: 12px;
    padding: 18px;
    line-height: 1.6;
    vertical-align: middle;
    border-bottom: 1px solid #edf1ee;
}

.feedback-table tbody tr:last-child td {
    border-bottom: none;
}

.feedback-table tbody tr:hover {
    background: #f9fbf9;
}

/* COLUMN WIDTHS */
.feedback-table th:nth-child(1) {
    width: 8%;
}

.feedback-table th:nth-child(2) {
    width: 14%;
}

.feedback-table th:nth-child(3) {
    width: 18%;
}

.feedback-table th:nth-child(4) {
    width: 40%;
}

.feedback-table th:nth-child(5) {
    width: 20%;
}

/* ID */
.feedback-id {
    color: #849188;
    font-size: 11px;
    font-weight: 600;
}

/* RESIDENT */
.feedback-resident {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 600;
    color: #294b37;
}

.feedback-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 31px;
    height: 31px;
    border-radius: 9px;
    background: #eaf4ec;
    color: #388351;
    font-size: 12px;
}

/* FEEDBACK TYPE */
.feedback-type {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.feedback-type.complaint {
    background: #fcebea;
    color: #b74740;
}

.feedback-type.suggestion {
    background: #eaf3ff;
    color: #3976b5;
}

.feedback-type.satisfaction {
    background: #e7f6ed;
    color: #25844b;
}

.feedback-type.other {
    background: #f0f2f0;
    color: #68766d;
}

/* MESSAGE */
.feedback-message {
    color: #405448;
    font-size: 12px;
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
}

/* DATE */
.feedback-date {
    color: #53665a;
    font-size: 11px;
    font-weight: 600;
}

.feedback-time {
    display: block;
    color: #94a097;
    font-size: 10px;
    margin-top: 4px;
    font-weight: 400;
}

/* EMPTY STATE */
.feedback-empty {
    text-align: center;
    padding: 55px 20px !important;
}

.feedback-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    margin: 0 auto 15px;
    background: #edf5ef;
    color: #75a085;
    border-radius: 15px;
    font-size: 24px;
}

.feedback-empty-title {
    color: #385342;
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 5px;
}

.feedback-empty-text {
    color: #8a978d;
    font-size: 12px;
}

/* RESPONSIVE */
@media (max-width: 1100px) {
    .feedback-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767px) {
    .feedback-container .page-title {
        font-size: 21px;
    }

    .feedback-stats {
        gap: 10px;
    }

    .feedback-stat-card {
        padding: 14px;
    }

    .feedback-stat-value {
        font-size: 23px;
    }

    .feedback-stat-icon {
        width: 35px;
        height: 35px;
        font-size: 14px;
    }

    .feedback-card-header {
        padding: 17px;
    }

    .feedback-table {
        min-width: 780px;
    }
}

@media (max-width: 400px) {
    .feedback-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="feedback-container">

    <!-- PAGE HEADER -->
    <div class="feedback-header">
        <h1 class="page-title">
            Resident Feedback
        </h1>

        <p class="page-description">
            View and monitor complaints, suggestions,
            and satisfaction messages submitted by residents.
        </p>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="feedback-stats">

        <div class="feedback-stat-card">
            <div>
                <div class="feedback-stat-label">
                    Total Feedback
                </div>

                <div class="feedback-stat-value">
                    <?= $counts["total"] ?>
                </div>
            </div>

            <div class="feedback-stat-icon total">
                <i class="fas fa-comments"></i>
            </div>
        </div>

        <div class="feedback-stat-card">
            <div>
                <div class="feedback-stat-label">
                    Complaints
                </div>

                <div class="feedback-stat-value">
                    <?= $counts["complaint"] ?>
                </div>
            </div>

            <div class="feedback-stat-icon complaints">
                <i class="fas fa-exclamation-circle"></i>
            </div>
        </div>

        <div class="feedback-stat-card">
            <div>
                <div class="feedback-stat-label">
                    Suggestions
                </div>

                <div class="feedback-stat-value">
                    <?= $counts["suggestion"] ?>
                </div>
            </div>

            <div class="feedback-stat-icon suggestions">
                <i class="fas fa-lightbulb"></i>
            </div>
        </div>

        <div class="feedback-stat-card">
            <div>
                <div class="feedback-stat-label">
                    Satisfaction
                </div>

                <div class="feedback-stat-value">
                    <?= $counts["satisfaction"] ?>
                </div>
            </div>

            <div class="feedback-stat-icon satisfaction">
                <i class="fas fa-smile"></i>
            </div>
        </div>

    </div>

    <!-- FEEDBACK TABLE -->
    <div class="feedback-card">

        <div class="feedback-card-header">
            <div>
                <h3 class="feedback-card-title">
                    <i class="fas fa-list-alt me-2"></i>
                    All Resident Feedback
                </h3>

                <p class="feedback-card-subtitle">
                    Complete history of submitted resident feedback.
                </p>
            </div>

            <span class="feedback-count">
                <?= $counts["total"] ?>
                <?= $counts["total"] === 1 ? "Record" : "Records" ?>
            </span>
        </div>

        <div class="table-responsive">
            <table class="feedback-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Resident</th>
                        <th>Feedback Type</th>
                        <th>Message</th>
                        <th>Date Submitted</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($feedbacks)): ?>

                        <?php foreach ($feedbacks as $row): ?>
                            <?php
                            $type = trim(
                                (string) ($row["feedback_type"] ?? "")
                            );

                            $typeKey = strtolower($type);

                            $typeClass = in_array(
                                $typeKey,
                                ["complaint", "suggestion", "satisfaction"],
                                true
                            ) ? $typeKey : "other";

                            $icons = [
                                "complaint" => "fa-exclamation-circle",
                                "suggestion" => "fa-lightbulb",
                                "satisfaction" => "fa-smile",
                                "other" => "fa-comment"
                            ];

                            $icon = $icons[$typeClass];

                            $timestamp = strtotime(
                                $row["created_at"] ?? ""
                            );
                            ?>

                            <tr>
                                <!-- ID -->
                                <td>
                                    <span class="feedback-id">
                                        #<?= (int) $row["id"] ?>
                                    </span>
                                </td>

                                <!-- RESIDENT -->
                                <td>
                                    <div class="feedback-resident">
                                        <span class="feedback-avatar">
                                            <i class="fas fa-user"></i>
                                        </span>

                                        <span>
                                            <?= feedbackEscape($row["user_id"]) ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- TYPE -->
                                <td>
                                    <span class="feedback-type <?= $typeClass ?>">
                                        <i class="fas <?= $icon ?>"></i>
                                        <?= feedbackEscape($type ?: "Other") ?>
                                    </span>
                                </td>

                                <!-- MESSAGE -->
                                <td>
                                    <div class="feedback-message">
                                        <?= feedbackEscape($row["message"]) ?>
                                    </div>
                                </td>

                                <!-- DATE -->
                                <td>
                                    <span class="feedback-date">
                                        <?= $timestamp
                                            ? date("M d, Y", $timestamp)
                                            : "N/A" ?>
                                    </span>

                                    <span class="feedback-time">
                                        <?= $timestamp
                                            ? date("h:i A", $timestamp)
                                            : "" ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="feedback-empty">

                                <div class="feedback-empty-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>

                                <div class="feedback-empty-title">
                                    No Feedback Yet
                                </div>

                                <div class="feedback-empty-text">
                                    Resident feedback will appear here
                                    once submitted.
                                </div>

                            </td>
                        </tr>

                    <?php endif; ?>
                </tbody>

            </table>
        </div>

    </div>

</div>
