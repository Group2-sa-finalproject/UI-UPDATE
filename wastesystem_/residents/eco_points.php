<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'residents') {
    header('Location: ../index.php'); exit;
}
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection is unavailable.');
$conn->set_charset('utf8mb4');
$user_id = (int) $_SESSION['user_id'];
const MAX_SUBSCRIPTION_DISCOUNT = 150;
$message = ''; $message_type = ''; $fullname = 'Resident';
$available_points = $reserved_points = $redeem_limit = $total_earned = $total_redeemed = 0;
$subscription_exists = false; $redemption_history = [];
if (empty($_SESSION['eco_points_csrf'])) $_SESSION['eco_points_csrf'] = bin2hex(random_bytes(32));
function h($value) { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
try {
    $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ? AND role = 'residents' LIMIT 1");
    if (!$stmt) throw new Exception('Unable to retrieve resident information.');
    $stmt->bind_param('i', $user_id); $stmt->execute();
    $resident = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$resident) throw new Exception('Resident account not found.');
    $fullname = $resident['fullname'] ?: 'Resident';

    $stmt = $conn->prepare('SELECT COALESCE(SUM(points), 0) AS total_points FROM eco_points WHERE user_id = ?');
    if (!$stmt) throw new Exception('Unable to retrieve earned Eco Points.');
    $stmt->bind_param('i', $user_id); $stmt->execute();
    $total_earned = (int)($stmt->get_result()->fetch_assoc()['total_points'] ?? 0); $stmt->close();

    $stmt = $conn->prepare('SELECT COALESCE(SUM(points_redeemed), 0) AS total_redeemed FROM subscription_point_redemptions WHERE user_id = ?');
    if (!$stmt) throw new Exception('Unable to retrieve redeemed Eco Points.');
    $stmt->bind_param('i', $user_id); $stmt->execute();
    $total_redeemed = (int)($stmt->get_result()->fetch_assoc()['total_redeemed'] ?? 0); $stmt->close();
    $available_points = max(0, $total_earned - $total_redeemed);

    $stmt = $conn->prepare('SELECT COALESCE(SUM(points_redeemed), 0) AS reserved FROM subscription_point_redemptions WHERE user_id = ? AND subscription_id IS NULL');
    if (!$stmt) throw new Exception('Unable to retrieve reserved Eco Points.');
    $stmt->bind_param('i', $user_id); $stmt->execute();
    $reserved_points = (int)($stmt->get_result()->fetch_assoc()['reserved'] ?? 0); $stmt->close();
    $redeem_limit = min($available_points, max(0, MAX_SUBSCRIPTION_DISCOUNT - $reserved_points));

    $stmt = $conn->prepare('SELECT id FROM subscriptions WHERE user_id = ? LIMIT 1');
    if (!$stmt) throw new Exception('Unable to check subscription record.');
    $stmt->bind_param('i', $user_id); $stmt->execute();
    $subscription_exists = $stmt->get_result()->num_rows > 0; $stmt->close();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_points'])) {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['eco_points_csrf'], (string)$_POST['csrf_token']))
            throw new Exception('Invalid request. Please refresh the page and try again.');
        if ($subscription_exists) throw new Exception('You already have a subscription record. Please check your Subscription page.');
        $points_to_redeem = filter_var($_POST['points_to_redeem'] ?? null, FILTER_VALIDATE_INT);
        if ($points_to_redeem === false || $points_to_redeem < 1) throw new Exception('Please enter a valid number of Eco Points.');
        if ($points_to_redeem > $redeem_limit) throw new Exception('You can only reserve up to ' . number_format($redeem_limit) . ' additional points.');
        $discount_amount = (float)$points_to_redeem;
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('INSERT INTO subscription_point_redemptions (subscription_id, user_id, points_redeemed, discount_amount, created_at) VALUES (NULL, ?, ?, ?, NOW())');
            if (!$stmt) throw new Exception('Unable to prepare Eco Points redemption.');
            $stmt->bind_param('iid', $user_id, $points_to_redeem, $discount_amount);
            if (!$stmt->execute()) throw new Exception('Failed to save Eco Points redemption.');
            $stmt->close(); $conn->commit();
            header('Location: eco_points.php?success=1'); exit;
        } catch (Throwable $e) { $conn->rollback(); throw $e; }
    }
    if (isset($_GET['success']) && $_GET['success'] === '1') {
        $message = 'Your Eco Points have been successfully reserved!'; $message_type = 'success';
    }
    $stmt = $conn->prepare('SELECT id, created_at, points_redeemed, discount_amount, subscription_id FROM subscription_point_redemptions WHERE user_id = ? ORDER BY created_at DESC, id DESC');
    if (!$stmt) throw new Exception('Unable to retrieve redemption history.');
    $stmt->bind_param('i', $user_id); $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $redemption_history[] = $row;
    $stmt->close();
} catch (Throwable $e) { $message = $e->getMessage(); $message_type = 'error'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Eco Points | EcoTrack</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box}
body{margin:0;background:#f4f7f5;font-family:Arial,Helvetica,sans-serif;color:#263b30}
a{text-decoration:none}
/* Exact dashboard sidebar selectors and dimensions */
.sidebar{position:fixed;top:0;left:0;width:250px;height:100vh;background:#123d2b;color:#fff;padding:25px 15px;overflow-y:auto;z-index:1000}
.sidebar-logo{text-align:center;margin-bottom:35px}
.sidebar-logo h3{font-size:23px;font-weight:800;color:#fff;margin:0}
.sidebar-logo i{color:#9be7ad;margin-right:7px}
.sidebar-menu{list-style:none;padding:0;margin:0}
.sidebar-menu li{margin-bottom:8px}
.sidebar-menu a{display:flex;align-items:center;gap:13px;padding:13px 15px;color:#d8e8df;border-radius:8px;font-size:14px;font-weight:600;transition:.2s ease}
.sidebar-menu a i{width:20px;text-align:center;font-size:16px}
.sidebar-menu a:hover{background:rgba(255,255,255,.12);color:#fff}
.sidebar-menu a.active{background:#fff;color:#123d2b}
.sidebar-menu .logout-link{margin-top:25px;color:#ffcccc}
.sidebar-menu .logout-link:hover{background:rgba(255,255,255,.12);color:#fff}
.main-content{margin-left:250px;min-height:100vh}
.topbar{background:#fff;padding:18px 30px;display:flex;justify-content:flex-end;align-items:center;border-bottom:1px solid #e5eae6;min-height:75px}
.resident-profile{display:flex;align-items:center;gap:12px}
.resident-profile .profile-icon{width:40px;height:40px;background:#e3f3e8;color:#17643b;border-radius:50%;display:flex;justify-content:center;align-items:center;font-size:17px}
.resident-profile .profile-name{font-size:14px;font-weight:700;color:#183d2b}
.resident-profile .profile-role{display:block;font-size:11px;color:#819087;font-weight:500}
.content{padding:30px;min-width:0}
.page-heading{margin-bottom:25px}
.page-heading h2{font-size:26px;color:#183d2b;font-weight:800;margin-bottom:7px}
.page-heading p{color:#78877e;font-size:14px;margin:0}
.dashboard-card{background:#fff;padding:25px;border-radius:13px;box-shadow:0 3px 12px rgba(0,0,0,.05);border:1px solid #edf1ee;margin-bottom:22px}
.dashboard-card h3{font-size:18px;font-weight:800;color:#183d2b;margin:0 0 17px}
.dashboard-card h3 i{color:#198754;margin-right:8px}
.dashboard-card p{color:#66746b;font-size:14px;line-height:1.6}
.summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-bottom:22px}
.summary-card{background:#fff;border:1px solid #edf1ee;border-radius:13px;padding:20px;box-shadow:0 3px 12px rgba(0,0,0,.05)}
.summary-icon,.points-display-icon{background:#e7f5eb;color:#17643b;border-radius:10px;display:flex;align-items:center;justify-content:center}
.summary-icon{width:46px;height:46px;font-size:19px;margin-bottom:15px}
.summary-label{color:#78877e;font-size:13px;margin-bottom:7px}
.summary-value{color:#183d2b;font-size:26px;font-weight:800;margin:0}
.points-display{display:flex;align-items:center;gap:18px;margin-bottom:15px}
.points-display-icon{width:55px;height:55px;min-width:55px;font-size:24px}
.points-display-label{color:#78877e;font-size:13px;margin-bottom:5px}
.points-display-value{color:#183d2b;font-size:28px;font-weight:800;margin:0}
.detail-row{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;padding:13px 0;border-bottom:1px solid #edf1ee;font-size:14px}
.detail-row:last-child{border-bottom:0}
.detail-row span{color:#78877e}
.detail-row strong{color:#183d2b;text-align:right;overflow-wrap:anywhere}
.notice{padding:14px 17px;border-radius:9px;margin-bottom:20px;line-height:1.5;font-size:14px}
.notice.success{background:#edf7ef;border:1px solid #c6e8d0;color:#17643b}
.notice.error{background:#fce8e8;border:1px solid #f3cccc;color:#842029}
.notice.info{background:#edf7ef;border-left:4px solid #21834d;color:#355b43}
.discount-summary{background:#edf7ef;border:1px solid #dceee0;border-left:4px solid #198754;padding:16px;border-radius:9px;margin:17px 0}
.form-row{margin:18px 0}
.form-row label{display:block;font-weight:700;color:#304b3b;font-size:13px;margin-bottom:8px}
.form-row input{width:100%;max-width:320px;padding:11px 13px;border:1px solid #d6e0d9;border-radius:8px;font-size:13px;color:#263b30;background:#fff}
.form-row input:focus{border-color:#198754;box-shadow:0 0 0 3px rgba(25,135,84,.12);outline:none}
.small-note{color:#829087;font-size:13px;line-height:1.6}
.btn-ecotrack{display:inline-flex;align-items:center;justify-content:center;gap:7px;background:#17643b;color:#fff;border:0;border-radius:8px;padding:11px 18px;font-size:13px;font-weight:700;cursor:pointer}
.btn-ecotrack:hover{background:#104b2c;color:#fff}
.history-table-wrapper{width:100%;overflow-x:auto;border:1px solid #e7ede9;border-radius:9px}
.history-table{width:100%;min-width:600px;border-collapse:collapse;margin:0;font-size:13px}
.history-table th,.history-table td{padding:14px 15px;text-align:left;border-bottom:1px solid #e9eeeb}
.history-table th{background:#f4f8f5;color:#365342;font-weight:700;white-space:nowrap}
.history-table td{color:#53645a}
.history-table tbody tr:last-child td{border-bottom:0}
.history-table tbody tr:hover{background:#f9fbf9}
.status-badge{display:inline-block;padding:6px 12px;border-radius:30px;font-size:11px;font-weight:700;white-space:nowrap}
.status-reserved{background:#fff3cd;color:#856404}
.status-applied{background:#d8f3df;color:#17643b}
.empty-state{text-align:center;padding:30px 15px;color:#829087}
.empty-state i{display:block;font-size:30px;color:#a8b9ad;margin-bottom:12px}
.dashboard-card ul{padding-left:22px;color:#53645a;font-size:14px;line-height:1.9}
.dashboard-footer{margin-top:35px;padding:20px 0;border-top:1px solid #e2e9e5;color:#929b96;font-size:12px;text-align:center}
@media(max-width:1000px){.content{padding:20px}.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){
.sidebar{width:70px;padding:20px 8px}
.sidebar-logo h3{font-size:0}
.sidebar-logo i{font-size:25px;margin:0}
.sidebar-menu a{justify-content:center;padding:13px 8px}
.sidebar-menu a span{display:none}
.sidebar-menu a i{font-size:18px;width:auto}
.main-content{margin-left:70px}
.topbar{padding:15px}
.content{padding:18px 13px}
.page-heading h2{font-size:21px}
.dashboard-card{padding:18px}
.summary-grid{grid-template-columns:1fr}
.summary-card{padding:18px}
.points-display-value{font-size:23px}
}
@media print{.sidebar,.topbar,.page-heading,.dashboard-footer,.btn-ecotrack,form{display:none!important}.main-content{margin-left:0!important}.content{padding:0!important}body{background:#fff!important}.dashboard-card{box-shadow:none!important;border:1px solid #ddd!important}}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo"><h3><i class="fas fa-recycle"></i> EcoTrack</h3></div>
  <ul class="sidebar-menu">
    <li><a href="dashboard.php"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
    <li><a href="eco_points.php" class="active" aria-current="page"><i class="fas fa-coins"></i><span>Eco Points</span></a></li>
    <li><a href="collection_schedule.php"><i class="fas fa-calendar-alt"></i><span>Collection Schedule</span></a></li>
    <li><a href="track_truck.php"><i class="fas fa-truck"></i><span>Track Garbage Truck</span></a></li>
    <li><a href="subscription.php"><i class="fas fa-file-invoice-dollar"></i><span>Subscription</span></a></li>
    <li><a href="register_household.php"><i class="fas fa-house-user"></i><span>Register Household</span></a></li>
    <li><a href="feedback.php"><i class="fas fa-comment-dots"></i><span>Feedback</span></a></li>
    <li><a href="qrcode.php"><i class="fas fa-qrcode"></i><span>Resident QR Code</span></a></li>
    <li><a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
  </ul>
</aside>
<div class="main-content">
  <div class="topbar"><div class="resident-profile"><div class="profile-icon"><i class="fas fa-user"></i></div><div><div class="profile-name"><?= h($fullname) ?></div><span class="profile-role">Resident</span></div></div></div>
  <main class="content">
    <div class="page-heading"><h2><i class="fas fa-coins me-2"></i>My Eco Points</h2><p>Earn points by participating in proper waste management.</p></div>
    <?php if ($message !== ''): ?>
      <div class="notice <?= h($message_type) ?>"><i class="fas <?= $message_type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> me-2"></i><?= h($message) ?></div>
    <?php endif; ?>
    <div class="summary-grid">
      <div class="summary-card"><div class="summary-icon"><i class="fas fa-star"></i></div><p class="summary-label">Available Eco Points</p><h3 class="summary-value"><?= number_format($available_points) ?></h3></div>
      <div class="summary-card"><div class="summary-icon"><i class="fas fa-lock"></i></div><p class="summary-label">Reserved Points</p><h3 class="summary-value"><?= number_format($reserved_points) ?></h3></div>
      <div class="summary-card"><div class="summary-icon"><i class="fas fa-coins"></i></div><p class="summary-label">Additional Redeemable Points</p><h3 class="summary-value"><?= number_format($redeem_limit) ?></h3></div>
    </div>
    <section class="dashboard-card">
      <h3><i class="fas fa-star"></i>My Total Available Points</h3>
      <div class="points-display"><div class="points-display-icon"><i class="fas fa-coins"></i></div><div><p class="points-display-label">Current Available Balance</p><h2 class="points-display-value"><?= number_format($available_points) ?> Points</h2></div></div>
      <p class="small-note mb-0">Your available balance is your earned Eco Points minus all points already redeemed or reserved.</p>
    </section>
    <section class="dashboard-card">
      <h3><i class="fas fa-gift"></i>Reserve Eco Points for Your Subscription</h3>
      <p><strong>1 Eco Point = ₱1.00 discount.</strong> You may reserve up to 150 points for your one-month subscription fee of ₱150.</p>
      <div class="detail-row"><span><i class="fas fa-lock me-2"></i>Points Already Reserved</span><strong><?= number_format($reserved_points) ?> Points</strong></div>
      <div class="detail-row"><span><i class="fas fa-coins me-2"></i>Maximum Additional Points</span><strong><?= number_format($redeem_limit) ?> Points</strong></div>
      <?php if ($subscription_exists): ?>
        <div class="notice error mt-3"><i class="fas fa-circle-exclamation me-2"></i>You already have a subscription record. Please check your Subscription page.</div>
        <a href="subscription.php" class="btn-ecotrack"><i class="fas fa-file-invoice-dollar"></i>View Subscription</a>
      <?php elseif ($redeem_limit <= 0): ?>
        <div class="notice <?= $reserved_points >= MAX_SUBSCRIPTION_DISCOUNT ? 'success' : 'error' ?> mt-3">
          <?php if ($reserved_points >= MAX_SUBSCRIPTION_DISCOUNT): ?><i class="fas fa-check-circle me-2"></i>You have already reserved the maximum of 150 Eco Points for your next subscription.
          <?php else: ?><i class="fas fa-info-circle me-2"></i>You currently have no available points to redeem.<?php endif; ?>
        </div>
      <?php else: ?>
        <form method="POST" action="eco_points.php" class="mt-3">
          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['eco_points_csrf']) ?>">
          <div class="form-row"><label for="points_to_redeem">Eco Points to Redeem</label>
            <input type="number" id="points_to_redeem" name="points_to_redeem" min="1" max="<?= (int)$redeem_limit ?>" step="1" value="1" required>
            <p class="small-note mt-2 mb-0">You can reserve up to <?= number_format($redeem_limit) ?> additional points.</p>
          </div>
          <div class="discount-summary" aria-live="polite">
            <div class="detail-row"><span><i class="fas fa-tag me-2"></i>Eco Points Discount</span><strong id="discount_preview">₱1.00</strong></div>
            <div class="detail-row"><span><i class="fas fa-wallet me-2"></i>Remaining Subscription Amount</span><strong id="total_preview">₱<?= number_format(max(0, 150 - $reserved_points - 1), 2) ?></strong></div>
          </div>
          <button type="submit" name="redeem_points" value="1" class="btn-ecotrack"><i class="fas fa-check"></i>Apply Points</button>
        </form>
      <?php endif; ?>
      <div class="notice info mt-3 mb-0"><i class="fas fa-info-circle me-2"></i>Applying points only records your redemption. It does not create a subscription. Your reserved points will be applied when you submit your subscription application.</div>
    </section>
    <section class="dashboard-card">
      <h3><i class="fas fa-clock-rotate-left"></i>My Redemption History</h3>
      <?php if (!$redemption_history): ?>
        <div class="empty-state"><i class="fas fa-receipt"></i><p>You have no Eco Points redemption records yet.</p></div>
      <?php else: ?>
        <div class="history-table-wrapper"><table class="history-table"><thead><tr><th>Date</th><th>Points</th><th>Discount</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($redemption_history as $redemption): ?><tr>
          <td><?= h($redemption['created_at']) ?></td>
          <td><strong><?= number_format((int)$redemption['points_redeemed']) ?></strong></td>
          <td><strong>₱<?= number_format((float)$redemption['discount_amount'], 2) ?></strong></td>
          <td><?php if ($redemption['subscription_id'] === null): ?><span class="status-badge status-reserved"><i class="fas fa-hourglass-half me-1"></i>Reserved</span><?php else: ?><span class="status-badge status-applied"><i class="fas fa-check-circle me-1"></i>Applied to Subscription</span><?php endif; ?></td>
        </tr><?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </section>
    <section class="dashboard-card"><h3><i class="fas fa-leaf"></i>How to Earn Points</h3>
      <ul><li><strong>Proper Waste Segregation:</strong> Separate biodegradable, recyclable, and residual waste.</li><li><strong>Recycling Activities:</strong> Bring recyclable materials to the designated collection area.</li><li><strong>Scheduled Waste Collection:</strong> Follow the community waste collection schedule.</li><li><strong>Community Clean-Up:</strong> Participate in approved community clean-up activities.</li><li><strong>Environmental Programs:</strong> Join official environmental programs.</li></ul>
    </section>
    <section class="dashboard-card"><h3><i class="fas fa-circle-exclamation"></i>Important Redemption Rules</h3>
      <ul><li>1 Eco Point is equal to ₱1.00 discount.</li><li>The monthly subscription fee is ₱150.00.</li><li>A maximum of 150 Eco Points can be reserved for one subscription.</li><li>Reserved points are immediately deducted from your available balance.</li><li>Applying points does not create a subscription.</li><li>Reserved points will be attached when you apply through the Subscription page.</li><li>Cash Simulation payments still require administrator confirmation.</li></ul>
    </section>
    <div class="dashboard-footer">© <?= date('Y') ?> EcoTrack Waste Collection Management System<br>Resident Dashboard</div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const pointsInput = document.getElementById('points_to_redeem');

    if (pointsInput) {
        const discountPreview = document.getElementById('discount_preview');
        const totalPreview = document.getElementById('total_preview');
        const reserved = <?= (int)$reserved_points ?>;

        function updatePreview() {
            let points = parseInt(pointsInput.value, 10);

            if (!Number.isFinite(points) || points < 0) {
                points = 0;
            }

            points = Math.min(
                points,
                parseInt(pointsInput.max, 10) || 0,
                150
            );

            discountPreview.textContent = '₱' + points.toFixed(2);
            totalPreview.textContent = '₱' +
                Math.max(0, 150 - reserved - points).toFixed(2);
        }

        pointsInput.addEventListener('input', updatePreview);
        updatePreview();

        const redemptionForm = pointsInput.closest('form');

        if (redemptionForm) {
            redemptionForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const points = parseInt(pointsInput.value, 10);
                const maxPoints = parseInt(pointsInput.max, 10) || 0;

                if (!Number.isInteger(points) || points < 1 || points > maxPoints) {
                    Swal.fire({
                        title: 'Invalid Eco Points',
                        text: 'Please enter a valid number of points to redeem.',
                        icon: 'warning',
                        confirmButtonColor: '#17643b'
                    });
                    return;
                }

                Swal.fire({
                    title: 'Confirm Eco Points Redemption?',
                    html: `
                        You are about to reserve
                        <strong>${points} Eco Points</strong>
                        worth <strong>₱${points.toFixed(2)}</strong>
                        as a discount for your subscription.
                        <br><br>
                        <small>These points will be deducted from your available balance.</small>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#17643b',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Redeem Points',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        redemptionForm.submit();
                    }
                });
            });
        }
    }
});
</script>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
