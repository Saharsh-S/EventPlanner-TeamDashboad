<?php


session_start();

// Already logged in → skip straight to events
if (!empty($_SESSION['user_id'])) {
    header('Location: events.php');
    exit;
}

require_once 'includes/db.php';

// Flash message forwarded from register.php
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Sanitise POST inputs ──────────────────────────────
    $email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL)         ?? '');
    $password =      filter_input(INPUT_POST, 'password', FILTER_DEFAULT)                ?? '';
    $role     = trim(filter_input(INPUT_POST, 'role',     FILTER_SANITIZE_SPECIAL_CHARS) ?? 'member');

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM Users WHERE email = ? AND role = ? LIMIT 1');
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            header('Location: events.php');
            exit;
        } else {
            $error = 'Invalid email, password, or role. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In CSS Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="my_style.css">
    <link rel="stylesheet" href="app_style.css">
    <style>
        .form-footer-link {
            margin-top: 20px; text-align: center;
            font-size: 0.8rem; color: #555;
        }
        .form-footer-link a {
            color: #60b8e0; font-weight: 600; text-decoration: none;
        }
        .form-footer-link a:hover { text-decoration: underline; }
    </style>
</head>
<body class="login-body">

<div class="login-page">
    <div class="login-card">

        <div class="login-brand">
            <img src="imgs/Interconnected unity in blue and black.png" alt="CSS Logo" class="login-logo">
            <div>
                <div class="brand-name">CSS Dashboard</div>
                <div class="brand-sub">McMaster CS Society</div>
            </div>
        </div>

        <h2 class="login-title">Sign in</h2>
        <p class="login-subtitle">Log in to access the event &amp; productivity dashboard.</p>

        <!-- Flash from register.php (success) or POST error -->
        <?php if ($flash): ?>
            <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>">
                <?= htmlspecialchars($flash['msg']) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="login-form" novalidate>

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@mcmaster.ca"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                    autofocus
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                    required
                    autocomplete="current-password"
                >
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="member"
                        <?= (($_POST['role'] ?? 'member') === 'member') ? 'selected' : '' ?>>
                        Member
                    </option>
                    <option value="executive"
                        <?= (($_POST['role'] ?? '') === 'executive') ? 'selected' : '' ?>>
                        Executive
                    </option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Sign In &rarr;</button>

        </form>

        <!-- Link to registration page -->
        <div class="form-footer-link">
            New member? <a href="register.php">Create an account</a>
        </div>

        <div class="demo-hint">
            <p class="demo-label">Demo accounts</p>
            <div class="demo-creds">
                Executive: <strong>exec@mcmaster.ca</strong> / <strong>exec123</strong><br>
                Member: <strong>member@mcmaster.ca</strong> / <strong>member123</strong>
            </div>
        </div>

    </div>
</div>

<script>
/**
 * login.js (inline)
 * Submit form on Enter key press from any field.
 * Standards: const/let, addEventListener, DOMContentLoaded.
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('login-form');
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') form.submit();
    });
});
</script>

</body>
</html>