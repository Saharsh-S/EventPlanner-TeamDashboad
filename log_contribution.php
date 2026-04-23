<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireExec();

// Only accept POST - anything else goes back to dashboard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

// Read and sanitise POST values
$eventId  = (int)($_POST['event_id']        ?? 0);
$mainTeam = trim($_POST['main_team']         ?? '');
$members  = (int)($_POST['members_involved'] ?? 0);
$note     = trim($_POST['contribution']      ?? '');

// sub_team: empty string becomes NULL
$subTeamRaw = trim($_POST['sub_team'] ?? '');
$subTeam    = ($subTeamRaw === '') ? null : $subTeamRaw;

// Whitelist validation (must match ENUM in setup.sql exactly)
$allowedMain = ['communications', 'studentsupport', 'events', 'outreach', 'webtech'];
$allowedSub  = ['design', 'socialmedia', 'academic', 'mentorship'];

$subTeamValid = ($subTeam === null) || in_array($subTeam, $allowedSub, true);

if ($eventId <= 0 || !in_array($mainTeam, $allowedMain, true) || !$subTeamValid || $members < 1 || $members > 10000) {
    $reasons = [];
    if ($eventId <= 0)                             $reasons[] = 'no event selected';
    if (!in_array($mainTeam, $allowedMain, true))  $reasons[] = "invalid team '$mainTeam'";
    if (!$subTeamValid)                            $reasons[] = "invalid sub-team '$subTeamRaw'";
    if ($members < 1)                              $reasons[] = 'members must be at least 1';
    if ($members > 10000)                          $reasons[] = 'members cannot exceed 10,000';

    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'Validation failed: ' . implode(', ', $reasons) . '.',
    ];
    header('Location: dashboard.php');
    exit;
}

// Database insert
try {
    $pdo    = getDB();
    $userId = currentUser()['id'];

    // Confirm event exists
    $check = $pdo->prepare('SELECT event_id FROM Events WHERE event_id = ?');
    $check->execute([$eventId]);
    if (!$check->fetch()) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Event not found.'];
        header('Location: dashboard.php');
        exit;
    }

    // Insert with explicit column list
    if ($subTeam === null) {
        $insert = $pdo->prepare(
            'INSERT INTO Team_Stats
                 (event_id, main_team, contribution, members_involved, logged_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([$eventId, $mainTeam, $note, $members, $userId]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO Team_Stats
                 (event_id, main_team, sub_team, contribution, members_involved, logged_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$eventId, $mainTeam, $subTeam, $note, $members, $userId]);
    }

    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contribution logged successfully.'];

} catch (PDOException $e) {
    error_log('log_contribution PDOException: ' . $e->getMessage());
    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'DB error: ' . $e->getMessage(),
    ];
}

header('Location: dashboard.php');
exit;