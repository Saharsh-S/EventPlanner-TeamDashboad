<?php

session_start();

// Already logged in → skip registration
if (!empty($_SESSION['user_id'])) {
    header('Location: events.php');
    exit;
}

require_once 'includes/db.php';

$errors = [];
$vals   = ['name' => '', 'email' => ''];  // repopulate form on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Sanitise inputs ───────────────────────────────────
    $name     = trim(filter_input(INPUT_POST, 'name',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL)         ?? '');
    $password =      filter_input(INPUT_POST, 'password', FILTER_DEFAULT)                ?? '';
    $confirm  =      filter_input(INPUT_POST, 'confirm',  FILTER_DEFAULT)                ?? '';

    $vals = ['name' => $name, 'email' => $email];

    // ── Validate ──────────────────────────────────────────
    if (strlen($name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (!str_ends_with(strtolower($email), '@mcmaster.ca')) {
        $errors[] = 'Only @mcmaster.ca email addresses are accepted.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // ── Database checks + insert ──────────────────────────
    if (empty($errors)) {
        $pdo = getDB();

        // Check for duplicate email (case-insensitive via LOWER)
        $dupCheck = $pdo->prepare('SELECT user_id FROM Users WHERE LOWER(email) = LOWER(?)');
        $dupCheck->execute([$email]);

        if ($dupCheck->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            // Hash password with bcrypt (PASSWORD_DEFAULT = bcrypt)
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $insert = $pdo->prepare(
                'INSERT INTO Users (name, email, password, role) VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$name, $email, $hash, 'member']);

            // Set flash and redirect to login
            $_SESSION['flash'] = [
                'type' => 'success',
                'msg'  => 'Account created! You can now sign in.',
            ];
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account CSS Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="my_style.css">
    <link rel="stylesheet" href="app_style.css">
    <style>
        /* ── Password strength bar ── */
        .strength-wrap { margin-top: 8px; }
        .strength-bar-bg {
            height: 3px; background: #1a1a1a;
            border-radius: 2px; overflow: hidden;
        }
        .strength-bar-fill {
            height: 100%; width: 0;
            border-radius: 2px;
            transition: width 0.3s ease, background 0.3s ease;
        }
        .strength-label {
            font-size: 0.63rem; font-weight: 700;
            letter-spacing: 0.06em; text-transform: uppercase;
            color: #444; margin-top: 4px; min-height: 14px;
        }

        /* ── Confirm match indicator ── */
        .match-msg {
            font-size: 0.63rem; font-weight: 700;
            letter-spacing: 0.05em; margin-top: 5px; min-height: 14px;
        }
        .match-ok  { color: #6dbe6d; }
        .match-bad { color: #e06060; }

        /* ── Inline show/hide toggle ── */
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 52px; }
        .pw-show-btn {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; color: #444;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.68rem; font-weight: 700;
            letter-spacing: 0.04em; text-transform: uppercase;
            padding: 0; cursor: pointer;
            transition: color 0.2s;
        }
        .pw-show-btn:hover { color: #60b8e0; }

        /* ── McMaster badge ── */
        .email-badge {
            display: inline-block;
            font-size: 0.58rem; font-weight: 700;
            letter-spacing: 0.06em; text-transform: uppercase;
            background: rgba(96,184,224,0.1);
            border: 1px solid rgba(96,184,224,0.25);
            color: #60b8e0; border-radius: 4px;
            padding: 2px 7px; margin-left: 6px;
            vertical-align: middle;
        }

        /* ── Footer link ── */
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

<div class="login-page" style="max-width:460px;">
    <div class="login-card">

        <!-- Brand -->
        <div class="login-brand">
            <img src="imgs/Interconnected unity in blue and black.png" alt="CSS Logo" class="login-logo">
            <div>
                <div class="brand-name">CSS Dashboard</div>
                <div class="brand-sub">McMaster CS Society</div>
            </div>
        </div>

        <h2 class="login-title">Create Account</h2>
        <p class="login-subtitle">
            Sign up with your McMaster email to access events and RSVP.
        </p>

        <!-- Validation errors -->
        <?php if (!empty($errors)): ?>
            <div class="flash flash-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Registration form -->
        <form method="POST" action="register.php" id="reg-form" novalidate>

            <!-- Full name -->
            <div class="form-group">
                <label for="name">Full Name <span class="req">*</span></label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="e.g. Jordan Lee"
                    value="<?= htmlspecialchars($vals['name']) ?>"
                    required
                    autofocus
                    autocomplete="name"
                >
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">
                    Email <span class="req">*</span>
                    <span class="email-badge">@mcmaster.ca only</span>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@mcmaster.ca"
                    value="<?= htmlspecialchars($vals['email']) ?>"
                    required
                    autocomplete="email"
                >
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password">
                    Password <span class="req">*</span>
                    <span style="font-size:0.58rem;color:#444;font-weight:600;
                                 letter-spacing:0.02em;text-transform:none;">
                        (min. 8 characters)
                    </span>
                </label>
                <div class="pw-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                        required
                        autocomplete="new-password"
                    >
                    <button type="button" class="pw-show-btn" data-target="password">Show</button>
                </div>
                <div class="strength-wrap">
                    <div class="strength-bar-bg">
                        <div class="strength-bar-fill" id="strength-fill"></div>
                    </div>
                    <div class="strength-label" id="strength-label"></div>
                </div>
            </div>

            <!-- Confirm password -->
            <div class="form-group">
                <label for="confirm">Confirm Password <span class="req">*</span></label>
                <div class="pw-wrap">
                    <input
                        type="password"
                        id="confirm"
                        name="confirm"
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                        required
                        autocomplete="new-password"
                    >
                    <button type="button" class="pw-show-btn" data-target="confirm">Show</button>
                </div>
                <div class="match-msg" id="match-msg"></div>
            </div>

            <button type="submit" class="btn-primary">Create Account &rarr;</button>

        </form>

        <div class="form-footer-link">
            Already have an account? <a href="login.php">Sign in</a>
        </div>

    </div>
</div>

<script>
/**
 * Inline script for register.php
 *
 * Handles:
 *   - Real-time password strength meter
 *   - Confirm-password match indicator
 *   - Show/hide password toggle
 *
 * Standards: const/let, addEventListener, DOMContentLoaded wrapper.
 */
document.addEventListener('DOMContentLoaded', function () {

    const pwInput  = document.getElementById('password');
    const cfmInput = document.getElementById('confirm');
    const fillEl   = document.getElementById('strength-fill');
    const lblEl    = document.getElementById('strength-label');
    const matchEl  = document.getElementById('match-msg');

    // ── Password strength meter ────────────────────────────
    pwInput.addEventListener('input', function () {
        updateStrength(this.value);
        updateMatch();
    });

    /**
     * Compute a strength score 0–5 and update the bar + label.
     *
     * @param {string} pw - Current password value.
     */
    function updateStrength(pw) {
        let score = 0;
        if (pw.length >= 8)           score++;
        if (pw.length >= 12)          score++;
        if (/[A-Z]/.test(pw))        score++;
        if (/[0-9]/.test(pw))        score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;

        const levels = [
            { pct: '0%',   color: '',        text: ''          },
            { pct: '20%',  color: '#e06060', text: 'Weak'      },
            { pct: '40%',  color: '#f5a623', text: 'Fair'      },
            { pct: '65%',  color: '#f5d623', text: 'Good'      },
            { pct: '85%',  color: '#6dbe6d', text: 'Strong'    },
            { pct: '100%', color: '#60b8e0', text: 'Excellent' },
        ];

        const lvl = levels[Math.min(score, 5)];
        fillEl.style.width      = lvl.pct;
        fillEl.style.background = lvl.color;
        lblEl.textContent       = lvl.text;
        lblEl.style.color       = lvl.color;
    }

    // ── Confirm match indicator ────────────────────────────
    cfmInput.addEventListener('input', updateMatch);

    /**
     * Show a match / no-match indicator below the confirm field.
     */
    function updateMatch() {
        const pw  = pwInput.value;
        const cfm = cfmInput.value;
        if (!cfm) { matchEl.textContent = ''; matchEl.className = 'match-msg'; return; }

        if (pw === cfm) {
            matchEl.textContent = '\u2713 Passwords match';
            matchEl.className   = 'match-msg match-ok';
        } else {
            matchEl.textContent = '\u2717 Passwords do not match';
            matchEl.className   = 'match-msg match-bad';
        }
    }

    // ── Show / hide password toggles ──────────────────────
    document.querySelectorAll('.pw-show-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.target);
            if (input.type === 'password') {
                input.type        = 'text';
                btn.textContent   = 'Hide';
            } else {
                input.type        = 'password';
                btn.textContent   = 'Show';
            }
        });
    });

});
</script>

</body>
</html>