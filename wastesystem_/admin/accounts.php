<?php
/* Manage Accounts module - included by admin/dashboard.php. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied.");
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    echo '<div class="alert alert-danger">Database connection is unavailable.</div>';
    return;
}
$conn->set_charset("utf8mb4");

if (!function_exists("accountsPageEscape")) {
    function accountsPageEscape($value): string {
        return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
    }
}

if (empty($_SESSION["accounts_csrf"])) {
    $_SESSION["accounts_csrf"] = bin2hex(random_bytes(32));
}

$accountNotice = "";
$accountNoticeType = "success";
$currentAdminId = (int)$_SESSION["user_id"];

/* Delete account action. Editing is handled on the separate edit_account.php page. */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["account_action"] ?? "") === "delete") {
    $token = (string)($_POST["csrf_token"] ?? "");
    $id = filter_var($_POST["account_id"] ?? null, FILTER_VALIDATE_INT);

    if (!hash_equals($_SESSION["accounts_csrf"], $token)) {
        $accountNotice = "Invalid security token. Please refresh the page and try again.";
        $accountNoticeType = "danger";
    } elseif (!$id || $id < 1) {
        $accountNotice = "Invalid account selected.";
        $accountNoticeType = "danger";
    } elseif ((int)$id === $currentAdminId) {
        $accountNotice = "You cannot delete the administrator account currently in use.";
        $accountNoticeType = "danger";
    } else {
        $check = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $target = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$target) {
            $accountNotice = "The selected account no longer exists.";
            $accountNoticeType = "danger";
        } elseif ($target["role"] === "admin") {
            $countResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
            $adminCount = (int)($countResult->fetch_assoc()["total"] ?? 0);
            $countResult->free();

            if ($adminCount <= 1) {
                $accountNotice = "You cannot delete the last administrator account.";
                $accountNoticeType = "danger";
            }
        }

        if ($accountNotice === "") {
            $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
            if (!$delete) {
                $accountNotice = "Unable to prepare the account deletion.";
                $accountNoticeType = "danger";
            } else {
                $delete->bind_param("i", $id);
                if ($delete->execute() && $delete->affected_rows > 0) {
                    $accountNotice = "Account deleted successfully.";
                } else {
                    error_log("Manage Accounts delete failed: " . $delete->error);
                    $accountNotice = "Unable to delete this account. It may be linked to other records in the system.";
                    $accountNoticeType = "danger";
                }
                $delete->close();
            }
        }
    }
    $_SESSION["accounts_csrf"] = bin2hex(random_bytes(32));
}

if (isset($_GET["updated"]) && $_GET["updated"] === "1") {
    $accountNotice = "Account updated successfully.";
}
if (isset($_GET["deleted"]) && $_GET["deleted"] === "1") {
    $accountNotice = "Account deleted successfully.";
}

$roles = [
    "admin" => "Administrators",
    "collector" => "Garbage Collectors",
    "residents" => "Residents"
];
$accounts = ["admin" => [], "collector" => [], "residents" => []];

$result = $conn->query("SELECT id, fullname, users, role FROM users ORDER BY fullname ASC");
if (!$result) {
    error_log("Manage Accounts query failed: " . $conn->error);
    echo '<div class="alert alert-danger">Unable to load accounts. Please check the database error log.</div>';
    return;
}
while ($row = $result->fetch_assoc()) {
    if (isset($accounts[$row["role"]])) {
        $accounts[$row["role"]][] = $row;
    }
}
$result->free();

// ACCOUNT SUMMARY COUNTS
$totalAccounts = array_sum(array_map("count", $accounts));
$totalAdmins = count($accounts["admin"]);
$totalCollectors = count($accounts["collector"]);
$totalResidents = count($accounts["residents"]);
?>

<style>
.accounts-page{width:100%;color:#263b30}.accounts-page *{box-sizing:border-box}
.add-account-btn{display:inline-flex;align-items:center;justify-content:center;background:#238653;color:#fff;padding:11px 18px;min-height:42px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;transition:.2s}
.add-account-btn:hover{background:#176b40;color:#fff;transform:translateY(-1px)}
.accounts-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:20px}

/* ==========================================
   PAGE HEADING - DASHBOARD STYLE
   ========================================== */

.accounts-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 25px;
}

.accounts-header h2 {
    color: #173e2b;
    font-size: 25px;
    font-weight: 700;
    font-family: inherit;
    margin: 0 0 7px;
}

.accounts-header p {
    color: #829087;
    font-size: 13px;
    font-weight: 400;
    font-family: inherit;
    line-height: 1.6;
    margin: 0;
}

/* ==========================================
   ACCOUNT SUMMARY CARDS
   ========================================== */

.accounts-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}

.accounts-stat-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: #fff;
    border: 1px solid #e7eee9;
    border-radius: 12px;
    padding: 19px;
    box-shadow: 0 3px 12px rgba(30, 70, 45, .03);
    transition: .2s ease;
}

