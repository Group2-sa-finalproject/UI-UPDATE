<?php
/* Standalone edit-account page. Save as admin/edit_account.php. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../index.php");
    exit;
}

require_once "../config/database.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit("Database connection is unavailable.");
}
$conn->set_charset("utf8mb4");

function editAccountEscape($value): string {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["edit_account_csrf"])) {
    $_SESSION["edit_account_csrf"] = bin2hex(random_bytes(32));
}

$currentAdminId = (int)$_SESSION["user_id"];
$accountId = filter_var($_GET["id"] ?? $_POST["account_id"] ?? null, FILTER_VALIDATE_INT);
if (!$accountId || $accountId < 1) {
    header("Location: dashboard.php?page=accounts");
    exit;
}

$error = "";
$success = "";
$allowedRoles = ["admin", "collector", "residents"];

$getAccount = $conn->prepare("SELECT id, fullname, users, role FROM users WHERE id = ?");
$getAccount->bind_param("i", $accountId);
$getAccount->execute();
$account = $getAccount->get_result()->fetch_assoc();
$getAccount->close();

if (!$account) {
    http_response_code(404);
    exit("The selected account was not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = (string)($_POST["csrf_token"] ?? "");
    $fullname = trim((string)($_POST["fullname"] ?? ""));
    $username = trim((string)($_POST["username"] ?? ""));
    $role = (string)($_POST["role"] ?? "");

    if (!hash_equals($_SESSION["edit_account_csrf"], $token)) {
        $error = "Your security token is invalid. Please reload the page and try again.";
    } elseif ($fullname === "" || $username === "") {
        $error = "Full name and username are required.";
    } elseif (mb_strlen($fullname) > 150 || mb_strlen($username) > 100) {
        $error = "The full name or username is too long.";
    } elseif (!in_array($role, $allowedRoles, true)) {
        $error = "Please choose a valid account type.";
    } elseif ((int)$accountId === $currentAdminId && $role !== $account["role"]) {
        $error = "For security, you cannot change your own account type.";
    } else {
        /* Do not demote the final administrator. */
        if ($account["role"] === "admin" && $role !== "admin") {
            $countResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
            $adminCount = (int)($countResult->fetch_assoc()["total"] ?? 0);
            $countResult->free();
            if ($adminCount <= 1) {
                $error = "You cannot change the role of the last administrator.";
            }
        }

        if ($error === "") {
            $update = $conn->prepare("UPDATE users SET fullname = ?, users = ?, role = ? WHERE id = ?");
            if (!$update) {
                $error = "Unable to prepare the account update.";
            } else {
                $update->bind_param("sssi", $fullname, $username, $role, $accountId);
                if ($update->execute()) {
                    $update->close();
                    $_SESSION["edit_account_csrf"] = bin2hex(random_bytes(32));
                    header("Location: dashboard.php?page=accounts&updated=1");
                    exit;
                } else {
                    if ($conn->errno === 1062) {
                        $error = "That username is already being used. Please choose another one.";
                    } else {
                        error_log("Edit account update failed: " . $update->error);
                        $error = "Unable to update this account. Please check the PHP error log.";
                    }
                    $update->close();
                }
            }
        }
    }

    /* Preserve submitted values so the admin does not need to retype them. */
    $account["fullname"] = $fullname;
    $account["users"] = $username;
    $account["role"] = $role;
    $_SESSION["edit_account_csrf"] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Account | EcoTrack Admin</title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body{margin:0;background:#f3f7f4;color:#263b30;font-family:Arial,sans-serif}
        .topbar{background:#176b40;color:#fff;padding:15px 25px;display:flex;align-items:center;justify-content:space-between;gap:15px;box-shadow:0 2px 10px rgba(0,0,0,.08)}
        .brand{font-weight:700;color:#fff;text-decoration:none;font-size:17px}
        .page-wrap{max-width:850px;margin:38px auto;padding:0 18px}
        .back-link{display:inline-flex;align-items:center;gap:7px;color:#237449;text-decoration:none;font-weight:600;font-size:13px;margin-bottom:18px}
        .edit-card{background:#fff;border:1px solid #e5eee8;border-radius:16px;box-shadow:0 8px 28px rgba(25,65,42,.06);overflow:hidden}
        .card-heading{padding:25px 28px;background:linear-gradient(135deg,#eaf5ee,#f8fbf9);border-bottom:1px solid #e5eee8}
        .card-heading h1{font-size:24px;font-weight:700;color:#183e2c;margin:0 0 6px}
        .card-heading p{margin:0;color:#718077;font-size:13px}
        .card-body{padding:28px}
        .form-label{font-size:13px;font-weight:700;color:#45564d}
        .form-control,.form-select{min-height:45px;border-color:#dce6df;border-radius:8px}
        .form-control:focus,.form-select:focus{border-color:#238653;box-shadow:0 0 0 3px rgba(35,134,83,.1)}
        .account-meta{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px}
        .meta-pill{background:#edf5ef;color:#237449;border-radius:30px;padding:7px 12px;font-size:12px;font-weight:600}
        .form-actions{
    display:flex;
    gap:14px;
    justify-content:flex-end;
    flex-wrap:wrap;
    margin-top:30px;
}

.form-actions .btn{
    min-width:150px;
    min-height:48px;
    padding:12px 22px;
    font-size:15px;
    font-weight:600;
    border-radius:9px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    transition:all .2s ease;
}

.form-actions .btn:hover{
    transform:translateY(-2px);
    box-shadow:0 4px 10px rgba(0,0,0,.10);
}
        .form-actions .btn{
    font-size:12px;
    padding:6px 10px;
    border-radius:7px;
    font-weight:600;
    transition:all .2s ease;
}

.form-actions .btn-outline-success{
    color:#238653;
    border:1px solid #238653;
    background:transparent;
}

.form-actions .btn-outline-success:hover{
    background:#238653;
    color:#fff;
}

.form-actions .btn-outline-secondary{
    color:#6c757d;
    border:1px solid #6c757d;
    background:transparent;
}

.form-actions .btn-outline-secondary:hover{
    background:#6c757d;
    color:#fff;
} 
        @media(max-width:576px){.topbar{padding:13px 16px}.card-heading,.card-body{padding:20px}.form-actions>*{width:100%}}
    </style>
</head>
<body>
<nav class="topbar">
    <a href="dashboard.php" class="brand"><i class="bi bi-recycle me-2"></i>EcoTrack Admin</a>
    
</nav>

<main class="page-wrap">
    <a href="dashboard.php?page=accounts" class="back-link"><i class="bi bi-arrow-left"></i>Back to Manage Accounts</a>

    <section class="edit-card">
        <div class="card-heading">
            <h1><i class="bi bi-person-gear me-2 text-success"></i>Edit Account</h1>
            <p>Update the account information and access level.</p>
        </div>
        <div class="card-body">
            <div class="account-meta">
                <span class="meta-pill">Account ID: #<?= (int)$account["id"] ?></span>
                <span class="meta-pill">Current type: <?= editAccountEscape(ucfirst($account["role"])) ?></span>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert alert-danger"><?= editAccountEscape($error) ?></div>
            <?php endif; ?>

            <form method="post" id="editAccountForm" action="edit_account.php?id=<?= (int)$accountId ?>">
                <input type="hidden" name="csrf_token" value="<?= editAccountEscape($_SESSION["edit_account_csrf"]) ?>">
                <input type="hidden" name="account_id" value="<?= (int)$accountId ?>">

                <div class="mb-3">
                    <label for="fullname" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="fullname" name="fullname"
                           value="<?= editAccountEscape($account["fullname"]) ?>" maxlength="150" required>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= editAccountEscape($account["users"]) ?>" maxlength="100" required>
                </div>

                <div class="mb-2">
                    <label for="role" class="form-label">Account Type</label>
                    <select class="form-select" id="role" name="role" required <?= (int)$accountId === $currentAdminId ? "disabled" : "" ?>>
                        <option value="admin" <?= $account["role"] === "admin" ? "selected" : "" ?>>Administrator</option>
                        <option value="collector" <?= $account["role"] === "collector" ? "selected" : "" ?>>Garbage Collector</option>
                        <option value="residents" <?= $account["role"] === "residents" ? "selected" : "" ?>>Resident</option>
                    </select>
                    <?php if ((int)$accountId === $currentAdminId): ?>
                        <input type="hidden" name="role" value="<?= editAccountEscape($account["role"]) ?>">
                        <small class="text-muted">You cannot change your own account type.</small>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a href="dashboard.php?page=accounts" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-success px-4"><i class="bi bi-check2-circle me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </section>
</main>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const editForm = document.getElementById("editAccountForm");

    if (!editForm) return;

    editForm.addEventListener("submit", function (event) {
        event.preventDefault();

        Swal.fire({
            title: "Save Changes?",
            text: "Are you sure you want to update this account's information?",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#198754",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "Yes, save changes",
            cancelButtonText: "Cancel",
            reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) {
                editForm.submit();
            }
        });
    });
});
</script>

</body>
</html>
