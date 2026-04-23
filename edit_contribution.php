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

// Read and sanitise POST values
$statId   = (int)($_POST['stat_id']          ?? 0);
$eventId  = (int)($_POST['event_id']         ?? 0);
$members  = (int)($_POST['members_involved'] ?? 0);
$note     = trim($_POST['contribution']      ?? '');

$subTeamRaw = trim($_POST['sub_team'] ?? '');
$subTeam    = ($subTeamRaw === '') ? null : $subTeamRaw;

$allowedSub = ['design', 'socialmedia', 'academic', 'mentorship'];
$subTeamValid = ($subTeam === null) || in_array($subTeam, $allowedSub, true);

if ($statId <= 0 || $eventId <= 0 || !$subTeamValid || $members < 1 || $members > 10000) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid data. Please try again.'];
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo = getDB();

    // Verify the stat row belongs to this database
    $check = $pdo->prepare('SELECT stat_id FROM Team_Stats WHERE stat_id = ?');
    $check->execute([$statId]);
    if (!$check->fetch()) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Contribution not found.'];
        header('Location: dashboard.php');
        exit;
    }

    // Update the row
    $update = $pdo->prepare(
        'UPDATE Team_Stats
         SET event_id = ?, sub_team = ?, contribution = ?, members_involved = ?
         WHERE stat_id = ?'
    );
    $update->execute([$eventId, $subTeam, $note, $members, $statId]);

    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contribution updated successfully.'];

} catch (PDOException $e) {
    error_log('edit_contribution PDOException: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'DB error: ' . $e->getMessage()];
}

header('Location: dashboard.php');
exit;