.accounts-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 14px rgba(30, 70, 45, .07);
}

.accounts-stat-label {
    color: #7a897f;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 8px;
}

.accounts-stat-value {
    color: #183e2c;
    font-size: 27px;
    font-weight: 700;
    line-height: 1;
}

.accounts-stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    font-size: 17px;
    background: #eaf5ed;
    color: #25804a;
}

.accounts-stat-icon.admin {
    background: #eaf5ed;
    color: #25804a;
}

.accounts-stat-icon.collector {
    background: #eaf3ff;
    color: #397bc3;
}

.accounts-stat-icon.resident {
    background: #fff0ee;
    color: #d35b4f;
}

/* ==========================================
   SEARCH TOOLBAR
   ========================================== */

.accounts-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.account-search {
    flex: 1 1 260px;
    max-width: 420px;
}

.account-search label {
    display: block;
    color: #52645a;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 7px;
}

.account-search input {
    width: 100%;
    height: 43px;
    padding: 0 14px;
    border: 1px solid #dce6df;
    border-radius: 8px;
    background: #fff;
    color: #263b30;
    font-size: 13px;
    outline: none;
}

.account-search input:focus {
    border-color: #238653;
    box-shadow: 0 0 0 3px rgba(35,134,83,.1);
}

/* ==========================================
   RESPONSIVE SUMMARY CARDS
   ========================================== */

@media (max-width: 1100px) {
    .accounts-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    .accounts-header {
        align-items: stretch;
    }

    .accounts-header h2 {
        font-size: 22px;
    }

    .add-account-btn {
        width: 100%;
    }

    .accounts-stats {
        gap: 10px;
    }

    .accounts-stat-card {
        padding: 14px;
    }

    .accounts-stat-value {
        font-size: 23px;
    }

    .accounts-stat-icon {
        width: 35px;
        height: 35px;
        font-size: 14px;
    }

    .accounts-toolbar {
        align-items: stretch;
    }

    .account-search {
        flex: 1 1 100%;
        max-width: none;
    }
}

@media (max-width: 400px) {
    .accounts-stats {
        grid-template-columns: 1fr;
    }
}

