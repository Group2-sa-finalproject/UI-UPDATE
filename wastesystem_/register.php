
<?php
session_start();
include "config/database.php";

$message = "";

$fullname = "";
$username = "";
$email = "";
$address = "";

if (isset($_POST["register"])) {

    $fullname = trim($_POST["fullname"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $terms = isset($_POST["terms"]);

    if (
        $fullname === "" ||
        $username === "" ||
        $email === "" ||
        $address === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $message = "Please complete all required fields.";

    } elseif (mb_strlen($address) > 500) {
        $message = "Address must be 500 characters or fewer.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";

    } elseif (!$terms) {
        $message = "Please agree to the Terms and Conditions.";

    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";

    } else {

        // Check if username already exists
        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE users = ? LIMIT 1"
        );

        if (!$check) {
            $message = "Database error: " . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param($check, "s", $username);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);

            if (mysqli_stmt_num_rows($check) > 0) {
                $message = "Username already exists. Please choose another.";

            } else {

                // Check if email already exists
                $check_email = mysqli_prepare(
                    $conn,
                    "SELECT id FROM users WHERE email = ? LIMIT 1"
                );

                if (!$check_email) {
                    $message = "Database error: " . mysqli_error($conn);

                } else {

                    mysqli_stmt_bind_param($check_email, "s", $email);
                    mysqli_stmt_execute($check_email);
                    mysqli_stmt_store_result($check_email);

                    if (mysqli_stmt_num_rows($check_email) > 0) {
                        $message = "Email address is already registered.";

                    } else {

                        // Hash the password
                        $hashed_password = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        // Register resident account
                        $sql = mysqli_prepare(
                            $conn,
                            "INSERT INTO users (fullname, users, email, address, password, role)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );

                        if (!$sql) {
                            $message = "Database error: " . mysqli_error($conn);

                        } else {

                            $role = "residents";

                            mysqli_stmt_bind_param(
                                $sql,
                                "ssssss",
                                $fullname,
                                $username,
                                $email,
                                $address,
                                $hashed_password,
                                $role
                            );

                            if (mysqli_stmt_execute($sql)) {
                                mysqli_stmt_close($sql);
                                mysqli_stmt_close($check_email);
                                mysqli_stmt_close($check);

                                header("Location: index.php?registered=1");
                                exit;

                            } else {
                                $message = "Registration failed: " . mysqli_stmt_error($sql);
                            }

                            mysqli_stmt_close($sql);
                        }
                    }

                    mysqli_stmt_close($check_email);
                }
            }

            mysqli_stmt_close($check);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EcoTrack – Create Your Account</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:#f4f8f5;color:#183c2e}
p,h1,h2{margin:0}
a{text-decoration:none}
svg{display:block;width:100%;height:100%}

/* Layout */
.page{min-height:100vh}
@media(min-width:1024px){.page{display:grid;grid-template-columns:minmax(500px,1.02fr) minmax(560px,.98fr)}}

/* Brand panel */
.brand{position:relative;overflow:hidden;background:#146c43;color:#fff}
@media(min-width:1024px){.brand{min-height:100vh}}
.brand::before{content:"";position:absolute;inset:0;opacity:.45;
  background:radial-gradient(circle at 20% 20%,rgba(255,255,255,.08) 0 2px,transparent 3px) 0 0/48px 48px,
             radial-gradient(circle at 70% 60%,rgba(255,255,255,.06) 0 3px,transparent 4px) 0 0/72px 72px}
.glow{position:absolute;border-radius:50%;filter:blur(64px)}
.glow.a{left:-112px;top:38%;width:288px;height:288px;background:rgba(93,187,120,.2)}
.glow.b{right:-96px;top:-80px;width:288px;height:288px;background:rgba(132,212,154,.15)}
.brand-inner{position:relative;z-index:1;margin:0 auto;max-width:760px;height:100%;display:flex;flex-direction:column;padding:24px}
@media(min-width:640px){.brand-inner{padding-left:40px;padding-right:40px}}
@media(min-width:1024px){.brand-inner{padding:36px clamp(42px,5vw,80px)}}

.logo{display:flex;align-items:center;gap:12px}
.logo-mark{display:grid;place-items:center;flex-shrink:0;width:48px;height:48px;border-radius:16px;background:rgba(255,255,255,.15);box-shadow:inset 0 0 0 1px rgba(255,255,255,.25)}
.logo-mark svg{width:28px;height:28px}
.logo-name{font-size:24px;font-weight:800;letter-spacing:-.04em}
.logo-sub{margin-top:2px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.24em;color:rgba(255,255,255,.65)}

.hero{margin-top:36px;max-width:620px}
@media(min-width:1024px){.hero{margin-top:clamp(40px,6vh,76px)}}
.pill{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:999px;border:1px solid rgba(255,255,255,.2);
  background:rgba(255,255,255,.1);backdrop-filter:blur(12px);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.14em;color:#e0f7e7}
.pill span{color:#a9e8bb}
.hero h1{margin-top:20px;font-size:clamp(2.5rem,4.1vw,4.5rem);font-weight:800;line-height:.98;letter-spacing:-.055em}
.hero h1 em{font-style:normal;color:#a9e8bb}
.hero h2{margin-top:20px;max-width:540px;font-size:18px;font-weight:600;line-height:1.6}
.hero p{margin-top:12px;max-width:540px;font-size:14px;line-height:24px;color:rgba(255,255,255,.68)}

.showcase{position:relative;margin-top:28px;display:none}
@media(min-width:1024px){.showcase{display:block}}
.photo{position:relative;height:clamp(220px,27vh,320px);overflow:hidden;border-radius:28px;border:1px solid rgba(255,255,255,.15);box-shadow:0 24px 65px rgba(3,44,26,.3)}
.photo img{width:100%;height:100%;object-fit:cover;object-position:center}
.photo::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(7,62,38,.75),transparent)}
.photo p{position:absolute;z-index:1;bottom:20px;left:24px;font-size:14px;font-weight:600;color:rgba(255,255,255,.95)}
.features{position:absolute;left:50%;bottom:-40px;width:92%;transform:translateX(-50%);display:flex;gap:8px}
.feature{flex:1;min-width:0;display:flex;align-items:center;gap:10px;padding:12px;border-radius:16px;border:1px solid rgba(255,255,255,.2);
  background:rgba(255,255,255,.95);color:#173d2d;box-shadow:0 20px 25px rgba(0,0,0,.15);backdrop-filter:blur(8px)}
.feature .ic{display:grid;place-items:center;flex-shrink:0;width:32px;height:32px;border-radius:12px;background:#e7f5eb;color:#198754}
.feature .ic svg{width:16px;height:16px}
.feature b{display:block;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.feature small{display:block;font-size:9px;color:#789083;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tagline{display:none;margin-top:auto;padding-top:64px;text-align:center;font-size:12px;font-weight:500;letter-spacing:.025em;color:rgba(255,255,255,.55)}
@media(min-width:1024px){.tagline{display:block}}

/* Form panel */
.form-side{position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden;
  min-height:calc(100vh - 190px);padding:40px 20px;background:linear-gradient(135deg,#dff2e5 0%,#f2faf5 24%,#fff 58%)}
@media(min-width:640px){.form-side{padding-left:40px;padding-right:40px}}
@media(min-width:1024px){.form-side{min-height:100vh;padding-top:32px;padding-bottom:32px}}
.blob{position:absolute}
.blob.l{left:-160px;top:50%;width:620px;height:620px;transform:translateY(-50%);border-radius:50%;background:rgba(25,135,84,.12);filter:blur(64px)}
.blob.tr{right:0;top:0;width:192px;height:192px;border-bottom-left-radius:100%;background:rgba(223,242,229,.8)}
.blob.bl{left:0;bottom:0;width:176px;height:176px;border-top-right-radius:100%;background:rgba(216,239,224,.7)}

.card{position:relative;z-index:1;width:100%;max-width:540px;padding:32px 24px;border-radius:30px;border:1px solid #cde5d5;background:rgba(255,255,255,.92);
  box-shadow:0 28px 80px rgba(20,108,67,.2),0 8px 24px rgba(20,108,67,.1);backdrop-filter:blur(24px)}
@media(min-width:640px){.card{padding-left:36px;padding-right:36px}}
@media(min-width:1024px){.card{padding:28px 32px}}
@media(min-width:1280px){.card{padding:36px 40px}}
.card-head{margin-bottom:28px;text-align:center}
@media(min-width:1024px){.card-head{margin-bottom:24px}}
.card-icon{display:grid;place-items:center;width:56px;height:56px;margin:0 auto 16px;border-radius:16px;background:#e8f5ec;color:#198754;box-shadow:0 8px 25px rgba(25,135,84,.12)}
.card-icon svg{width:32px;height:32px}
.card-head h2{font-size:clamp(1.8rem,3vw,2.25rem);font-weight:800;letter-spacing:-.04em;color:#153d2d}
.card-head p{margin-top:8px;font-size:14px;color:#71857b}

form{display:flex;flex-direction:column;gap:16px}
.row{display:grid;gap:16px}
@media(min-width:640px){.row{grid-template-columns:1fr 1fr}}
.field{display:block}
.label{display:block;margin-bottom:8px;font-size:13px;font-weight:600;color:#274c3d}
.control{position:relative;display:block}
.icon-wrap{position:absolute;top:0;bottom:0;left:0;width:48px;display:grid;place-items:center;pointer-events:none}
.icon-badge{display:grid;place-items:center;width:32px;height:32px;border-radius:8px;color:#fff;
  background:linear-gradient(135deg,#198754,#146c43);box-shadow:0 5px 12px rgba(20,108,67,.28);transition:transform .2s,box-shadow .2s}
.icon-badge svg{width:17px;height:17px}
.control:focus-within .icon-badge{transform:scale(1.05);box-shadow:0 7px 16px rgba(20,108,67,.38)}
.input{width:100%;height:52px;padding:0 16px 0 48px;border:1px solid #dce8e1;border-radius:12px;background:#fbfdfc;
  font:inherit;font-size:14px;color:#183c2e;outline:none;transition:all .2s}
.input.has-toggle{padding-right:48px}
.input::placeholder{color:#9baca4}
.input:hover{border-color:#b8d2c3}
.input:focus{border-color:#198754;background:#fff;box-shadow:0 0 0 4px rgba(25,135,84,.1)}
.input:user-invalid{border-color:#d9534f}

.toggle{position:absolute;top:0;bottom:0;right:0;width:48px;display:grid;place-items:center;border:0;background:none;cursor:pointer;color:#82988e;transition:color .2s}
.toggle:hover{color:#198754}
.toggle:focus-visible{outline:2px solid #198754;outline-offset:-4px}
.toggle svg{width:20px;height:20px}

.terms{display:flex;align-items:flex-start;gap:12px;padding:4px 0;font-size:13px;line-height:20px;color:#627a6f;cursor:pointer}
.terms input{position:absolute;width:1px;height:1px;opacity:0}
.box{display:grid;place-items:center;flex-shrink:0;width:18px;height:18px;margin-top:2px;border:1px solid #b8cbc0;border-radius:5px;background:#fff;font-size:11px;font-weight:700;color:transparent;transition:all .2s}
.terms input:checked + .box{border-color:#198754;background:#198754;color:#fff}
.terms input:focus-visible + .box{box-shadow:0 0 0 4px rgba(25,135,84,.15)}
.terms a{font-weight:600;color:#198754}
.terms a:hover{text-decoration:underline;text-underline-offset:4px}

.submit{display:flex;align-items:center;justify-content:center;gap:12px;width:100%;height:54px;border:0;border-radius:12px;cursor:pointer;
  background:#198754;color:#fff;font:inherit;font-size:14px;font-weight:700;box-shadow:0 12px 28px rgba(25,135,84,.22);transition:all .2s}
.submit:hover{transform:translateY(-2px);background:#146c43;box-shadow:0 16px 34px rgba(25,135,84,.28)}
.submit:active{transform:none}
.submit:focus-visible{outline:2px solid #198754;outline-offset:2px}
.submit svg{width:16px;height:16px;transition:transform .2s}
.submit:hover svg{transform:translateX(4px)}

.divider{display:flex;align-items:center;gap:16px;margin:24px 0}
.divider i{flex:1;height:1px;background:#e5ece8}
.divider span{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:#a1b0a8}
.login{text-align:center;font-size:14px;color:#71857b}
.login a{font-weight:700;color:#198754}
.login a:hover{color:#146c43;text-decoration:underline;text-underline-offset:4px}
.secure{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:24px;font-size:11px;color:#8c9e95}
.secure svg{width:14px;height:14px;color:#198754}

.form-error{margin-bottom:16px;padding:12px 14px;border:1px solid #f1b8b5;border-radius:10px;background:#fff0ef;color:#a52d28;font-size:13px;line-height:1.5}
.address-note{display:block;margin-top:7px;font-size:11px;line-height:1.5;color:#71857b}
</style>
</head>

<body>

<!-- SVG icons -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-leaf" viewBox="0 0 24 24"><path d="M20.6 3.5C13.9 3.6 7.2 5.6 4.9 10.8c-1.7 3.8.3 7.3 3.9 7.3 6.8 0 10-8.1 11.8-14.6Z" fill="currentColor"/><path d="M3.5 21c2.7-5 6.2-8.2 11.5-11.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><path d="M12 12a4.25 4.25 0 1 0 0-8.5 4.25 4.25 0 0 0 0 8.5ZM4.5 20.5c.55-4.02 3.16-6 7.5-6s6.95 1.98 7.5 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/></symbol>
  <symbol id="i-at" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M15.5 8.5v5a2 2 0 0 0 4 0V12M15.5 12a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="13" rx="2.5" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="m4.5 7.5 7.5 5.5 7.5-5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" fill="none"/></symbol>
  <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4.5" y="10" width="15" height="10.5" rx="2.5" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/><circle cx="12" cy="15" r="1.2" fill="currentColor"/></symbol>
  <symbol id="i-eye" viewBox="0 0 24 24"><path d="M3 12s3.2-5 9-5 9 5 9 5-3.2 5-9 5-9-5-9-5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/><circle cx="12" cy="12" r="2.2" stroke="currentColor" stroke-width="1.7" fill="none"/></symbol>
</svg>

<main class="page">

  <!-- LEFT BRAND PANEL -->
  <section class="brand">
    <div class="glow a"></div>
    <div class="glow b"></div>

    <div class="brand-inner">

      <div class="logo">
        <span class="logo-mark">
          <svg><use href="#i-leaf"/></svg>
        </span>
        <div>
          <p class="logo-name">EcoTrack</p>
          <p class="logo-sub">Waste Management</p>
        </div>
      </div>

      <div class="hero">
        <div class="pill"><span>✦</span> Smart Waste Management</div>

        <h1>Join EcoTrack<br><em>Today!</em></h1>

        <h2>Building a Cleaner Community, One Collection at a Time.</h2>

        <p>
          Create your account and become part of a smarter, cleaner,
          and more sustainable community through organized waste collection.
        </p>
      </div>

      <div class="showcase">
        <div class="photo">
          <img
            alt="Waste collection truck serving a city neighborhood"
            src="https://images.unsplash.com/photo-1762235634143-6d350fe349e8?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&ixlib=rb-4.1.0&q=85&w=1400"
          >
          <p>Reliable collection. Healthier neighborhoods.</p>
        </div>

        <div class="features">

          <div class="feature">
            <span class="ic">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 3 1.3 4.7L18 9l-4.7 1.3L12 15l-1.3-4.7L6 9l4.7-1.3L12 3Zm6 11 .7 2.3L21 17l-2.3.7L18 20l-.7-2.3L15 17l2.3-.7L18 14Z"/>
              </svg>
            </span>
            <span style="min-width:0">
              <b>Smart Waste</b>
              <small>Better solutions</small>
            </span>
          </div>

          <div class="feature">
            <span class="ic">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 8h12M8 4v3m8-3v3M6 6h12v14H6zM9 12h2m2 0h2m-6 4h2m2 0h2"/>
              </svg>
            </span>
            <span style="min-width:0">
              <b>Organized</b>
              <small>Timely collection</small>
            </span>
          </div>

          <div class="feature">
            <span class="ic">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8.5 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.5-1a2.5 2.5 0 1 0 0-5M3 19c.4-3.3 2.2-5 5.5-5s5.1 1.7 5.5 5m.5-5c3 0 4.7 1.5 5 4.5"/>
              </svg>
            </span>
            <span style="min-width:0">
              <b>Community</b>
              <small>Cleaner together</small>
            </span>
          </div>

        </div>
      </div>

      <p class="tagline">Together for a Cleaner and Greener Tomorrow.</p>

    </div>
  </section>


  <!-- RIGHT REGISTRATION PANEL -->
  <section class="form-side">

    <div class="blob l"></div>
    <div class="blob tr"></div>
    <div class="blob bl"></div>

    <div class="card">

      <div class="card-head">
        <div class="card-icon">
          <svg><use href="#i-leaf"/></svg>
        </div>

        <h2>Create Your Account</h2>
        <p>Register now and start using EcoTrack.</p>
      </div>

      <?php if ($message !== "") { ?>
        <div class="form-error">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php } ?>

      <form id="register-form" method="POST" action="">

        <div class="row">

          <label class="field">
            <span class="label">Full Name</span>

            <span class="control">
              <span class="icon-wrap">
                <span class="icon-badge">
                  <svg><use href="#i-user"/></svg>
                </span>
              </span>

              <input
                class="input"
                type="text"
                name="fullname"
                autocomplete="name"
                placeholder="Enter your full name"
                value="<?php echo htmlspecialchars($fullname); ?>"
                required
              >
            </span>
          </label>

          <label class="field">
            <span class="label">Username</span>

            <span class="control">
              <span class="icon-wrap">
                <span class="icon-badge">
                  <svg><use href="#i-at"/></svg>
                </span>
              </span>

              <input
                class="input"
                type="text"
                name="username"
                autocomplete="username"
                placeholder="Create a username"
                value="<?php echo htmlspecialchars($username); ?>"
                required
              >
            </span>
          </label>

        </div>


        <!-- EMAIL -->
        <label class="field">
          <span class="label">Email Address</span>

          <span class="control">
            <span class="icon-wrap">
              <span class="icon-badge">
                <svg><use href="#i-mail"/></svg>
              </span>
            </span>

            <input
              class="input"
              type="email"
              name="email"
              autocomplete="email"
              placeholder="Enter your email address"
              value="<?php echo htmlspecialchars($email); ?>"
              required
            >
          </span>
        </label>


        <!-- ADDRESS SAVED FOR AUTOMATIC HOUSEHOLD REGISTRATION -->
        <label class="field">
          <span class="label">Complete Home Address</span>
          <span class="control">
            <span class="icon-wrap"><span class="icon-badge">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </span></span>
            <input class="input" type="text" name="address" autocomplete="street-address"
              maxlength="500" placeholder="House no., street, barangay, city, province"
              value="<?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?>" required>
          </span>
          <small class="address-note">This address will automatically appear when you register your household.</small>
        </label>

        <!-- PASSWORD -->
        <label class="field">
          <span class="label">Password</span>

          <span class="control">
            <span class="icon-wrap">
              <span class="icon-badge">
                <svg><use href="#i-lock"/></svg>
              </span>
            </span>

            <input
              class="input has-toggle"
              type="password"
              name="password"
              autocomplete="new-password"
              minlength="8"
              placeholder="Create a password"
              required
            >

            <button type="button" class="toggle" aria-label="Show password">
              <svg><use href="#i-eye"/></svg>
            </button>
          </span>
        </label>


        <!-- CONFIRM PASSWORD -->
        <label class="field">
          <span class="label">Confirm Password</span>

          <span class="control">
            <span class="icon-wrap">
              <span class="icon-badge">
                <svg><use href="#i-lock"/></svg>
              </span>
            </span>

            <input
              class="input has-toggle"
              type="password"
              name="confirm_password"
              autocomplete="new-password"
              minlength="8"
              placeholder="Confirm your password"
              required
            >

            <button type="button" class="toggle" aria-label="Show confirm password">
              <svg><use href="#i-eye"/></svg>
            </button>
          </span>
        </label>


        <!-- TERMS AND CONDITIONS -->
        <label class="terms">
          <input type="checkbox" name="terms" value="1" required>
          <span class="box">✓</span>
          <span>
            I agree to the
            <a href="#terms">Terms and Conditions</a>
          </span>
        </label>


        <!-- REGISTER BUTTON -->
        <button type="submit" name="register" value="1" class="submit">  Create Account

          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 12h14m-5-5 5 5-5 5"/>
          </svg>
        </button>

      </form>


      <div class="divider">
        <i></i>
        <span>or</span>
        <i></i>
      </div>

      <p class="login">
        Already have an account?
        <a href="index.php">Login Here</a>
      </p>

      <div class="secure">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
          <path d="M12 3 5.5 5.7v5.2c0 4.5 2.8 8 6.5 10.1 3.7-2.1 6.5-5.6 6.5-10.1V5.7L12 3Z"/>
          <path d="m9 12 2 2 4-4"/>
        </svg>

        Your personal information is protected and secure.
      </div>

    </div>
  </section>

</main>


<script>

// Show / hide password
document.querySelectorAll('.toggle').forEach(function (btn) {

    btn.addEventListener('click', function () {

        var input = btn.parentElement.querySelector('input');

        input.type = input.type === 'password' ? 'text' : 'password';

    });

});


// Confirm-password check
var form = document.getElementById('register-form');

form.addEventListener('submit', function (e) {

    var p = form.password.value;
    var c = form.confirm_password.value;

    if (p !== c) {

        e.preventDefault();

        form.confirm_password.setCustomValidity('Passwords do not match');
        form.confirm_password.reportValidity();

    }

});

form.confirm_password.addEventListener('input', function () {

    form.confirm_password.setCustomValidity('');

});

</script>

</body>
</html>
