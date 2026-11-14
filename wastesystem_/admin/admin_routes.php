<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db_system.php";

// Admin authentication
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$errorMessage = "";

// Create a CSRF token for all management forms.
if (empty($_SESSION['routes_csrf'])) {
    $_SESSION['routes_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['routes_csrf'];

if (!function_exists('h')) {
function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
}

if (!function_exists('routesRedirect')) {
function routesRedirect(string $query): void
{
    $url = 'dashboard.php?page=routes&' . ltrim($query, '&?');
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
    }
    exit;
}
}

if (!function_exists('verifyRoutesCsrf')) {
function verifyRoutesCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $saved = $_SESSION['routes_csrf'] ?? '';
    if (!$saved || !is_string($submitted) || !hash_equals($saved, $submitted)) {
        throw new Exception('Your session verification failed. Please refresh the page and try again.');
    }
}
}

// =====================================================
// CREATE HOUSEHOLD GROUP
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    try {
        verifyRoutesCsrf();
        $groupName = trim($_POST['group_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($groupName === '') {
            throw new Exception('Please enter a group name.');
        }
        if (mb_strlen($groupName) > 100) {
            throw new Exception('Group name must not exceed 100 characters.');
        }

        $stmt = $pdo->prepare("INSERT INTO household_groups (group_name, description) VALUES (?, ?)");
        $stmt->execute([$groupName, $description !== '' ? $description : null]);
        routesRedirect('group_created=1');
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// =====================================================
// ASSIGN / MOVE HOUSEHOLDS TO A GROUP
// group_id = 0 means Unassigned (NULL in the database).
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_households'])) {
    try {
        verifyRoutesCsrf();
        $groupId = (int)($_POST['group_id'] ?? -1);
        $householdIds = $_POST['household_ids'] ?? [];

        if ($groupId < 0) {
            throw new Exception('Please select a destination group.');
        }
        if (!is_array($householdIds) || !$householdIds) {
            throw new Exception('Please select at least one household.');
        }

        if ($groupId > 0) {
            $checkGroup = $pdo->prepare('SELECT id FROM household_groups WHERE id = ?');
            $checkGroup->execute([$groupId]);
            if (!$checkGroup->fetchColumn()) {
                throw new Exception('The selected household group does not exist.');
            }
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $householdIds), fn($id) => $id > 0)));
        if (!$ids) {
            throw new Exception('No valid households were selected.');
        }

        $pdo->beginTransaction();
        $update = $pdo->prepare('UPDATE households SET group_id = ? WHERE id = ?');
        foreach ($ids as $householdId) {
            $update->execute([$groupId === 0 ? null : $groupId, $householdId]);
        }
        $pdo->commit();

        routesRedirect('households_assigned=1');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