.account-search{flex:1 1 260px;max-width:420px}
.account-search label{display:block;color:#52645a;font-size:12px;font-weight:600;margin-bottom:7px}
.account-search input{width:100%;height:43px;padding:0 14px;border:1px solid #dce6df;border-radius:8px;background:#fff;color:#263b30;font-size:13px;outline:none}
.account-search input:focus{border-color:#238653;box-shadow:0 0 0 3px rgba(35,134,83,.1)}
.account-section{background:#fff;border:1px solid #e7eee9;border-radius:12px;padding:21px;margin-bottom:18px;box-shadow:0 2px 8px rgba(30,70,45,.03)}
.account-section-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
.account-section h2{font-size:16px;font-weight:700;color:#214b34;margin:0}
.account-count{display:inline-flex;align-items:center;justify-content:center;min-width:27px;height:25px;padding:0 9px;background:#e8f4ec;color:#237449;font-size:11px;font-weight:700;border-radius:20px}
.account-table-wrap{width:100%;overflow-x:auto;border:1px solid #edf1ee;border-radius:8px}
.account-table{width:100%;min-width:720px;border-collapse:collapse;font-size:13px}
.account-table th{background:#f5f8f6;color:#45564d;font-weight:600;text-align:left;padding:13px 15px;border-bottom:1px solid #e4ebe6;white-space:nowrap}
.account-table td{padding:14px 15px;border-bottom:1px solid #edf1ee;color:#52645a;vertical-align:middle}
.account-table tbody tr:last-child td{border-bottom:none}.account-table tbody tr:hover{background:#f8fbf9}
.role-badge{display:inline-block;background:#edf5ef;color:#237449;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;white-space:nowrap}
.account-actions{display:flex;gap:7px;white-space:nowrap}.account-actions .btn{font-size:12px;padding:6px 10px;border-radius:7px}
.empty-message{text-align:center;color:#929e96!important;padding:25px!important}
.no-search-results{display:none;color:#78877e;background:#f8faf8;border:1px dashed #dce6df;border-radius:8px;padding:18px;text-align:center;font-size:13px;margin-top:12px}
@media(max-width:650px){.accounts-header{align-items:stretch}.accounts-header h1{font-size:22px}.add-account-btn{width:100%}.accounts-toolbar{align-items:stretch}.accounts-summary,.account-search{flex:1 1 100%;max-width:none}.account-section{padding:15px}.account-section-header{align-items:flex-start}}
</style>

<div class="accounts-page">
    
    <!-- PAGE HEADING -->

    <div class="accounts-header">
        <div>
            <h2>Manage Accounts</h2>

            <p>
                View, edit, and manage all registered accounts in the system.
            </p>
        </div>

        <a href="add_account.php" class="add-account-btn">
            <i class="fas fa-user-plus me-1"></i>
            Add New Account
        </a>
    </div>


    <?php if ($accountNotice !== ""): ?>
        <div class="alert alert-<?= accountsPageEscape($accountNoticeType) ?> alert-dismissible fade show" role="alert">
            <?= accountsPageEscape($accountNotice) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- ACCOUNT SUMMARY CARDS -->
    <div class="accounts-stats">

        <!-- TOTAL ACCOUNTS -->
        <div class="accounts-stat-card">
            <div>
                <div class="accounts-stat-label">Total Accounts</div>
                <div class="accounts-stat-value">
                    <?= number_format($totalAccounts) ?>
                </div>
            </div>
            <div class="accounts-stat-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>

        <!-- ADMINISTRATORS -->
        <div class="accounts-stat-card">
            <div>
                <div class="accounts-stat-label">Administrators</div>
                <div class="accounts-stat-value">
                    <?= number_format($totalAdmins) ?>
                </div>
            </div>
            <div class="accounts-stat-icon admin">
                <i class="fas fa-user-shield"></i>
            </div>
        </div>

        <!-- GARBAGE COLLECTORS -->
        <div class="accounts-stat-card">
            <div>
                <div class="accounts-stat-label">Garbage Collectors</div>
                <div class="accounts-stat-value">
                    <?= number_format($totalCollectors) ?>
                </div>
            </div>
            <div class="accounts-stat-icon collector">
                <i class="fas fa-truck"></i>
            </div>
        </div>

        <!-- RESIDENTS -->
        <div class="accounts-stat-card">
            <div>
                <div class="accounts-stat-label">Residents</div>
                <div class="accounts-stat-value">
                    <?= number_format($totalResidents) ?>
                </div>
            </div>
            <div class="accounts-stat-icon resident">
                <i class="fas fa-house-user"></i>
            </div>
        </div>

    </div>

    <!-- SEARCH TOOLBAR -->
    <div class="accounts-toolbar">
        <div class="account-search">
            <label for="accountSearch">Search Accounts</label>
            <input
                type="search"
                id="accountSearch"
                placeholder="Search by ID, name, username, or account type..."
                autocomplete="off"
            >
        </div>
    </div>

    <?php foreach ($roles as $role => $title): ?>
        <section class="account-section">
            <div class="account-section-header">
                <h2><?= accountsPageEscape($title) ?></h2>
                <span class="account-count"><?= count($accounts[$role]) ?></span>
            </div>
            <div class="account-table-wrap">
                <table class="account-table">
                    <thead><tr><th>Account ID</th><th>Full Name</th><th>Username</th><th>Account Type</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($accounts[$role]): ?>
                        <?php foreach ($accounts[$role] as $account): ?>
                            <tr class="account-row">
                                <td><?= (int)$account["id"] ?></td>
                                <td><?= accountsPageEscape($account["fullname"]) ?></td>
                                <td><?= accountsPageEscape($account["users"]) ?></td>
                                <td><span class="role-badge"><?= accountsPageEscape(ucfirst($account["role"])) ?></span></td>
                                <td>
                                    <div class="account-actions">
                                        <a class="btn btn-sm btn-outline-success" href="edit_account.php?id=<?= (int)$account["id"] ?>">Edit</a>
                                        <?php if ((int)$account["id"] !== $currentAdminId): ?>
                                            <form method="post" class="m-0 delete-account-form">
                                                <input type="hidden" name="csrf_token" value="<?= accountsPageEscape($_SESSION["accounts_csrf"]) ?>">
                                                <input type="hidden" name="account_action" value="delete">
                                                <input type="hidden" name="account_id" value="<?= (int)$account["id"] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="You cannot delete the account currently in use">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="empty-message">No <?= accountsPageEscape(strtolower($title)) ?> accounts found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="no-search-results">No matching accounts found in this category.</div>
        </section>
    <?php endforeach; ?>
</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function () {
    const root = document.querySelector(".accounts-page");
    if (!root) return;

    // ACCOUNT SEARCH
    const searchInput = root.querySelector("#accountSearch");
    const sections = root.querySelectorAll(".account-section");

    if (searchInput) {
        searchInput.addEventListener("input", function () {
            const term = this.value.toLowerCase().trim();

            sections.forEach(function (section) {
                const rows = section.querySelectorAll(".account-row");
                const empty = section.querySelector(".no-search-results");
                let visible = 0;

                rows.forEach(function (row) {
                    const match = row.textContent.toLowerCase().includes(term);
                    row.style.display = match ? "" : "none";

                    if (match) visible++;
                });

                if (empty) {
                    empty.style.display =
                        rows.length > 0 && visible === 0 ? "block" : "none";
                }
            });
        });
    }

    // SWEETALERT DELETE CONFIRMATION
    root.querySelectorAll(".delete-account-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            event.preventDefault();

            Swal.fire({
                title: "Are you sure?",
                text: "This account will be permanently deleted. This action cannot be undone.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#dc3545",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "Cancel",
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    // Submit the form to your existing PHP delete handler.
                    form.submit();
                }
            });
        });
    });

})();
</script>
