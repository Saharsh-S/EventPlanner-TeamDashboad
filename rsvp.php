<?php


session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

require_once 'includes/db.php';

$userId  = (int)$_SESSION['user_id'];
$eventId = (int)($_POST['event_id'] ?? 0);

if ($eventId <= 0) {
    echo json_encode(['error' => 'Invalid event.']);
    exit;
}

$pdo = getDB();

// Check event exists
$ev = $pdo->prepare('SELECT event_id FROM Events WHERE event_id = ?');
$ev->execute([$eventId]);
if (!$ev->fetch()) {
    echo json_encode(['error' => 'Event not found.']);
    exit;
}

// Check existing RSVP
$check = $pdo->prepare('SELECT rsvp_id FROM RSVPs WHERE user_id = ? AND event_id = ?');
$check->execute([$userId, $eventId]);
$existing = $check->fetch();

if ($existing) {
    // DELETE - toggle off
    $pdo->prepare('DELETE FROM RSVPs WHERE user_id = ? AND event_id = ?')
        ->execute([$userId, $eventId]);
    $rsvpd = false;
} else {
    // INSERT - toggle on
    $pdo->prepare('INSERT INTO RSVPs (user_id, event_id, status) VALUES (?, ?, \'interested\')')
        ->execute([$userId, $eventId]);
    $rsvpd = true;
}

// Live count - SELECT COUNT(*)
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM RSVPs WHERE event_id = ?');
$countStmt->execute([$eventId]);
$count = (int)$countStmt->fetchColumn();

echo json_encode(['rsvpd' => $rsvpd, 'count' => $count]);
