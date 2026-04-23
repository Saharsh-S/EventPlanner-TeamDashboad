<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireExec();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events.php');
    exit;
}

$pdo     = getDB();
$eventId = (int)($_POST['event_id'] ?? 0);

if ($eventId <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid event ID.'];
    header('Location: events.php');
    exit;
}

// Verify event exists
$stmt = $pdo->prepare('SELECT title FROM Events WHERE event_id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Event not found.'];
    header('Location: events.php');
    exit;
}

// RSVPs and Team_Stats are cascade-deleted via FK constraints.
// If your MySQL FK setup doesn't cascade, uncomment these:
// $pdo->prepare('DELETE FROM RSVPs WHERE event_id = ?')->execute([$eventId]);
// $pdo->prepare('DELETE FROM Team_Stats WHERE event_id = ?')->execute([$eventId]);

$pdo->prepare('DELETE FROM Events WHERE event_id = ?')->execute([$eventId]);

$_SESSION['flash'] = [
    'type' => 'success',
    'msg'  => '"' . $event['title'] . '" was deleted.'
];
header('Location: events.php');
exit;