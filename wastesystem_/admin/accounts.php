<?php

// Account categories
$roles = [
    "admin" => "Administrators",
    "collector" => "Garbage Collectors",
    "residents" => "Residents"
];

// Prepare account arrays
$accounts = [
    "admin" => [],
    "collector" => [],
    "residents" => []
];

// Get all accounts from users table
$sql = "SELECT id, fullname, users, role
        FROM users
        ORDER BY fullname ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo '<p class="account-error">Database query failed: ' .
        htmlspecialchars(mysqli_error($conn)) . '</p>';
} else {

    // Separate accounts according to their roles
    while ($row = mysqli_fetch_assoc($result)) {

        $role = $row["role"];

        if (isset($accounts[$role])) {
            $accounts[$role][] = $row;
        }
    }
}

// Total number of accounts
$total_accounts = 0;

foreach ($accounts as $account_list) {
    $total_accounts += count($account_list);
}

?>

<style>
    .accounts-page {
        width: 100%;
        color: #263b30;
    }

    .accounts-page * {
        box-sizing: border-box;
    }

    .accounts-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
    }

    .accounts-header h1 {
        font-size: 25px;
        font-weight: 700;
        color: #183e2c;
        margin: 0 0 6px;
    }

    .accounts-header p {
        color: #78877e;
        font-size: 13px;
        margin: 0;
    }

    .add-account-btn {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        background: #238653;
        color: #fff;
        padding: 11px 18px;
        min-height: 42px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: background .2s ease, transform .2s ease;
    }

    .add-account-btn:hover {
        background: #176b40;
        color: #fff;
        transform: translateY(-1px);
    }

    .accounts-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .accounts-summary {
        flex: 0 0 220px;
        background: #fff;
        border: 1px solid #e7eee9;
        border-radius: 12px;
        padding: 17px 20px;
        box-shadow: 0 2px 8px rgba(30, 70, 45, .03);
    }

    .accounts-summary span {
        display: block;
        color: #78877e;
        font-size: 12px;
        margin-bottom: 6px;
    }

    .accounts-summary h2 {
        color: #1b4430;
        font-size: 27px;
        font-weight: 700;
        margin: 0;
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
        transition: border-color .2s ease, box-shadow .2s ease;
    }

    .account-search input:focus {
        border-color: #238653;
        box-shadow: 0 0 0 3px rgba(35, 134, 83, .10);
    }

    .account-section {
        background: #fff;
        border: 1px solid #e7eee9;
        border-radius: 12px;
        padding: 21px;
        margin-bottom: 18px;
        box-shadow: 0 2px 8px rgba(30, 70, 45, .03);
    }

    .account-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }

    .account-section h2 {
        font-size: 16px;
        font-weight: 700;
        color: #214b34;
        margin: 0;
    }

    .account-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 27px;
        height: 25px;
        padding: 0 9px;
        background: #e8f4ec;
        color: #237449;
        font-size: 11px;
        font-weight: 700;
        border-radius: 20px;
    }

    .account-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #edf1ee;
        border-radius: 8px;
    }

    .account-table {
        width: 100%;
        min-width: 560px;
        border-collapse: collapse;
        font-size: 13px;
    }

    .account-table th {
        background: #f5f8f6;
        color: #45564d;
        font-weight: 600;
        text-align: left;
        padding: 13px 15px;
        border-bottom: 1px solid #e4ebe6;
        white-space: nowrap;
    }

    .account-table td {
        padding: 14px 15px;
        border-bottom: 1px solid #edf1ee;
        color: #52645a;
        vertical-align: middle;
    }

    .account-table tbody tr:last-child td {
        border-bottom: none;
    }

    .account-table tbody tr:hover {
        background: #f8fbf9;
    }

    .account-table td:first-child {
        color: #748279;
        font-variant-numeric: tabular-nums;
    }

    .role-badge {
        display: inline-block;
        background: #edf5ef;
        color: #237449;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }

    .empty-message {
        text-align: center;
        color: #929e96 !important;
        padding: 25px !important;
    }

    .account-error {
        color: #b42318;
        background: #fff1f0;
        border: 1px solid #ffd8d5;
        padding: 12px 14px;
        border-radius: 8px;
        font-size: 13px;
    }

    .no-search-results {
        display: none;
        color: #78877e;
        background: #f8faf8;
        border: 1px dashed #dce6df;
        border-radius: 8px;
        padding: 18px;
        text-align: center;
        font-size: 13px;
        margin-top: 12px;
    }

    @media (max-width: 650px) {
        .accounts-header {
            align-items: stretch;
        }

        .accounts-header h1 {
            font-size: 22px;
        }

        .add-account-btn {
            width: 100%;
        }

        .accounts-toolbar {
            align-items: stretch;
        }

        .accounts-summary,
        .account-search {
            flex: 1 1 100%;
            max-width: none;
        }

        .account-section {
            padding: 15px;
        }

        .account-section-header {
            align-items: flex-start;
        }
    }
</style>

<div class="accounts-page">

    <div class="accounts-header">
        <div>
            <h1>Manage Accounts</h1>
            <p>View and monitor all registered accounts in the system.</p>
        </div>

        <a href="add_account.php" class="add-account-btn">
            Add New Account
        </a>
    </div>

    <div class="accounts-toolbar">

        <div class="accounts-summary">
            <span>Total Registered Accounts</span>
            <h2><?php echo number_format($total_accounts); ?></h2>
        </div>

        <div class="account-search">
            <label for="accountSearch">Search accounts</label>
            <input
                type="search"
                id="accountSearch"
                placeholder="Search by ID, name, username, or account type..."
                autocomplete="off"
            >
        </div>

    </div>

    <?php foreach ($roles as $role => $title) { ?>

        <section class="account-section">

            <div class="account-section-header">
                <h2><?php echo htmlspecialchars($title); ?></h2>

                <span class="account-count">
                    <?php echo count($accounts[$role]); ?>
                </span>
            </div>

            <div class="account-table-wrap">
                <table class="account-table">

                    <thead>
                        <tr>
                            <th>Account ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Account Type</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($accounts[$role]) > 0) { ?>

                            <?php foreach ($accounts[$role] as $account) { ?>

                                <tr class="account-row">

                                    <td>
                                        <?php echo htmlspecialchars((string) $account["id"]); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($account["fullname"]); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($account["users"]); ?>
                                    </td>

                                    <td>
                                        <span class="role-badge">
                                            <?php echo htmlspecialchars(ucfirst($account["role"])); ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php } ?>

                        <?php } else { ?>

                            <tr>
                                <td colspan="4" class="empty-message">
                                    No <?php echo htmlspecialchars(strtolower($title)); ?> accounts found.
                                </td>
                            </tr>

                        <?php } ?>

                    </tbody>

                </table>
            </div>

            <div class="no-search-results">
                No matching accounts found in this category.
            </div>

        </section>

    <?php } ?>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const searchInput = document.getElementById("accountSearch");
    const sections = document.querySelectorAll(".account-section");

    if (!searchInput) {
        return;
    }

    searchInput.addEventListener("input", function () {

        const searchValue = this.value.toLowerCase().trim();

        sections.forEach(function (section) {

            const rows = section.querySelectorAll(".account-row");
            const noResults = section.querySelector(".no-search-results");
            let visibleRows = 0;

            rows.forEach(function (row) {

                const rowText = row.textContent.toLowerCase();
                const matches = rowText.includes(searchValue);

                row.style.display = matches ? "" : "none";

                if (matches) {
                    visibleRows++;
                }

            });

            if (rows.length > 0) {
                noResults.style.display = visibleRows === 0 ? "block" : "none";
            } else {
                noResults.style.display = "none";
            }

        });

    });

});
</script>
