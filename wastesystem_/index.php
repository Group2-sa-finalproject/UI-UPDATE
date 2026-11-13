<?php
session_start();
include "config/database.php";

// Redirect users who are already logged in
if (isset($_SESSION["role"])) {

    if ($_SESSION["role"] == "admin") {
        header("Location: admin/dashboard.php");
        exit;

    } elseif ($_SESSION["role"] == "residents") {
        header("Location: residents/dashboard.php");
        exit;

    } elseif ($_SESSION["role"] == "collector") {
        header("Location: collector/dashboard.php");
        exit;
    }
}

$error = "";
$success = "";

// Registration success message
if (isset($_GET["registered"])) {
    $success = "Registration successful! You can now log in.";
}

// Login process
if (isset($_POST["login"])) {

    $username = mysqli_real_escape_string($conn, $_POST["username"]);
    $password = $_POST["password"];

    $sql = "SELECT * FROM users WHERE users='$username' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) == 1) {

        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user["password"])) {

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["full_name"] = $user["fullname"];
            $_SESSION["role"] = $user["role"];

            // Redirect according to user role
            if ($user["role"] == "admin") {

                header("Location: admin/dashboard.php");
                exit;

            } elseif ($user["role"] == "residents") {

                header("Location: residents/dashboard.php");
                exit;

            } elseif ($user["role"] == "collector") {

                header("Location: collector/dashboard.php");
                exit;

            } else {

                $error = "Invalid user role.";

            }

        } else {

            $error = "Incorrect username or password.";

        }

    } else {

        $error = "Incorrect username or password.";

    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>EcoTrack – Login</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="style.css">
    <style>

        /* Login messages */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .alert-danger {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        /* Login button */
        .btn-signin {
            cursor: pointer;
            width: 100%;
        }

        /* Password toggle */
        .toggle {
            cursor: pointer;
        }

    </style>

</head>

<body>

    <div class="split">

        <!-- LEFT: BRAND PANEL -->
        <aside class="brand-panel">

            <div class="brand-top">

                <span class="logo-box">

                    <svg viewBox="0 0 24 24" width="24" height="24"
                        fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">

                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10Z"/>

                        <path d="M2 21c0-3 1.9-5.5 6-7"/>

                    </svg>

                </span>

                <span class="logo-text">
                    Eco<span>Track</span>
                </span>

            </div>


            <div class="brand-body">

                <span class="tag">
                    ✦ Smart Waste Management
                </span>

                <h1>
                    Welcome to <span>EcoTrack</span>
                </h1>

                <h2>
                    Building a Cleaner Community,<br>
                    One Collection at a Time.
                </h2>

                <p>
                    EcoTrack makes garbage collection simpler through organized schedules,
                    convenient pickup requests, and real-time truck tracking—all in one
                    connected platform.
                </p>


                <div class="showcase">

                    <div class="stat-badge">

                        <span class="stat-icon">⟳</span>

                        <div>
                            <strong>98%</strong>
                            <small>On-time</small>
                        </div>

                    </div>


                    <div class="showcase-photo">

                        <img src="images/ui.jpg" alt="Garbage collection truck">

                    </div>


                    <div class="progress-card">

                        <span class="pc-icon">🚛</span>

                        <div>
                            <strong>Collection in progress</strong>
                            <small>Truck ET-204 · On route</small>
                        </div>

                        <i class="pc-dot"></i>

                    </div>

                </div>

            </div>


            <p class="brand-footer">
                🍃 Together for a Cleaner and Greener Tomorrow.
            </p>

        </aside>


        <!-- RIGHT: LOGIN FORM -->
        <main class="form-panel">

            <div class="form-wrap">

                <span class="form-icon">

                    <svg viewBox="0 0 24 24" width="26" height="26"
                        fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">

                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10Z"/>

                    </svg>

                </span>


                <h3>Welcome Back!</h3>

                <p class="subtitle">
                    Sign in to access your EcoTrack account.
                </p>


                <!-- SUCCESS MESSAGE -->
                <?php if ($success != "") { ?>

                    <div class="alert alert-success">

                        <?php echo htmlspecialchars($success); ?>

                    </div>

                <?php } ?>


                <!-- ERROR MESSAGE -->
                <?php if ($error != "") { ?>

                    <div class="alert alert-danger">

                        <?php echo htmlspecialchars($error); ?>

                    </div>

                <?php } ?>


                <!-- LOGIN FORM -->
                <form id="loginForm" method="POST" action="">

                    <!-- USERNAME -->
                    <label for="username">
                        Username
                    </label>

                    <div class="field">

                        <svg class="f-icon" viewBox="0 0 24 24"
                            width="18" height="18" fill="none"
                            stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">

                            <circle cx="12" cy="8" r="4"/>

                            <path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>

                        </svg>

                        <input
                            id="username"
                            name="username"
                            type="text"
                            placeholder="Enter your username"
                            autocomplete="username"
                            required
                        >

                    </div>


                    <!-- PASSWORD -->
                    <label for="password">
                        Password
                    </label>

                    <div class="field">

                        <svg class="f-icon" viewBox="0 0 24 24"
                            width="18" height="18" fill="none"
                            stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">

                            <rect x="4" y="11" width="16" height="10" rx="2"/>

                            <path d="M8 11V8a4 4 0 0 1 8 0v3"/>

                        </svg>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="toggle"
                            id="togglePw"
                            aria-label="Show password"
                        >

                            <svg viewBox="0 0 24 24"
                                width="20" height="20"
                                fill="none" stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round">

                                <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/>

                                <circle cx="12" cy="12" r="3"/>

                            </svg>

                        </button>

                    </div>


                    <!-- REMEMBER AND FORGOT PASSWORD -->
                    <div class="row">

                        <label class="remember">

                            <input type="checkbox" name="remember">

                            Remember me

                        </label>

                        <a href="#" class="forgot">
                            Forgot Password?
                        </a>

                    </div>


                    <!-- SIGN IN BUTTON -->
                    <button
                        type="submit"
                        name="login"
                        class="btn-signin"
                    >

                        Sign In 

                    </button>

                </form>


                <!-- REGISTRATION LINK -->
                <p class="register">

                    Don't have an account?

                    <a href="register.php">
                        Register
                    </a>

                </p>


                <div class="divider"></div>


                <p class="role-note">
                    🔒 Your account role is identified automatically after signing in.
                </p>

            </div>


            <!-- FOOTER -->
            <footer class="copyright">

                © 2026 <span>EcoTrack</span>

            </footer>

        </main>

    </div>


    <!-- SHOW / HIDE PASSWORD -->
    <script>

        const pw = document.getElementById('password');

        document.getElementById('togglePw').addEventListener('click', () => {

            pw.type = pw.type === 'password' ? 'text' : 'password';

        });

    </script>

</body>
</html>