// =====================================================
// ASSIGN COLLECTOR TO A ROUTE
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_collector'])) {
    try {
        verifyRoutesCsrf();
        $routeId = (int)($_POST['route_id'] ?? 0);
        $collectorId = (int)($_POST['collector_id'] ?? 0);
        if ($routeId <= 0 || $collectorId <= 0) {
            throw new Exception('Please select a valid route and collector.');
        }

        $collectorCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'collector'");
        $collectorCheck->execute([$collectorId]);
        if (!$collectorCheck->fetchColumn()) {
            throw new Exception('Invalid collector selected.');
        }

        $stmt = $pdo->prepare("UPDATE collection_routes SET collector_id = ?, status = 'assigned' WHERE id = ? AND status IN ('pending', 'assigned')");
        $stmt->execute([$collectorId, $routeId]);
        if ($stmt->rowCount() === 0) {
            $exists = $pdo->prepare("SELECT id FROM collection_routes WHERE id = ? AND status IN ('pending', 'assigned')");
            $exists->execute([$routeId]);
            if (!$exists->fetchColumn()) {
                throw new Exception('This route cannot be assigned because it may no longer be pending or assigned.');
            }
            // Same collector may already be assigned; treat as successful.
        }

        routesRedirect('assigned=1');
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// =====================================================
// EDIT REGULAR ROUTE NAME
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_route'])) {
    try {
        verifyRoutesCsrf();
        $routeId = (int)($_POST['route_id'] ?? 0);
        $routeName = trim($_POST['route_name'] ?? '');
        if ($routeId <= 0 || $routeName === '') {
            throw new Exception('Please enter a valid route name.');
        }

        $stmt = $pdo->prepare("UPDATE collection_routes SET route_name = ? WHERE id = ? AND route_type = 'regular'");
        $stmt->execute([$routeName, $routeId]);
        routesRedirect('edited=1');
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// =====================================================
// DELETE REGULAR ROUTE AND ITS STOPS
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_route'])) {
    try {
        verifyRoutesCsrf();
        $routeId = (int)($_POST['route_id'] ?? 0);
        if ($routeId <= 0) {
            throw new Exception('Invalid route selected.');
        }

        $pdo->beginTransaction();
        $check = $pdo->prepare("SELECT id FROM collection_routes WHERE id = ? AND route_type = 'regular'");
        $check->execute([$routeId]);
        if (!$check->fetchColumn()) {
            throw new Exception('Regular route not found.');
        }

        $deleteStops = $pdo->prepare('DELETE FROM route_stops WHERE route_id = ?');
        $deleteStops->execute([$routeId]);
        $deleteRoute = $pdo->prepare("DELETE FROM collection_routes WHERE id = ? AND route_type = 'regular'");
        $deleteRoute->execute([$routeId]);
        $pdo->commit();

        routesRedirect('deleted=1');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

// =====================================================
// GENERATE A REGULAR ROUTE FOR ONE SELECTED GROUP ONLY
// group_id = 0 means households that are still Unassigned.
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_regular_route'])) {
    try {
        verifyRoutesCsrf();
        $groupId = (int)($_POST['group_id'] ?? -1);
        if ($groupId < 0) {
            throw new Exception('Please select a household group before generating a route.');
        }

        if ($groupId === 0) {
            $groupName = 'Unassigned';
            $householdStmt = $pdo->query("SELECT id FROM households WHERE group_id IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY id ASC");
        } else {
            $groupCheck = $pdo->prepare('SELECT group_name FROM household_groups WHERE id = ?');
            $groupCheck->execute([$groupId]);
            $groupName = $groupCheck->fetchColumn();
            if ($groupName === false) {
                throw new Exception('Selected household group does not exist.');
            }
            $householdStmt = $pdo->prepare('SELECT id FROM households WHERE group_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY id ASC');
            $householdStmt->execute([$groupId]);
        }

        $routeHouseholds = $householdStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$routeHouseholds) {
            throw new Exception('No households with GPS coordinates were found in ' . $groupName . '.');
        }

        $pdo->beginTransaction();
        $routeStmt = $pdo->prepare("INSERT INTO collection_routes (route_name, route_type, status, created_by, group_id) VALUES (?, 'regular', 'pending', ?, ?)");
        $routeStmt->execute([
            'Regular Collection Route - ' . $groupName . ' - ' . date('Y-m-d H:i'),
            (int)$_SESSION['user_id'],
            $groupId === 0 ? null : $groupId
        ]);
        $routeId = (int)$pdo->lastInsertId();

        $stopStmt = $pdo->prepare('INSERT INTO route_stops (route_id, household_id, stop_order) VALUES (?, ?, ?)');
        foreach ($routeHouseholds as $index => $householdId) {
            $stopStmt->execute([$routeId, (int)$householdId, $index + 1]);
        }
        $pdo->commit();

        routesRedirect('success=1');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

// =====================================================
// LOAD PAGE DATA
// =====================================================
$collectors = $pdo->query("SELECT id, fullname FROM users WHERE role = 'collector' ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);

$householdGroups = $pdo->query('SELECT id, group_name, description FROM household_groups ORDER BY group_name ASC')->fetchAll(PDO::FETCH_ASSOC);

// All households, including those without coordinates, for group assignment.
$groupHouseholds = $pdo->query("SELECT h.id, h.house_address, h.latitude, h.longitude, h.group_id, u.fullname, hg.group_name FROM households h INNER JOIN users u ON h.user_id = u.id LEFT JOIN household_groups hg ON h.group_id = hg.id ORDER BY h.id ASC")->fetchAll(PDO::FETCH_ASSOC);

$allRoutes = $pdo->query("SELECT cr.id, cr.route_name, cr.route_type, cr.status, cr.collector_id, cr.group_id, u.fullname AS collector_name, hg.group_name FROM collection_routes cr LEFT JOIN users u ON cr.collector_id = u.id LEFT JOIN household_groups hg ON cr.group_id = hg.id ORDER BY cr.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Only households with valid coordinates are sent to Google Maps.
$households = $pdo->query("SELECT h.id, h.house_address, h.latitude, h.longitude, h.group_id, u.fullname, hg.group_name FROM households h INNER JOIN users u ON h.user_id = u.id LEFT JOIN household_groups hg ON h.group_id = hg.id WHERE h.latitude IS NOT NULL AND h.longitude IS NOT NULL AND h.latitude <> '' AND h.longitude <> '' ORDER BY h.id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Display stops from the most recently generated regular route.
$routeStopsStmt = $pdo->query("SELECT h.id, h.house_address, h.latitude, h.longitude, h.group_id, u.fullname, rs.stop_order FROM collection_routes cr INNER JOIN route_stops rs ON cr.id = rs.route_id INNER JOIN households h ON rs.household_id = h.id INNER JOIN users u ON h.user_id = u.id WHERE cr.id = (SELECT MAX(id) FROM collection_routes WHERE route_type = 'regular') ORDER BY rs.stop_order ASC");
$routeStops = $routeStopsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalHouseholds = count($groupHouseholds);
$householdsWithCoordinates = count($households);
$unassignedCount = 0;
foreach ($groupHouseholds as $household) {
    if ($household['group_id'] === null) {
        $unassignedCount++;
    }
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$householdsJson = json_encode($households, $jsonFlags);
$routeStopsJson = json_encode($routeStops, $jsonFlags);
if ($householdsJson === false || $routeStopsJson === false) {
    die('Unable to process household location data.');
}
?>
<style>
.route-page{--green:#198754;--green-dark:#146c43;--green-soft:#e8f5ee;--ink:#24332b;--muted:#718078;--line:#e7eee9;--bg:#f4f7f5;--white:#fff;--red:#dc3545;--blue:#1976d2;--shadow:0 5px 22px rgba(25,60,40,.06)}.route-page, .route-page *{box-sizing:border-box}.route-page{width:100%;min-width:0;color:var(--ink);font-family:Inter,Segoe UI,Arial,sans-serif;line-height:1.5}.route-page .page-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;margin-bottom:24px;flex-wrap:wrap}.route-page .eyebrow{color:var(--green);font-size:11px;text-transform:uppercase;letter-spacing:1.3px;font-weight:800;margin-bottom:6px}.route-page .page-heading h1{font-size:29px;letter-spacing:-.6px;margin-bottom:5px}.route-page .page-heading p{color:var(--muted);font-size:14px}.route-page .date-label{color:var(--muted);font-size:13px;background:#fff;border:1px solid var(--line);padding:9px 13px;border-radius:9px}.route-page .alert{padding:13px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:10px}.route-page .success{background:#e9f7ef;color:#207443;border:1px solid #ccebd8}.route-page .error{background:#fff0f0;color:#b42332;border:1px solid #f5d0d3}.route-page .summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:24px}.route-page .summary-card{background:#fff;border:1px solid var(--line);border-radius:13px;padding:19px;box-shadow:var(--shadow);display:flex;align-items:center;gap:14px}.route-page .summary-icon{width:46px;height:46px;flex:0 0 46px;border-radius:12px;background:var(--green-soft);color:var(--green);display:grid;place-items:center;font-size:19px}.route-page .summary-card h3{font-size:25px;line-height:1.15;margin-bottom:4px}.route-page .summary-card p{font-size:12px;color:var(--muted)}.route-page .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:23px;margin-bottom:22px;box-shadow:var(--shadow)}.route-page .card-header{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:19px}.route-page .section-title{display:flex;align-items:center;gap:10px}.route-page .section-title>i{color:var(--green);font-size:18px}.route-page .section-title h2{font-size:18px;letter-spacing:-.2px}.route-page .section-subtitle{font-size:12px;color:var(--muted);margin-top:3px}.route-page .btn{border:0;border-radius:8px;padding:10px 14px;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-size:12px;font-weight:750;text-decoration:none;cursor:pointer;transition:.18s;white-space:nowrap}.route-page .btn:hover{transform:translateY(-1px);filter:brightness(.96)}.route-page .btn-green{background:var(--green);color:#fff}.route-page .btn-green:hover{background:var(--green-dark)}.route-page .btn-blue{background:var(--blue);color:#fff}.route-page .btn-red{background:var(--red);color:#fff}.route-page .btn-light{background:#f0f5f2;color:#365344;border:1px solid var(--line)}.route-page .form-control, .route-page select, .route-page input[type=text]{width:100%;min-width:0;padding:10px 12px;border:1px solid #dce5df;border-radius:8px;background:#fff;color:var(--ink);font:inherit;font-size:13px;outline:none}.route-page .form-control:focus, .route-page select:focus, .route-page input[type=text]:focus{border-color:#64b88a;box-shadow:0 0 0 3px rgba(25,135,84,.1)}.route-page .form-row{display:flex;gap:10px;flex-wrap:wrap}.route-page .form-row>*{flex:1;min-width:180px}.route-page .form-panel{background:#f8fbf9;border:1px solid var(--line);border-radius:11px;padding:17px;margin-bottom:22px}.route-page .form-panel h3{font-size:14px;margin-bottom:12px}.route-page .helper{font-size:11px;color:var(--muted);margin-top:7px}.route-page .table-wrap{width:100%;overflow-x:auto;border:1px solid var(--line);border-radius:10px}.route-page table{width:100%;border-collapse:collapse;min-width:720px}.route-page th, .route-page td{padding:13px 12px;text-align:left;border-bottom:1px solid #edf1ee;font-size:12px;vertical-align:middle}.route-page th{background:#f2f8f4;color:#376b4e;font-size:11px;text-transform:uppercase;letter-spacing:.45px;font-weight:800}.route-page tbody tr:last-child td{border-bottom:0}.route-page tbody tr:hover{background:#fbfdfb}.route-page .household-id{font-weight:800;color:var(--green)}.route-page .muted{color:var(--muted)}.route-page .empty{text-align:center;padding:27px;color:var(--muted)}.route-page .status{display:inline-flex;padding:5px 9px;border-radius:30px;font-size:10px;font-weight:800;text-transform:capitalize;background:#f0f2f1;color:#66736b}.route-page .status.pending{background:#fff5df;color:#946200}.route-page .status.assigned{background:#e8f1ff;color:#245ca8}.route-page .status.completed{background:#e7f6ec;color:#247344}.route-page .status.cancelled, .route-page .status.rejected{background:#fff0f0;color:#b42332}.route-page .route-actions{display:grid;gap:7px;min-width:220px}.route-page .inline-form{display:flex;gap:6px;align-items:center}.route-page .inline-form input{min-width:120px}.route-page .inline-form select{min-width:125px}.route-page .check-cell{text-align:center}.route-page .check-cell input{width:16px;height:16px;accent-color:var(--green);cursor:pointer}.route-page .table-tools{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:12px}.route-page .table-tools input{max-width:280px}.route-page .group-name{font-weight:700;color:#365344}.route-page .tag-unassigned{color:#a56b00;font-weight:750}.route-page .group-tag{display:inline-block;padding:4px 8px;border-radius:20px;background:var(--green-soft);color:#28734a;font-size:10px;font-weight:800}.route-page .map-controls{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.route-page .map-note{font-size:12px;color:var(--muted);margin-top:10px}.route-page #routeMessage{font-size:12px;color:var(--green);margin:12px 0;min-height:18px}.route-page #map{width:100%;height:480px;border-radius:11px;border:1px solid var(--line);overflow:hidden}.route-page .map-legend{display:flex;gap:16px;flex-wrap:wrap;margin-top:12px;font-size:11px;color:var(--muted)}.route-page .legend-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px;background:#198754}.route-page .legend-dot.route{background:#1976d2}.route-page .section-anchor{scroll-margin-top:90px}.route-page .footer{text-align:center;color:#91a097;font-size:11px;padding:5px 0 20px}@media(max-width:1000px){.route-page .summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.route-page{padding:0 0 20px}.route-page .page-heading h1{font-size:24px}.route-page .summary-grid{grid-template-columns:1fr 1fr;gap:9px}.route-page .summary-card{padding:13px;gap:9px}.route-page .summary-icon{width:37px;height:37px;flex-basis:37px;font-size:15px}.route-page .summary-card h3{font-size:21px}.route-page .summary-card p{font-size:10px}.route-page .card{padding:15px}.route-page .card-header{align-items:flex-start}.route-page .form-row>*{min-width:100%}.route-page #map{height:360px}}
    
</style>
<div class="route-page">
    <header class="page-heading" id="overview">
        <div>
            <div class="eyebrow">Administration / Operations</div>
            <h1>Collection Route Management</h1>
            <p>Organize households into groups, generate collection routes, and assign collectors.</p>
        </div>
        <div class="date-label"><i class="far fa-calendar"></i> <?php echo date('F d, Y'); ?></div>
    </header>

    <?php if (isset($_GET['success'])): ?><div class="alert success"><i class="fas fa-circle-check"></i> Regular collection route generated successfully.</div><?php endif; ?>
    <?php if (isset($_GET['group_created'])): ?><div class="alert success"><i class="fas fa-circle-check"></i> Household group created successfully.</div><?php endif; ?>
    <?php if (isset($_GET['households_assigned'])): ?><div class="alert success"><i class="fas fa-circle-check"></i> Selected households were assigned successfully.</div><?php endif; ?>
    <?php if (isset($_GET['assigned'])): ?><div class="alert success"><i class="fas fa-circle-check"></i> Collector assigned successfully.</div><?php endif; ?>
    <?php if (isset($_GET['edited'])): ?><div class="alert success"><i class="fas fa-circle-check"></i> Route name updated successfully.</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="alert success"><i class="fas fa-circle-check"></i> Regular route and its stops deleted successfully.</div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="alert error"><i class="fas fa-circle-exclamation"></i> <?php echo h($errorMessage); ?></div><?php endif; ?>

    <section class="summary-grid">
        <div class="summary-card"><div class="summary-icon"><i class="fas fa-house"></i></div><div><h3><?php echo $totalHouseholds; ?></h3><p>Registered Households</p></div></div>
        <div class="summary-card"><div class="summary-icon"><i class="fas fa-location-dot"></i></div><div><h3><?php echo $householdsWithCoordinates; ?></h3><p>Households with GPS</p></div></div>
        <div class="summary-card"><div class="summary-icon"><i class="fas fa-map-pin"></i></div><div><h3><?php echo count($routeStops); ?></h3><p>Stops in Latest Regular Route</p></div></div>
        <div class="summary-card"><div class="summary-icon"><i class="fas fa-circle-question"></i></div><div><h3><?php echo $unassignedCount; ?></h3><p>Unassigned Households</p></div></div>
    </section>

    <!-- ROUTE MANAGEMENT -->
    <section class="card section-anchor" id="routes">
        <div class="card-header">
            <div><div class="section-title"><i class="fas fa-route"></i><h2>Collection Routes</h2></div><p class="section-subtitle">Assign collectors, rename regular routes, or remove routes.</p></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Route ID</th><th>Route Name</th><th>Type</th><th>Group</th><th>Status</th><th>Collector</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ($allRoutes): foreach ($allRoutes as $route): ?>
                    <tr>
                        <td class="household-id">#<?php echo (int)$route['id']; ?></td>
                        <td><strong><?php echo h($route['route_name']); ?></strong></td>
                        <td><?php echo h(ucfirst($route['route_type'])); ?></td>
                        <td><?php echo $route['group_id'] === null ? '<span class="tag-unassigned">Unassigned</span>' : '<span class="group-tag">'.h($route['group_name'] ?? 'Group').'</span>'; ?></td>
                        <td><span class="status <?php echo h(strtolower($route['status'])); ?>"><?php echo h($route['status']); ?></span></td>
                        <td><?php echo h($route['collector_name'] ?? 'Not assigned'); ?></td>
                        <td>
                            <div class="route-actions">
                            <?php if (in_array($route['status'], ['pending','assigned'], true)): ?>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Assign this collector to the selected route?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                    <input type="hidden" name="route_id" value="<?php echo (int)$route['id']; ?>">
                                    <select name="collector_id" required aria-label="Select collector"><option value="">Select collector</option>
                                        <?php foreach ($collectors as $collector): ?><option value="<?php echo (int)$collector['id']; ?>" <?php echo ((int)$route['collector_id'] === (int)$collector['id']) ? 'selected' : ''; ?>><?php echo h($collector['fullname']); ?></option><?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-green" type="submit" name="assign_collector" value="1"><i class="fas fa-user-check"></i> Assign</button>
                                </form>
                            <?php else: ?><span class="muted">Route is not assignable</span><?php endif; ?>
                            <?php if ($route['route_type'] === 'regular'): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                    <input type="hidden" name="route_id" value="<?php echo (int)$route['id']; ?>">
                                    <input type="text" name="route_name" value="<?php echo h($route['route_name']); ?>" required aria-label="Route name">
                                    <button class="btn btn-blue" type="submit" name="edit_route" value="1"><i class="fas fa-pen"></i> Edit</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Delete this regular route and all of its saved stops? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                    <input type="hidden" name="route_id" value="<?php echo (int)$route['id']; ?>">
                                    <button class="btn btn-red" type="submit" name="delete_route" value="1"><i class="fas fa-trash"></i> Delete Route</button>
                                </form>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" class="empty"><i class="fas fa-route"></i><br>No collection routes have been generated yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- HOUSEHOLD GROUP MANAGEMENT -->
    <section class="card section-anchor" id="groups">
        <div class="card-header">
            <div><div class="section-title"><i class="fas fa-layer-group"></i><h2>Household Group Management</h2></div><p class="section-subtitle">Create collection zones and organize registered households.</p></div>
        </div>

        <div class="form-panel">
            <h3><i class="fas fa-plus-circle" style="color:var(--green)"></i> Create a New Household Group</h3>
            <form method="POST" class="form-row">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="text" name="group_name" placeholder="Group name (e.g. Zone A)" maxlength="100" required>
                <input type="text" name="description" placeholder="Description (optional)">
                <button type="submit" name="create_group" value="1" class="btn btn-green"><i class="fas fa-plus"></i> Create Group</button>
            </form>
        </div>

        <div class="card-header" style="margin-bottom:12px">
            <div><div class="section-title"><i class="fas fa-users"></i><h2>Assign Households to Groups</h2></div><p class="section-subtitle">Select one or more households, then choose their destination group.</p></div>
        </div>
        <form method="POST" id="assignHouseholdsForm" onsubmit="return confirm('Move the selected households to the chosen group?');">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <div class="table-tools">
                <select name="group_id" required style="max-width:260px">
                    <option value="">Choose destination group</option>
                    <option value="0">Unassigned (remove from current group)</option>
                    <?php foreach ($householdGroups as $group): ?><option value="<?php echo (int)$group['id']; ?>"><?php echo h($group['group_name']); ?></option><?php endforeach; ?>
                </select>
                <button type="submit" name="assign_households" value="1" class="btn btn-green"><i class="fas fa-check"></i> Assign Selected</button>
                <button type="button" class="btn btn-light" onclick="toggleAllHouseholds(true)"><i class="fas fa-check-square"></i> Select All</button>
                <button type="button" class="btn btn-light" onclick="toggleAllHouseholds(false)"><i class="far fa-square"></i> Clear</button>
                <input type="text" id="householdSearch" placeholder="Search resident or address..." oninput="filterHouseholds()">
            </div>
            <div class="table-wrap">
                <table id="groupHouseholdTable">
                    <thead><tr><th class="check-cell"><input type="checkbox" aria-label="Select all visible" onchange="toggleVisible(this)"></th><th>Household ID</th><th>Resident</th><th>House Address</th><th>Current Group</th><th>GPS</th></tr></thead>
                    <tbody>
                    <?php if ($groupHouseholds): foreach ($groupHouseholds as $household): ?>
                        <tr>
                            <td class="check-cell"><input type="checkbox" class="household-checkbox" name="household_ids[]" value="<?php echo (int)$household['id']; ?>" aria-label="Select household <?php echo (int)$household['id']; ?>"></td>
                            <td class="household-id">#<?php echo (int)$household['id']; ?></td>
                            <td><?php echo h($household['fullname']); ?></td>
                            <td><?php echo h($household['house_address']); ?></td>
                            <td><?php if ($household['group_id'] === null): ?><span class="tag-unassigned">Unassigned</span><?php else: ?><span class="group-tag"><?php echo h($household['group_name'] ?? 'Unknown group'); ?></span><?php endif; ?></td>
                            <td><?php echo ($household['latitude'] !== null && $household['longitude'] !== null && $household['latitude'] !== '' && $household['longitude'] !== '') ? '<span style="color:#247344"><i class="fas fa-check-circle"></i> Available</span>' : '<span class="muted">Not set</span>'; ?></td>
                        </tr>
                    <?php endforeach; else: ?><tr><td colspan="6" class="empty">No registered households found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="helper">Households without a group remain available under “Unassigned”. Households without GPS can be grouped, but they will not be included in generated map routes until coordinates are saved.</p>
        </form>
    </section>

    <!-- REGISTERED HOUSEHOLDS / ROUTE GENERATION -->
    <section class="card section-anchor" id="households">
        <div class="card-header">
            <div><div class="section-title"><i class="fas fa-house-user"></i><h2>Registered Households</h2></div><p class="section-subtitle">Generate a regular collection route for one group at a time.</p></div>
        </div>
        <div class="form-panel">
            <h3><i class="fas fa-route" style="color:var(--green)"></i> Generate Group-Based Route</h3>
            <form method="POST" class="form-row" onsubmit="return confirm('Generate a regular collection route using only households in the selected group that have GPS coordinates?');">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <select name="group_id" required>
                    <option value="">Select a household group</option>
                    <option value="0">Unassigned Households</option>
                    <?php foreach ($householdGroups as $group): ?><option value="<?php echo (int)$group['id']; ?>"><?php echo h($group['group_name']); ?></option><?php endforeach; ?>
                </select>
                <button type="submit" name="generate_regular_route" value="1" class="btn btn-green"><i class="fas fa-wand-magic-sparkles"></i> Generate Route</button>
            </form>
            <p class="helper">Only households assigned to the selected group and having both latitude and longitude will become route stops. Generating a route will not move households between groups.</p>
        </div>
        <div class="table-tools"><input type="text" id="registeredSearch" placeholder="Search resident, address, or group..." oninput="filterRegistered()"><span class="muted" style="font-size:11px">Showing <?php echo count($households); ?> households with GPS coordinates</span></div>
        <div class="table-wrap">
            <table id="registeredTable">
                <thead><tr><th>Household ID</th><th>Resident Name</th><th>House Address</th><th>Group</th><th>Latitude</th><th>Longitude</th></tr></thead>
                <tbody>
                <?php if ($households): foreach ($households as $household): ?>
                    <tr>
                        <td class="household-id">#<?php echo (int)$household['id']; ?></td>
                        <td><?php echo h($household['fullname']); ?></td>
                        <td><?php echo h($household['house_address']); ?></td>
                        <td><?php echo $household['group_id'] === null ? '<span class="tag-unassigned">Unassigned</span>' : '<span class="group-tag">'.h($household['group_name'] ?? 'Unknown group').'</span>'; ?></td>
                        <td><?php echo h($household['latitude']); ?></td>
                        <td><?php echo h($household['longitude']); ?></td>
                    </tr>
                <?php endforeach; else: ?><tr><td colspan="6" class="empty">No registered households with GPS coordinates found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- GOOGLE MAP -->
    <section class="card section-anchor" id="map-section">
        <div class="card-header">
            <div><div class="section-title"><i class="fas fa-map-marked-alt"></i><h2>Household Location Map</h2></div><p class="section-subtitle">View all households with GPS coordinates and calculate the latest regular route.</p></div>
        </div>
        <div class="map-controls">
            <button type="button" class="btn btn-blue" onclick="startRouteFromMyLocation()"><i class="fas fa-location-arrow"></i> Start Route from My Location</button>
            <button type="button" class="btn btn-light" onclick="showAllHouseholds()"><i class="fas fa-globe"></i> Show All Households</button>
            <button type="button" class="btn btn-light" onclick="showLatestRouteStops()"><i class="fas fa-route"></i> Show Latest Route Stops</button>
        </div>
        <p id="routeMessage" role="status"></p>
        <div id="map"></div>
        <div class="map-legend"><span><i class="legend-dot"></i> Registered household</span><span><i class="legend-dot route"></i> Latest route stop</span></div>
        <p class="map-note">The map initially displays all registered households with valid coordinates. The route button uses the stops from the most recently generated regular route.</p>
    </section>
</div>

<script>
const households = <?php echo $householdsJson; ?>;
const routeStops = <?php echo $routeStopsJson; ?>;
let map;
let directionsRoute = null;
let householdMarkers = [];
let routeMarkers = [];
let infoWindow;

async function initMap() {
    const routesLibrary = await google.maps.importLibrary('routes');
    directionsRoute = routesLibrary.Route;

    map = new google.maps.Map(document.getElementById('map'), {
        zoom: 13,
        center: {lat: 15.2200, lng: 120.5700},
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true
    });
    infoWindow = new google.maps.InfoWindow();
    createHouseholdMarkers();
    createRouteMarkers();
    showAllHouseholds();
}

function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
}

function markerContent(household, isRouteStop) {
    return `<div style="min-width:170px;padding:3px 2px">
        <strong>${escapeHTML(household.fullname)}</strong><br>
        <span>${escapeHTML(household.house_address)}</span><br>
        <small>Household ID: ${escapeHTML(household.id)}</small>
        ${isRouteStop ? `<br><small style="color:#1976d2;font-weight:bold">Latest route stop #${escapeHTML(household.stop_order)}</small>` : ''}
    </div>`;
}

function createHouseholdMarkers() {
    householdMarkers.forEach(marker => marker.setMap(null));
    householdMarkers = [];
    households.forEach(household => {
        const position = {lat: Number(household.latitude), lng: Number(household.longitude)};
        if (!Number.isFinite(position.lat) || !Number.isFinite(position.lng)) return;
        const marker = new google.maps.Marker({
            position, map: null, title: household.fullname,
            icon: {path: google.maps.SymbolPath.CIRCLE, scale: 7, fillColor: '#198754', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2}
        });
        marker.addListener('click', () => { infoWindow.setContent(markerContent(household, false)); infoWindow.open(map, marker); });
        householdMarkers.push(marker);
    });
}

function createRouteMarkers() {
    routeMarkers.forEach(marker => marker.setMap(null));
    routeMarkers = [];
    routeStops.forEach(stop => {
        const position = {lat: Number(stop.latitude), lng: Number(stop.longitude)};
        if (!Number.isFinite(position.lat) || !Number.isFinite(position.lng)) return;
        const marker = new google.maps.Marker({
            position, map: null, title: `Stop ${stop.stop_order}: ${stop.fullname}`,
            label: {text: String(stop.stop_order), color: '#ffffff', fontWeight: 'bold'},
            icon: {path: google.maps.SymbolPath.CIRCLE, scale: 10, fillColor: '#1976d2', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2}
        });
        marker.addListener('click', () => { infoWindow.setContent(markerContent(stop, true)); infoWindow.open(map, marker); });
        routeMarkers.push(marker);
    });
}

function fitToMarkers(markers) {
    if (!markers.length) return;
    const bounds = new google.maps.LatLngBounds();
    markers.forEach(marker => bounds.extend(marker.getPosition()));
    if (markers.length === 1) { map.setCenter(bounds.getCenter()); map.setZoom(16); }
    else { map.fitBounds(bounds); }
}

function showAllHouseholds() {
    routeMarkers.forEach(marker => marker.setMap(null));
    householdMarkers.forEach(marker => marker.setMap(map));
    fitToMarkers(householdMarkers);
    document.getElementById('routeMessage').textContent = `${householdMarkers.length} registered household location(s) displayed.`;
}


function showLatestRouteStops() {
    // Hide all household markers first
    householdMarkers.forEach(marker => marker.setMap(null));

    // Display only stops belonging to the latest generated route
    routeMarkers.forEach(marker => marker.setMap(map));

    fitToMarkers(routeMarkers);

    document.getElementById('routeMessage').textContent =
        routeMarkers.length
        ? `${routeMarkers.length} household(s) belonging to the latest generated group route displayed.`
        : 'No stops are available in the latest generated route.';
}


function startRouteFromMyLocation() {
    const message = document.getElementById('routeMessage');
    if (!navigator.geolocation) {
        message.textContent = 'Your browser does not support location services.';
        return;
    }
    if (!routeStops.length) {
        message.textContent = 'No households found in the latest regular route. Generate a group route first.';
        return;
    }

    message.textContent = 'Getting your current location...';
    navigator.geolocation.getCurrentPosition(async position => {
        try {
            const startLocation = {lat: position.coords.latitude, lng: position.coords.longitude};
            const lastStop = routeStops[routeStops.length - 1];
            const destination = {lat: Number(lastStop.latitude), lng: Number(lastStop.longitude)};
            const request = {
                origin: startLocation,
                destination,
                intermediates: routeStops.slice(0, -1).map(stop => ({location: {lat: Number(stop.latitude), lng: Number(stop.longitude)}})),
                travelMode: 'DRIVING',
                optimizeWaypointOrder: true,
                fields: ['path', 'optimizedIntermediateWaypointIndices']
            };
            message.textContent = 'Location detected. Calculating driving route...';
            const {routes} = await directionsRoute.computeRoutes(request);
            if (!routes || !routes.length) {
                message.textContent = 'No driving route was found.';
                return;
            }
            // Clear the previous route polyline before drawing the new one.
            if (window.currentRoutePolylines) window.currentRoutePolylines.forEach(line => line.setMap(null));
            window.currentRoutePolylines = routes[0].createPolylines();
            window.currentRoutePolylines.forEach(line => line.setMap(map));
            routeMarkers.forEach(marker => marker.setMap(map));
            householdMarkers.forEach(marker => marker.setMap(null));
            fitToMarkers(routeMarkers);
            message.textContent = 'Driving route displayed successfully. The route starts from your current location.';
        } catch (error) {
            console.error('Routes API error:', error);
            message.textContent = 'Unable to calculate route: ' + (error.message || 'Please check your Google Maps Routes API configuration.');
        }
    }, error => {
        console.error(error);
        message.textContent = 'Unable to get your location. Please allow location access in your browser.';
    }, {enableHighAccuracy: true, timeout: 15000, maximumAge: 0});
}

function toggleAllHouseholds(checked) {
    document.querySelectorAll('.household-checkbox').forEach(box => {
        if (box.closest('tr').style.display !== 'none') box.checked = checked;
    });
}
function toggleVisible(master) {
    document.querySelectorAll('#groupHouseholdTable tbody tr').forEach(row => {
        if (row.style.display !== 'none') {
            const checkbox = row.querySelector('.household-checkbox');
            if (checkbox) checkbox.checked = master.checked;
        }
    });
}
function filterHouseholds() {
    const term = document.getElementById('householdSearch').value.toLowerCase();
    document.querySelectorAll('#groupHouseholdTable tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
    });
}
function filterRegistered() {
    const term = document.getElementById('registeredSearch').value.toLowerCase();
    document.querySelectorAll('#registeredTable tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
    });
}
</script>
<script async src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCz8mz80N6FN03Q1q1nQFGeZLduXsZpNHY&callback=initMap"></script>
