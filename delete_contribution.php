<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireExec();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

// ── Read and sanitise POST value ──────────────────────────
$statId = (int)($_POST['stat_id'] ?? 0);

if ($statId <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid contribution ID.'];
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo = getDB();

    // Verify the row exists before deleting
    $check = $pdo->prepare('SELECT stat_id FROM Team_Stats WHERE stat_id = ?');
    $check->execute([$statId]);
    if (!$check->fetch()) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Contribution not found.'];
        header('Location: dashboard.php');
        exit;
    }

    $pdo->prepare('DELETE FROM Team_Stats WHERE stat_id = ?')->execute([$statId]);

    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contribution deleted.'];

} catch (PDOException $e) {
    error_log('delete_contribution PDOException: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'DB error: ' . $e->getMessage()];
}

header('Location: dashboard.php');
exit;
